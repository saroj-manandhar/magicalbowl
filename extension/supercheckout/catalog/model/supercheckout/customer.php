<?php

namespace Opencart\Catalog\Model\Extension\Supercheckout\Supercheckout;

class Customer extends \Opencart\System\Engine\Model
{
    public function addFacebookGoogleCustomer($data)
    {
        if (isset($data['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($data['customer_group_id'], $this->config->get('config_customer_group_display'))) {
            $customer_group_id = $data['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $this->load->model('account/customer_group');

        $customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);
       
        //Modified as the salt is not being used in OC4.0 table
        $this->db->query("INSERT INTO `" . DB_PREFIX . "customer` SET `customer_group_id` = '" . (int)$customer_group_id . "', `store_id` = '" . (int)$this->config->get('config_store_id') . "', `language_id` = '" . (int)$this->config->get('config_language_id') . "', `firstname` = '" . $this->db->escape((string)$data['firstname']) . "', `lastname` = '" . $this->db->escape((string)$data['lastname']) . "', `email` = '" . $this->db->escape((string)$data['email']) . "', `telephone` = '" . $this->db->escape((string)$data['telephone']) . "', `custom_field` = '" . $this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "', `password` = '" . $this->db->escape(password_hash(html_entity_decode($data['password'], ENT_QUOTES, 'UTF-8'), PASSWORD_DEFAULT)) . "', `newsletter` = '" . (isset($data['newsletter']) ? (int)$data['newsletter'] : 0) . "', `ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', `status` = '" . (int)!$customer_group_info['approval'] . "', `date_added` = NOW()");

      //  $this->db->query("INSERT INTO " . DB_PREFIX . "customer SET customer_group_id = '" . (int) $customer_group_id . "', store_id = '" . (int) $this->config->get('config_store_id') . "', language_id = '" . (int) $this->config->get('config_language_id') . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "', salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "', newsletter = '" . (isset($data['newsletter']) ? (int) $data['newsletter'] : 0) . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', status = '" . (int) !$customer_group_info['approval'] . "', date_added = NOW()");
        $customer_id = $this->db->getLastId();

        if ($customer_group_info['approval']) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "customer_approval` SET customer_id = '" . (int) $customer_id . "', type = 'customer', date_added = NOW()");
        }

        $this->db->query("INSERT INTO " . DB_PREFIX . "address SET customer_id = '" . (int) $customer_id . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', company = '" . $this->db->escape($data['company']) . "', address_1 = '" . $this->db->escape($data['address_1']) . "', address_2 = '" . $this->db->escape($data['address_2']) . "', city = '" . $this->db->escape($data['city']) . "', postcode = '" . $this->db->escape($data['postcode']) . "', country_id = '" . (int) $data['country_id'] . "', zone_id = '" . (int) $data['zone_id'] . "'");
        $address_id = $this->db->getLastId();

      //  $this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int) $address_id . "' WHERE customer_id = '" . (int) $customer_id . "'");

        $this->language->load('mail/register');
        $this->language->load('supercheckout/supercheckout');

        if ($this->config->get('config_logo') && file_exists(DIR_IMAGE . $this->config->get('config_logo'))) {
            $logo_path = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
        }

        $subject = sprintf($this->language->get('text_subject'), $this->config->get('config_name'));

        $message = sprintf($this->language->get('text_welcome'), $this->config->get('config_name')) . "\n";
        $message .= $this->url->link('account/login', '', 'SSL') . "\n\n";
        $message .= $this->language->get('text_service') . "\n";
        $message .= $this->language->get('text_your_password_is') . $data['password'] . "\n\n";
        $message .= $this->language->get('text_thanks') . "\n";
        $message .= $this->config->get('config_name');

        // $mail = new Mail($this->config->get('config_mail_engine'));
        // $mail->protocol = $this->config->get('config_mail_protocol');
        // $mail->parameter = $this->config->get('config_mail_parameter');
        // $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        // $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        // $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        // $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        // $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');


        

        // $mail->setTo($data['email']);
        // $mail->setFrom($this->config->get('config_email'));
        // $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
        // $mail->setSubject($subject);
        // $mail->setText($message);
        // $mail->send();

        //modified for oc4.0

        $mail_option = [
            'parameter'     => $this->config->get('config_mail_parameter'),
            'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
            'smtp_username' => $this->config->get('config_mail_smtp_username'),
            'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
            'smtp_port'     => $this->config->get('config_mail_smtp_port'),
            'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
        ];

        $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
        $mail->setTo($data['email']);
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
        $mail->setSubject($subject);
        $mail->setText($message);
        $mail->send();

        //End changes

        // Send to main admin email if new account email is enabled
        if (in_array('account', (array) $this->config->get('config_mail_alert'))) {
            $message = $this->language->get('text_signup') . "\n\n";
            $message .= $this->language->get('text_website') . ' ' . html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . "\n";
            $message .= $this->language->get('text_firstname') . ' ' . $data['firstname'] . "\n";
            $message .= $this->language->get('text_lastname') . ' ' . $data['lastname'] . "\n";
            $message .= $this->language->get('text_customer_group') . ' ' . $customer_group_info['name'] . "\n";
            $message .= $this->language->get('text_email') . ' ' . $data['email'] . "\n";
            $message .= $this->language->get('text_telephone') . ' ' . $data['telephone'] . "\n";
            $mail_option = [
                'parameter'     => $this->config->get('config_mail_parameter'),
                'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
                'smtp_username' => $this->config->get('config_mail_smtp_username'),
                'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
                'smtp_port'     => $this->config->get('config_mail_smtp_port'),
                'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
            ];
    
            $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
            $mail->setTo($this->config->get('config_email'));
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(html_entity_decode($this->language->get('text_new_customer'), ENT_QUOTES, 'UTF-8'));
            $mail->setText($message);
            $mail->send();

            // Send to additional alert emails if new account email is enabled
            $emails = explode(',', $this->config->get('config_alert_email'));

            foreach ($emails as $email) {
                try {
                    // Use utf8_strlen if available and callable
                    if (function_exists('utf8_strlen')) {
                        $length = utf8_strlen($email);
                    } else {
                        $length = mb_strlen($email, 'UTF-8');
                    }
                } catch (Throwable $e) {
                    // In case utf8_strlen throws any error, fall back
                    $length = mb_strlen($email, 'UTF-8');
                }

                if ($length > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->setTo($email);
                    $mail->send();
                }
            }

        }
    }

    public function addGuestAsCustomer($data)
    {
        if (isset($data['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($data['customer_group_id'], $this->config->get('config_customer_group_display'))) {
            $customer_group_id = $data['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $this->load->model('account/customer_group');

        $customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);

       // $this->db->query("INSERT INTO " . DB_PREFIX . "customer SET customer_group_id = '" . (int) $customer_group_id . "', store_id = '" . (int) $this->config->get('config_store_id') . "', language_id = '" . (int) $this->config->get('config_language_id') . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "', salt = '" . $this->db->escape($salt = token(9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "', newsletter = '" . (isset($data['newsletter']) ? (int) $data['newsletter'] : 0) . "', ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', status = '" . (int) !$customer_group_info['approval'] . "',safe=1, date_added = NOW()");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "customer` SET `customer_group_id` = '" . (int)$customer_group_id . "', `store_id` = '" . (int)$this->config->get('config_store_id') . "', `language_id` = '" . (int)$this->config->get('config_language_id') . "', `firstname` = '" . $this->db->escape((string)$data['firstname']) . "', `lastname` = '" . $this->db->escape((string)$data['lastname']) . "', `email` = '" . $this->db->escape((string)$data['email']) . "', `telephone` = '" . $this->db->escape((string)$data['telephone']) . "', `custom_field` = '" .$this->db->escape(isset($data['custom_field']['account']) ? json_encode($data['custom_field']['account']) : '') . "', `password` = '" . $this->db->escape(password_hash(html_entity_decode($data['password'], ENT_QUOTES, 'UTF-8'), PASSWORD_DEFAULT)) . "', `newsletter` = '" . (isset($data['newsletter']) ? (int)$data['newsletter'] : 0) . "', `ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "', `status` = '" . (int)!$customer_group_info['approval'] . "', `date_added` = NOW()");

        $customer_id = $this->db->getLastId();

        if ($customer_group_info['approval']) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "customer_approval` SET customer_id = '" . (int) $customer_id . "', type = 'customer', date_added = NOW()");
        }

        $this->db->query("INSERT INTO " . DB_PREFIX . "address SET customer_id = '" . (int) $customer_id . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', company = '" . $this->db->escape($data['company']) . "', address_1 = '" . $this->db->escape($data['address_1']) . "', address_2 = '" . $this->db->escape($data['address_2']) . "', city = '" . $this->db->escape($data['city']) . "', postcode = '" . $this->db->escape($data['postcode']) . "', country_id = '" . (int) $data['country_id'] . "', zone_id = '" . (int) $data['zone_id'] . "'");
        $address_id = $this->db->getLastId();

       // $this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int) $address_id . "' WHERE customer_id = '" . (int) $customer_id . "'");

        $this->language->load('mail/register');
        $this->language->load('supercheckout/supercheckout');

        $subject = sprintf($this->language->get('text_subject'), $this->config->get('config_name'));

        $message = sprintf($this->language->get('text_welcome'), $this->config->get('config_name')) . "\n\n";
        $message .= $this->url->link('account/login', '', 'SSL') . "\n\n";
        $message .= $this->language->get('text_service') . "\n";
        $message .= $this->language->get('text_your_password_is') . $data['password'] . "\n\n";
        $message .= $this->language->get('text_thanks') . "\n";
        $message .= $this->config->get('config_name');


        $mail_option = [
            'parameter'     => $this->config->get('config_mail_parameter'),
            'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
            'smtp_username' => $this->config->get('config_mail_smtp_username'),
            'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
            'smtp_port'     => $this->config->get('config_mail_smtp_port'),
            'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
        ];

        $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
        $mail->setTo($data['email']);
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
        $mail->setSubject($subject);
        $mail->setText($message);
        $mail->send();

        // Send to main admin email if new account email is enabled
        if (in_array('account', (array) $this->config->get('config_mail_alert'))) {
            $message = $this->language->get('text_signup') . "\n\n";
            $message .= $this->language->get('text_website') . ' ' . html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . "\n";
            $message .= $this->language->get('text_firstname') . ' ' . $data['firstname'] . "\n";
            $message .= $this->language->get('text_lastname') . ' ' . $data['lastname'] . "\n";
            $message .= $this->language->get('text_customer_group') . ' ' . $customer_group_info['name'] . "\n";
            $message .= $this->language->get('text_email') . ' ' . $data['email'] . "\n";
            $message .= $this->language->get('text_telephone') . ' ' . $data['telephone'] . "\n";

            $mail_option = [
                'parameter'     => $this->config->get('config_mail_parameter'),
                'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
                'smtp_username' => $this->config->get('config_mail_smtp_username'),
                'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
                'smtp_port'     => $this->config->get('config_mail_smtp_port'),
                'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
            ];
    
            $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
            $mail->setTo($this->config->get('config_email'));
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(html_entity_decode($this->language->get('text_new_customer'), ENT_QUOTES, 'UTF-8'));
            $mail->setText($message);
            $mail->send();

            //Send to additional alert emails if new account email is enabled
            $emails = explode(',', $this->config->get('config_alert_email'));

            foreach ($emails as $email) {
                // Safely get string length
                try {
                    if (function_exists('utf8_strlen')) {
                        $length = utf8_strlen($email);
                    } else {
                        $length = mb_strlen($email, 'UTF-8');
                    }
                } catch (Throwable $e) {
                    // Fallback in case utf8_strlen causes an error
                    $length = mb_strlen($email, 'UTF-8');
                }

                // Validate and send email
                if ($length > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->setTo($email);
                    $mail->send();
                }
            }
        }
        return $customer_id;
    }

    public function editCustomer($data)
    {

        $this->db->query("UPDATE " . DB_PREFIX . "customer SET firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', fax = '" . $this->db->escape($data['fax']) . "' WHERE customer_id = '" . (int) $this->customer->getId() . "'");
    }

    public function editPassword($email, $password)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `password` = '" . $this->db->escape(password_hash(html_entity_decode($password, ENT_QUOTES, 'UTF-8'), PASSWORD_DEFAULT)) . "', `code` = '' WHERE LCASE(`email`) = '" . $this->db->escape(oc_strtolower($email)) . "' AND customer_group_id='" . $this->config->get('config_customer_group_id') . "'");

        //$this->db->query("UPDATE " . DB_PREFIX . "customer SET salt = '" . $this->db->escape($salt = substr(md5(uniqid(rand(), true)), 0, 9)) . "', password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($password)))) . "' WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "' AND customer_group_id='" . $this->config->get('config_customer_group_id') . "'");
    }

    public function editNewsletter($newsletter)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET newsletter = '" . (int) $newsletter . "' WHERE customer_id = '" . (int) $this->customer->getId() . "'");
    }

    public function getCustomer($customer_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int) $customer_id . "'");
        return $query->row;
    }

