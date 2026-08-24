<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Shipping;

class Eco extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/opencart/shipping/eco');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart/shipping/eco', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart/shipping/eco.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$data['shipping_eco_cost'] = $this->config->get('shipping_eco_cost');

		// Tax Class
		$this->load->model('localisation/tax_class');
		$data['shipping_eco_tax_class_id'] = (int)$this->config->get('shipping_eco_tax_class_id');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		// Geo Zone
		$this->load->model('localisation/geo_zone');
		$data['shipping_eco_geo_zone_id'] = $this->config->get('shipping_eco_geo_zone_id');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['shipping_eco_status'] = $this->config->get('shipping_eco_status');
		$data['shipping_eco_sort_order'] = $this->config->get('shipping_eco_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/shipping/eco', $data));
	}

	public function save(): void {
		$this->load->language('extension/opencart/shipping/eco');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart/shipping/eco')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('shipping_eco', $this->request->post);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
