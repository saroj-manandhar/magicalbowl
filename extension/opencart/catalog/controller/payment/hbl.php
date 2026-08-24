<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;

class Hbl extends \Opencart\System\Engine\Controller {
	/**
	 * Validate HBL signature
	 */
	private function validateSignature(array $data, string $api_key): bool {
		if (empty($data) || !isset($data['Signature'])) {
			return false;
		}

		$receivedSignature = $data['Signature'];
		unset($data['Signature']);

		ksort($data);

		$stringToHash = '';
		foreach ($data as $key => $value) {
			$stringToHash .= "$key=$value&";
		}

		$stringToHash .= "apiKey=$api_key";
		$calculatedSignature = hash_hmac('sha256', $stringToHash, $api_key);

		return strtolower($receivedSignature) === strtolower($calculatedSignature);
	}

	/**
	 * Payment button page
	 */
	public function index(): string {
		$this->load->language('extension/opencart/payment/hbl');

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/opencart/payment/hbl', $data);
	}

	/**
	 * Confirm order and redirect to HBL payment
	 */
	public function confirm(): void {
		$this->load->language('extension/opencart/payment/hbl');
		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = 'Order session not found.';
		}

		if (!$json) {
			$this->load->model('checkout/order');
			$order_id = $this->session->data['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);

			if (!$order_info) {
				$json['error'] = 'Order not found.';
			}
		}

		if (!$json) {
			// Load HBL library
			require_once DIR_SYSTEM . 'library/hbl/vendor/autoload.php';
			require_once DIR_SYSTEM . 'library/hbl/ActionRequest_NPR.php';
			require_once DIR_SYSTEM . 'library/hbl/SecurityData_NPR.php';
			require_once DIR_SYSTEM . 'library/hbl/api/Payment_NPR.php';
			require_once DIR_SYSTEM . 'library/hbl/api/Inquiry_NPR.php';
			require_once DIR_SYSTEM . 'library/hbl/api/VoidRequest_NPR.php';
			require_once DIR_SYSTEM . 'library/hbl/api/Settlement_NPR.php';
			require_once DIR_SYSTEM . 'library/hbl/api/Refund_NPR.php';

			$api_key = $this->config->get('payment_hbl_api_key');
			$merchant_id = $this->config->get('payment_hbl_merchant_id');
			$secure3D = $this->config->get('payment_hbl_three_d_secure') === 'Y' ? true : false;

			if (!$api_key || !$merchant_id) {
				$json['error'] = 'HBL payment configuration missing.';
			}
		}

		if (!$json) {
			$token = hash('sha256', $order_id . $this->config->get('config_encryption'));

			$success_url = html_entity_decode($this->url->link('extension/opencart/payment/hbl.success', 'order_id=' . $order_id . '&token=' . $token . '&language=' . $this->config->get('config_language'), true));
			$fail_url = html_entity_decode($this->url->link('extension/opencart/payment/hbl.failure', 'order_id=' . $order_id . '&token=' . $token . '&language=' . $this->config->get('config_language'), true));
			$cancel_url = html_entity_decode($this->url->link('extension/opencart/payment/hbl.cancel', 'order_id=' . $order_id . '&token=' . $token . '&language=' . $this->config->get('config_language'), true));
			$backend_url = html_entity_decode($this->url->link('extension/opencart/payment/hbl.callback', 'order_id=' . $order_id . '&language=' . $this->config->get('config_language'), true));

			$payment_amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

			try {
				$payment = new \hbl\api\Payment();

				$joseResponse = $payment->ExecuteFormJose(
					$merchant_id,
					$api_key,
					$order_info['currency_code'],
					$payment_amount,
					$secure3D,
					$success_url,
					$fail_url,
					$cancel_url,
					$backend_url
				);

				$response_obj = json_decode($joseResponse);

				if (isset($response_obj->response->Data->paymentPage->paymentPageURL)) {
					$paymentUrl = $response_obj->response->Data->paymentPage->paymentPageURL;

					$this->model_checkout_order->addHistory($order_id, 1, 'Redirecting to HBL payment gateway', false);

					$log = new \Opencart\System\Library\Log('hbl.log');
					$log->write('Redirect URL: ' . $paymentUrl);

					$json['redirect'] = $paymentUrl;
				} else {
					$json['error'] = 'Problem with HBL payment. Payment URL not received.';
					$log = new \Opencart\System\Library\Log('hbl.log');
					$log->write('HBL Payment Error: No payment URL in response - ' . $joseResponse);
				}
			} catch (\Exception $e) {
				$json['error'] = 'Payment API Error: ' . $e->getMessage();
				$log = new \Opencart\System\Library\Log('hbl.log');
				$log->write('HBL Payment Exception: ' . $e->getMessage());
			}
		}

		if (ob_get_length()) {
			ob_clean();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * HBL server-to-server callback
	 */
	public function callback(): void {
		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;

		$this->load->model('checkout/order');
		$api_key = $this->config->get('payment_hbl_api_key');

		$raw_input = file_get_contents('php://input');
		$log = new \Opencart\System\Library\Log('hbl.log');
		$log->write('=== HBL CALLBACK CALLED ===');
		$log->write('HBL Callback RAW: ' . $raw_input);
		$log->write('HBL Callback - Order ID from URL: ' . $order_id);

		// If it's a JOSE (JWE/JWS) payload, decrypt it first
		if (strpos($raw_input, 'eyJ') === 0) {
			try {
				require_once DIR_SYSTEM . 'library/hbl/vendor/autoload.php';
				require_once DIR_SYSTEM . 'library/hbl/ActionRequest_NPR.php';
				require_once DIR_SYSTEM . 'library/hbl/SecurityData_NPR.php';
				require_once DIR_SYSTEM . 'library/hbl/api/Payment_NPR.php';

				$payment = new \hbl\api\Payment();
				$decrypted = $payment->DecryptResponse($raw_input);
				$log->write('HBL Callback Decrypted: ' . $decrypted);
				$raw_input = $decrypted;
			} catch (\Exception $e) {
				$log->write('HBL Callback Decryption Failed: ' . $e->getMessage());
				http_response_code(400);
				echo json_encode(['status' => 'error', 'message' => 'Decryption failed: ' . $e->getMessage()]);
				return;
			}
		}

		$data = json_decode($raw_input, true);
		if (!$data) {
			$log->write("HBL Callback: No JSON received");
			http_response_code(400);
			echo json_encode(['status' => 'error', 'message' => 'No JSON data']);
			return;
		}

		$log->write("HBL Callback - Received data: " . print_r($data, true));

		if (!$order_id && isset($data['order_id'])) {
			$order_id = (int)$data['order_id'];
		}

		if (isset($data['Signature'])) {
			if (!$this->validateSignature($data, $api_key)) {
				$log->write("HBL Callback: Invalid Signature");
				http_response_code(400);
				echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
				return;
			}
		}

		if ($order_id) {
			$result = $data['ResponseCode'] ?? ($data['response']['ApiResponse']['ResponseCode'] ?? ($data['response']['Data']['paymentResult']['priorPaymentResponseDetails']['ResponseCode'] ?? ''));
			$message = $data['ResponseDescription'] ?? ($data['response']['ApiResponse']['ResponseDescription'] ?? ($data['response']['Data']['paymentResult']['priorPaymentResponseDetails']['ResponseDescription'] ?? ''));
			$payment_id = $data['PaymentID'] ?? ($data['response']['Data']['paymentResult']['pspResponse']['PspPaymentId'] ?? ($data['response']['Data']['paymentResult']['controllerInternalID'] ?? ''));
			$two_c_two_p_order_no = $data['orderNo'] ?? ($data['response']['Data']['paymentResult']['orderNo'] ?? '');

			if ($result === "000" || $result === "PC-B050000") {
				$status = $this->config->get('payment_hbl_order_status_id') ?: 2;
				$this->model_checkout_order->addHistory(
					$order_id,
					$status,
					"HBL Payment Success | PaymentID: $payment_id | 2C2P Order: $two_c_two_p_order_no",
					false
				);
			} else {
				$this->model_checkout_order->addHistory(
					$order_id,
					10,
					"HBL Payment Failed: $message (Code: $result) | 2C2P Order: $two_c_two_p_order_no",
					false
				);
			}
		}

		$log->write('=== END HBL CALLBACK ===');
		http_response_code(200);
		echo json_encode(['status' => 'success']);
	}

	public function success(): void {
		$this->load->model('checkout/order');
		$api_key = $this->config->get('payment_hbl_api_key');

		$order_id = $this->request->get['order_id'] ?? ($this->session->data['order_id'] ?? 0);

		if (!$order_id) {
			$this->session->data['error'] = 'Order session expired. Please try again.';
			$this->response->redirect($this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language')));
			return;
		}

		// 1. Check if the callback already processed the order successfully
		$order_info = $this->model_checkout_order->getOrder($order_id);
		$success_status = $this->config->get('payment_hbl_order_status_id') ?: 2;
		if ($order_info && (int)$order_info['order_status_id'] === (int)$success_status) {
			$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language')));
			return;
		}

		// 2. Check for JWE payload (from raw input, POST or GET parameter)
		$raw_input = file_get_contents('php://input');
		$paymentResponse = $this->request->post['paymentResponse'] ?? ($this->request->get['paymentResponse'] ?? '');
		$jwe_token = '';
		if (strpos($raw_input, 'eyJ') === 0) {
			$jwe_token = $raw_input;
		} elseif (strpos($paymentResponse, 'eyJ') === 0) {
			$jwe_token = $paymentResponse;
		}

		$response = $this->request->get;

		if ($jwe_token) {
			try {
				require_once DIR_SYSTEM . 'library/hbl/vendor/autoload.php';
				require_once DIR_SYSTEM . 'library/hbl/ActionRequest_NPR.php';
				require_once DIR_SYSTEM . 'library/hbl/SecurityData_NPR.php';
				require_once DIR_SYSTEM . 'library/hbl/api/Payment_NPR.php';

				$payment = new \hbl\api\Payment();
				$decrypted = $payment->DecryptResponse($jwe_token);
				$response = json_decode($decrypted, true) ?? [];
			} catch (\Exception $e) {
				$log = new \Opencart\System\Library\Log('hbl.log');
				$log->write('HBL Success JWE Decryption Failed: ' . $e->getMessage());
			}
		}

		// 3. Fallback to validate traditional signature if signature is present
		if (isset($response['Signature'])) {
			if (!$this->validateSignature($response, $api_key)) {
				$this->session->data['error'] = "Invalid Signature. Payment verification failed.";
				$this->response->redirect($this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language')));
				return;
			}
		}

		$result_code = $response['ResponseCode'] ?? ($response['response']['ApiResponse']['ResponseCode'] ?? ($response['response']['Data']['paymentResult']['priorPaymentResponseDetails']['ResponseCode'] ?? ''));
		$payment_id = $response['PaymentID'] ?? ($response['response']['Data']['paymentResult']['pspResponse']['PspPaymentId'] ?? ($response['response']['Data']['paymentResult']['controllerInternalID'] ?? ''));

		if ($result_code === "000" || $result_code === "PC-B050000") {
			$this->model_checkout_order->addHistory($order_id, $success_status, "HBL Payment Success | PaymentID: $payment_id", true);
			$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language')));
			return;
		}

		$result_msg = $response['ResponseDescription'] ?? ($response['response']['ApiResponse']['ResponseDescription'] ?? ($response['response']['Data']['paymentResult']['priorPaymentResponseDetails']['ResponseDescription'] ?? 'Payment failed'));
		$this->session->data['error'] = "Payment failed: $result_msg";
		$this->response->redirect($this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language')));
	}

	/**
	 * Customer redirected after payment failure
	 */
	public function failure(): void {
		$this->load->model('checkout/order');

		$order_id = $this->request->get['order_id'] ?? ($this->session->data['order_id'] ?? 0);

		// Check if the callback already processed the order successfully
		$order_info = $this->model_checkout_order->getOrder($order_id);
		$success_status = $this->config->get('payment_hbl_order_status_id') ?: 2;
		if ($order_info && (int)$order_info['order_status_id'] === (int)$success_status) {
			$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language')));
			return;
		}

		$response_code = $this->request->get['ResponseCode'] ?? 'Unknown';
		$response_msg = $this->request->get['ResponseDescription'] ?? 'Transaction was declined';

		if ($order_id) {
			$this->model_checkout_order->addHistory($order_id, 10, "HBL Payment Failed: $response_msg (Code: $response_code)", false);
		}

		$this->session->data['error'] = "Payment could not be processed. Please try again or use a different payment method.";
		$this->response->redirect($this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true));
	}

	/**
	 * Customer cancelled payment
	 */
	public function cancel(): void {
		$this->load->model('checkout/order');

		$order_id = $this->request->get['order_id'] ?? ($this->session->data['order_id'] ?? 0);

		// Check if the callback already processed the order successfully
		$order_info = $this->model_checkout_order->getOrder($order_id);
		$success_status = $this->config->get('payment_hbl_order_status_id') ?: 2;
		if ($order_info && (int)$order_info['order_status_id'] === (int)$success_status) {
			$this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language')));
			return;
		}

		if ($order_id) {
			$this->model_checkout_order->addHistory($order_id, 7, "Payment cancelled by customer at HBL gateway", false);
		}

		$this->session->data['error'] = 'Payment was cancelled.';
		$this->response->redirect($this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true));
	}
}
