<?php

namespace Opencart\Catalog\Controller\Extension\Supercheckout\Supercheckout;

class ShippingMethod extends \Opencart\System\Engine\Controller
{
    private $settings = array();

    public function index(): void
    {
        $this->response->setOutput($this->shippingMethods());
    }


    public function shippingMethods()
    {
        $this->load->language('extension/supercheckout/supercheckout/supercheckout');

        $this->load->model('account/address');
        $this->load->model('setting/setting');
        $this->load->model('setting/extension');

        $result = $this->model_setting_setting->getSetting('supercheckout', $this->config->get('config_store_id'));
        $this->settings = $result['supercheckout'];
        $data['settings'] = $result['supercheckout'];
        /** 
         * Check if the supercheckout new template is enabled then check if the template file exists or not
         * If the file exists then set the default theme to the current theme
         * @date 06-02-2025
         * @modifier Amit Singh
        */
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template'] && file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/shipping_method_new.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } elseif (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/shipping_method.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } else {
            $data['default_theme'] = 'default';
        }

        //If customer is logged in whether through store or through facebook or google
        if ($this->customer->isLogged()) {
            $this->load->model('account/address');
            if (isset($this->session->data['shipping_address']['country_id']) && $this->session->data['shipping_address']['country_id']) {
                $shipping_address = $this->session->data['shipping_address'];
            } else {
                $shipping_address = $this->model_account_address->getAddress((int)$this->customer->getId(), (int)$this->customer->getAddressId());
            }
        } elseif (isset($this->session->data['guest'])) {
            $this->session->data['guest']['shipping']['zone_id'] = $this->session->data['shipping_zone_id'];
            $this->session->data['guest']['shipping']['country_id'] = $this->session->data['shipping_country_id'];
            $shipping_address = $this->session->data['guest']['shipping'];
        }

        if (empty($data['settings'])) {
            $this->config->load('supercheckout_settings');
            $settings = $this->config->get('supercheckout_settings');
            $data['settings'] = $settings;
        }

        if (isset($data['settings']['step']['shipping_method']['logo'])) {
            foreach ($data['settings']['step']['shipping_method']['logo'] as $key => $value) {
                if (file_exists('image/' . $value)) {
                    $data['shipping_logo'][$key] = true;
                   
                } else {
                    $data['shipping_logo'][$key] = false;
                }
            }
        }

