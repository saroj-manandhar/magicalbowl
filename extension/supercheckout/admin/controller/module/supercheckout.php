<?php

namespace Opencart\Admin\Controller\Extension\Supercheckout\Module;

require_once(DIR_EXTENSION . 'supercheckout/system/library/kbsupercheckout/newsletter/Batch.php');
require_once(DIR_EXTENSION . 'supercheckout/system/library/kbsupercheckout/newsletter/MailChimp.php');
require_once(DIR_EXTENSION . 'supercheckout/system/library/kbsupercheckout/newsletter/Webhook.php');
require_once(DIR_EXTENSION . 'supercheckout/system/library/kbsupercheckout/newsletter/sendinBlue/Mailin.php');

use DrewM\MailChimp\MailChimp;
use Exception;

class Supercheckout extends \Opencart\System\Engine\Controller
{
	private $error = array();
	private $session_token_key = 'token';
	private $session_token = '';
	private $module_path = '';

	public function __construct($registry)
	{
		parent::__construct($registry);
		if (VERSION >= 3.0) {
			$this->session_token_key = 'user_token';
			$this->session_token = $this->session->data['user_token'];
		} else {
			$this->session_token_key = 'token';
			$this->session_token = $this->session->data['token'];
		}
		if (VERSION <= '2.2.0') {
			$this->module_path = 'module';
		} else {
			$this->module_path = 'extension/supercheckout/module';
		}
	}

