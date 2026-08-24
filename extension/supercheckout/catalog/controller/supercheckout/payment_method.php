<?php

namespace Opencart\Catalog\Controller\Extension\Supercheckout\Supercheckout;

class PaymentMethod extends \Opencart\System\Engine\Controller
{
    private $settings = array();

    public function index(): void {
		$this->response->setOutput($this->paymentMethods());
	}

    public function paymentMethods()
    {
        //Setting for supercheckout plugin from database or from default settings
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');
        $this->load->model('account/address');

        $this->load->language('extension/supercheckout/supercheckout/supercheckout');

        $result = $this->model_setting_setting->getSetting('supercheckout', $this->config->get('config_store_id'));
        $this->settings = $result['supercheckout'];
        $data['settings'] = $result['supercheckout'];

        if (empty($data['settings'])) {
            $settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
            $data['settings'] = $settings['default_supercheckout'];
            $data['supercheckout'] = $settings['default_supercheckout'];
        }
        /**
         * Check if the supercheckout new template is enabled then check if the template file exists or not
         * If the file exists then set the default theme to the current theme
         * @date 06-02-2025
         * @modifier Amit Singh
         */
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template'] && file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/payment_method_new.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } elseif (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/payment_method.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } else {
            $data['default_theme'] = 'default';
        }

        if (isset($data['settings']['step']['payment_method']['logo'])) {
            foreach ($data['settings']['step']['payment_method']['logo'] as $key => $value) {
                if (file_exists('image/' . $value)) {
                    $data['payment_logo'][$key] = true;
                } else {
                    $data['payment_logo'][$key] = false;
                }
            }
        }

        // if customer is logged in whether through store or through facebook or google
        if ($this->customer->isLogged() && isset($this->session->data['payment_address_id'])) {
            $payment_address = $this->model_account_address->getAddress($this->customer->getId(), $this->session->data['payment_address_id']);
        }  elseif ($this->customer->isLogged() && !isset($this->session->data['payment_address_id'])) {
            //if customer is logged in and DOES NOT has entry in addres book
            $payment_address['country_id'] = $this->session->data['payment_country_id'];
            $payment_address['zone_id'] = $this->session->data['payment_zone_id'];
        } elseif (isset($this->session->data['guest'])) {
            $payment_address = $this->session->data['guest']['payment'];
        }

        if (!empty($payment_address)) {

            $totals = array();
            $total = 0;
            $taxes = $this->cart->getTaxes();

            //Because __call can not keep var references so we put them into an array. 			
            $total_data = array(
                'totals' => &$totals,
                'taxes' => &$taxes,
                'total' => &$total
            );
            
            $this->load->model('setting/extension');
            $results = $this->model_setting_extension->getExtensionsByType('total');

            $sort_order = array();
            foreach ($results as $key => $value) {
                $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
            }
            array_multisort($sort_order, SORT_ASC, $results);

            foreach ($results as $result) {
                if ($this->config->get('total_' . $result['code'] . '_status')) {
                    $this->load->model('extension/' . $result['extension'] . '/total/' . $result['code']);

                    // __call can not pass-by-reference so we get PHP to call it as an anonymous function.
                    ($this->{'model_extension_' . $result['extension'] . '_total_' . $result['code']}->getTotal)($total_data, $taxes, $total);
                }
            }

            // Payment Methods
            $method_data = array();
            $results = $this->model_setting_extension->getExtensionsByType('payment');

            foreach ($results as $result) {
                if ($this->config->get('payment_' . $result['code'] . '_status')) {
                    $this->load->model('extension/' . $result['extension'] . '/payment/' . $result['code']);
                    $method = $this->{'model_extension_' . $result['extension'] . '_payment_' . $result['code']}->getMethods($payment_address);
                    if ($method) {
                        $method_data[$result['code']] = $method;
                    }
                }
            }

            $sort_order = [];
            foreach ($method_data as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $method_data);
            $this->session->data['payment_methods'] = $method_data;
        }