        $data['error_no_shipping_product'] = $this->language->get('error_no_shipping_product');
        if (!empty($shipping_address)) {

            if (!isset($shipping_address['city']) && isset($this->session->data['shipping_address']['city'])) {
                $shipping_address['city'] = $this->session->data['shipping_address']['city'];
            } else if (!isset($shipping_address['city']) && !isset($this->session->data['shipping_address']['city'])) {
                $shipping_address['city'] = '';
            }
            if (!isset($shipping_address['zone_code']) && isset($this->session->data['shipping_address']['zone_code'])) {
                $shipping_address['zone_code'] = $this->session->data['shipping_address']['zone_code'];
            } else if (!isset($shipping_address['zone_code']) && !isset($this->session->data['shipping_address']['zone_code'])) {
                $shipping_address['zone_code'] = '';
            }
            if (!isset($shipping_address['postcode']) && isset($this->session->data['shipping_address']['postcode'])) {
                $shipping_address['postcode'] = $this->session->data['shipping_address']['postcode'];
            } else if (!isset($shipping_address['postcode']) && !isset($this->session->data['shipping_address']['postcode'])) {
                $shipping_address['postcode'] = isset($this->session->data['shipping']['shipping_postcode']) ? $this->session->data['shipping']['shipping_postcode'] : "";
            }
            if (!isset($shipping_address['iso_code_2']) && isset($this->session->data['shipping_address']['iso_code_2'])) {
                $shipping_address['iso_code_2'] = $this->session->data['shipping_address']['iso_code_2'];
            } else if (!isset($shipping_address['iso_code_2']) && !isset($this->session->data['shipping_address']['iso_code_2'])) {
                $shipping_address['iso_code_2'] = isset($this->session->data['shipping_iso_code_2']) ? $this->session->data['shipping_iso_code_2'] : "";
            }
            if (!isset($shipping_address['iso_code_3']) && isset($this->session->data['shipping_address']['iso_code_3'])) {
                $shipping_address['iso_code_3'] = $this->session->data['shipping_address']['iso_code_3'];
            } else if (!isset($shipping_address['iso_code_3']) && !isset($this->session->data['shipping_address']['iso_code_3'])) {
                $shipping_address['iso_code_3'] = isset($this->session->data['shipping_iso_code_3']) ? $this->session->data['shipping_iso_code_3'] : "";
            }
            if (!isset($shipping_address['firstname']) && isset($this->session->data['shipping_address']['firstname'])) {
                $shipping_address['firstname'] = $this->session->data['shipping_address']['firstname'];
            } else if (!isset($shipping_address['firstname']) && !isset($this->session->data['shipping_address']['firstname'])) {
                $shipping_address['firstname'] = '';
            }
            if (!isset($shipping_address['lastname']) && isset($this->session->data['shipping_address']['lastname'])) {
                $shipping_address['lastname'] = $this->session->data['shipping_address']['lastname'];
            } else if (!isset($shipping_address['lastname']) && !isset($this->session->data['shipping_address']['lastname'])) {
                $shipping_address['lastname'] = '';
            }
            if (!isset($shipping_address['company']) && isset($this->session->data['shipping_address']['company'])) {
                $shipping_address['company'] = $this->session->data['shipping_address']['company'];
            } else if (!isset($shipping_address['company']) && !isset($this->session->data['shipping_address']['company'])) {
                $shipping_address['company'] = '';
            }
            if (!isset($shipping_address['address_1']) && isset($this->session->data['shipping_address']['address_1'])) {
                $shipping_address['address_1'] = $this->session->data['shipping_address']['address_1'];
            } else if (!isset($shipping_address['address_1']) && !isset($this->session->data['shipping_address']['address_1'])) {
                $shipping_address['address_1'] = '';
            }

            // Shipping Methods
            $quote_data = array();

            $results = $this->model_setting_extension->getExtensionsByType('shipping');
            foreach ($results as $result) {
                if ($this->config->get('shipping_' . $result['code'] . '_status')) {
                    $this->load->model('extension/' . $result['extension'] . '/shipping/' . $result['code']);
                    $quote = $this->{'model_extension_' . $result['extension'] . '_shipping_' . $result['code']}->getQuote($shipping_address);

                    if ($quote) {
                        $quote_data[$result['code']] = [
                            'title'      => $quote['name'],
                            'quote'      => $quote['quote'],
                            'sort_order' => $quote['sort_order'],
                            'error'      => $quote['error']
                        ];
                    }
                }
            }

            $sort_order = array();

            foreach ($quote_data as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $quote_data);
            $this->session->data['shipping_methods'] = $quote_data;
        }

        if (isset($this->settings['step']['shipping_method']['available'])) {
            $this->session->data['available_shipping'] = $this->settings['step']['shipping_method']['available'];
        } else {
            $this->session->data['available_shipping'] = array();
        }

        $all_shipping = isset($this->session->data['shipping_methods']) ? $this->session->data['shipping_methods'] : '';

        $this->session->data['shipping_methods'] = array();

        $all_shipping_keys = !empty($all_shipping) ? array_keys($all_shipping) : '';
        if (isset($this->session->data['available_shipping'])) {
            foreach ($this->session->data['available_shipping'] as $key => $value) {
                if (in_array($key, $all_shipping_keys)) {
                    $this->session->data['shipping_methods'][$key] = $all_shipping[$key];
                }
            }
        }

        $data['language_id'] = $this->config->get('config_language_id');
        $data['text_shipping_method'] = $this->language->get('text_shipping_method');
        $data['text_comments'] = $this->language->get('text_comments');
        $data['button_continue'] = $this->language->get('button_continue');
        $data['shipping_required'] = $this->cart->hasShipping();
        if (empty($this->session->data['shipping_methods'])) {
            $data['error_warning'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['shipping_methods'])) {
            $sort_order = array();
            foreach ($this->session->data['shipping_methods'] as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }
            array_multisort($sort_order, SORT_ASC, $this->session->data['shipping_methods']);
            $data['shipping_methods'] = $this->session->data['shipping_methods'];
        } else {
            $data['shipping_methods'] = array();
        }

        //For getting first method set to default IF and only IF default is not set at the admin
        $get_first_method_shipping = array();
        foreach ($this->session->data['shipping_methods'] as $methods => $key) {
            $get_first_method_shipping[] = $methods;
        }

        $default_shipping = isset($this->settings['step']['shipping_method']['default_option']) ? $this->settings['step']['shipping_method']['default_option'] : array();

        $current_shipping_method = array();

