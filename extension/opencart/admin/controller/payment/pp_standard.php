<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Payment;
/**
 * Class PpStandard
 *
 * @package Opencart\Admin\Controller\Extension\Opencart\Payment
 */
class PpStandard extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/opencart/payment/pp_standard');

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
			'href' => $this->url->link('extension/opencart/payment/pp_standard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart/payment/pp_standard.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		// General settings
		$data['payment_pp_standard_email'] = $this->config->get('payment_pp_standard_email') ?: '';
		$data['payment_pp_standard_test'] = $this->config->get('payment_pp_standard_test');
		$data['payment_pp_standard_transaction'] = $this->config->get('payment_pp_standard_transaction');
		$data['payment_pp_standard_debug'] = $this->config->get('payment_pp_standard_debug');
		$data['payment_pp_standard_total'] = $this->config->get('payment_pp_standard_total') ?: '';

		// Order Statuses
		$data['payment_pp_standard_canceled_reversal_status_id'] = (int)$this->config->get('payment_pp_standard_canceled_reversal_status_id');
		$data['payment_pp_standard_completed_status_id'] = (int)$this->config->get('payment_pp_standard_completed_status_id');
		$data['payment_pp_standard_denied_status_id'] = (int)$this->config->get('payment_pp_standard_denied_status_id');
		$data['payment_pp_standard_expired_status_id'] = (int)$this->config->get('payment_pp_standard_expired_status_id');
		$data['payment_pp_standard_failed_status_id'] = (int)$this->config->get('payment_pp_standard_failed_status_id');
		$data['payment_pp_standard_pending_status_id'] = (int)$this->config->get('payment_pp_standard_pending_status_id');
		$data['payment_pp_standard_processed_status_id'] = (int)$this->config->get('payment_pp_standard_processed_status_id');
		$data['payment_pp_standard_refunded_status_id'] = (int)$this->config->get('payment_pp_standard_refunded_status_id');
		$data['payment_pp_standard_reversed_status_id'] = (int)$this->config->get('payment_pp_standard_reversed_status_id');
		$data['payment_pp_standard_voided_status_id'] = (int)$this->config->get('payment_pp_standard_voided_status_id');

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo Zone
		$data['payment_pp_standard_geo_zone_id'] = $this->config->get('payment_pp_standard_geo_zone_id');

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		// Status & Sort Order
		$data['payment_pp_standard_status'] = $this->config->get('payment_pp_standard_status');
		$data['payment_pp_standard_sort_order'] = $this->config->get('payment_pp_standard_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/payment/pp_standard', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/opencart/payment/pp_standard');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart/payment/pp_standard')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_pp_standard_email'])) {
			$json['error']['email'] = $this->language->get('error_email');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_pp_standard', $this->request->post);

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
		// Nothing extra needed
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_pp_standard');
	}
}
