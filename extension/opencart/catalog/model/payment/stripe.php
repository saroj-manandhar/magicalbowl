<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;

class Stripe extends \Opencart\System\Engine\Model {
	public function getMethods(array $address = []): array {
		$this->load->language('extension/opencart/payment/stripe');

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('payment_stripe_status')) {
			$status = false;
		} else {
			$status = true;
		}

		$method_data = [];

		if ($status) {
			$option_data['stripe'] = [
				'code' => 'stripe.stripe',
				'name' => $this->language->get('text_title')
			];

			$method_data = [
				'code'       => 'stripe',
				'name'       => $this->language->get('text_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_stripe_sort_order')
			];
		}

		return $method_data;
	}
}