        if (isset($this->session->data['shipping_method'])) {
            $current_shipping_method = explode('.', $this->session->data['shipping_method']['code']);
        }
        if (isset($this->session->data['shipping_method'])) {
            if (isset($current_shipping_method[1]) && !in_array($current_shipping_method[1], $get_first_method_shipping)) {
                if (!empty($get_first_method_shipping)) {
                    if (!in_array($default_shipping, $get_first_method_shipping)) {
                        if (isset($this->session->data['shipping_methods'][$get_first_method_shipping[0]]['quote'][$get_first_method_shipping[0]])) {
                            $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$get_first_method_shipping[0]]['quote'][$get_first_method_shipping[0]];
                        }
                    } else {
                        foreach ($this->session->data['shipping_methods'][$default_shipping]['quote'] as $shipping_methods_key => $shipping_methods_val) {
                            $this->session->data['shipping_method'] = $shipping_methods_val;
                            break;
                        }
                    }
                } else {
                    unset($this->session->data['shipping_method']);
                }
            }
        } else {
            if (!empty($get_first_method_shipping)) {
                if (!in_array($default_shipping, $get_first_method_shipping)) {
                    if (isset($this->session->data['shipping_methods'][$get_first_method_shipping[0]]['quote'][$get_first_method_shipping[0]])) {
                        $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$get_first_method_shipping[0]]['quote'][$get_first_method_shipping[0]];
                    }
                } else {
                    foreach ($this->session->data['shipping_methods'][$default_shipping]['quote'] as $shipping_methods_key => $shipping_methods_val) {
                        $this->session->data['shipping_method'] = $shipping_methods_val;
                        break;
                    }
                }
            } else {
                unset($this->session->data['shipping_method']);
            }
        }

        if (isset($this->session->data['shipping_method']['code'])) {
            $data['codeShipping'] = $this->session->data['shipping_method']['code'];
        } else {
            $data['codeShipping'] = $this->settings['step']['shipping_method']['default_option'] . '.' . $this->settings['step']['shipping_method']['default_option'];
        }

        if (isset($this->session->data['comment'])) {
            $data['comment'] = $this->session->data['comment'];
        } else {
            $data['comment'] = '';
        }

        /**
         * Check if new template is enabled
         * If enabled, load new template or load old template
         * @date 05-02-2025
         * @modifier Amit Singh
         */
        $data['sessions'] = $this->session->data;
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template']) {
            return $this->load->view('extension/supercheckout/supercheckout/shipping_method_new', $data);
        } else {
            return $this->load->view('extension/supercheckout/supercheckout/shipping_method', $data);
        }
        
    }

    //Validate if shipping address has been set.
    public function validate()
    {
        $json = array();

        $this->load->language('extension/supercheckout/supercheckout/supercheckout');

        $this->load->model('account/address');

        //If customer is logged in whether through store or through facebook or google
        if ($this->customer->isLogged()) {

            $shipping_address['country_id'] = $this->session->data['shipping_country_id'];
            $shipping_address['zone_id'] = $this->session->data['shipping_zone_id'];
            $shipping_address['postcode'] = isset($this->session->data['shipping']['shipping_postcode']) ? $this->session->data['shipping']['shipping_postcode'] : "";
            $shipping_address['iso_code_2'] = isset($this->session->data['shipping_iso_code_2']) ? $this->session->data['shipping_iso_code_2'] : "";
            $shipping_address['iso_code_3'] = isset($this->session->data['shipping_iso_code_3']) ? $this->session->data['shipping_iso_code_3'] : "";
            $shipping_address['zone_code'] = '';
            $shipping_address['city'] = '';
        } elseif (isset($this->session->data['guest'])) {
            if (isset($this->session->data['use_for_shipping']) && isset($this->session->data['guest']['payment'])) {
                $this->session->data['guest']['shipping'] = $this->session->data['guest']['payment'];
            }
            $shipping_address = $this->session->data['guest']['shipping'];
        }

        //Validate minimum quantity requirments.
        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            $product_total = 0;

            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }

            if ($product['minimum'] > $product_total) {
                $json['redirect'] = $this->url->link('supercheckout/cart', 'language=' . $this->config->get('config_language'));
                break;
            }
        }

        if ($this->cart->hasShipping()) {
            if (!$json) {
                if (!isset($this->request->post['shipping_method'])) {
                    $json['error']['warning'] = $this->language->get('error_shipping');
                } else {
                    $shipping = explode('.', $this->request->post['shipping_method']);
                    if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                        $json['error']['warning'] = $this->language->get('error_shipping');
                    }
                }

                if (!$json) {
                    $shipping = explode('.', $this->request->post['shipping_method']);
                    $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
                }
            }
        }
        $this->response->setOutput(json_encode($json));
    }
}
