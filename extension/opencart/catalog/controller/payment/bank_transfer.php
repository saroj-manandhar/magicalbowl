<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;
/**
 * Class Bank Transfer
 *
 * @package Opencart\Catalog\Controller\Extension\Opencart\Payment
 */
class BankTransfer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/opencart/payment/bank_transfer');

		$bank = $this->config->get('payment_bank_transfer_bank_' . $this->config->get('config_language_id'));
		if (empty($bank)) {
			$bank = $this->config->get('payment_bank_transfer_bank' . $this->config->get('config_language_id'));
		}
		$bank = preg_replace("/(\r?\n){3,}/", "\n\n", (string)$bank);

		$data['bank'] = nl2br($bank);

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/opencart/payment/bank_transfer', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/opencart/payment/bank_transfer');

		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');
		}

		// Order
		if (isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			if (!$order_info) {
				$json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

				unset($this->session->data['order_id']);
			}
		} else {
			$json['error'] = $this->language->get('error_order');
		}

		$payment_code = isset($this->session->data['payment_method']['code']) ? $this->session->data['payment_method']['code'] : '';
		if (!isset($this->session->data['payment_method']) || ($payment_code != 'bank_transfer.bank_transfer' && $payment_code != 'bank_transfer')) {
			$json['error'] = $this->language->get('error_payment_method');
		}

		if (!$json) {
			$bank = $this->config->get('payment_bank_transfer_bank_' . $this->config->get('config_language_id'));
			if (empty($bank)) {
				$bank = $this->config->get('payment_bank_transfer_bank' . $this->config->get('config_language_id'));
			}

			$comment  = $this->language->get('text_instruction') . "\n\n";
			$comment .= $bank . "\n\n";
			$comment .= $this->language->get('text_payment');

			// Order
			$this->load->model('checkout/order');

			$this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_bank_transfer_order_status_id'), $comment, true);

			$json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
