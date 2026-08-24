<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;

class Hbl extends \Opencart\System\Engine\Model {
	public function getMethods(array $address = []): array {
		$this->load->language('extension/opencart/payment/hbl');

		if (!$this->config->get('payment_hbl_status')) {
			return [];
		}

		if ($this->config->get('payment_hbl_geo_zone_id')) {
			if (isset($address['country_id']) && isset($address['zone_id'])) {
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_hbl_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

				if (!$query->num_rows) {
					return [];
				}
			}
		}

		$option_data['hbl'] = [
			'code' => 'hbl.hbl',
			'name' => $this->language->get('heading_title')
		];

		$method_data = [
			'code'       => 'hbl',
			'name'       => $this->language->get('heading_title'),
			'option'     => $option_data,
			'sort_order' => $this->config->get('payment_hbl_sort_order')
		];

		return $method_data;
	}
}
