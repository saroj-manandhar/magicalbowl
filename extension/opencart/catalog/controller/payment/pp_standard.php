<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;

class PpStandard extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->language('extension/opencart/payment/pp_standard');

		$data['language'] = $this->config->get('config_language');

		// Get order info for PayPal form
		if (isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if ($order_info) {
				// PayPal URL
				if ($this->config->get('payment_pp_standard_test')) {
					$data['action'] = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
				} else {
					$data['action'] = 'https://www.paypal.com/cgi-bin/webscr';
				}

				$data['business'] = $this->config->get('payment_pp_standard_email');
				$data['item_name'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . ' - Order #' . $order_info['order_id'];

				// Calculate amount
				$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

				$data['amount'] = number_format($amount, 2, '.', '');
				$data['currency_code'] = $order_info['currency_code'];
				$data['order_id'] = $order_info['order_id'];

				// Billing details
				$data['first_name'] = html_entity_decode($order_info['payment_firstname'], ENT_QUOTES, 'UTF-8');
				$data['last_name'] = html_entity_decode($order_info['payment_lastname'], ENT_QUOTES, 'UTF-8');
				$data['address1'] = html_entity_decode($order_info['payment_address_1'], ENT_QUOTES, 'UTF-8');
				$data['address2'] = html_entity_decode($order_info['payment_address_2'], ENT_QUOTES, 'UTF-8');
				$data['city'] = html_entity_decode($order_info['payment_city'], ENT_QUOTES, 'UTF-8');
				$data['zip'] = html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8');
				$data['country'] = $order_info['payment_iso_code_2'];
				$data['email'] = $order_info['email'];

				// Transaction method
				if ($this->config->get('payment_pp_standard_transaction')) {
					$data['paymentaction'] = 'sale';
				} else {
					$data['paymentaction'] = 'authorization';
				}

				// URLs
				$data['return'] = $this->url->link('extension/opencart/payment/pp_standard.callback', 'language=' . $this->config->get('config_language'), true);
				$data['cancel_return'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true);
				$data['notify_url'] = $this->url->link('extension/opencart/payment/pp_standard.callback', 'language=' . $this->config->get('config_language'), true);

				$data['custom'] = $order_info['order_id'];
			}
		}

		return $this->load->view('extension/opencart/payment/pp_standard', $data);
	}

	public function confirm(): void {
		$this->load->language('extension/opencart/payment/pp_standard');
		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = 'No order found in session.';
		}

		if (!$json) {
			$this->load->model('checkout/order');

			// Set order to pending status before redirecting to PayPal
			$this->model_checkout_order->addHistory(
				$this->session->data['order_id'],
				$this->config->get('payment_pp_standard_pending_status_id') ?: 1,
				'PayPal Payment Pending - Customer redirecting to PayPal.'
			);

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function callback(): void {
		$this->load->language('extension/opencart/payment/pp_standard');

		if (isset($this->request->post['custom'])) {
			$order_id = (int)$this->request->post['custom'];
		} elseif (isset($this->request->get['custom'])) {
			$order_id = (int)$this->request->get['custom'];
		} else {
			$order_id = 0;
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if ($order_info) {
			// Debug logging
			if ($this->config->get('payment_pp_standard_debug')) {
				$log = new \Opencart\System\Library\Log('paypal_standard.log');
				$log->write('PayPal IPN Data: ' . print_r($this->request->post, true));
			}

			$payment_status = $this->request->post['payment_status'] ?? '';

			switch (strtolower($payment_status)) {
				case 'completed':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_completed_status_id') ?: 5, 'PayPal Payment Completed.');
					break;
				case 'denied':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_denied_status_id') ?: 8);
					break;
				case 'expired':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_expired_status_id') ?: 14);
					break;
				case 'failed':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_failed_status_id') ?: 10);
					break;
				case 'pending':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_pending_status_id') ?: 1);
					break;
				case 'processed':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_processed_status_id') ?: 15);
					break;
				case 'refunded':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_refunded_status_id') ?: 11);
					break;
				case 'reversed':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_reversed_status_id') ?: 12);
					break;
				case 'canceled_reversal':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_canceled_reversal_status_id') ?: 7);
					break;
				case 'voided':
					$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_pp_standard_voided_status_id') ?: 16);
					break;
			}
		}

		// Redirect to success page for return URL
		if (!isset($this->request->post['txn_type'])) {
			$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
		}
	}
}
