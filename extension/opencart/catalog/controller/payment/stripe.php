<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;

class Stripe extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->language('extension/opencart/payment/stripe');

		$data['language'] = $this->config->get('config_language');

		// Determine which keys to use based on environment
		$environment = $this->config->get('payment_stripe_environment') ?: 'test';

		if ($environment == 'live') {
			$data['stripe_public_key'] = $this->config->get('payment_stripe_live_public_key');
		} else {
			$data['stripe_public_key'] = $this->config->get('payment_stripe_test_public_key');
		}

		return $this->load->view('extension/opencart/payment/stripe', $data);
	}

	public function confirm(): void {
		$this->load->language('extension/opencart/payment/stripe');
		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = 'No order found in session.';
		}

		if (!$json) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if (!$order_info) {
				$json['error'] = 'Order not found.';
			}
		}

		if (!$json) {
			// Get the Stripe token from POST
			$stripe_token = $this->request->post['stripeToken'] ?? '';

			if (empty($stripe_token)) {
				$json['error'] = 'Payment token is missing. Please try again.';
			}
		}

		if (!$json) {
			// Determine environment
			$environment = $this->config->get('payment_stripe_environment') ?: 'test';

			if ($environment == 'live') {
				$secret_key = $this->config->get('payment_stripe_live_secret_key');
			} else {
				$secret_key = $this->config->get('payment_stripe_test_secret_key');
			}

			// Calculate amount in cents
			$amount = (int)round($order_info['total'] * $this->currency->getvalue($order_info['currency_code']) * 100);
			$currency = strtolower($order_info['currency_code']);

			// Create charge via Stripe API
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/charges');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
				'amount'      => $amount,
				'currency'    => $currency,
				'source'      => $stripe_token,
				'description' => 'Order #' . $order_info['order_id'] . ' - ' . $order_info['store_name'],
				'metadata'    => [
					'order_id'   => $order_info['order_id'],
					'email'      => $order_info['email']
				]
			]));

			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			$result = json_decode($response, true);

			// Debug logging
			if ($this->config->get('payment_stripe_debug')) {
				$log = new \Opencart\System\Library\Log('stripe.log');
				$log->write('Stripe Charge Response: ' . print_r($result, true));
			}

			if ($http_code == 200 && isset($result['id']) && $result['paid'] === true) {
				// Payment successful
				$this->model_checkout_order->addHistory(
					$this->session->data['order_id'],
					$this->config->get('payment_stripe_order_success_status_id') ?: 5,
					'Stripe Payment Successful. Charge ID: ' . $result['id']
				);

				$json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
			} else {
				// Payment failed
				$error_message = $result['error']['message'] ?? 'Payment failed. Please try again.';

				$this->model_checkout_order->addHistory(
					$this->session->data['order_id'],
					$this->config->get('payment_stripe_order_failed_status_id') ?: 10,
					'Stripe Payment Failed: ' . $error_message
				);

				$json['error'] = $error_message;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
