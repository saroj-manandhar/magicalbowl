<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Payment;
/**
 * Class Stripe
 *
 * @package Opencart\Admin\Controller\Extension\Opencart\Payment
 */
class Stripe extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/opencart/payment/stripe');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart/payment/stripe', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart/payment/stripe.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		// Environment
		$data['payment_stripe_environment'] = $this->config->get('payment_stripe_environment') ?: 'test';

		// API Keys
		$data['payment_stripe_test_public_key'] = $this->config->get('payment_stripe_test_public_key') ?: '';
		$data['payment_stripe_test_secret_key'] = $this->config->get('payment_stripe_test_secret_key') ?: '';
		$data['payment_stripe_live_public_key'] = $this->config->get('payment_stripe_live_public_key') ?: '';
		$data['payment_stripe_live_secret_key'] = $this->config->get('payment_stripe_live_secret_key') ?: '';

		// Order Statuses
		$data['payment_stripe_order_success_status_id'] = (int)$this->config->get('payment_stripe_order_success_status_id');
		$data['payment_stripe_order_failed_status_id'] = (int)$this->config->get('payment_stripe_order_failed_status_id');

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_stripe_geo_zone_id'] = $this->config->get('payment_stripe_geo_zone_id');

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		// Status, Debug, Sort Order
		$data['payment_stripe_status'] = $this->config->get('payment_stripe_status');
		$data['payment_stripe_debug'] = $this->config->get('payment_stripe_debug');
		$data['payment_stripe_sort_order'] = $this->config->get('payment_stripe_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/payment/stripe', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/opencart/payment/stripe');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart/payment/stripe')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		// Validate based on environment
		if (!isset($json['error'])) {
			$environment = $this->request->post['payment_stripe_environment'] ?? 'test';

			if ($environment == 'test') {
				if (empty(trim($this->request->post['payment_stripe_test_public_key'] ?? ''))) {
					$json['error']['test_public_key'] = $this->language->get('error_test_public_key');
				}
				if (empty(trim($this->request->post['payment_stripe_test_secret_key'] ?? ''))) {
					$json['error']['test_secret_key'] = $this->language->get('error_test_secret_key');
				}
			} else {
				if (empty(trim($this->request->post['payment_stripe_live_public_key'] ?? ''))) {
					$json['error']['live_public_key'] = $this->language->get('error_live_public_key');
				}
				if (empty(trim($this->request->post['payment_stripe_live_secret_key'] ?? ''))) {
					$json['error']['live_secret_key'] = $this->language->get('error_live_secret_key');
				}
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_stripe', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		// Nothing extra needed for install
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_stripe');
	}
}