        //Find all the payment methods which are avaible on the selected shipping method like simplifycommerce, bank_transfer, cod etc
        $ship2pay_avaliable_payment_methods = array();
        $shipping_methods_data = $this->session->data['available_shipping'];
        foreach ($shipping_methods_data as $key => $value) {
            if ($key == explode('.', $this->session->data['shipping_method']['code'])[0]) {
                foreach ($value as $method) {
                    $ship2pay_avaliable_payment_methods[] = $method;
                }
            }
        }

        //Find the default payment method selected from the admin end i.e. bank_transfer
        $default_payment = isset($this->settings['step']['payment_method']['default_option']) ? $this->settings['step']['payment_method']['default_option'] : array();

        // Data in session i.e. $this->session->data['payment_method']['code'] will be like bank_transfer.bank_transfer, simplifycommerce.simplifycommerce. Function to find the first part of the payment method code before the dot. 
        $selected_payment_method_code = '';
        if(!empty($this->session->data['payment_method']['code'])) {
            $selected_payment_method_code_array = explode(".", $this->session->data['payment_method']['code']);
            if(!empty($selected_payment_method_code_array[0])) {
                $selected_payment_method_code = $selected_payment_method_code_array[0];
            }
        }
        

        //If selected payment method code is not avaliable in the ship2payment method list.
        if (isset($selected_payment_method_code) && !in_array($selected_payment_method_code, $ship2pay_avaliable_payment_methods)) {
            if (!in_array($default_payment, $ship2pay_avaliable_payment_methods)) {
                foreach ($ship2pay_avaliable_payment_methods as $key => $value) {
                    if (isset($this->session->data['payment_methods'][$value])) {
                        $this->session->data['payment_method'] = @$this->session->data['payment_methods'][$ship2pay_avaliable_payment_methods[$key]]['option'][$ship2pay_avaliable_payment_methods[$key]];
                    }
                }
            } else {
                //If default payment method is avaible in the avaliable payment methods list then set that payment method as selected payment method.
                if(!empty($this->session->data['payment_methods'][$default_payment]['option'][$default_payment])) {
                    $this->session->data['payment_method'] = $this->session->data['payment_methods'][$default_payment]['option'][$default_payment];
                } else {
                    //Select the first payment methods as the selected one
                    foreach($this->session->data['payment_methods'] as $key => $value) {
                        $this->session->data['payment_method'] = $this->session->data['payment_methods'][$key]['option'][$key];
                        break;
                    }
                }
            }
        }

        if (isset($this->session->data['payment_methods'])) {
            foreach ($this->session->data['payment_methods'] as $key => $value) {
                if (in_array($key, $ship2pay_avaliable_payment_methods)) {
                    $data['payment_methods'][$key] = $value;
                }
            }
        } else {
            $data['payment_methods'] = array();
        }


        $data['text_shipping_not_available'] = $this->language->get('text_shipping_not_available');
        $data['text_payment_method'] = $this->language->get('text_payment_method');
        $data['text_comments'] = $this->language->get('text_comments');
        $data['button_continue'] = $this->language->get('button_continue');