	public function index(): void
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');
		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}


		$data['store_id'] = $store_id;


		$this->preventReinstall();

		$classes_array = $this->getClasses();
		if (isset($classes_array['anchor_classes']['supercheckout_classes'])) {
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		}

		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger'])) {
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];
		}


		// Load settings for supercheckout plugin from database or from default settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		} else {
			$settings_data['supercheckout'] = $old_default_settings['default_supercheckout'];
			$this->model_setting_setting->editSetting('supercheckout', $settings_data, $store_id);
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}

		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$this->request->post['supercheckout']['general']['column_width'] = $old_settings2['supercheckout']['general']['column_width'];
				$old_settings2['supercheckout']['general'] = $this->request->post['supercheckout']['general'];
				$old_settings2['supercheckout']['testing_mode'] = $this->request->post['supercheckout']['testing_mode'];
				$old_settings2['supercheckout']['custom'] = $this->request->post['supercheckout']['custom'];
				$old_settings2['supercheckout']['step']['login']['option']['guest']['display'] = $this->request->post['supercheckout']['step']['login']['option']['guest']['display'];
				$old_settings2['supercheckout']['free_shipping_amount'] = $this->request->post['supercheckout']['free_shipping_amount'];
				$old_settings2['supercheckout']['step']['autodetect_country'] = $this->request->post['supercheckout']['step']['autodetect_country'];
				$old_settings2['supercheckout']['step']['inlinevalidation'] = $this->request->post['supercheckout']['step']['inlinevalidation'];
				$old_settings2['supercheckout']['autofill_address'] = $this->request->post['supercheckout']['autofill_address'];
				$old_settings2['supercheckout']['general']['supercheckout_enable_new_template'] = $this->request->post['supercheckout']['general']['supercheckout_enable_new_template'];

				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				$enable_status['module_supercheckout_status'] = $this->request->post['supercheckout']['general']['enable'];
				$this->model_setting_setting->editSetting('module_supercheckout', $enable_status, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}



		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}
		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		$data['supercheckout_entry_product'] = $this->language->get('supercheckout_entry_product');
		$data['supercheckout_entry_image'] = $this->language->get('supercheckout_entry_image');
		$data['supercheckout_entry_layout'] = $this->language->get('supercheckout_entry_layout');
		$data['supercheckout_entry_position'] = $this->language->get('supercheckout_entry_position');
		$data['supercheckout_entry_status'] = $this->language->get('supercheckout_entry_status');
		$data['supercheckout_entry_sort_order'] = $this->language->get('supercheckout_entry_sort_order');

		//General Settings tab & info
		$data['supercheckout_text_newsletter_enable'] = $this->language->get('supercheckout_text_newsletter_enable');
		$data['supercheckout_text_general'] = $this->language->get('supercheckout_text_general');
		$data['supercheckout_text_general_enable'] = $this->language->get('supercheckout_text_general_enable');
		$data['supercheckout_text_general_guestenable'] = $this->language->get('supercheckout_text_general_guestenable');
		$data['supercheckout_text_general_guest_manual'] = $this->language->get('supercheckout_text_general_guest_manual');
		$data['supercheckout_text_custom_style'] = $this->language->get('supercheckout_text_custom_style');
		$data['supercheckout_text_testing_url'] = $this->language->get('supercheckout_text_testing_url');
		$data['supercheckout_text_testing_enable'] = $this->language->get('supercheckout_text_testing_enable');
		$data['text_copy'] = $this->language->get('text_copy');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');

		$data['supercheckout_text_general_default'] = $this->language->get('supercheckout_text_general_default');
		$data['supercheckout_text_register'] = $this->language->get('supercheckout_text_register');
		$data['supercheckout_text_guest'] = $this->language->get('supercheckout_text_guest');

		$data['supercheckout_text_step_login_option'] = $this->language->get('supercheckout_text_step_login_option');
		$data['supercheckout_text_inline_validation'] = $this->language->get('supercheckout_text_inline_validation');
		$data['step_autodetect_country'] = $this->language->get('step_autodetect_country');
		$data['supercheckout_text_autofill_enable_tooltip'] = $this->language->get('supercheckout_text_autofill_enable_tooltip');
		$data['supercheckout_autofill_address_tooltip'] = $this->language->get('supercheckout_autofill_address_tooltip');
		$data['supercheckout_text_login'] = $this->language->get('supercheckout_text_login');
		$data['step_login_option_register_display'] = $this->language->get('supercheckout_text_register');
		$data['step_login_option_guest_display'] = $this->language->get('supercheckout_text_guest');
		$data['supercheckout_text_enable_new_template'] = $this->language->get('supercheckout_text_enable_new_template');
		$data['supercheckout_text_enable_new_template_tooltip'] = $this->language->get('supercheckout_text_enable_new_template_tooltip');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');


		//Tooltips
		//General
		$data['general_enable_newsletter_tooltip'] = $this->language->get('general_enable_newsletter_tooltip');
		$data['general_enable_supercheckout_tooltip'] = $this->language->get('general_enable_supercheckout_tooltip');
		$data['custom_style_supercheckout_tooltip'] = $this->language->get('custom_style_supercheckout_tooltip');
		$data['general_guestenable_supercheckout_tooltip'] = $this->language->get('general_guestenable_supercheckout_tooltip');
		$data['general_guest_manual_supercheckout_tooltip'] = $this->language->get('general_guest_manual_supercheckout_tooltip');
		$data['general_default_supercheckout_tooltip'] = $this->language->get('general_default_supercheckout_tooltip');
		$data['step_login_option_supercheckout_tooltip'] = $this->language->get('step_login_option_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');
		$data['supercheckout_text_testing_enable_tooltip'] = $this->language->get('supercheckout_text_testing_enable_tooltip');
		$data['supercheckout_text_testing_url_tooltip'] = $this->language->get('supercheckout_text_testing_url_tooltip');

		$data['free_shipping_amount_tooltip'] = $this->language->get('free_shipping_amount_tooltip');

		$data['supercheckout_free_shipping_amount'] = $this->language->get('supercheckout_free_shipping_amount');
		$data['text_freeshipping_info'] = $this->language->get('text_freeshipping_info');


		//errors
		$data['error_empty_field'] = $this->language->get('error_empty_field');
		$data['error_invalid_url'] = $this->language->get('error_invalid_url');
		$data['error_max_url'] = $this->language->get('error_max_url');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/customer_group');
			$results_customer_group = $this->model_sale_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		} else {
			$this->load->model('customer/customer_group');
			$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}




		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_general'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);


		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();




		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}
		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}
		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		$data['supercheckout']['testing_mode']['url'] = HTTP_CATALOG . 'index.php?route=extension/supercheckout/supercheckout/supercheckout';

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 1;

		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);

		$data['store_id'] = $store_id;
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);

		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/supercheckout', $data));
	}

	public function store_swticher($data = array())
	{
		$this->load->language($this->module_path . '/kbsupercheckout');
		$this->load->model('setting/store');
		$data['stores'] = $this->model_setting_store->getStores();
		if (!empty($data['stores'])) {
			if (VERSION < '2.2.0') {
				return $this->load->view($this->module_path . '/kbsupercheckout/store_switcher.tpl', $data);
			} else {
				return $this->load->view($this->module_path . '/kbsupercheckout/store_switcher', $data);
			}
		} else {
			return "";
		}
	}


	public function support()
	{

		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');
		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$data['store_id'] = $store_id;
		$this->preventReinstall();

		$classes_array = $this->getClasses();
		if (isset($classes_array['anchor_classes']['supercheckout_classes'])) {
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		}

		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger'])) {
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_general'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['supercheckout_text_support'] = $this->language->get('supercheckout_text_support');
		$data['text_support_other'] = $this->language->get('text_support_other');
		$data['text_support_marketplace'] = $this->language->get('text_support_marketplace');
		$data['text_support_marketplace_descp'] = $this->language->get('text_support_marketplace_descp');
		$data['text_support_etsy'] = $this->language->get('text_support_etsy');
		$data['text_support_etsy_descp'] = $this->language->get('text_support_etsy_descp');
		$data['text_support_ebay'] = $this->language->get('text_support_ebay');
		$data['text_support_ebay_descp'] = $this->language->get('text_support_ebay_descp');
		$data['text_support_mab'] = $this->language->get('text_support_mab');
		$data['text_support_mab_descp'] = $this->language->get('text_support_mab_descp');
		$data['text_support_view_more'] = $this->language->get('text_support_view_more');
		$data['text_support_ticket1'] = $this->language->get('text_support_ticket1');
		$data['text_support_ticket2'] = $this->language->get('text_support_ticket2');
		$data['text_support_ticket3'] = $this->language->get('text_support_ticket3');
		$data['text_click_here'] = $this->language->get('text_click_here');
		$data['text_user_manual'] = $this->language->get('text_user_manual');

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 12;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);

		$data['heading_title'] = $this->language->get('heading_title');

		//links
		$data['token'] = $this->session_token;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		if (VERSION < '2.2.0') {
			$this->response->setOutput($this->load->view($this->module_path . '/kbsupercheckout/support.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/support', $data));
		}
	}

	public function tabs($data = array())
	{
		$this->load->language($this->module_path . '/kbsupercheckout');

		$store_id = $data['store_id'];

		$data['supercheckout_text_general'] = $this->language->get('supercheckout_text_general');
		$data['supercheckout_text_customizer'] = $this->language->get('supercheckout_text_customizer');
		$data['supercheckout_text_login'] = $this->language->get('supercheckout_text_login');
		$data['supercheckout_text_payment_address'] = $this->language->get('supercheckout_text_payment_address');
		$data['supercheckout_text_shipping_address'] = $this->language->get('supercheckout_text_shipping_address');
		$data['supercheckout_text_shipping_method'] = $this->language->get('supercheckout_text_shipping_method');
		$data['supercheckout_text_ship2pay'] = $this->language->get('supercheckout_text_ship2pay');
		$data['supercheckout_text_payment_method'] = $this->language->get('supercheckout_text_payment_method');
		$data['supercheckout_text_confirm'] = $this->language->get('supercheckout_text_cart');
		$data['supercheckout_text_design'] = $this->language->get('supercheckout_text_design');
		$data['supercheckout_text_mailchimp'] = $this->language->get('supercheckout_text_mailchimp');
		$data['supercheckout_text_support'] = $this->language->get('supercheckout_text_support');

		$data['tab_general_settings'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_general_customizer'] = $this->url->link($this->module_path . '/supercheckout|customizer', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_login'] = $this->url->link($this->module_path . '/supercheckout|login', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_payment_address'] = $this->url->link($this->module_path . '/supercheckout|payment_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_shipping_address'] = $this->url->link($this->module_path . '/supercheckout|shipping_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_shipping'] = $this->url->link($this->module_path . '/supercheckout|shipping_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_ship2pay'] = $this->url->link($this->module_path . '/supercheckout|ship2pay', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_payment'] = $this->url->link($this->module_path . '/supercheckout|payment_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_confirm'] = $this->url->link($this->module_path . '/supercheckout|confirm', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_design_checkout'] = $this->url->link($this->module_path . '/supercheckout|design_checkout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_mailchimp'] = $this->url->link($this->module_path . '/supercheckout|newsletter', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);
		$data['tab_support'] = $this->url->link($this->module_path . '/supercheckout|support', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true);

		if (VERSION < 2.2) {
			return $this->load->view($this->module_path . '/kbsupercheckout/tabs.tpl', $data);
		} else {
			return $this->load->view('extension/supercheckout/kbsupercheckout/tabs', $data);
		}
	}

	public function customizer()
	{

		$this->load->language($this->module_path . '/supercheckout');

		$this->load->model('setting/setting');
		$this->load->model('setting/setting');

		$this->document->setTitle($this->language->get('heading_title_main'));

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}

		// Load settings for supercheckout plugin from database or from default settings
		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {

				$this->session->data['success'] = $this->language->get('supercheckout_text_success_customizer');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);

				$old_settings2['supercheckout']['step']['customizer']['kb_button_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_button_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_button_border_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_button_border_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_button_text_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_button_text_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_border_bottom_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_border_bottom_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_ac_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_ac_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_logout_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_logout_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_login_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_login_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_coupon_button_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_coupon_button_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_voucher_button_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_voucher_button_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['kb_shipping_bar_bg_color'] = $this->request->post['supercheckout']['step']['customizer']['kb_shipping_bar_bg_color'];
				$old_settings2['supercheckout']['step']['customizer']['custom_css'] = $this->request->post['supercheckout']['step']['customizer']['custom_css'];
				$old_settings2['supercheckout']['step']['customizer']['custom_js'] = $this->request->post['supercheckout']['step']['customizer']['custom_js'];


				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|customizer', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		//General Settings tab & info
		$data['supercheckout_text_button_background_color'] = $this->language->get('supercheckout_text_button_background_color');
		$data['supercheckout_text_button_border_color'] = $this->language->get('supercheckout_text_button_border_color');
		$data['supercheckout_text_button_text_color'] = $this->language->get('supercheckout_text_button_text_color');
		$data['supercheckout_text_button_preview'] = $this->language->get('supercheckout_text_button_preview');

		$data['supercheckout_text_button_border_bottom_color'] = $this->language->get('supercheckout_text_button_border_bottom_color');
		$data['supercheckout_text_button_ac_bg_color'] = $this->language->get('supercheckout_text_button_ac_bg_color');
		$data['supercheckout_text_button_logout_bg_color'] = $this->language->get('supercheckout_text_button_logout_bg_color');
		$data['supercheckout_text_button_login_bg_color'] = $this->language->get('supercheckout_text_button_login_bg_color');
		$data['supercheckout_text_button_coupon_bg_color'] = $this->language->get('supercheckout_text_button_coupon_bg_color');
		$data['supercheckout_text_button_voucher_bg_color'] = $this->language->get('supercheckout_text_button_voucher_bg_color');
		$data['supercheckout_text_button_shipping_bar_bg_color'] = $this->language->get('supercheckout_text_button_shipping_bar_bg_color');
		$data['supercheckout_text_customizer_custom_css'] = $this->language->get('supercheckout_text_customizer_custom_css');
		$data['supercheckout_text_customizer_custom_js'] = $this->language->get('supercheckout_text_customizer_custom_js');
		$data['supercheckout_text_customizer'] = $this->language->get('supercheckout_text_customizer');

		//errors
		$data['error_empty_field'] = $this->language->get('error_empty_field');
		$data['error_invalid_url'] = $this->language->get('error_invalid_url');
		$data['error_max_url'] = $this->language->get('error_max_url');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_customizer'),
			'href' => $this->url->link($this->module_path . '/supercheckout|customizer', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|customizer', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		
		$data['supercheckout'] = array();

		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		} 


		/**
		 * Change added to show the customization details with correct index
		 * @date 06-02-2025
		 * @modifier Amit Singh
		 */
		if(empty($data['supercheckout']['step']['customizer']['kb_button_bg_color'])) {
			$data['supercheckout']['step']['customizer']['kb_button_bg_color'] = '#5ebd5e';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_button_border_color'])) {
			$data['supercheckout']['step']['customizer']['kb_button_border_color'] = '#5ebd5e';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_button_text_color'])) {
			$data['supercheckout']['step']['customizer']['kb_button_text_color'] = '#ffffff';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_border_bottom_color'])) {
			$data['supercheckout']['step']['customizer']['kb_border_bottom_color'] = '#5ebd5e';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_login_bg_color'])) {
			$data['supercheckout']['step']['customizer']['kb_login_bg_color'] = '#5ebd5e';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_logout_bg_color'])) {
			$data['supercheckout']['step']['customizer']['kb_logout_bg_color'] = '#000000';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_coupon_button_bg_color'])) {
			$data['supercheckout']['step']['customizer']['kb_coupon_button_bg_color'] = '#5ebd5e';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_voucher_button_bg_color'])) {
			$data['supercheckout']['step']['customizer']['kb_voucher_button_bg_color'] = '#5ebd5e';
		}

		if(empty($data['supercheckout']['step']['customizer']['kb_shipping_bar_bg_color'])) {
			$data['supercheckout']['step']['customizer']['kb_shipping_bar_bg_color'] = '#5ebd5e';
		}

		

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 2;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);

		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);

		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/customizer', $data));
	}

	public function login()
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_login');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$old_settings2['supercheckout']['step']['google_login'] = $this->request->post['supercheckout']['step']['google_login'];
				$old_settings2['supercheckout']['step']['facebook_login'] = $this->request->post['supercheckout']['step']['facebook_login'];
				$old_settings2['supercheckout']['step']['paypal_login'] = $this->request->post['supercheckout']['step']['paypal_login'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|login', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');

		//error
		$data['error_form'] = $this->language->get('error_form');
		$data['error_facebook_app_id'] = $this->language->get('error_facebook_app_id');
		$data['error_facebook_secret_key'] = $this->language->get('error_facebook_secret_key');
		$data['error_google_app_id'] = $this->language->get('error_google_app_id');
		$data['error_google_client_id'] = $this->language->get('error_google_client_id');
		$data['error_google_secret_key'] = $this->language->get('error_google_secret_key');
		$data['error_popup_image'] = $this->language->get('error_popup_image');

		//Login tab and info
		$data['supercheckout_text_facebook_login'] = $this->language->get('supercheckout_text_facebook_login');
		$data['supercheckout_text_facebook_login_display'] = $this->language->get('supercheckout_text_facebook_login_display');
		$data['supercheckout_text_google_login_display'] = $this->language->get('supercheckout_text_google_login_display');
		$data['supercheckout_text_paypal_login_display'] = $this->language->get('supercheckout_text_paypal_login_display');
		$data['supercheckout_text_facebook_app_id'] = $this->language->get('supercheckout_text_facebook_app_id');
		$data['supercheckout_text_facebook_app_secret'] = $this->language->get('supercheckout_text_facebook_app_secret');
		$data['supercheckout_text_google_app_id'] = $this->language->get('supercheckout_text_google_app_id');
		$data['supercheckout_text_google_client_id'] = $this->language->get('supercheckout_text_google_client_id');
		$data['supercheckout_text_google_app_secret'] = $this->language->get('supercheckout_text_google_app_secret');
		$data['supercheckout_text_paypal_client_id'] = $this->language->get('supercheckout_text_paypal_client_id');
		$data['supercheckout_text_paypal_secret'] = $this->language->get('supercheckout_text_paypal_secret');
		$data['supercheckout_text_login'] = $this->language->get('supercheckout_text_login');
		$data['heading_facebook'] = $this->language->get('heading_facebook');
		$data['heading_google'] = $this->language->get('heading_google');
		$data['heading_paypal'] = $this->language->get('heading_paypal');


		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips

		//Login
		$data['facebook_login_display_supercheckout_tooltip'] = $this->language->get('facebook_login_display_supercheckout_tooltip');
		$data['facebook_app_id_supercheckout_tooltip'] = $this->language->get('facebook_app_id_supercheckout_tooltip');
		$data['facebook_secret_supercheckout_tooltip'] = $this->language->get('facebook_secret_supercheckout_tooltip');
		$data['google_login_display_supercheckout_tooltip'] = $this->language->get('google_login_display_supercheckout_tooltip');
		$data['paypal_login_display_supercheckout_tooltip'] = $this->language->get('paypal_login_display_supercheckout_tooltip');
		$data['google_app_id_supercheckout_tooltip'] = $this->language->get('google_app_id_supercheckout_tooltip');
		$data['google_client_id_supercheckout_tooltip'] = $this->language->get('google_client_id_supercheckout_tooltip');
		$data['google_secret_supercheckout_tooltip'] = $this->language->get('google_secret_supercheckout_tooltip');
		$data['paypal_client_id_supercheckout_tooltip'] = $this->language->get('paypal_client_id_supercheckout_tooltip');
		$data['paypal_secret_supercheckout_tooltip'] = $this->language->get('paypal_secret_supercheckout_tooltip');

		//errors
		$data['error_empty_field'] = $this->language->get('error_empty_field');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		//Check coupon & voucher status in store
		$data['coupon_status'] = $this->config->get('total_coupon_status');
		$data['voucher_status'] = $this->config->get('total_voucher_status');
		$data['reward_status'] = $this->config->get('total_reward_status');
		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/customer_group');
			$results_customer_group = $this->model_sale_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		} else {
			$this->load->model('customer/customer_group');
			$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		}


		//Right menu cookies check
		if (isset($this->request->cookie['rightMenu'])) {
			$data['rightMenu'] = true;
		} else {
			$data['rightMenu'] = false;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_login'),
			'href' => $this->url->link($this->module_path . '/supercheckout|login', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|login', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}


		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		//$settings['step']['payment_address']['fields']['agree']['information_id'] = $this->config->get('config_account_id');
		//$settings['step']['payment_address']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_account_id');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 3;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|login', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/login', $data));
	}

	public function payment_address()
	{

		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_PayAdd');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$old_settings2['supercheckout']['step']['payment_address']['fields'] = $this->request->post['supercheckout']['step']['payment_address']['fields'];
				$old_settings2['supercheckout']['option']['guest']['payment_address'] = $this->request->post['supercheckout']['option']['guest']['payment_address'];
				$old_settings2['supercheckout']['option']['logged']['payment_address'] = $this->request->post['supercheckout']['option']['logged']['payment_address'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|payment_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}


		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['settings_checked'] = $this->language->get('settings_checked');

		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		$data['supercheckout_entry_firstname'] = $this->language->get('supercheckout_entry_firstname');
		$data['supercheckout_entry_lastname'] = $this->language->get('supercheckout_entry_lastname');
		$data['supercheckout_entry_telephone'] = $this->language->get('supercheckout_entry_telephone');
		$data['supercheckout_entry_company'] = $this->language->get('supercheckout_entry_company');
		$data['supercheckout_entry_company_id'] = $this->language->get('supercheckout_entry_company_id');
		$data['supercheckout_entry_tax_id'] = $this->language->get('supercheckout_entry_tax_id');
		$data['supercheckout_entry_address_1'] = $this->language->get('supercheckout_entry_address_1');
		$data['supercheckout_entry_address_2'] = $this->language->get('supercheckout_entry_address_2');
		$data['supercheckout_entry_postcode'] = $this->language->get('supercheckout_entry_postcode');
		$data['supercheckout_entry_city'] = $this->language->get('supercheckout_entry_city');
		$data['supercheckout_entry_country'] = $this->language->get('supercheckout_entry_country');
		$data['supercheckout_entry_zone'] = $this->language->get('supercheckout_entry_zone');
		$data['supercheckout_entry_shipping'] = $this->language->get('supercheckout_entry_shipping');

		//Payment address
		$data['supercheckout_text_payment_address'] = $this->language->get('supercheckout_text_payment_address');
		$data['supercheckout_text_guest_customer'] = $this->language->get('supercheckout_text_guest_customer');
		$data['supercheckout_text_registrating_customer'] = $this->language->get('supercheckout_text_registrating_customer');
		$data['supercheckout_text_logged_in_customer'] = $this->language->get('supercheckout_text_logged_in_customer');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		//General
		$data['general_enable_newsletter_tooltip'] = $this->language->get('general_enable_newsletter_tooltip');
		$data['general_enable_supercheckout_tooltip'] = $this->language->get('general_enable_supercheckout_tooltip');
		$data['custom_style_supercheckout_tooltip'] = $this->language->get('custom_style_supercheckout_tooltip');
		$data['general_guestenable_supercheckout_tooltip'] = $this->language->get('general_guestenable_supercheckout_tooltip');
		$data['general_guest_manual_supercheckout_tooltip'] = $this->language->get('general_guest_manual_supercheckout_tooltip');
		$data['general_default_supercheckout_tooltip'] = $this->language->get('general_default_supercheckout_tooltip');
		$data['step_login_option_supercheckout_tooltip'] = $this->language->get('step_login_option_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');
		$data['text_warning'] = $this->language->get('text_warning');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/customer_group');
			$results_customer_group = $this->model_sale_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		} else {
			$this->load->model('customer/customer_group');
			$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		}
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);


		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_payment_address'),
			'href' => $this->url->link($this->module_path . '/supercheckout|payment_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|payment_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}


		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		//$settings['step']['payment_address']['fields']['agree']['information_id'] = $this->config->get('config_account_id');
		//$settings['step']['payment_address']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_account_id');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/custom_field');
			$custom_fields = $this->model_sale_custom_field->getCustomFields();
		} else {
			$this->load->model('customer/custom_field');
			$custom_fields = $this->model_customer_custom_field->getCustomFields();
		}

		foreach ($custom_fields as $key => $value) {
			if ($value['location'] == 'address') {
				if (VERSION <= 2.1) {
					$this->load->model('sale/custom_field');
					$custom_field_name = $this->model_sale_custom_field->getCustomFieldDescriptions($value['custom_field_id']);
				} else {
					$this->load->model('customer/custom_field');
					$custom_field_name = $this->model_customer_custom_field->getDescriptions($value['custom_field_id']);
				}

				$data['custom_fields_status'][$value['custom_field_id']] = $value['status'];
				if ($value['status'] == 1 && isset($data['supercheckout']['option']['guest']['payment_address']['fields'][$value['custom_field_id']]['display'])) {
					$custom_data1['guest'][$value['custom_field_id']]['display'] = $data['supercheckout']['option']['guest']['payment_address']['fields'][$value['custom_field_id']]['display'];
				} else {
					$custom_data1['guest'][$value['custom_field_id']]['display'] = $value['status'];
				}
				if ($value['status'] == 1 && isset($data['supercheckout']['option']['logged']['payment_address']['fields'][$value['custom_field_id']]['display'])) {
					$custom_data1['logged'][$value['custom_field_id']]['display'] = $data['supercheckout']['option']['logged']['payment_address']['fields'][$value['custom_field_id']]['display'];
				} else {
					$custom_data1['logged'][$value['custom_field_id']]['display'] = $value['status'];
				}
				$custom_data1['guest'][$value['custom_field_id']]['require'] = '1';
				$custom_data1['logged'][$value['custom_field_id']]['require'] = '1';

				if (isset($data['supercheckout']['option']['guest']['payment_address']['fields'][$value['custom_field_id']]['require'])) {
					$custom_data1['guest'][$value['custom_field_id']]['require'] = $data['supercheckout']['option']['guest']['payment_address']['fields'][$value['custom_field_id']]['require'];
				}
				if (isset($data['supercheckout']['option']['logged']['payment_address']['fields'][$value['custom_field_id']]['require'])) {
					$custom_data1['logged'][$value['custom_field_id']]['require'] = $data['supercheckout']['option']['logged']['payment_address']['fields'][$value['custom_field_id']]['require'];
				}

				$custom_data2[$value['custom_field_id']]['title'] = $custom_field_name[$this->config->get('config_language_id')]['name'];
				$custom_data2[$value['custom_field_id']]['id'] = $value['custom_field_id'];
				$custom_data2[$value['custom_field_id']]['sort_order'] = $value['sort_order'];
				if (isset($data['supercheckout']['step']['payment_address']['fields'][$value['custom_field_id']]['sort_order'])) {
					$custom_data2[$value['custom_field_id']]['sort_order'] = $data['supercheckout']['step']['payment_address']['fields'][$value['custom_field_id']]['sort_order'];
				}
			}
		}
		if (isset($custom_data1)) {
			$data['customer_group_field_array'] = array();
			foreach ($custom_data1['guest'] as $key => $value) {
				$data['supercheckout']['option']['guest']['payment_address']['fields'][$key] = $value;
			}
			foreach ($custom_data1['logged'] as $key => $value) {
				$data['supercheckout']['option']['logged']['payment_address']['fields'][$key] = $value;
			}
			foreach ($custom_data2 as $key => $value) {
				$data['custom_group_field_array'][] = $value['id'];
				$data['supercheckout']['step']['payment_address']['fields'][$key] = $value;
			}
		}
		//                var_dump(in_array('1', $data['custom_group_field_array']));die;

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 4;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|payment_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		//	$this->template = $this->module_path . '/kbsupercheckout|payment_address.tpl';


		//code for opencart2.0

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		if (VERSION < '2.2.0') {
			$this->response->setOutput($this->load->view($this->module_path . '/kbsupercheckout/payment_address.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/payment_address', $data));
		}
	}

	public function shipping_address()
	{

		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_shipAdd');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$old_settings2['supercheckout']['step']['shipping_address']['fields'] = $this->request->post['supercheckout']['step']['shipping_address']['fields'];
				$old_settings2['supercheckout']['option']['guest']['shipping_address'] = $this->request->post['supercheckout']['option']['guest']['shipping_address'];
				$old_settings2['supercheckout']['option']['logged']['shipping_address'] = $this->request->post['supercheckout']['option']['logged']['shipping_address'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|shipping_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');

		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		$data['supercheckout_entry_firstname'] = $this->language->get('supercheckout_entry_firstname');
		$data['supercheckout_entry_lastname'] = $this->language->get('supercheckout_entry_lastname');
		$data['supercheckout_entry_telephone'] = $this->language->get('supercheckout_entry_telephone');
		$data['supercheckout_entry_company'] = $this->language->get('supercheckout_entry_company');
		$data['supercheckout_entry_company_id'] = $this->language->get('supercheckout_entry_company_id');
		$data['supercheckout_entry_tax_id'] = $this->language->get('supercheckout_entry_tax_id');
		$data['supercheckout_entry_address_1'] = $this->language->get('supercheckout_entry_address_1');
		$data['supercheckout_entry_address_2'] = $this->language->get('supercheckout_entry_address_2');
		$data['supercheckout_entry_postcode'] = $this->language->get('supercheckout_entry_postcode');
		$data['supercheckout_entry_city'] = $this->language->get('supercheckout_entry_city');
		$data['supercheckout_entry_country'] = $this->language->get('supercheckout_entry_country');
		$data['supercheckout_entry_zone'] = $this->language->get('supercheckout_entry_zone');
		$data['supercheckout_entry_shipping'] = $this->language->get('supercheckout_entry_shipping');
		$data['text_warning'] = $this->language->get('text_warning');

		//Payment address
		$data['supercheckout_text_guest_customer'] = $this->language->get('supercheckout_text_guest_customer');
		$data['supercheckout_text_registrating_customer'] = $this->language->get('supercheckout_text_registrating_customer');
		$data['supercheckout_text_logged_in_customer'] = $this->language->get('supercheckout_text_logged_in_customer');

		//Shipping address
		$data['supercheckout_text_shipping_address'] = $this->language->get('supercheckout_text_shipping_address');


		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		//General
		$data['general_enable_newsletter_tooltip'] = $this->language->get('general_enable_newsletter_tooltip');
		$data['general_enable_supercheckout_tooltip'] = $this->language->get('general_enable_supercheckout_tooltip');
		$data['custom_style_supercheckout_tooltip'] = $this->language->get('custom_style_supercheckout_tooltip');
		$data['general_guestenable_supercheckout_tooltip'] = $this->language->get('general_guestenable_supercheckout_tooltip');
		$data['general_guest_manual_supercheckout_tooltip'] = $this->language->get('general_guest_manual_supercheckout_tooltip');
		$data['general_default_supercheckout_tooltip'] = $this->language->get('general_default_supercheckout_tooltip');
		$data['step_login_option_supercheckout_tooltip'] = $this->language->get('step_login_option_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/customer_group');
			$results_customer_group = $this->model_sale_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		} else {
			$this->load->model('customer/customer_group');
			$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_shipping_address'),
			'href' => $this->url->link($this->module_path . '/supercheckout|shipping_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|shipping_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}


		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		//$settings['step']['payment_address']['fields']['agree']['information_id'] = $this->config->get('config_account_id');
		//$settings['step']['payment_address']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_account_id');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/custom_field');
			$custom_fields = $this->model_sale_custom_field->getCustomFields();
		} else {
			$this->load->model('customer/custom_field');
			$custom_fields = $this->model_customer_custom_field->getCustomFields();
		}

		foreach ($custom_fields as $key => $value) {
			if ($value['location'] == 'address') {
				if (VERSION <= 2.1) {
					$this->load->model('sale/custom_field');
					$custom_field_name = $this->model_sale_custom_field->getCustomFieldDescriptions($value['custom_field_id']);
				} else {
					$this->load->model('customer/custom_field');
					$custom_field_name = $this->model_customer_custom_field->getDescriptions($value['custom_field_id']);
				}

				$data['custom_fields_status'][$value['custom_field_id']] = $value['status'];
				if ($value['status'] == 1 && isset($data['supercheckout']['option']['guest']['shipping_address']['fields'][$value['custom_field_id']]['display'])) {
					$custom_data1['guest'][$value['custom_field_id']]['display'] = $data['supercheckout']['option']['guest']['shipping_address']['fields'][$value['custom_field_id']]['display'];
				} else {
					$custom_data1['guest'][$value['custom_field_id']]['display'] = $value['status'];
				}
				if ($value['status'] == 1 && isset($data['supercheckout']['option']['logged']['shipping_address']['fields'][$value['custom_field_id']]['display'])) {
					$custom_data1['logged'][$value['custom_field_id']]['display'] = $data['supercheckout']['option']['logged']['shipping_address']['fields'][$value['custom_field_id']]['display'];
				} else {
					$custom_data1['logged'][$value['custom_field_id']]['display'] = $value['status'];
				}
				$custom_data1['guest'][$value['custom_field_id']]['require'] = '1';
				$custom_data1['logged'][$value['custom_field_id']]['require'] = '1';

				if (isset($data['supercheckout']['option']['guest']['shipping_address']['fields'][$value['custom_field_id']]['require'])) {
					$custom_data1['guest'][$value['custom_field_id']]['require'] = $data['supercheckout']['option']['guest']['shipping_address']['fields'][$value['custom_field_id']]['require'];
				}
				if (isset($data['supercheckout']['option']['logged']['shipping_address']['fields'][$value['custom_field_id']]['require'])) {
					$custom_data1['logged'][$value['custom_field_id']]['require'] = $data['supercheckout']['option']['logged']['shipping_address']['fields'][$value['custom_field_id']]['require'];
				}

				$custom_data2[$value['custom_field_id']]['title'] = $custom_field_name[$this->config->get('config_language_id')]['name'];
				$custom_data2[$value['custom_field_id']]['id'] = $value['custom_field_id'];
				$custom_data2[$value['custom_field_id']]['sort_order'] = $value['sort_order'];
				if (isset($data['supercheckout']['step']['shipping_address']['fields'][$value['custom_field_id']]['sort_order'])) {
					$custom_data2[$value['custom_field_id']]['sort_order'] = $data['supercheckout']['step']['shipping_address']['fields'][$value['custom_field_id']]['sort_order'];
				}
			}
		}
		if (isset($custom_data1)) {
			$data['customer_group_field_array'] = array();
			foreach ($custom_data1['guest'] as $key => $value) {
				$data['supercheckout']['option']['guest']['shipping_address']['fields'][$key] = $value;
			}
			foreach ($custom_data1['logged'] as $key => $value) {
				$data['supercheckout']['option']['logged']['shipping_address']['fields'][$key] = $value;
			}
			foreach ($custom_data2 as $key => $value) {
				$data['custom_group_field_array'][] = $value['id'];
				$data['supercheckout']['step']['shipping_address']['fields'][$key] = $value;
			}
		}

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 5;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|shipping_address', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		//$this->template = $this->module_path . '/kbsupercheckout|shipping_address.tpl';


		//code for opencart2.0

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		if (VERSION < '2.2.0') {
			$this->response->setOutput($this->load->view($this->module_path . '/kbsupercheckout/shipping_address.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/shipping_address', $data));
		}
	}

	public function shipping_method()
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes'])) {
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		}
			
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger'])) {
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];
		}
			
		//Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}

		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_shipMethod');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}

				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$this->request->post['supercheckout']['step']['shipping_method']['three-column'] = $old_settings2['supercheckout']['step']['shipping_method']['three-column'];
				$this->request->post['supercheckout']['step']['shipping_method']['two-column'] = $old_settings2['supercheckout']['step']['shipping_method']['two-column'];
				$this->request->post['supercheckout']['step']['shipping_method']['one-column'] = $old_settings2['supercheckout']['step']['shipping_method']['one-column'];
				$this->request->post['supercheckout']['step']['shipping_method']['available'] = $old_settings2['supercheckout']['step']['shipping_method']['available'];
				$old_settings2['supercheckout']['step']['shipping_method'] = $this->request->post['supercheckout']['step']['shipping_method'];
				$old_settings2['supercheckout']['shipping_logo']['default_option'] = $this->request->post['supercheckout']['shipping_logo']['default_option'];
				$old_settings2['supercheckout']['step']['shipping_method']['logo'] = $this->request->post['supercheckout']['step']['shipping_method']['logo'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|shipping_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_img_hint'] = $this->language->get('text_img_hint');

		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		$data['supercheckout_entry_title'] = $this->language->get('supercheckout_entry_title');
		$data['supercheckout_entry_logo'] = $this->language->get('supercheckout_entry_logo');
		$data['supercheckout_entry_add_logo'] = $this->language->get('supercheckout_entry_add_logo');


		//Shipping method
		$data['supercheckout_text_shipping_method'] = $this->language->get('supercheckout_text_shipping_method');
		$data['supercheckout_text_shipping_method_display_options'] = $this->language->get('supercheckout_text_shipping_method_display_options');
		$data['supercheckout_text_shipping_method_display_title'] = $this->language->get('supercheckout_text_shipping_method_display_title');
		$data['supercheckout_text_shipping_method_default_option'] = $this->language->get('supercheckout_text_shipping_method_default_option');
		$data['supercheckout_text_shipping_method_logo_display_options'] = $this->language->get('supercheckout_text_shipping_method_logo_display_options');

		//Payment method
		$data['supercheckout_text_only'] = $this->language->get('supercheckout_text_only');
		$data['supercheckout_text_with_image'] = $this->language->get('supercheckout_text_with_image');
		$data['supercheckout_image_only'] = $this->language->get('supercheckout_image_only');

		//Confirm
		$data['supercheckout_text_confirm'] = $this->language->get('supercheckout_text_confirm');
		$data['supercheckout_text_confirm_display'] = $this->language->get('supercheckout_text_confirm_display');
		$data['supercheckout_text_agree'] = $this->language->get('supercheckout_text_agree');
		$data['supercheckout_text_comments'] = $this->language->get('supercheckout_text_comments');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		//General
		$data['custom_style_supercheckout_tooltip'] = $this->language->get('custom_style_supercheckout_tooltip');
		$data['step_login_option_supercheckout_tooltip'] = $this->language->get('step_login_option_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');

		//Shipping Method
		$data['shipping_method_display_options_supercheckout_tooltip'] = $this->language->get('shipping_method_display_options_supercheckout_tooltip');
		$data['shipping_method_display_title_supercheckout_tooltip'] = $this->language->get('shipping_method_display_title_supercheckout_tooltip');
		$data['shipping_method_default_option_supercheckout_tooltip'] = $this->language->get('shipping_method_default_option_supercheckout_tooltip');
		$data['shipping_method_logo_display_options_tooltip'] = $this->language->get('shipping_method_logo_display_options_tooltip');
		$data['supercheckout_entry_shipping_method_title_tooltip'] = $this->language->get('supercheckout_entry_shipping_method_title_tooltip');
		$data['supercheckout_entry_shipping_method_logo_tooltip'] = $this->language->get('supercheckout_entry_shipping_method_logo_tooltip');

		//errors
		$data['error_empty_field'] = $this->language->get('error_empty_field');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest'])) {
			$data['guest_enable'] = $store_setting['config_checkout_guest'];
		}

		$this->load->model('customer/customer_group');
		$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);

		//Right menu cookies check
		if (isset($this->request->cookie['rightMenu'])) {
			$data['rightMenu'] = true;
		} else {
			$data['rightMenu'] = false;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_shipping_method'),
			'href' => $this->url->link($this->module_path . '/supercheckout|shipping_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|shipping_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}
		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}

		$data['supercheckout']['general']['store_id'] = $store_id;

		//Get Shipping methods
		$this->load->model('setting/extension');
		$data['shipping_methods'] = array();
		$shipping_methods = $this->model_setting_extension->getExtensionsByType('shipping');

		//Changes added by pragya maurya for fetching teh shipping methods
		foreach ($shipping_methods as $var => $shipping) {
			if ($this->config->get('shipping_' . $shipping['code'] . '_status')) {
				$this->load->language('extension/opencart/shipping/' . $shipping['code']);
				$data['shipping_methods'][] = array(
					'code' => $shipping['code'],
					'title' => $this->language->get('heading_title')
				);
			}
		}

		foreach ($data['shipping_methods'] as $key => $value) {
			if (isset($data['supercheckout']['step']['shipping_method']['logo'][$value['code']]) && $data['supercheckout']['step']['shipping_method']['logo'][$value['code']] != '') {
				$data['shipping_logo'][$value['code'] . '.' . $value['code']] = $data['supercheckout']['step']['shipping_method']['logo'][$value['code']];
			} else {
				if (!file_exists(DIR_IMAGE . 'catalog/kbsupercheckout/' . $value['code'] . '.' . $value['code'] . '.png')) {
					$data['shipping_logo'][$value['code'] . '.' . $value['code']] = 'catalog/kbsupercheckout/shipping_logo.png';
				} else {
					$data['shipping_logo'][$value['code'] . '.' . $value['code']] = 'catalog/kbsupercheckout/' . $value['code'] . '.png';
				}
			}
		}
		foreach ($data['shipping_logo'] as $key => $value) {
			$data['supercheckout']['step']['shipping_method']['logo'][$key] = $value;
		}

		$data['image_dir_url'] = HTTP_CATALOG . 'image/';
		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 6;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|shipping_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		//$this->template = $this->module_path . '/kbsupercheckout/shipping_method.tpl';

		//code for opencart2.0

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		if (VERSION < '2.2.0') {
			$this->response->setOutput($this->load->view($this->module_path . '/kbsupercheckout/shipping_method.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/shipping_method', $data));
		}
	}

	public function ship2pay()
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}

		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes'])) {
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		}

		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger'])) {
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];
		}

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}

		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_ship2pay');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$old_settings2['supercheckout']['step']['shipping_method']['available'] = $this->request->post['supercheckout']['step']['shipping_method']['available'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|ship2pay', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['supercheckout_text_ship2pay'] = $this->language->get('supercheckout_text_ship2pay');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//errors
		$data['error_empty_field'] = $this->language->get('error_empty_field');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		//Right menu cookies check
		if (isset($this->request->cookie['rightMenu'])) {
			$data['rightMenu'] = true;
		} else {
			$data['rightMenu'] = false;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_ship2pay'),
			'href' => $this->url->link($this->module_path . '/supercheckout|ship2pay', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|ship2pay', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		//Get Shipping methods
		$this->load->model('setting/extension');
		$data['shipping_methods'] = array();
		$shipping_methods = $this->model_setting_extension->getExtensionsByType('shipping');
		foreach ($shipping_methods as $shipping) {
			if ($this->config->get('shipping_' . $shipping['code'] . '_status')) {
				$this->load->language('extension/' . $shipping['extension'] . '/shipping/' . $shipping['code']);
				$data['shipping_methods'][] = array(
					'code' => $shipping['code'],
					'title' => $this->language->get('heading_title')
				);
			}
		}

		//Get Payment methods
		$this->load->model('setting/extension');
		$data['payment_methods'] = array();
		$payment_methods = $this->model_setting_extension->getExtensionsByType('payment');

		foreach ($payment_methods as $payment) {
			if ($this->config->get('payment_' . $payment['code'] . '_status')) {

				$this->load->language('extension/' . $payment['extension'] . '/payment/' . $payment['code']);

				$data['payment_methods'][] = array(
					'code' => $payment['code'],
					'title' => $this->language->get('heading_title')
				);
			}
		}


		//Get Stores
		$this->load->model('setting/store');
		$data['stores'] = array();
		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->config->get('config_name') . $this->language->get('text_default'),
			'url'      => HTTP_CATALOG,
			'edit'     => $this->url->link('setting/setting', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true)
		);


		$results = $this->model_setting_store->getStores();
		if ($results) {
			$data['stores'][] = array('store_id' => 0, 'name' => $this->config->get('config_name'));
			foreach ($results as $result) {
				$data['stores'][] = array(
					'store_id' => $result['store_id'],
					'name' => $result['name'],
					'href' => $result['url']
				);
			}
		}
		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 7;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|ship2pay', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/ship2pay', $data));
	}

	public function payment_method()
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_PayMethod');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$this->request->post['supercheckout']['step']['payment_method']['three-column'] = $old_settings2['supercheckout']['step']['payment_method']['three-column'];
				$this->request->post['supercheckout']['step']['payment_method']['two-column'] = $old_settings2['supercheckout']['step']['payment_method']['two-column'];
				$this->request->post['supercheckout']['step']['payment_method']['one-column'] = $old_settings2['supercheckout']['step']['payment_method']['one-column'];
				$old_settings2['supercheckout']['step']['payment_method'] = $this->request->post['supercheckout']['step']['payment_method'];
				$old_settings2['supercheckout']['payment_logo']['default_option'] = $this->request->post['supercheckout']['payment_logo']['default_option'];
				$old_settings2['supercheckout']['step']['payment_method']['logo'] = $this->request->post['supercheckout']['step']['payment_method']['logo'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|payment_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_img_hint'] = $this->language->get('text_img_hint');

		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		//General Settings tab & info
		$data['supercheckout_text_custom_style'] = $this->language->get('supercheckout_text_custom_style');
		$data['supercheckout_entry_title'] = $this->language->get('supercheckout_entry_title');
		$data['supercheckout_entry_logo'] = $this->language->get('supercheckout_entry_logo');
		$data['supercheckout_entry_add_logo'] = $this->language->get('supercheckout_entry_add_logo');

		$data['supercheckout_text_general_default'] = $this->language->get('supercheckout_text_general_default');
		$data['supercheckout_text_register'] = $this->language->get('supercheckout_text_register');
		$data['supercheckout_text_guest'] = $this->language->get('supercheckout_text_guest');

		$data['supercheckout_text_step_login_option'] = $this->language->get('supercheckout_text_step_login_option');
		$data['supercheckout_text_login'] = $this->language->get('supercheckout_text_login');
		$data['step_login_option_register_display'] = $this->language->get('supercheckout_text_register');
		$data['step_login_option_guest_display'] = $this->language->get('supercheckout_text_guest');

		//Payment method
		$data['supercheckout_text_payment_method'] = $this->language->get('supercheckout_text_payment_method');
		$data['supercheckout_text_payment_method_display_options'] = $this->language->get('supercheckout_text_payment_method_display_options');
		$data['supercheckout_text_payment_method_logo_display_options'] = $this->language->get('supercheckout_text_payment_method_logo_display_options');
		$data['supercheckout_text_only'] = $this->language->get('supercheckout_text_only');
		$data['supercheckout_text_with_image'] = $this->language->get('supercheckout_text_with_image');
		$data['supercheckout_image_only'] = $this->language->get('supercheckout_image_only');
		$data['supercheckout_text_payment_method_default_option'] = $this->language->get('supercheckout_text_payment_method_default_option');

		//Confirm
		$data['supercheckout_text_confirm'] = $this->language->get('supercheckout_text_confirm');
		$data['supercheckout_text_confirm_display'] = $this->language->get('supercheckout_text_confirm_display');
		$data['supercheckout_text_agree'] = $this->language->get('supercheckout_text_agree');
		$data['supercheckout_text_comments'] = $this->language->get('supercheckout_text_comments');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		//General
		$data['custom_style_supercheckout_tooltip'] = $this->language->get('custom_style_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');

		//Payment Method
		$data['payment_method_display_options_supercheckout_tooltip'] = $this->language->get('payment_method_display_options_supercheckout_tooltip');
		$data['payment_method_logo_display_options_supercheckout_tooltip'] = $this->language->get('payment_method_logo_display_options_supercheckout_tooltip');
		$data['payment_method_default_option_supercheckout_tooltip'] = $this->language->get('payment_method_default_option_supercheckout_tooltip');
		$data['supercheckout_entry_payment_method_title_tooltip'] = $this->language->get('supercheckout_entry_payment_method_title_tooltip');
		$data['supercheckout_entry_payment_method_logo_tooltip'] = $this->language->get('supercheckout_entry_payment_method_logo_tooltip');

		//errors
		$data['error_empty_field'] = $this->language->get('error_empty_field');
		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest'])) {
			$data['guest_enable'] = $store_setting['config_checkout_guest'];
		}

		$this->load->model('customer/customer_group');
		$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_payment_method'),
			'href' => $this->url->link($this->module_path . '/supercheckout|payment_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|payment_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout/saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout/saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		//Get Payment methods
		$this->load->model('setting/extension');
		$data['payment_methods'] = array();
		$payment_methods = $this->model_setting_extension->getExtensionsByType('payment');
		foreach ($payment_methods as $payment) {
			if ($this->config->get('payment_' . $payment['code'] . '_status')) {
				$this->load->language('extension/' . $payment['extension'] . '/payment/' . $payment['code']);
				$data['payment_methods'][] = array(
					'code' => $payment['code'],
					'title' => $this->language->get('heading_title')
				);
			}
		}

		$data['image_dir_url'] = HTTP_CATALOG . 'image/';
		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 8;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|payment_method', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/payment_method', $data));
	}

	public function confirm()
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_cart');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$old_settings2['supercheckout']['step']['cart']['image_width'] = $this->request->post['supercheckout']['step']['cart']['image_width'];
				$old_settings2['supercheckout']['step']['cart']['image_height'] = $this->request->post['supercheckout']['step']['cart']['image_height'];
				$old_settings2['supercheckout']['step']['confirm']['fields'] = $this->request->post['supercheckout']['step']['confirm']['fields'];
				$old_settings2['supercheckout']['option']['guest']['cart'] = $this->request->post['supercheckout']['option']['guest']['cart'];
				$old_settings2['supercheckout']['option']['logged']['cart'] = $this->request->post['supercheckout']['option']['logged']['cart'];
				$old_settings2['supercheckout']['option']['guest']['confirm'] = $this->request->post['supercheckout']['option']['guest']['confirm'];
				$old_settings2['supercheckout']['option']['logged']['confirm'] = $this->request->post['supercheckout']['option']['logged']['confirm'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|confirm', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');
		$data['error_positive_number'] = $this->language->get('positive_number');
		$data['error_positive_number'] = $this->language->get('positive_number');
		$data['error_number'] = $this->language->get('error_number');
		$data['error_empty_field'] = $this->language->get('error_empty_field');

		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		//General Settings tab & info
		$data['supercheckout_text_newsletter_enable'] = $this->language->get('supercheckout_text_newsletter_enable');
		$data['supercheckout_text_general'] = $this->language->get('supercheckout_text_general');
		$data['supercheckout_text_general_enable'] = $this->language->get('supercheckout_text_general_enable');
		$data['supercheckout_text_general_guestenable'] = $this->language->get('supercheckout_text_general_guestenable');
		$data['supercheckout_text_general_guest_manual'] = $this->language->get('supercheckout_text_general_guest_manual');
		$data['supercheckout_text_custom_style'] = $this->language->get('supercheckout_text_custom_style');

		$data['supercheckout_text_general_default'] = $this->language->get('supercheckout_text_general_default');
		$data['supercheckout_text_register'] = $this->language->get('supercheckout_text_register');
		$data['supercheckout_text_guest'] = $this->language->get('supercheckout_text_guest');

		$data['supercheckout_text_step_login_option'] = $this->language->get('supercheckout_text_step_login_option');
		$data['supercheckout_text_login'] = $this->language->get('supercheckout_text_login');
		$data['step_login_option_register_display'] = $this->language->get('supercheckout_text_register');
		$data['step_login_option_guest_display'] = $this->language->get('supercheckout_text_guest');

		//Cart		
		$data['text_show'] = $this->language->get('text_show');
		$data['text_hide'] = $this->language->get('text_hide');
		$data['supercheckout_text_cart'] = $this->language->get('supercheckout_text_cart');
		$data['supercheckout_text_warning'] = $this->language->get('supercheckout_text_warning');
		$data['supercheckout_text_applicable'] = $this->language->get('supercheckout_text_applicable');
		$data['supercheckout_text_image_size'] = $this->language->get('supercheckout_text_image_size');
		$data['supercheckout_text_cart_display'] = $this->language->get('supercheckout_text_cart_display');
		$data['field_name_title']['supercheckout_text_cart_columns_image'] = $this->language->get('supercheckout_text_cart_columns_image');
		$data['field_name_title']['supercheckout_text_cart_columns_name'] = $this->language->get('supercheckout_text_cart_columns_name');
		$data['field_name_title']['supercheckout_text_cart_columns_model'] = $this->language->get('supercheckout_text_cart_columns_model');
		$data['field_name_title']['supercheckout_text_cart_columns_quantity'] = $this->language->get('supercheckout_text_cart_columns_quantity');
		$data['field_name_title']['supercheckout_text_cart_columns_price'] = $this->language->get('supercheckout_text_cart_columns_price');
		$data['field_name_title']['supercheckout_text_cart_columns_total'] = $this->language->get('supercheckout_text_cart_columns_total');
		$data['supercheckout_text_cart_option_coupon'] = $this->language->get('supercheckout_text_cart_option_coupon');
		$data['supercheckout_text_cart_option_voucher'] = $this->language->get('supercheckout_text_cart_option_voucher');
		$data['supercheckout_text_cart_option_reward'] = $this->language->get('supercheckout_text_cart_option_reward');

		//Confirm
		$data['supercheckout_text_confirm'] = $this->language->get('supercheckout_text_confirm');
		$data['supercheckout_text_confirm_display'] = $this->language->get('supercheckout_text_confirm_display');
		$data['supercheckout_text_agree'] = $this->language->get('supercheckout_text_agree');
		$data['supercheckout_text_comments'] = $this->language->get('supercheckout_text_comments');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		//General
		$data['general_default_supercheckout_tooltip'] = $this->language->get('general_default_supercheckout_tooltip');
		$data['step_login_option_supercheckout_tooltip'] = $this->language->get('step_login_option_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');

		//Cart
		$data['image_size_supercheckout_tooltip'] = $this->language->get('image_size_supercheckout_tooltip');
		$data['cart_display_supercheckout_tooltip'] = $this->language->get('cart_display_supercheckout_tooltip');
		$data['cart_option_coupon_supercheckout_tooltip'] = $this->language->get('cart_option_coupon_supercheckout_tooltip');
		$data['cart_option_reward_supercheckout_tooltip'] = $this->language->get('cart_option_reward_supercheckout_tooltip');
		$data['cart_option_voucher_supercheckout_tooltip'] = $this->language->get('cart_option_voucher_supercheckout_tooltip');
		$data['cart_option_coupon_disabled_supercheckout_tooltip'] = $this->language->get('cart_option_coupon_disabled_supercheckout_tooltip');
		$data['cart_option_reward_disabled_supercheckout_tooltip'] = $this->language->get('cart_option_reward_disabled_supercheckout_tooltip');
		$data['cart_option_reward_applicable_supercheckout_tooltip'] = $this->language->get('cart_option_reward_applicable_supercheckout_tooltip');
		$data['cart_option_voucher_disabled_supercheckout_tooltip'] = $this->language->get('cart_option_voucher_disabled_supercheckout_tooltip');
		$data['supercheckout_text_guest_customer'] = $this->language->get('supercheckout_text_guest_customer');
		$data['supercheckout_text_registrating_customer'] = $this->language->get('supercheckout_text_registrating_customer');
		$data['supercheckout_text_logged_in_customer'] = $this->language->get('supercheckout_text_logged_in_customer');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		//Check coupon & voucher status in store
		$data['coupon_status'] = $this->config->get('total_coupon_status');
		$data['voucher_status'] = $this->config->get('total_voucher_status');
		$data['reward_status'] = $this->config->get('total_reward_status');
		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/customer_group');
			$results_customer_group = $this->model_sale_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		} else {
			$this->load->model('customer/customer_group');
			$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		}
		if ($store_setting['config_checkout_id']) {
			$this->load->model('catalog/information');
			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));
			if ($information_info) {
				$data['text_agree'] = 1;
			} else {
				$data['text_agree'] = 0;
			}
		} else {
			$data['text_agree'] = 0;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_confirm'),
			'href' => $this->url->link($this->module_path . '/supercheckout/confirm', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|confirm', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}


		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		//$settings['step']['payment_address']['fields']['agree']['information_id'] = $this->config->get('config_account_id');
		//$settings['step']['payment_address']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_account_id');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 9;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|confirm', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/confirm', $data));
	}

	public function design_checkout()
	{

		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_design');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$this->request->post['supercheckout']['step']['login']['option'] = $old_settings2['supercheckout']['step']['login']['option'];
				$old_settings2['supercheckout']['general']['layout'] = $this->request->post['supercheckout']['general']['layout'];
				$old_settings2['supercheckout']['general']['column_width'] = $this->request->post['supercheckout']['general']['column_width'];
				$old_settings2['supercheckout']['general']['checkout_style'] = isset($this->request->post['supercheckout']['general']['checkout_style']) ? $this->request->post['supercheckout']['general']['checkout_style'] : '';
				$old_settings2['supercheckout']['step']['html'] = $this->request->post['supercheckout']['step']['html'];
				$old_settings2['supercheckout']['step']['html_value'] = $this->request->post['supercheckout']['step']['html_value'];
				$old_settings2['supercheckout']['step']['modal_value'] = $this->request->post['supercheckout']['step']['modal_value'];
				$old_settings2['supercheckout']['step']['login'] = $this->request->post['supercheckout']['step']['login'];
				$old_settings2['supercheckout']['step']['payment_method']['three-column'] = $this->request->post['supercheckout']['step']['payment_method']['three-column'];
				$old_settings2['supercheckout']['step']['payment_method']['two-column'] = $this->request->post['supercheckout']['step']['payment_method']['two-column'];
				$old_settings2['supercheckout']['step']['payment_method']['one-column'] = $this->request->post['supercheckout']['step']['payment_method']['one-column'];
				$old_settings2['supercheckout']['step']['shipping_method']['three-column'] = $this->request->post['supercheckout']['step']['shipping_method']['three-column'];
				$old_settings2['supercheckout']['step']['shipping_method']['two-column'] = $this->request->post['supercheckout']['step']['shipping_method']['two-column'];
				$old_settings2['supercheckout']['step']['shipping_method']['one-column'] = $this->request->post['supercheckout']['step']['shipping_method']['one-column'];
				$old_settings2['supercheckout']['step']['payment_address']['three-column'] = $this->request->post['supercheckout']['step']['payment_address']['three-column'];
				$old_settings2['supercheckout']['step']['payment_address']['two-column'] = $this->request->post['supercheckout']['step']['payment_address']['two-column'];
				$old_settings2['supercheckout']['step']['payment_address']['one-column'] = $this->request->post['supercheckout']['step']['payment_address']['one-column'];
				$old_settings2['supercheckout']['step']['shipping_address']['three-column'] = $this->request->post['supercheckout']['step']['shipping_address']['three-column'];
				$old_settings2['supercheckout']['step']['shipping_address']['two-column'] = $this->request->post['supercheckout']['step']['shipping_address']['two-column'];
				$old_settings2['supercheckout']['step']['shipping_address']['one-column'] = $this->request->post['supercheckout']['step']['shipping_address']['one-column'];
				$old_settings2['supercheckout']['step']['cart']['three-column'] = $this->request->post['supercheckout']['step']['cart']['three-column'];
				$old_settings2['supercheckout']['step']['cart']['two-column'] = $this->request->post['supercheckout']['step']['cart']['two-column'];
				$old_settings2['supercheckout']['step']['cart']['one-column'] = $this->request->post['supercheckout']['step']['cart']['one-column'];
				$old_settings2['supercheckout']['step']['confirm']['three-column'] = $this->request->post['supercheckout']['step']['confirm']['three-column'];
				$old_settings2['supercheckout']['step']['confirm']['two-column'] = $this->request->post['supercheckout']['step']['confirm']['two-column'];
				$old_settings2['supercheckout']['step']['confirm']['one-column'] = $this->request->post['supercheckout']['step']['confirm']['one-column'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link('extension/supercheckout/module/supercheckout|design_checkout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_main'] = $this->language->get('heading_title_main');

		// Words
		$data['settings_display'] = $this->language->get('settings_display');
		$data['settings_require'] = $this->language->get('settings_require');
		$data['settings_enable'] = $this->language->get('settings_enable');
		$data['supercheckout_text_enabled'] = $this->language->get('supercheckout_text_enabled');
		$data['supercheckout_text_disabled'] = $this->language->get('supercheckout_text_disabled');

		$data['supercheckout_entry_product'] = $this->language->get('supercheckout_entry_product');
		$data['supercheckout_entry_image'] = $this->language->get('supercheckout_entry_image');
		$data['supercheckout_entry_layout'] = $this->language->get('supercheckout_entry_layout');
		$data['supercheckout_entry_position'] = $this->language->get('supercheckout_entry_position');
		$data['supercheckout_entry_status'] = $this->language->get('supercheckout_entry_status');
		$data['supercheckout_entry_sort_order'] = $this->language->get('supercheckout_entry_sort_order');

		$data['supercheckout_entry_firstname'] = $this->language->get('supercheckout_entry_firstname');
		$data['supercheckout_entry_lastname'] = $this->language->get('supercheckout_entry_lastname');
		$data['supercheckout_entry_telephone'] = $this->language->get('supercheckout_entry_telephone');
		$data['supercheckout_entry_company'] = $this->language->get('supercheckout_entry_company');
		$data['supercheckout_entry_company_id'] = $this->language->get('supercheckout_entry_company_id');
		$data['supercheckout_entry_tax_id'] = $this->language->get('supercheckout_entry_tax_id');
		$data['supercheckout_entry_address_1'] = $this->language->get('supercheckout_entry_address_1');
		$data['supercheckout_entry_address_2'] = $this->language->get('supercheckout_entry_address_2');
		$data['supercheckout_entry_postcode'] = $this->language->get('supercheckout_entry_postcode');
		$data['supercheckout_entry_city'] = $this->language->get('supercheckout_entry_city');
		$data['supercheckout_entry_country'] = $this->language->get('supercheckout_entry_country');
		$data['supercheckout_entry_zone'] = $this->language->get('supercheckout_entry_zone');
		$data['supercheckout_entry_shipping'] = $this->language->get('supercheckout_entry_shipping');

		//General Settings tab & info
		$data['supercheckout_text_newsletter_enable'] = $this->language->get('supercheckout_text_newsletter_enable');
		$data['supercheckout_text_general'] = $this->language->get('supercheckout_text_general');
		$data['supercheckout_text_general_enable'] = $this->language->get('supercheckout_text_general_enable');
		$data['supercheckout_text_general_guestenable'] = $this->language->get('supercheckout_text_general_guestenable');
		$data['supercheckout_text_general_guest_manual'] = $this->language->get('supercheckout_text_general_guest_manual');
		$data['supercheckout_text_custom_style'] = $this->language->get('supercheckout_text_custom_style');

		$data['supercheckout_text_general_default'] = $this->language->get('supercheckout_text_general_default');
		$data['supercheckout_text_register'] = $this->language->get('supercheckout_text_register');
		$data['supercheckout_text_guest'] = $this->language->get('supercheckout_text_guest');

		$data['supercheckout_text_step_login_option'] = $this->language->get('supercheckout_text_step_login_option');
		$data['supercheckout_text_login'] = $this->language->get('supercheckout_text_login');
		$data['step_login_option_register_display'] = $this->language->get('supercheckout_text_register');
		$data['step_login_option_guest_display'] = $this->language->get('supercheckout_text_guest');

		//error
		$data['error_form'] = $this->language->get('error_form');
		$data['error_facebook_app_id'] = $this->language->get('error_facebook_app_id');
		$data['error_facebook_secret_key'] = $this->language->get('error_facebook_secret_key');
		$data['error_google_app_id'] = $this->language->get('error_google_app_id');
		$data['error_google_client_id'] = $this->language->get('error_google_client_id');
		$data['error_google_secret_key'] = $this->language->get('error_google_secret_key');
		$data['error_popup_image'] = $this->language->get('error_popup_image');

		//Login tab and info
		$data['supercheckout_text_facebook_login'] = $this->language->get('supercheckout_text_facebook_login');
		$data['supercheckout_text_facebook_login_display'] = $this->language->get('supercheckout_text_facebook_login_display');
		$data['supercheckout_text_google_login_display'] = $this->language->get('supercheckout_text_google_login_display');
		$data['supercheckout_text_paypal_login_display'] = $this->language->get('supercheckout_text_paypal_login_display');
		$data['supercheckout_text_facebook_app_id'] = $this->language->get('supercheckout_text_facebook_app_id');
		$data['supercheckout_text_facebook_app_secret'] = $this->language->get('supercheckout_text_facebook_app_secret');
		$data['supercheckout_text_google_app_id'] = $this->language->get('supercheckout_text_google_app_id');
		$data['supercheckout_text_google_client_id'] = $this->language->get('supercheckout_text_google_client_id');
		$data['supercheckout_text_google_app_secret'] = $this->language->get('supercheckout_text_google_app_secret');


		//Payment address
		$data['supercheckout_text_payment_address'] = $this->language->get('supercheckout_text_payment_address');
		$data['supercheckout_text_guest_customer'] = $this->language->get('supercheckout_text_guest_customer');
		$data['supercheckout_text_registrating_customer'] = $this->language->get('supercheckout_text_registrating_customer');
		$data['supercheckout_text_logged_in_customer'] = $this->language->get('supercheckout_text_logged_in_customer');

		//Shipping address
		$data['supercheckout_text_shipping_address'] = $this->language->get('supercheckout_text_shipping_address');


		//Shipping method
		$data['supercheckout_text_shipping_method'] = $this->language->get('supercheckout_text_shipping_method');
		$data['supercheckout_text_shipping_method_display_options'] = $this->language->get('supercheckout_text_shipping_method_display_options');
		$data['supercheckout_text_shipping_method_display_title'] = $this->language->get('supercheckout_text_shipping_method_display_title');
		$data['supercheckout_text_shipping_method_default_option'] = $this->language->get('supercheckout_text_shipping_method_default_option');

		//Payment method
		$data['supercheckout_text_payment_method'] = $this->language->get('supercheckout_text_payment_method');
		$data['supercheckout_text_payment_method_display_options'] = $this->language->get('supercheckout_text_payment_method_display_options');
		$data['supercheckout_text_payment_method_logo_display_options'] = $this->language->get('supercheckout_text_payment_method_logo_display_options');
		$data['supercheckout_text_only'] = $this->language->get('supercheckout_text_only');
		$data['supercheckout_text_with_image'] = $this->language->get('supercheckout_text_with_image');
		$data['supercheckout_image_only'] = $this->language->get('supercheckout_image_only');
		$data['supercheckout_text_payment_method_default_option'] = $this->language->get('supercheckout_text_payment_method_default_option');

		//Cart
		$data['supercheckout_text_cart'] = $this->language->get('supercheckout_text_cart');
		$data['supercheckout_text_warning'] = $this->language->get('supercheckout_text_warning');
		$data['supercheckout_text_applicable'] = $this->language->get('supercheckout_text_applicable');
		$data['supercheckout_text_image_size'] = $this->language->get('supercheckout_text_image_size');
		$data['supercheckout_text_cart_display'] = $this->language->get('supercheckout_text_cart_display');
		$data['supercheckout_text_cart_columns_image'] = $this->language->get('supercheckout_text_cart_columns_image');
		$data['supercheckout_text_cart_columns_name'] = $this->language->get('supercheckout_text_cart_columns_name');
		$data['supercheckout_text_cart_columns_model'] = $this->language->get('supercheckout_text_cart_columns_model');
		$data['supercheckout_text_cart_columns_quantity'] = $this->language->get('supercheckout_text_cart_columns_quantity');
		$data['supercheckout_text_cart_columns_price'] = $this->language->get('supercheckout_text_cart_columns_price');
		$data['supercheckout_text_cart_columns_total'] = $this->language->get('supercheckout_text_cart_columns_total');
		$data['supercheckout_text_cart_option_coupon'] = $this->language->get('supercheckout_text_cart_option_coupon');
		$data['supercheckout_text_cart_option_voucher'] = $this->language->get('supercheckout_text_cart_option_voucher');
		$data['supercheckout_text_cart_option_reward'] = $this->language->get('supercheckout_text_cart_option_reward');

		//Confirm
		$data['supercheckout_text_confirm'] = $this->language->get('supercheckout_text_confirm');
		$data['supercheckout_text_confirm_display'] = $this->language->get('supercheckout_text_confirm_display');
		$data['supercheckout_text_agree'] = $this->language->get('supercheckout_text_agree');
		$data['supercheckout_text_comments'] = $this->language->get('supercheckout_text_comments');

		//HTML
		$data['html_content'] = $this->language->get('html_content');
		$data['supercheckout_text_html'] = $this->language->get('supercheckout_text_html');
		$data['supercheckout_text_html_header'] = $this->language->get('supercheckout_text_html_header');
		$data['supercheckout_text_html_footer'] = $this->language->get('supercheckout_text_html_footer');
		$data['supercheckout_text_html_description'] = $this->language->get('supercheckout_text_html_description');

		//Design
		$data['supercheckout_text_design'] = $this->language->get('supercheckout_text_design');
		$data['supercheckout_text_payment_address_description'] = $this->language->get('supercheckout_text_payment_address_description');
		$data['supercheckout_text_shipping_address_description'] = $this->language->get('supercheckout_text_shipping_address_description');
		$data['supercheckout_text_shipping_method_description'] = $this->language->get('supercheckout_text_shipping_method_description');
		$data['supercheckout_text_payment_method_description'] = $this->language->get('supercheckout_text_payment_method_description');
		$data['supercheckout_text_cart_description'] = $this->language->get('supercheckout_text_cart_description');
		$data['supercheckout_text_confirm_description'] = $this->language->get('supercheckout_text_confirm_description');
		$data['text_column_1'] = $this->language->get('text_column_1');
		$data['text_column_2'] = $this->language->get('text_column_2');
		$data['text_column_3'] = $this->language->get('text_column_3');
		$data['text_step_checkout'] = $this->language->get('text_step_checkout');
		$data['text_edit_html'] = $this->language->get('text_edit_html');
		$data['text_save'] = $this->language->get('text_save');
		$data['text_close'] = $this->language->get('text_close');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		//General
		$data['general_enable_newsletter_tooltip'] = $this->language->get('general_enable_newsletter_tooltip');
		$data['general_enable_supercheckout_tooltip'] = $this->language->get('general_enable_supercheckout_tooltip');
		$data['custom_style_supercheckout_tooltip'] = $this->language->get('custom_style_supercheckout_tooltip');
		$data['general_guestenable_supercheckout_tooltip'] = $this->language->get('general_guestenable_supercheckout_tooltip');
		$data['general_guest_manual_supercheckout_tooltip'] = $this->language->get('general_guest_manual_supercheckout_tooltip');
		$data['general_default_supercheckout_tooltip'] = $this->language->get('general_default_supercheckout_tooltip');
		$data['step_login_option_supercheckout_tooltip'] = $this->language->get('step_login_option_supercheckout_tooltip');
		$data['guest_enable_disabled_supercheckout_tooltip'] = $this->language->get('guest_enable_disabled_supercheckout_tooltip');
		$data['field_disabled_supercheckout_tooltip'] = $this->language->get('field_disabled_supercheckout_tooltip');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_save_and_stay'] = $this->language->get('button_save_and_stay');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');

		//Check coupon & voucher status in store
		$data['coupon_status'] = $this->config->get('total_coupon_status');
		$data['voucher_status'] = $this->config->get('total_voucher_status');
		$data['reward_status'] = $this->config->get('total_reward_status');
		$store_setting = $this->model_setting_setting->getSetting('config', $store_id);
		if (isset($store_setting['config_checkout_guest']))
			$data['guest_enable'] = $store_setting['config_checkout_guest'];

		if (version_compare(VERSION, '2.1.0.1', '<')) {
			$this->load->model('sale/customer_group');
			$results_customer_group = $this->model_sale_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		} else {
			$this->load->model('customer/customer_group');
			$results_customer_group = $this->model_customer_customer_group->getCustomerGroup($store_setting['config_customer_group_id']);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_design'),
			'href' => $this->url->link($this->module_path . '/supercheckout|design_checkout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}
		//links
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|design_checkout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}
		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}
		if (!isset($this->request->get['layout'])) {
			$data['layout'] = $data['supercheckout']['general']['layout'];
		} else {
			$data['layout'] = $this->request->get['layout'];
		}
		if (isset($data['supercheckout']['step']['html_value']['value']['footer'])) {
			$data['supercheckout']['step']['html_value']['value']['header'] = html_entity_decode($data['supercheckout']['step']['html_value']['value']['header']);
		}
		if (isset($data['supercheckout']['step']['html_value']['value']['header'])) {
			$data['supercheckout']['step']['html_value']['value']['footer'] = html_entity_decode($data['supercheckout']['step']['html_value']['value']['footer']);
		}
		foreach ($data['supercheckout']['step']['html'] as $key => $value) {
			$value['value'] = html_entity_decode($value['value']);
			$data['supercheckout']['step']['html'][$key] = $value;
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;

		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 10;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|design_checkout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/design_checkout', $data));
	}

	public function newsletter()
	{
		$this->load->language($this->module_path . '/supercheckout');
		$this->document->setTitle($this->language->get('heading_title_main'));
		$this->load->model('setting/setting');

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}
		$this->preventReinstall();
		$classes_array = $this->getClasses();

		if (isset($classes_array['anchor_classes']['supercheckout_classes']))
			$data['anchor_classes'] = $classes_array['anchor_classes']['supercheckout_classes'];
		if (isset($classes_array['anchor_classes_trigger']['supercheckout_trigger']))
			$data['anchor_classes_trigger'] = $classes_array['anchor_classes_trigger']['supercheckout_trigger'];

		// Load settings for supercheckout plugin from database or from default settings
		$this->load->model('setting/setting');

		//Check for old settings
		$old_settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$old_default_settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
		if (!empty($old_settings)) {
			$new_settings = array();
			if (!isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_settings['supercheckout']['general'] = array_merge($old_settings['supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('supercheckout', $old_settings, $store_id);
			}
		}
		if (!empty($old_default_settings)) {
			$new_settings = array();
			if (isset($old_settings['supercheckout']['general']['adv_id'])) {
				$new_settings = array('default_supercheckout' => array('general' => array('version' => '2.2', 'adv_id' => 0, 'plugin_id' => 'OC0001')));
				$old_default_settings['default_supercheckout']['general'] = array_merge($old_default_settings['default_supercheckout']['general'], $new_settings['default_supercheckout']['general']);
				$this->model_setting_setting->editSetting('default_supercheckout', $old_default_settings, $store_id);
			}
		}
		$result = $this->model_setting_setting->getSetting('supercheckout', $store_id);

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) { {
				$this->session->data['success'] = $this->language->get('supercheckout_text_success_newsletter');
				if (isset($this->request->post['supercheckout']['general']['settings']['value'])) {
					$settings = str_replace("amp;", "", urldecode($this->request->post['supercheckout']['general']['settings']['bulk']));
					parse_str($settings, $this->request->post);
				}
				$old_settings2 = $this->model_setting_setting->getSetting('supercheckout', $store_id);
				$old_settings2['supercheckout']['mailchimp'] = $this->request->post['supercheckout']['mailchimp'];
				$old_settings2['supercheckout']['klaviyo'] = $this->request->post['supercheckout']['klaviyo'];
				$old_settings2['supercheckout']['sendinblue'] = $this->request->post['supercheckout']['sendinblue'];
				$this->model_setting_setting->editSetting('supercheckout', $old_settings2, $store_id);
				if (!isset($this->request->post['save'])) {
					$this->response->redirect($this->url->link($this->module_path . '/supercheckout|newsletter', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				} else if (!isset($this->session_token)) {
					$this->response->redirect($this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
				}
			}
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			$this->session->data['success'] = '';
		} else {
			$data['success'] = '';
		}


		$data['heading_title'] = $this->language->get('heading_title');
		$data['supercheckout_text_mailchimp'] = $this->language->get('supercheckout_text_mailchimp');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_get_list'] = $this->language->get('text_get_list');

		$data['supercheckout_text_mailchimp_enable'] = $this->language->get('supercheckout_text_mailchimp_enable');
		$data['supercheckout_text_mailchimp_api'] = $this->language->get('supercheckout_text_mailchimp_api');
		$data['supercheckout_text_mailchimp_list'] = $this->language->get('supercheckout_text_mailchimp_list');
		$data['text_mailchimp_empty_list'] = $this->language->get('text_mailchimp_empty_list');
		$data['text_mailchimp_invalid_key'] = $this->language->get('text_mailchimp_invalid_key');

		$data['supercheckout_text_klaviyo_enable'] = $this->language->get('supercheckout_text_klaviyo_enable');
		$data['supercheckout_text_klaviyo_api'] = $this->language->get('supercheckout_text_klaviyo_api');
		$data['supercheckout_text_klaviyo_list'] = $this->language->get('supercheckout_text_klaviyo_list');
		$data['text_klaviyo_empty_list'] = $this->language->get('text_klaviyo_empty_list');
		$data['text_klaviyo_invalid_key'] = $this->language->get('text_klaviyo_invalid_key');

		$data['supercheckout_text_sendinblue_enable'] = $this->language->get('supercheckout_text_sendinblue_enable');
		$data['supercheckout_text_sendinblue_api'] = $this->language->get('supercheckout_text_sendinblue_api');
		$data['supercheckout_text_sendinblue_list'] = $this->language->get('supercheckout_text_sendinblue_list');
		$data['text_sendinblue_empty_list'] = $this->language->get('text_sendinblue_empty_list');
		$data['text_sendinblue_invalid_key'] = $this->language->get('text_sendinblue_invalid_key');

		$data['error_empty_field'] = $this->language->get('error_empty_field');

		//Language
		$data['supercheckout_text_language'] = $this->language->get('supercheckout_text_language');

		//Tooltips
		$data['supercheckout_text_mailchimp_enable_tooltip'] = $this->language->get('supercheckout_text_mailchimp_enable_tooltip');
		$data['supercheckout_text_mailchimp_api_tooltip'] = $this->language->get('supercheckout_text_mailchimp_api_tooltip');
		$data['supercheckout_text_mailchimp_list_tooltip'] = $this->language->get('supercheckout_text_mailchimp_list_tooltip');

		$data['supercheckout_text_klaviyo_enable_tooltip'] = $this->language->get('supercheckout_text_klaviyo_enable_tooltip');
		$data['supercheckout_text_klaviyo_api_tooltip'] = $this->language->get('supercheckout_text_klaviyo_api_tooltip');
		$data['supercheckout_text_klaviyo_list_tooltip'] = $this->language->get('supercheckout_text_klaviyo_list_tooltip');

		$data['supercheckout_text_sendinblue_enable_tooltip'] = $this->language->get('supercheckout_text_sendinblue_enable_tooltip');
		$data['supercheckout_text_sendinblue_api_tooltip'] = $this->language->get('supercheckout_text_sendinblue_api_tooltip');
		$data['supercheckout_text_sendinblue_list_tooltip'] = $this->language->get('supercheckout_text_sendinblue_list_tooltip');

		//Buttons
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_remove'] = $this->language->get('button_remove');

		//Right menu cookies check
		if (isset($this->request->cookie['rightMenu'])) {
			$data['rightMenu'] = true;
		} else {
			$data['rightMenu'] = false;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		//Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_home'),
			'href' => $this->url->link('common/home', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_module'),
			'href' => $this->url->link('extension/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_main'),
			'href' => $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('supercheckout_text_mailchimp'),
			'href' => $this->url->link($this->module_path . '/supercheckout|newsletter', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'),
		);

		$data['mailchimp_list_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|mailchimp_getList', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
		$data['klaviyo_list_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|klaviyoGetLists', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
		$data['sendinblue_list_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|getSendinBlueList', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL'));
		$data['action'] = $this->url->link($this->module_path . '/supercheckout|newsletter', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes'] = $this->url->link($this->module_path . '/supercheckout|saveClasses', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['action_save_classes_trigger'] = $this->url->link($this->module_path . '/supercheckout|saveClassesTrigger', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['route'] = $this->url->link($this->module_path . '/supercheckout', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, 'SSL');
		$data['token'] = $this->session_token;
		$data['supercheckout'] = array();

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}


		if (isset($this->request->post['supercheckout'])) {
			$data['supercheckout'] = $this->request->post['supercheckout'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			$data['supercheckout'] = $settings['supercheckout'];
		}

		$data['supercheckout_modules'] = array();
		if (isset($this->request->post['supercheckout_module'])) {
			$data['supercheckout_modules'] = $this->request->post['supercheckout_module'];
		} elseif ($this->model_setting_setting->getSetting('supercheckout', $store_id)) {
			$modules = $this->model_setting_setting->getSetting('supercheckout', $store_id);
			if (!empty($modules['supercheckout_module'])) {
				$data['supercheckout_modules'] = $modules['supercheckout_module'];
			} else {
				$data['supercheckout_modules'] = array();
			}
		}

		if (empty($settings)) {
			$settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
			$data['settings'] = $settings['default_supercheckout'];
			$data['supercheckout'] = $settings['default_supercheckout'];
		}

		//Store Settings
		$settings['general']['default_email'] = $this->config->get('config_email');
		$settings['step']['confirm']['fields']['agree']['information_id'] = $this->config->get('config_checkout_id');
		$settings['step']['confirm']['fields']['agree']['error'][0]['information_id'] = $this->config->get('config_checkout_id');

		if (!empty($data['supercheckout'])) {
			$data['supercheckout'] = $this->merge($settings, $data['supercheckout']);
		} else {
			$data['supercheckout'] = $settings;
		}
		$data['supercheckout']['general']['store_id'] = $store_id;


		$tabs_data['store_id'] = $store_id;
		$tabs_data['active'] = 11;
		$data['tabs'] = $this->load->controller($this->module_path . '/supercheckout|tabs', $tabs_data);
		$data['store_id'] = $store_id;
		$data['current_url'] = html_entity_decode($this->url->link($this->module_path . '/supercheckout|newsletter', $this->session_token_key . '=' . $this->session_token . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', $this->session_token_key . '=' . $this->session_token . '&type=module&store_id=' . $store_id, true);
		$data['text_default'] = $this->language->get('text_default');
		$data['store_switcher'] = $this->load->controller($this->module_path . '/supercheckout|store_swticher', $data);
		$this->load->model('design/layout');
		$data['layouts'] = $this->model_design_layout->getLayouts();
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/supercheckout/kbsupercheckout/newsletter', $data));
	}

	private function validate()
	{
		if (!$this->user->hasPermission('modify', $this->module_path . '/supercheckout')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}

	public function merge(array &$array1, array &$array2)
	{
		$merged = $array1;
		foreach ($array2 as $key => &$value) {
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
				$merged[$key] = $this->merge($merged[$key], $value);
			} else {
				$merged[$key] = $value;
			}
		}
		return $merged;
	}

	public function get_title($fields, $texts)
	{
		$this->load->model('catalog/information');
		$array_full = $fields;
		$result = array();
		foreach ($fields as $key => $value) {
			foreach ($texts as $text) {
				if (isset($array_full[$text])) {
					if (!is_array($array_full[$text])) {
						$result[$text] = $this->language->get($array_full[$text]);
					} else {
						if (isset($array_full[$text][(int) $this->config->get('config_language_id')])) {
							$result[$text] = $array_full[$text][(int) $this->config->get('config_language_id')];
						} else {
							$result[$text] = current($array_full[$text]);
						}
					}
					if ((strpos($result[$text], '%s') !== false) && isset($array_full['information_id'])) {
						$information_info = $this->model_catalog_information->getInformation($array_full['information_id']);
						$result[$text] = sprintf($result[$text], $information_info['title']);
					}
				}
			}
			if (is_array($array_full[$key])) {
				$result[$key] = $this->get_title($array_full[$key], $texts);
			}
		}
		return $result;
	}

	public function install()
	{
		$this->load->model('setting/setting');
		$default_settings = $this->getDefaultSettings();
		
		$this->model_setting_setting->editSetting('default_supercheckout', $default_settings, 0);
		$this->model_setting_setting->editSetting('supercheckout', $default_settings, 0);

		$check_classes = $this->model_setting_setting->getSetting('supercheckout_classes');
		$check_classes_trigger = $this->model_setting_setting->getSetting('supercheckout_trigger');
		if (empty($check_classes)) {
			$this->model_setting_setting->editSetting('supercheckout_classes', array('supercheckout_classes' => '#display_payment .button, #display_payment .btn, #display_payment .button_oc, #display_payment input[type=submit]'));
		}
		if (empty($check_classes_trigger)) {
			$this->model_setting_setting->editSetting('supercheckout_trigger', array('supercheckout_trigger' => '#button-confirm,#display_payment .button, #display_payment .btn, #display_payment .button_oc, #display_payment input[type=submit]'));
		}

		// $this->load->model('localisation/language');
		// $languages = $this->model_localisation_language->getLanguages();
		// foreach($languages as $language) {
		// 	$checkIfSEOEntryExist = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE key = 'route' AND value = 'extension/supercheckout/supercheckout/supercheckout' AND keyword = 'supercheckout' and store_id = '0' AND language_id = '" . (int) $language['language_id'] . "'");
		// }
	}

	public function uninstall()
	{
		$this->load->model('setting/setting');

		//On uninstall, disable the module instead of the deleting the settings
		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = $this->config->get('config_store_id');
		}
		$settings = $this->model_setting_setting->getSetting('supercheckout', $store_id);
		$settings['supercheckout']['general']['enable'] = 0;
		$this->model_setting_setting->editSetting('supercheckout', $settings, $store_id);
		//$this->model_setting_setting->deleteSetting('default_supercheckout');
		//$this->model_setting_setting->deleteSetting('supercheckout');
	}

	public function getDefaultSettings()
	{
		$this->load->language($this->module_path . '/supercheckout');

		$data_payment_methods = array();
		$payment_methods = $this->model_setting_extension->getExtensionsByType('payment');
		foreach ($payment_methods as $payment) {
			if ($this->config->get('payment_' . $payment['code'] . '_status')) {
				$this->load->language('extension/' . $payment['extension'] . '/payment/' . $payment['code']);
				$data_payment_methods[] = $payment['code'];
			}
		}

		$data_shipping_methods = array();
		$shipping_methods = $this->model_setting_extension->getExtensionsByType('shipping');
		foreach ($shipping_methods as $shipping) {
			if ($this->config->get('shipping_' . $shipping['code'] . '_status')) {
				$this->load->language('extension/' . $shipping['extension'] . '/shipping/' . $shipping['code']);
				$shipping_default = $shipping['code'];
				$data_shipping_methods[$shipping['code']] = $data_payment_methods;
			}
		}

		return array('default_supercheckout' => array(
			'general' => array(
				'enable' => 0,
				'guestenable' => 0,
				'guest_manual' => 0,
				'layout' => '3-Column',
				'main_checkout' => 1,
				'column_width' => array(
					'one-column' => array(
						1 => '100', 2 => '0', 3 => '0', 'inside' => array(1 => '0', 2 => '0')
					),
					'three-column' => array(
						1 => '30', 2 => '25', 3 => '45', 'inside' => array(1 => '0', 2 => '0')
					),
					'two-column' => array(1 => '30', 2 => '70', 3 => '0', 'inside' => array(1 => '50', 2 => '50'))
				),
				'default_option' => 'guest',
				'custom_style' => '',
				'store_id' => 0,
				'settings' => array('value' => 0, 'bulk' => ''),
				'version' => '2.2',
				'adv_id' => 0,
				'plugin_id' => 'OC0001',
				'supercheckout_enable_new_template' => 1
			),
			'mailchimp' => array(
				'enable' => 0,
				'api' => ''
			),
			'testing_mode' => array(
				'enable' => 0,
				'url' => ''
			),
			'payment_logo' => array(
				'default_option' => 'textonly'
			),
			'shipping_logo' => array(
				'default_option' => 'textonly'
			),
			'step' => array(
				'customizer' => array(
					'kb_button_bg_color' => '#5ebd5e',
					'kb_button_border_color' => '#00e203',
					'kb_button_text_color' => '#ffffff',
					'kb_border_bottom_color' => '#00e203',
					'kb_ac_bg_color' => '#69bcff',
					'kb_login_bg_color' => '#5ebd5e',
					'kb_logout_bg_color' => '#5de4ff',
					'kb_coupon_button_bg_color' => '#1d02ff',
					'kb_voucher_button_bg_color' => '#40fff1',
					'kb_shipping_bar_bg_color' => '#5ebd5e',
					'custom_css' => '',
					'custom_js' => ''
				),
				'login' => array(
					'sort_order' => 1,
					'three-column' => array('column' => 1, 'row' => 0, 'column-inside' => 0),
					'two-column' => array('column' => 1, 'row' => 0, 'column-inside' => 1),
					'one-column' => array('column' => 0, 'row' => 0, 'column-inside' => 0),
					'width' => '50',
					'option' => array(
						'guest' => array(
							'title' => 'supercheckout_text_guest',
							'description' => 'step_option_guest_desciption',
							'display' => 1
						),
						'login' => array(
							'display' => 1
						)
					),
					'enable_slider' => 0
				),
				'payment_address' => array(
					'sort_order' => '2',
					'three-column' => array('column' => 1, 'row' => 1, 'column-inside' => 0),
					'two-column' => array('column' => 1, 'row' => 1, 'column-inside' => 1),
					'one-column' => array('column' => 0, 'row' => 1, 'column-inside' => 0),
					'width' => '50',
					'fields' => array(
						'firstname' => array(
							'id' => 'firstname',
							'title' => $this->language->get('supercheckout_entry_firstname'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 1,
							'class' => ''
						),
						'lastname' => array(
							'id' => 'lastname',
							'title' => $this->language->get('supercheckout_entry_lastname'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 2,
							'class' => ''
						),
						'telephone' => array(
							'id' => 'telephone',
							'title' => $this->language->get('supercheckout_entry_telephone'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 3,
							'class' => ''
						),
						'company' => array(
							'id' => 'company',
							'title' => $this->language->get('supercheckout_entry_company'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 9,
							'class' => ''
						),

						'address_1' => array(
							'id' => 'address_1',
							'title' => $this->language->get('supercheckout_entry_address_1'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 13,
							'class' => ''
						),
						'address_2' => array(
							'id' => 'address_2',
							'title' => $this->language->get('supercheckout_entry_address_2'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 14,
							'class' => ''
						),
						'city' => array(
							'id' => 'city',
							'title' => $this->language->get('supercheckout_entry_city'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 15,
							'class' => ''
						),
						'postcode' => array(
							'id' => 'postcode',
							'title' => $this->language->get('supercheckout_entry_postcode'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 16,
							'class' => ''
						),
						'country_id' => array(
							'id' => 'country_id',
							'title' => $this->language->get('supercheckout_entry_country'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 17,
							'class' => ''
						),
						'zone_id' => array(
							'id' => 'zone_id',
							'title' => $this->language->get('supercheckout_entry_zone'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 18,
							'class' => ''
						),
						'shipping' => array(
							'id' => 'shipping',
							'title' => $this->language->get('supercheckout_entry_shipping'),
							'custom' => 0,
							'display' => 0,
							'checked' => 0,
							'sort_order' => 20,
							'class' => '',
							'value' => 1
						)
					)
				),
				'shipping_address' => array(
					'sort_order' => '3',
					'three-column' => array('column' => 1, 'row' => 2, 'column-inside' => 0),
					'two-column' => array('column' => 1, 'row' => 2, 'column-inside' => 1),
					'one-column' => array('column' => 0, 'row' => 2, 'column-inside' => 0),
					'width' => '30',
					'fields' => array(
						'firstname' => array(
							'id' => 'firstname',
							'title' => $this->language->get('supercheckout_entry_firstname'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 1,
							'class' => ''
						),
						'lastname' => array(
							'id' => 'lastname',
							'title' => $this->language->get('supercheckout_entry_lastname'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 2,
							'class' => ''
						), //add company fields in shipping address by Shashank Agarwal on 13-07-2020
						'company' => array(
							'id' => 'company',
							'title' => $this->language->get('supercheckout_entry_company'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 3,
							'class' => ''
						),
						'address_1' => array(
							'id' => 'address_1',
							'title' => $this->language->get('supercheckout_entry_address_1'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 4,
							'class' => ''
						),
						'address_2' => array(
							'id' => 'address_2',
							'title' => $this->language->get('supercheckout_entry_address_2'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 5,
							'class' => ''
						),
						'city' => array(
							'id' => 'city',
							'title' => $this->language->get('supercheckout_entry_city'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 6,
							'class' => ''
						),
						'postcode' => array(
							'id' => 'postcode',
							'title' => $this->language->get('supercheckout_entry_postcode'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 7,
							'class' => ''
						),
						'country_id' => array(
							'id' => 'country_id',
							'title' => $this->language->get('supercheckout_entry_country'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 8,
							'class' => ''
						),
						'zone_id' => array(
							'id' => 'zone_id',
							'title' => $this->language->get('supercheckout_entry_zone'),
							'custom' => 0,
							'display' => 0,
							'require' => 0,
							'sort_order' => 9,
							'class' => ''
						),
					)
				),
				'shipping_method' => array(
					'sort_order' => 4,
					'three-column' => array('column' => 2, 'row' => 0, 'column-inside' => 0),
					'two-column' => array('column' => 1, 'row' => 0, 'column-inside' => 3),
					'one-column' => array('column' => 0, 'row' => 3, 'column-inside' => 0),
					'display' => 1,
					'display_title' => 1,
					'display_options' => 1,
					'default_option' => $shipping_default,
					'available' => $data_shipping_methods,
					'width' => '30'
				),
				'payment_method' => array(
					'sort_order' => 5,
					'three-column' => array('column' => 2, 'row' => 1, 'column-inside' => 0),
					'two-column' => array('column' => 2, 'row' => 0, 'column-inside' => 3),
					'one-column' => array('column' => 0, 'row' => 4, 'column-inside' => 0),
					'display' => 1,
					'display_options' => 1,
					'default_option' => $data_payment_methods[0],
					'width' => '30'
				),
				'cart' => array(
					'sort_order' => 6,
					'three-column' => array('column' => 3, 'row' => 0, 'column-inside' => 0),
					'two-column' => array('column' => 2, 'row' => 0, 'column-inside' => 2),
					'one-column' => array('column' => 0, 'row' => 5, 'column-inside' => 0),
					'image_width' => 230,
					'image_height' => 230,
					'width' => '50',
					'option' => array(
						'voucher' => array(
							'id' => 'voucher',
							'title' => array(1 => 'voucher'),
							'tooltip' => array(1 => 'voucher'),
							'type' => 'text',
							'refresh' => '3',
							'custom' => 0,
							'class' => ''
						),
						'coupon' => array(
							'id' => 'coupon',
							'title' => array(1 => 'coupon'),
							'tooltip' => array(1 => 'coupon'),
							'type' => 'text',
							'refresh' => '3',
							'custom' => 0,
							'class' => ''
						)
					),
				),
				'confirm' => array(
					'sort_order' => 7,
					'three-column' => array('column' => 3, 'row' => 1, 'column-inside' => 0),
					'two-column' => array('column' => 2, 'row' => 1, 'column-inside' => 4),
					'one-column' => array('column' => 0, 'row' => 6, 'column-inside' => 0),
					'width' => '50',
					'fields' => array(
						'comment' => array(
							'id' => 'comment',
							'title' => $this->language->get('supercheckout_text_comments'),
							'custom' => 0,
							'class' => ''
						),
						'agree' => array(
							'id' => 'agree',
							'title' => $this->language->get('supercheckout_text_agree'),
							'value' => 0,
							'custom' => 0,
							'class' => ''
						)
					)
				),
				'html' => array(
					'0_0' => array(
						'sort_order' => 8,
						'three-column' => array('column' => 3, 'row' => 4, 'column-inside' => 1),
						'two-column' => array('column' => 2, 'row' => 1, 'column-inside' => 4),
						'one-column' => array('column' => 0, 'row' => 7, 'column-inside' => 1),
						'value' => ""
					)
				),
				'modal_value' => 1,
				'facebook_login' => array(
					'display' => 0,
					'app_id' => '',
					'app_secret' => ''
				),
				'google_login' => array(
					'display' => 0,
					'app_id' => '',
					'client_id' => '',
					'app_secret' => ''
				),
			),
			'option' => array(
				'guest' => array(
					'display' => 1,
					'login' => array(),
					'payment_address' => array(
						'title' => 'supercheckout_text_your_details',
						'description' => 'option_guest_payment_address_description',
						'display' => 1,
						'fields' => array(
							'firstname' => array(
								'display' => 1,
								'require' => 1
							),
							'lastname' => array(
								'display' => 1,
								'require' => 1
							),
							'telephone' => array(
								'display' => 1,
								'require' => 1
							),
							'company' => array(
								'display' => 1,
								'require' => 0
							),

							'customer_group_id' => array(
								'display' => 1,
								'require' => 0
							),

							'address_1' => array(
								'display' => 1,
								'require' => 1
							),
							'address_2' => array(
								'display' => 0,
								'require' => 0
							),
							'city' => array(
								'display' => 1,
								'require' => 1
							),
							'postcode' => array(
								'display' => 1,
								'require' => 1
							),
							'country_id' => array(
								'display' => 1,
								'require' => 1
							),
							'zone_id' => array(
								'display' => 1,
								'require' => 1
							),
							'shipping' => array(
								'display' => 1,
								'value' => '0',
								'checked' => 1
							)
						)
					),
					'shipping_address' => array(
						'display' => 1,
						'title' => 'option_guest_shipping_address_title',
						'description' => 'option_guest_shipping_address_description',
						'fields' => array(
							'firstname' => array(
								'display' => 1,
								'require' => 1
							),
							'lastname' => array(
								'display' => 0,
								'require' => 0
							),
							'company' => array(
								'display' => 1,
								'require' => 0
							),
							'address_1' => array(
								'display' => 1,
								'require' => 1
							),
							'address_2' => array(
								'display' => 0,
								'require' => 0
							),
							'city' => array(
								'display' => 1,
								'require' => 0
							),
							'postcode' => array(
								'display' => 1,
								'require' => 1
							),
							'country_id' => array(
								'display' => 1,
								'require' => 1
							),
							'zone_id' => array(
								'display' => 1,
								'require' => 1
							),
						)
					),
					'shipping_method' => array(
						'title' => 'option_guest_shipping_method_title',
						'description' => 'supercheckout_text_shipping_method',
					),
					'payment_method' => array(
						'title' => 'option_guest_payment_method_title',
						'description' => 'supercheckout_text_payment_method',
					),
					'cart' => array(
						'display' => 1,
						'option' => array(
							'voucher' => array(
								'display' => 1
							),
							'coupon' => array(
								'display' => 1
							),
							'reward' => array(
								'display' => 1
							)
						),
						'columns' => array(
							'image' => 1,
							'name' => 1,
							'model' => 1,
							'quantity' => 1,
							'price' => 1,
							'total' => 1
						)
					),
					'confirm' => array(
						'display' => 1,
						'fields' => array(
							'comment' => array(
								'display' => 1
							),
							'agree' => array(
								'display' => 1,
								'require' => 1
							)
						)
					)
				),
				'logged' => array(
					'login' => array(),
					'payment_address' => array(
						'display' => 1,
						'title' => 'option_logged_payment_address_title',
						'description' => 'option_logged_payment_address_description',
						'fields' => array(
							'firstname' => array(
								'display' => 1,
								'require' => 1
							),
							'lastname' => array(
								'display' => 1,
								'require' => 1
							),
							'telephone' => array(
								'display' => 1,
								'require' => 1
							),
							'company' => array(
								'display' => 1,
								'require' => 0
							),

							'customer_group_id' => array(
								'display' => 1,
								'require' => 0
							),

							'address_1' => array(
								'display' => 1,
								'require' => 1
							),
							'address_2' => array(
								'display' => 0,
								'require' => 0
							),
							'city' => array(
								'display' => 1,
								'require' => 0
							),
							'postcode' => array(
								'display' => 1,
								'require' => 1
							),
							'country_id' => array(
								'display' => 1,
								'require' => 1
							),
							'zone_id' => array(
								'display' => 1,
								'require' => 1
							),
							'shipping' => array(
								'display' => 1,
								'value' => '0',
								'checked' => 1
							),
							'address_id' => array()
						)
					),
					'shipping_address' => array(
						'display' => 1,
						'title' => 'option_logged_shipping_address_title',
						'description' => 'option_logged_shipping_address_description',
						'fields' => array(
							'firstname' => array(
								'display' => 1,
								'require' => 1
							),
							'lastname' => array(
								'display' => 0,
								'require' => 0
							),
							'company' => array(
								'display' => 1,
								'require' => 0
							),
							'address_1' => array(
								'display' => 1,
								'require' => 1
							),
							'address_2' => array(
								'display' => 0,
								'require' => 0
							),
							'city' => array(
								'display' => 1,
								'require' => 1
							),
							'postcode' => array(
								'display' => 1,
								'require' => 1
							),
							'country_id' => array(
								'display' => 1,
								'require' => 1
							),
							'zone_id' => array(
								'display' => 1,
								'require' => 1
							),
						)
					),
					'shipping_method' => array(
						'title' => 'option_logged_shipping_method_title',
						'description' => 'supercheckout_text_shipping_method',
					),
					'payment_method' => array(
						'title' => 'option_logged_payment_method_title',
						'description' => 'supercheckout_text_payment_method',
					),
					'cart' => array(
						'display' => 1,
						'option' => array(
							'voucher' => array(
								'display' => 1
							),
							'coupon' => array(
								'display' => 1
							),
							'reward' => array(
								'display' => 1
							)
						),
						'columns' => array(
							'image' => 1,
							'name' => 1,
							'model' => 1,
							'quantity' => 1,
							'price' => 1,
							'total' => 1
						)
					),
					'confirm' => array(
						'display' => 1,
						'fields' => array(
							'comment' => array(
								'display' => 1
							),
							'agree' => array(
								'display' => 1,
								'require' => 1
							)
						)
					)
				)
			)
		));
	}

	public function saveClasses()
	{
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('supercheckout_classes', $this->request->post);
	}

	public function saveClassesTrigger()
	{
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('supercheckout_trigger', $this->request->post);
	}

	public function getClasses()
	{
		$classes = array();
		$this->load->model('setting/setting');
		$classes['anchor_classes'] = $this->model_setting_setting->getSetting('supercheckout_classes');
		$classes['anchor_classes_trigger'] = $this->model_setting_setting->getSetting('supercheckout_trigger');
		return $classes;
	}

	public function preventReinstall()
	{
		$this->load->model('setting/setting');
		$check_classes = $this->model_setting_setting->getSetting('supercheckout_classes');
		$check_classes_trigger = $this->model_setting_setting->getSetting('supercheckout_trigger');
		if (empty($check_classes)) {
			$this->model_setting_setting->editSetting('supercheckout_classes', array('supercheckout_classes' => '#display_payment .button, #display_payment .btn, #display_payment .button_oc, #display_payment input[type=submit]'));
		}
		if (empty($check_classes_trigger)) {
			$this->model_setting_setting->editSetting('supercheckout_trigger', array('supercheckout_trigger' => '#button-confirm,#display_payment .button, #display_payment .btn, #display_payment .button_oc, #display_payment input[type=submit]'));
		}
	}

	public function mailchimp_getList()
	{
		$flag = 0;
		$data = array();
		
		$key = $this->request->get['key'];
		if(!empty($key)) {
			try {
				$MailChimp = new MailChimp($key);
				$result = $MailChimp->get('lists');
				if ($MailChimp->success()) {
					$flag = 1;
					$data = $result;
				}
			} catch(Exception $e) {

			}
		}
		$json = array('flag' => $flag, 'data' => $data);
		$this->response->setOutput(json_encode($json));
	}

	public function klaviyoGetLists()
	{
		$api_key = $this->request->get['key'];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://a.klaviyo.com/api/v1/lists?api_key=' . $api_key);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$output = curl_exec($ch);
		curl_close($ch);
		$this->response->setOutput($output);
	}

	public function getSendinBlueList()
	{
		$apikey = $this->request->get['key'];
		$response = array(); //defining array to store response
		if (trim($apikey) != '' && $apikey !== null) {
			$mailin = new \Mailin('https://api.sendinblue.com/v2.0', $apikey);

			$folder = $mailin->get_lists(1)['data']; // it'll be modified later as get_lists() is not working to get all list
			/**
			 * Added condition to check if folder is empty or not
			 * @date 06-03-2023
			 * @author Tanisha Gupta
			 */
			if (!empty($folder)) {
				foreach ($folder as $value) {
					$response[] = $value['lists'];
				}
			}
		}
		/**
		 * Made changes according to the API v3
		 * @date 06-03-2023
		 * @author Tanisha Gupta
		 */
		if (empty($response)) {
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.sendinblue.com/v3/contacts/lists?limit=10&offset=0&sort=desc",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_HTTPHEADER => array(
					"accept: application/json",
					"api-key: " . $apikey . "",
				),
			));
			$result_data = curl_exec($curl);
			$result_data = json_decode($result_data, true);
			if (isset($result_data['lists'])) {
				unset($result_data['count']);
				$folder = $result_data;
				foreach ($folder as $k => $value) {
					$response[] = $value;
				}
			}
			curl_close($curl);
		}
		if (empty($response)) {
			$this->response->setOutput(json_encode($response));
		} else {
			$this->response->setOutput(json_encode($response[0]));
		}
	}
}
