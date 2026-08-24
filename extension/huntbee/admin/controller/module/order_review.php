<?php
namespace Opencart\Admin\Controller\Extension\Huntbee\Module;

class OrderReview extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language('extension/huntbee/module/order_review');
		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/huntbee/module/order_review', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/huntbee/module/order_review.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];
		$data['extension_version'] = '3.2.3';

		$data['module_order_review_status'] = $this->config->get('module_order_review_status');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/huntbee/module/order_review', $data));
	}

	public function save(): void {
		$this->load->language('extension/huntbee/module/order_review');
		$json = [];
		if (!$this->user->hasPermission('modify', 'extension/huntbee/module/order_review')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}
		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_order_review', $this->request->post);
			$json['success'] = $this->language->get('text_success');
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}