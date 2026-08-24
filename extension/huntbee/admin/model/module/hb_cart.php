<?php
namespace Opencart\Admin\Model\Extension\Huntbee\Module;

/**
 * Abandoned Cart HbCart Admin Model for OpenCart 4
 */
class HbCart extends \Opencart\System\Engine\Model {
	public function install(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "hb_cart` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`customer_id` INT(11) NOT NULL DEFAULT 0,
			`email` VARCHAR(96) NOT NULL,
			`firstname` VARCHAR(32) NOT NULL,
			`lastname` VARCHAR(32) NOT NULL,
			`telephone` VARCHAR(32) NOT NULL,
			`cart` TEXT NOT NULL,
			`session_id` VARCHAR(128) NOT NULL,
			`ip` VARCHAR(40) NOT NULL,
			`store_id` INT(11) NOT NULL DEFAULT 0,
			`last_visited` VARCHAR(255) NOT NULL,
			`date_added` DATETIME NOT NULL,
			`hb_cart_log_id` INT(11) NOT NULL DEFAULT 0,
			`email_viewed` TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "hb_cart_log` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`customer_id` INT(11) NOT NULL DEFAULT 0,
			`email` VARCHAR(96) NOT NULL,
			`store_id` INT(11) NOT NULL DEFAULT 0,
			`email_type_id` INT(11) NOT NULL DEFAULT 0,
			`date_added` DATETIME NOT NULL,
			`email_viewed` TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
	}

	public function uninstall(): void {
		// Schema preservation on uninstall
	}
}
