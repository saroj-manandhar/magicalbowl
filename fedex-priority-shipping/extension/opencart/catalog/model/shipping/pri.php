<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;

class Pri extends \Opencart\System\Engine\Model {
	public function getQuote(array $address): array {
		$this->load->language('extension/opencart/shipping/pri');

		// Geo Zone
		if ($this->config->get('shipping_pri_geo_zone_id')) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('shipping_pri_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
			if ($query->num_rows) {
				$status = true;
			} else {
				$status = false;
			}
		} else {
			$status = true;
		}

		if (!$this->config->get('shipping_pri_status')) {
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$pri_rate = null;
			$zone = null;
			$actual_weight = 0;
			$heavy_cost = 0;

			foreach ($this->cart->getProducts() as $product) {
				$actual_weight = $actual_weight + round($product['weight']/1000, 2);
			}
			$weight = ceil($actual_weight / 0.5) * 0.5;

			$country_name = $this->db->query("SELECT name FROM " . DB_PREFIX . "country_description WHERE country_id = '" . (int)$address['country_id'] . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
			foreach ($country_name->rows as $row) { 
				$qury = "SELECT fedex_zone_pri FROM " . DB_PREFIX . "postage_country_time WHERE country = '" . $this->db->escape($row['name']) . "'";
				$fedex_zone_pri = $this->db->query($qury);

				if ($fedex_zone_pri->num_rows > 0) {
					foreach ($fedex_zone_pri->rows as $erow) { 
						$zone = $erow['fedex_zone_pri'];
						if ($zone != null) {
							$sql = "SELECT `" . $this->db->escape($zone) . "` FROM " . DB_PREFIX . "fedex_rates WHERE kg = '" . (float)$weight . "' AND type ='priority'";
							$price = $this->db->query($sql);
							foreach ($price->rows as $pri) {
								$pri_rate = $this->currency->convert($pri[$zone], 'USD', $this->config->get('config_currency'));
							}
							
							$pri_rate += $heavy_cost;

							if ($pri_rate > 0) {
								$quote_data = [];
								$quote_data['pri'] = [
									'code'         => 'pri.pri',
									'name'         => $this->language->get('text_description'),
									'cost'         => $pri_rate,
									'tax_class_id' => $this->config->get('shipping_pri_tax_class_id'),
									'text'         => $this->currency->format($this->tax->calculate($pri_rate, $this->config->get('shipping_pri_tax_class_id'), $this->config->get('config_tax')), $this->session->data['currency'])
								];

								$method_data = [
									'code'       => 'pri',
									'name'       => $this->language->get('text_title'),
									'quote'      => $quote_data,
									'sort_order' => $this->config->get('shipping_pri_sort_order'),
									'error'      => false
								];
							}
						}
					}
				}
			}
		}

		return $method_data;
	}
}