        if (empty($this->session->data['payment_methods'])) {
            $data['error_warning'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
        } else {
            $data['error_warning'] = '';
        }
  

        $data['language_id'] = $this->config->get('config_language_id');

        if (isset($this->session->data['payment_method']['code'])) {
            $data['code'] = $this->session->data['payment_method']['code'];
        } else {
            $data['code'] = $this->settings['step']['payment_method']['default_option'];
        }

        if (isset($this->session->data['comment'])) {
            $data['comment'] = $this->session->data['comment'];
        } else {
            $data['comment'] = '';
        }

        if ($this->config->get('config_checkout_id')) {
            $this->load->model('catalog/information');

            $information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

            if ($information_info) {
                $data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/info', 'information_id=' . $this->config->get('config_checkout_id').'&language=' . $this->config->get('config_language')), $information_info['title'], $information_info['title']);
            } else {
                $data['text_agree'] = '';
            }
        } else {
            $data['text_agree'] = '';
        }

        if (isset($this->session->data['agree'])) {
            $data['agree'] = $this->session->data['agree'];
        } else {
            $data['agree'] = '';
        }

        //Changes added for loading the payment methods controllers
        $extension_info_pay = $this->model_setting_extension->getExtensionByCode('payment', $this->session->data['payment_method']['code']);

        if ($extension_info_pay) {
            $data['show_payment_details'] = $this->load->controller('extension/' . $extension_info_pay['extension'] . '/payment/' . $extension_info_pay['code']);
        }
        //End changes added 


        /**
         * Check if new template is enabled
         * If enabled, load new template or load old template
         * @date 05-02-2025
         * @modifier Amit Singh
         */
        $data['sessions'] = $this->session->data;
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template']) {
            return $this->load->view('extension/supercheckout/supercheckout/payment_method_new', $data);
        } else {
            return $this->load->view('extension/supercheckout/supercheckout/payment_method', $data);
        }
        
        
    }


    public function validate()
    {
        //loading settings for supecheckout plugin from database or from default settings
        $this->load->model('setting/setting');
        $result = $this->model_setting_setting->getSetting('supercheckout', $this->config->get('config_store_id'));
        $this->settings = $result['supercheckout'];
        $data['settings'] = $result['supercheckout'];

        if (empty($data['settings'])) {

            $this->config->load('supercheckout_settings');
            $settings = $this->config->get('supercheckout_settings');
            $data['settings'] = $settings;
        }

        $this->load->language('extension/supercheckout/supercheckout/supercheckout');

        $json = array();

        // Validate if payment address has been set.
        $this->load->model('account/address');

        // if customer is logged in whether through store or through facebook or google        
        if ($this->customer->isLogged()) {
            $payment_address['country_id'] = $this->session->data['payment_country_id'];
            $payment_address['zone_id'] = $this->session->data['payment_zone_id'];
            $payment_address['iso_code_2'] = isset($this->session->data['payment_iso_code_2']) ? $this->session->data['payment_iso_code_2'] : "";
            $payment_address['iso_code_3'] = isset($this->session->data['payment_iso_code_3']) ? $this->session->data['payment_iso_code_3'] : "";
            $payment_address['postcode'] = isset($this->session->data['payment']['payment_postcode']) ? $this->session->data['payment']['payment_postcode'] : "";
        } elseif (isset($this->session->data['guest'])) {
            $payment_address = $this->session->data['guest']['payment'];
        }

        if (empty($payment_address)) {
            $json['redirect'] = $this->url->link('extension/supercheckout/supercheckout/supercheckout', 'language=' . $this->config->get('config_language'));
        }

        // Validate cart has products and has stock.			
        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            $json['redirect'] = $this->url->link('extension/supercheckout/supercheckout/cart', 'language=' . $this->config->get('config_language'));
        }

        // Validate minimum quantity requirments.			
        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            $product_total = 0;

            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }

            if ($product['minimum'] > $product_total) {
                $json['redirect'] = $this->url->link('extension/supercheckout/supercheckout/cart', 'language=' . $this->config->get('config_language'));
                break;
            }
        }

        //if no error is found
        if (!$json) {
            if (empty($this->request->post['payment_method'])) {
                $json['error']['warning'] = $this->language->get('error_payment');
            } else {

                $payment_method_parts = explode(".", $this->request->post['payment_method']);
                $selected_payment_method_code = '';
                if(!empty($payment_method_parts[0])) {
                    $selected_payment_method_code = $payment_method_parts[0];
                }

                //Check if requested payment method is avalible in the list.
                $requested_payment_method_avaliable = false;
                if(!empty($this->session->data['payment_methods'])) {
                    foreach($this->session->data['payment_methods'] as $payment_code => $payment_method) {
                        if($payment_code == $selected_payment_method_code) {
                            if($payment_method['option'][$payment_code]['code'] == $this->request->post['payment_method']) {
                                $requested_payment_method_avaliable = true;
                            }
                        }
                    }
                }

                if ($requested_payment_method_avaliable == false) {
                    $json['error']['warning'] = $this->language->get('error_payment');
                }
            }

            if (!$json) {
                $this->session->data['payment_method'] = $this->session->data['payment_methods'][$selected_payment_method_code]['option'][$selected_payment_method_code];
            }
        }
        $this->response->setOutput(json_encode($json));
    }
}
