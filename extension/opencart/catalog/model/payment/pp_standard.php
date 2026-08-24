<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;

class PpStandard extends \Opencart\System\Engine\Model {
	public function getMethods(array $address = []): array {
		$this->load->language('extension/opencart/payment/pp_standard');

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('payment_pp_standard_status')) {
			$status = false;
		} else {
			$status = true;
		}

		$method_data = [];

		if ($status) {
			$option_data['pp_standard'] = [
				'code' => 'pp_standard.pp_standard',
				'name' => $this->language->get('text_title')
			];

			$method_data = [
				'code'       => 'pp_standard',
				'name'       => $this->language->get('text_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_pp_standard_sort_order')
			];
		}

		return $method_data;
	}
}
