<?php
namespace Opencart\Admin\Model\Extension\Opencart\Other;

class Aramexratesimport extends \Opencart\System\Engine\Model {
	public function clearRates(): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "aramex_rates`");
	}

	public function addRate(array $data): void {
		$id = isset($data['id']) ? (int)$data['id'] : 0;
		$kg = isset($data['kg']) ? (float)$data['kg'] : 0.0;
		$type = !empty($data['type']) ? trim((string)$data['type']) : 'priority';

		$zones = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k'];
		$zone_sql = [];
		foreach ($zones as $zone) {
			$val = isset($data[$zone]) ? (float)str_replace(',', '', (string)$data[$zone]) : 0.0;
			$zone_sql[] = "`" . $zone . "` = '" . $val . "'";
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "aramex_rates` SET 
			`id` = '" . $id . "',
			`kg` = '" . $kg . "',
			" . implode(", ", $zone_sql) . ",
			`type` = '" . $this->db->escape($type) . "'
		");
	}
}