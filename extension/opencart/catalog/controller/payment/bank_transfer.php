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

		// Check if payment proof was already uploaded in this session
		if (!empty($this->session->data['payment_proof']) && is_file(DIR_IMAGE . $this->session->data['payment_proof'])) {
			$data['payment_proof'] = $this->session->data['payment_proof'];
			$data['payment_proof_name'] = basename($this->session->data['payment_proof']);
			$data['payment_proof_url'] = $this->config->get('config_url') . 'image/' . $this->session->data['payment_proof'];
			$data['payment_proof_is_image'] = (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $this->session->data['payment_proof']);
		} else {
			$data['payment_proof'] = '';
			$data['payment_proof_name'] = '';
			$data['payment_proof_url'] = '';
			$data['payment_proof_is_image'] = false;
		}

		return $this->load->view('extension/opencart/payment/bank_transfer', $data);
	}

	/**
	 * Upload Proof of Payment
	 *
	 * @return void
	 */
	public function upload(): void {
		$this->load->language('extension/opencart/payment/bank_transfer');

		$json = [];

		if (!empty($this->request->files['payment_proof']['name']) && is_file($this->request->files['payment_proof']['tmp_name'])) {
			$filename = basename(preg_replace('/[^a-zA-Z0-9\.\-\_\s+]/', '', html_entity_decode($this->request->files['payment_proof']['name'], ENT_QUOTES, 'UTF-8')));

			if (!oc_validate_length($filename, 3, 128)) {
				$json['error'] = 'Filename must be between 3 and 128 characters!';
			}

			// Allowed extensions
			$allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'gif'];
			$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

			if (!in_array($ext, $allowed)) {
				$json['error'] = 'Invalid file type! Allowed formats: JPG, PNG, WEBP, PDF, GIF.';
			}

			// Allowed MIME types
			$allowed_mimes = [
				'image/jpeg',
				'image/pjpeg',
				'image/png',
				'image/x-png',
				'image/webp',
				'image/gif',
				'application/pdf'
			];

			if (!empty($this->request->files['payment_proof']['type']) && !in_array($this->request->files['payment_proof']['type'], $allowed_mimes)) {
				$json['error'] = 'Invalid file type!';
			}

			// Max size 10MB
			if ($this->request->files['payment_proof']['size'] > 10 * 1024 * 1024) {
				$json['error'] = 'File size is too large! Maximum allowed is 10MB.';
			}

			if ($this->request->files['payment_proof']['error'] != UPLOAD_ERR_OK) {
				$json['error'] = 'Upload error occurred (code ' . $this->request->files['payment_proof']['error'] . ')';
			}

			if (!$json) {
				$dir = DIR_IMAGE . 'catalog/payment_proofs/';
				if (!is_dir($dir)) {
					@mkdir($dir, 0777, true);
				}

				$new_filename = 'proof_' . date('Ymd_His') . '_' . oc_token(8) . '.' . $ext;
				$target_file = $dir . $new_filename;

				if (move_uploaded_file($this->request->files['payment_proof']['tmp_name'], $target_file)) {
					$relative_path = 'catalog/payment_proofs/' . $new_filename;
					$this->session->data['payment_proof'] = $relative_path;

					$json['success'] = 'Payment proof uploaded successfully!';
					$json['filename'] = $filename;
					$json['file'] = $relative_path;
					$json['is_image'] = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
					$json['url'] = $this->config->get('config_url') . 'image/' . $relative_path;
				} else {
					$json['error'] = 'Failed to save uploaded file on server.';
				}
			}
		} else {
			$json['error'] = 'Please select a file to upload!';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Remove Uploaded Proof
	 *
	 * @return void
	 */
	public function remove_upload(): void {
		$json = [];

		if (!empty($this->session->data['payment_proof'])) {
			$file = DIR_IMAGE . $this->session->data['payment_proof'];
			if (is_file($file)) {
				@unlink($file);
			}
			unset($this->session->data['payment_proof']);
			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
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

			// Save payment proof to order if uploaded
			$payment_proof = !empty($this->session->data['payment_proof']) ? $this->session->data['payment_proof'] : '';
			if ($payment_proof) {
				try {
					$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `payment_proof` = '" . $this->db->escape($payment_proof) . "' WHERE `order_id` = '" . (int)$this->session->data['order_id'] . "'");
				} catch (\Exception $e) {
					// Fallback if column not yet added to database
				}

				$comment .= "\n\nPayment Proof: " . basename($payment_proof);
				unset($this->session->data['payment_proof']);
			}

			// Order
			$this->load->model('checkout/order');

			$this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_bank_transfer_order_status_id'), $comment, true);

			$json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