    public function getCustomerByEmail($email)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "' AND customer_group_id='" . $this->config->get('config_customer_group_id') . "'");
        return $query->row;
    }

    public function getCustomerByToken($token)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE token = '" . $this->db->escape($token) . "' AND token != ''");
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET token = ''");
        return $query->row;
    }

    public function getCustomers($data = array())
    {
        $sql = "SELECT *, CONCAT(c.firstname, ' ', c.lastname) AS name, cg.name AS customer_group FROM " . DB_PREFIX . "customer c LEFT JOIN " . DB_PREFIX . "customer_group cg ON (c.customer_group_id = cg.customer_group_id) ";

        $implode = array();

        if (isset($data['filter_name']) && !is_null($data['filter_name'])) {
            $implode[] = "LCASE(CONCAT(c.firstname, ' ', c.lastname)) LIKE '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
        }

        if (isset($data['filter_email']) && !is_null($data['filter_email'])) {
            $implode[] = "LCASE(c.email) = '" . $this->db->escape(utf8_strtolower($data['filter_email'])) . "'";
        }

        if (isset($data['filter_customer_group_id']) && !is_null($data['filter_customer_group_id'])) {
            $implode[] = "cg.customer_group_id = '" . $this->db->escape($data['filter_customer_group_id']) . "'";
        }

        if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
            $implode[] = "c.status = '" . (int) $data['filter_status'] . "'";
        }

        if (isset($data['filter_approved']) && !is_null($data['filter_approved'])) {
            $implode[] = "c.approved = '" . (int) $data['filter_approved'] . "'";
        }

        if (isset($data['filter_ip']) && !is_null($data['filter_ip'])) {
            $implode[] = "c.customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
        }

        if (isset($data['filter_date_added']) && !is_null($data['filter_date_added'])) {
            $implode[] = "DATE(c.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
        }

        if ($implode) {
            $sql .= " WHERE " . implode(" AND ", $implode);
        }

        $sort_data = array(
            'name',
            'c.email',
            'customer_group',
            'c.status',
            'c.ip',
            'c.date_added'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int) $data['start'] . "," . (int) $data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getTotalCustomersByEmail($email)
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "' AND customer_group_id = '" . $this->config->get('config_customer_group_id') . "'");
        return $query->row['total'];
    }

    public function getIps($customer_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_ip` WHERE customer_id = '" . (int) $customer_id . "'");
        return $query->rows;
    }

    public function isBanIp($ip)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_ban_ip` WHERE ip = '" . $this->db->escape($ip) . "'");
        return $query->num_rows;
    }
}
