<?php
namespace Opencart\Admin\Model\Extension\Opencart\Other;

class Arameximport extends \Opencart\System\Engine\Model {
	public function clearCountries(): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "postage_country_time`");
	}

	public function addCountry(array $data): void {
		$id = isset($data['id']) ? (int)$data['id'] : 0;
		$country = isset($data['country']) ? trim((string)$data['country']) : '';
		$aramex_zone_pri = isset($data['aramex_zone_pri']) ? strtolower(trim((string)$data['aramex_zone_pri'])) : '';
		$fedex_zone_pri = isset($data['fedex_zone_pri']) && $data['fedex_zone_pri'] !== '' ? strtolower(trim((string)$data['fedex_zone_pri'])) : $aramex_zone_pri;
		$fedex_zone_eco = isset($data['fedex_zone_eco']) ? strtolower(trim((string)$data['fedex_zone_eco'])) : '';

		if ($country !== '') {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "postage_country_time` SET 
				`id` = '" . $id . "',
				`country` = '" . $this->db->escape($country) . "',
				`aramex_zone_pri` = '" . $this->db->escape($aramex_zone_pri) . "',
				`fedex_zone_pri` = '" . $this->db->escape($fedex_zone_pri) . "',
				`fedex_zone_eco` = '" . $this->db->escape($fedex_zone_eco) . "'
			");
		}
	}
}