# Docker Deployment Guide for VPS (Ubuntu 20.04)

This guide helps you deploy the new OpenCart 4 project on your Ubuntu 20.04 VPS under `magicalbowls.com/new_site` using Docker.

---

## 1. Install Docker & Docker Compose on Ubuntu 20.04

Run the following commands on your VPS terminal to set up Docker and Docker Compose:

```bash
# Update package database
sudo apt update

# Install prerequisites
sudo apt install -y apt-transport-https ca-certificates curl software-properties-common

# Add Docker’s official GPG key
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Add Docker repository
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu focal stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Update and install Docker CE
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io

# Enable and start Docker service
sudo systemctl enable docker
sudo systemctl start docker

# Install Docker Compose (V2)
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.24.5/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# Verify installations
docker --version
docker compose version
```

---

## 2. Connect Container to VPS Host Database

If your MySQL/MariaDB database is running on the VPS host itself (rather than in another container), the Docker container can access it using:
- Host IP on the default Docker network bridge: `172.17.0.1` (or your VPS private network IP).

### Allow MySQL access from Docker:
1. Open your MySQL configuration file on the VPS (typically `/etc/mysql/mysql.conf.d/mysqld.cnf` or `/etc/mysql/my.cnf`).
2. Ensure `bind-address` allows connections from the Docker bridge:
   ```ini
   # Change from 127.0.0.1 to allow all interfaces (or specify 172.17.0.1)
   bind-address = 0.0.0.0
   ```
3. Restart MySQL:
   ```bash
   sudo systemctl restart mysql
   ```
4. Grant access privileges to your database user from the Docker subnet (usually `172.17.%.%` or all `%`):
   ```sql
   GRANT ALL PRIVILEGES ON neww_msb.* TO 'root'@'%' IDENTIFIED BY 'your_password';
   FLUSH PRIVILEGES;
   ```

---

## 3. Configure and Start the Container

1. Clone or copy this repository directory onto your VPS under your target directory.
2. Edit the environment variables inside `docker-compose.yml` to match your production database credentials and domain settings:
   - `HTTP_SERVER`: `https://magicalbowls.com/new_site/`
   - `DB_HOSTNAME`: `172.17.0.1` (The VPS host IP on the docker bridge)
   - `DB_USERNAME`: `your_database_user`
   - `DB_PASSWORD`: `your_database_password`
   - `DB_DATABASE`: `your_database_name`
   - `DB_PORT`: `3306` (or your host DB port)
3. Start the application in detached mode:
   ```bash
   # Build the image and start container
   docker compose up -d --build
   ```
4. Verify the container is running:
   ```bash
   docker ps
   ```

---

## 4. Set Up Nginx Reverse Proxy on VPS

To map incoming requests for `magicalbowls.com/new_site` to the Docker container (which is running on port `8080`), add the following location block inside your Nginx server block on the VPS (usually in `/etc/nginx/sites-available/default` or similar configuration file):

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name magicalbowls.com www.magicalbowls.com;

    # Other configurations...

    # Proxy to Docker OpenCart 4
    location /new_site/ {
        proxy_pass http://127.0.0.1:8080/new_site/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Optional: Increase upload limits for large asset uploads
        client_max_body_size 64M;

        # Disable buffer to allow immediate streaming if needed
        proxy_buffering off;
    }
}
```

### Reload Nginx configuration:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

Now, the site will render perfectly under `https://magicalbowls.com/new_site/` and the admin panel under `https://magicalbowls.com/new_site/msbadmin/`.
