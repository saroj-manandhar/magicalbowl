<?php

namespace Opencart\Catalog\Controller\Extension\Supercheckout\Supercheckout;

use \Opencart\System\Helper as Helper;

class Confirm extends \Opencart\System\Engine\Controller
{
    private $settings = array();

    public function index()
    {

        $this->load->model('setting/setting');
        $this->load->model('account/address');
        $this->load->model('checkout/order');
        $this->load->model('extension/supercheckout/supercheckout/order');
        $this->load->model('tool/image');
        $this->load->model('tool/upload');
        $this->load->model('setting/extension');

        $this->load->language('extension/supercheckout/supercheckout/supercheckout');
        $this->load->language('checkout/cart');

        $result = $this->model_setting_setting->getSetting('supercheckout', $this->config->get('config_store_id'));
        if (!empty($result)) {
            $this->settings = $result['supercheckout'];
            $data['settings'] = $result['supercheckout'];
        } else {
            $settings = $this->model_setting_setting->getSetting('default_supercheckout', 0);
            $data['settings'] = $settings['default_supercheckout'];
            $this->settings = $data['settings'];
        }

        if (empty($data['settings']) || !$data['settings']['general']['enable']) {
            $this->response->redirect($this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language')));
        }
        /**
         * Check if the supercheckout new template is enabled then check if the template file exists or not
         * If the file exists then set the default theme to the current theme
         * @date 06-02-2025
         * @modifier Amit Singh
         */
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template'] && file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/confirm_new.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } elseif (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/confirm.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } else {
            $data['default_theme'] = 'default';
        }
        
        $data['logged'] = $this->customer->isLogged();

        if (isset($data['settings']['general']['default_option'])) { //for setting default value for guest or login
            $data['account'] = $data['settings']['general']['default_option'];
        } else {
            $data['account'] = 'guest';
        }

        foreach ($data['settings']['step'] as $key => $step) {
            $sort_block[$key] = $step;
        }

        $redirect = '';
        if ($this->cart->hasShipping()) {
            // Validate if shipping address has been set.		
            if ($this->customer->isLogged() && isset($this->session->data['shipping_address_id'])) {
                $shipping_address = $this->model_account_address->getAddress($this->customer->getId(), $this->session->data['shipping_address_id']);
            } elseif ($this->customer->isLogged() && !isset($this->session->data['shipping_address_id'])) {
                $shipping_address['country_id'] = $this->session->data['shipping_country_id'];
                $shipping_address['zone_id'] = $this->session->data['shipping_zone_id'];
                $shipping_address['postcode'] = isset($this->session->data['shipping']['shipping_postcode']) ? $this->session->data['shipping']['shipping_postcode'] : "";
            } elseif (isset($this->session->data['guest'])) {
                $shipping_address = $this->session->data['guest']['shipping'];
            }
            if (empty($shipping_address)) {
                $redirect = $this->url->link('extension/supercheckout/supercheckout/supercheckout', 'language=' . $this->config->get('config_language'));
            }
        }

        // Validate cart has products and has stock.	
        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            $redirect = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
        }

        //To get customer available reward points
        if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
            $points = $this->customer->getRewardPoints();

            $points_total = 0;
            foreach ($this->cart->getProducts() as $product) {
                if ($product['points']) {
                    $points_total += $product['points'];
                }
            }
        }
        $data['customer_available_points'] = $points;
        $data['total_product_points'] = $points_total;

        //Validate if payment address has been set.
        if ($this->customer->isLogged() && isset($this->session->data['payment_address_id'])) {
            $payment_address = $this->model_account_address->getAddress($this->customer->getId(), $this->session->data['payment_address_id']);
        } elseif ($this->customer->isLogged() && !isset($this->session->data['payment_address_id'])) {
            $payment_address['country_id'] = $this->session->data['payment_country_id'];
            $payment_address['zone_id'] = $this->session->data['payment_zone_id'];
        } elseif (isset($this->session->data['guest'])) {
            $payment_address = $this->session->data['guest']['payment'];
        }

        if (isset($shipping_address['country_id']) && isset($shipping_address['country_id'])) {
            $this->tax->setShippingAddress((int) $shipping_address['country_id'], (int) $shipping_address['zone_id']);
        }

        if (isset($payment_address['country_id']) && isset($payment_address['country_id'])) {
            $this->tax->setPaymentAddress((int) $payment_address['country_id'], (int) $payment_address['zone_id']);
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
                $redirect = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
                break;
            }
        }

        if (!$redirect) {

            $totals = array();
            $total = 0;
            $taxes = $this->cart->getTaxes();

            $total_data = array(
                'totals' => &$totals,
                'taxes' => &$taxes,
                'total' => &$total
            );

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
                    ($this->{'model_extension_' . $result['extension'] . '_total_' . $result['code']}->getTotal)($totals, $taxes, $total);
                }
            }

            $sort_order = array();
            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);

            $data['text_coupon_code'] = $this->language->get('text_coupon_code');
            $data['text_coupon_code_new'] = $this->language->get('text_coupon_code_new');
            $data['text_voucher_code'] = $this->language->get('text_voucher_code');
            $data['text_voucher_code_new'] = $this->language->get('text_voucher_code_new');
            $data['column_action'] = $this->language->get('column_action');
            $data['text_coupon_success'] = $this->language->get('text_coupon_success');
            $data['text_remove'] = $this->language->get('text_remove');
            $data['button_update_link'] = $this->language->get('button_update_link');
            $data['text_voucher_success'] = $this->language->get('text_voucher_success');
            $data['text_rewards_point'] = $this->language->get('text_rewards_point');
            $data['text_available_rewards_point'] = $this->language->get('text_available_rewards_point');
            $data['text_max_rewards_point'] = $this->language->get('text_max_rewards_point');
            $data['button_apply'] = $this->language->get('button_apply');
            $data['text_coupon_code_question'] = $this->language->get('text_coupon_code_question');
            $data['text_comments'] = $this->language->get('text_comments');
            $data['text_voucher_code_question'] = $this->language->get('text_voucher_code_question');

            $order_data = array();
            $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
            $order_data['store_id'] = $this->config->get('config_store_id');
            $order_data['store_name'] = $this->config->get('config_name');

            if ($order_data['store_id']) {
                $order_data['store_url'] = $this->config->get('config_url');
            } else {
                $order_data['store_url'] = HTTP_SERVER;
            }

            foreach ($totals as $total1) {
                $data['datatotal'][] = array(
                    'title' => $total1['title'],
                    'code' => $total1['code'],
                    'text' => $this->currency->format($total1['value'], $this->session->data['currency'])
                );
            }

            $payment_address['payment_address_id'] = 0;
            if ($this->customer->isLogged()) {
                $order_data['customer_id'] = $this->customer->getId();
                $order_data['firstname'] = $this->customer->getFirstName();
                $order_data['lastname'] = $this->customer->getLastName();
                $order_data['email'] = $this->customer->getEmail();
                $telephone = $this->customer->getTelephone();
                if ($telephone == "") {
                    $order_data['telephone'] = isset($this->session->data['payment']['payment_telephone']) ? $this->session->data['payment']['payment_telephone'] : "";
                } else {
                    $order_data['telephone'] = $telephone;
                }
                if (isset($this->session->data['payment'])) {
                    $order_data['payment_custom_field'] = isset($this->session->data['payment']['payment_custom_field']) ? $this->session->data['payment']['payment_custom_field'] : "";
                }
                $order_data['fax'] = '';

                $this->load->model('account/address');
                if (isset($this->session->data['payment_address_id'])) {
                    $payment_address = $this->model_account_address->getAddress($this->customer->getId(), $this->session->data['payment_address_id']);
                    $payment_address['payment_address_id'] = $this->session->data['payment_address_id'];
                } else {
                    $payment_address['country_id'] = $this->session->data['payment_country_id'];
                    $payment_address['zone_id'] = $this->session->data['payment_zone_id'];
                }
            } elseif (isset($this->session->data['guest'])) {

                $order_data['customer_id'] = 0;
                if (isset($this->session->data['guestAccount_customer_id'])) {
                    $order_data['customer_id'] = $this->session->data['guestAccount_customer_id'];
                }

                $order_data['customer_group_id'] = isset($this->session->data['guest']['customer_group_id']) ? $this->session->data['guest']['customer_group_id'] : 1;
                $order_data['firstname'] = $this->session->data['guest']['firstname'];
                $order_data['lastname'] = $this->session->data['guest']['lastname'];
                $order_data['email'] = $this->session->data['guest']['email'];
                $order_data['telephone'] = $this->session->data['guest']['telephone'];
                $order_data['fax'] = isset($this->session->data['guest']['fax']) ? $this->session->data['guest']['fax'] : '';

                $payment_address = $this->session->data['guest']['payment'];
            }

            $order_data['payment_address_id'] = !empty($payment_address['payment_address_id']) ? $payment_address['payment_address_id'] : 0;
            $order_data['payment_firstname'] = isset($payment_address['firstname']) ? $payment_address['firstname'] : "";
            $order_data['payment_lastname'] = isset($payment_address['lastname']) ? $payment_address['lastname'] : "";
            $order_data['payment_company'] = isset($payment_address['company']) ? $payment_address['company'] : "";
            $order_data['payment_address_1'] = isset($payment_address['address_1']) ? $payment_address['address_1'] : "";
            $order_data['payment_address_2'] = isset($payment_address['address_2']) ? $payment_address['address_2'] : "";
            $order_data['payment_city'] = isset($payment_address['city']) ? $payment_address['city'] : "";
            $order_data['payment_postcode'] = isset($payment_address['postcode']) ? $payment_address['postcode'] : "";
            $order_data['payment_zone'] = isset($payment_address['zone']) ? $payment_address['zone'] : "";
            $order_data['payment_zone_id'] = isset($payment_address['zone_id']) ? $payment_address['zone_id'] : "";
            $order_data['payment_country'] = isset($payment_address['country']) ? $payment_address['country'] : "";
            $order_data['payment_country_id'] = isset($payment_address['country_id']) ? $payment_address['country_id'] : "";
            $order_data['payment_address_format'] = isset($payment_address['address_format']) ? $payment_address['address_format'] : "";
            $order_data['payment_custom_field'] = isset($payment_address['custom_field']) ? $payment_address['custom_field'] : array();
            $order_data['custom_field'] = isset($this->session->data['guest']['custom_field']) ? $this->session->data['guest']['custom_field'] : array();

            if (isset($this->session->data['payment_method']['name'])) {
                $order_data['payment_method'] = $this->session->data['payment_method']['name'];
            } else {
                $order_data['payment_method'] = '';
            }

            if (isset($this->session->data['payment_method']['code'])) {
                $order_data['payment_code'] = $this->session->data['payment_method']['code'];
                $order_data['payment_code'] = $this->session->data['payment_method']['code'];
            } else {
                $order_data['payment_code'] = '';
            }

            $shipping_address['shipping_address_id'] = 0;
            if ($this->cart->hasShipping()) {
                if ($this->customer->isLogged()) {
                    $this->load->model('account/address');
                    if (isset($this->session->data['shipping_address_id'])) {
                        $shipping_address = $this->model_account_address->getAddress($this->customer->getId(), $this->session->data['shipping_address_id']);
                        $shipping_address['shipping_address_id'] = $this->session->data['shipping_address_id'];
                    } else {
                        $shipping_address['country_id'] = $this->session->data['shipping_country_id'];
                        $shipping_address['zone_id'] = $this->session->data['shipping_zone_id'];
                    }
                } elseif (isset($this->session->data['guest'])) {
                    $shipping_address = $this->session->data['guest']['shipping'];
                }

                $order_data['shipping_address_id'] = !empty($shipping_address['shipping_address_id']) ? $shipping_address['shipping_address_id'] : 0;
                $order_data['shipping_firstname'] = isset($shipping_address['firstname']) ? $shipping_address['firstname'] : "";
                $order_data['shipping_lastname'] = isset($shipping_address['lastname']) ? $shipping_address['lastname'] : "";
                $order_data['shipping_company'] = isset($shipping_address['company']) ? $shipping_address['company'] : "";
                $order_data['shipping_address_1'] = isset($shipping_address['address_1']) ? $shipping_address['address_1'] : "";
                $order_data['shipping_address_2'] = isset($shipping_address['address_2']) ? $shipping_address['address_2'] : "";
                $order_data['shipping_city'] = isset($shipping_address['city']) ? $shipping_address['city'] : "";
                $order_data['shipping_postcode'] = isset($shipping_address['postcode']) ? $shipping_address['postcode'] : "";
                $order_data['shipping_zone'] = isset($shipping_address['zone']) ? $shipping_address['zone'] : "";
                $order_data['shipping_zone_id'] = isset($shipping_address['zone_id']) ? $shipping_address['zone_id'] : "";
                $order_data['shipping_country'] = isset($shipping_address['country']) ? $shipping_address['country'] : "";
                $order_data['shipping_country_id'] = isset($shipping_address['country_id']) ? $shipping_address['country_id'] : "";
                $order_data['shipping_address_format'] = isset($shipping_address['address_format']) ? $shipping_address['address_format'] : "";
                $order_data['shipping_custom_field'] = isset($shipping_address['custom_field']) ? $shipping_address['custom_field'] : array();

                if (isset($this->session->data['shipping_method']['title'])) {
                    $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                } else {
                    $order_data['shipping_method'] = '';
                }
                if (isset($this->session->data['shipping_method']['code'])) {
                    $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                    $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                } else {
                    $order_data['shipping_code'] = '';
                }
            } else {
                $order_data['shipping_address_id'] = 0;
                $order_data['shipping_firstname'] = '';
                $order_data['shipping_lastname'] = '';
                $order_data['shipping_company'] = '';
                $order_data['shipping_address_1'] = '';
                $order_data['shipping_address_2'] = '';
                $order_data['shipping_city'] = '';
                $order_data['shipping_postcode'] = '';
                $order_data['shipping_zone'] = '';
                $order_data['shipping_zone'] = '';
                $order_data['shipping_zone_id'] = '';
                $order_data['shipping_country'] = '';
                $order_data['shipping_country_id'] = '';
                $order_data['shipping_address_format'] = '';
                $order_data['shipping_method'] = '';
                $order_data['shipping_code'] = '';
                $order_data['shipping_custom_field'] = array();
            }

            $product_data = array();

            foreach ($this->cart->getProducts() as $product) {
                $option_data = array();

                foreach ($product['option'] as $option) {
                    $option_data[] = array(
                        'product_option_id' => $option['product_option_id'],
                        'product_option_value_id' => $option['product_option_value_id'],
                        'option_id' => $option['option_id'],
                        'option_value_id' => $option['option_value_id'],
                        'name' => $option['name'],
                        'value' => $option['value'],
                        'type' => $option['type']
                    );
                }

                $subscription_data = [];

                if ($product['subscription']) {
                    $subscription_data = [
                        'subscription_plan_id' => $product['subscription']['subscription_plan_id'],
                        'name'                 => $product['subscription']['name'],
                        'trial_price'          => $product['subscription']['trial_price'],
                        'trial_tax'            => $this->tax->getTax($product['subscription']['trial_price'], $product['tax_class_id']),
                        'trial_frequency'      => $product['subscription']['trial_frequency'],
                        'trial_cycle'          => $product['subscription']['trial_cycle'],
                        'trial_duration'       => $product['subscription']['trial_duration'],
                        'trial_remaining'      => $product['subscription']['trial_remaining'],
                        'trial_status'         => $product['subscription']['trial_status'],
                        'price'                => $product['subscription']['price'],
                        'tax'                  => $this->tax->getTax($product['subscription']['price'], $product['tax_class_id']),
                        'frequency'            => $product['subscription']['frequency'],
                        'cycle'                => $product['subscription']['cycle'],
                        'duration'             => $product['subscription']['duration']
                    ];
                }

                $product_data[] = array(
                    'product_id' => $product['product_id'],
                    'master_id' => $product['master_id'],
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'option' => $option_data,
                    'subscription' => $subscription_data,
                    'download' => $product['download'],
                    'quantity' => $product['quantity'],
                    'subtract' => $product['subtract'],
                    'price' => $product['price'],
                    'total' => $product['total'],
                    'tax' => $this->tax->getTax($product['price'], $product['tax_class_id']),
                    'reward' => $product['reward']
                );
            } 

            // Gift Voucher
            $voucher_data = array();

            if (!empty($this->session->data['vouchers'])) {
                foreach ($this->session->data['vouchers'] as $voucher) {
                    $voucher_data[] = array(
                        'description' => $voucher['description'],
                        'code' => substr(md5(mt_rand()), 0, 10),
                        'to_name' => $voucher['to_name'],
                        'to_email' => $voucher['to_email'],
                        'from_name' => $voucher['from_name'],
                        'from_email' => $voucher['from_email'],
                        'voucher_theme_id' => $voucher['voucher_theme_id'],
                        'message' => $voucher['message'],
                        'amount' => $voucher['amount']
                    );
                }
            }

            $order_data['products'] = $product_data;
            $order_data['vouchers'] = $voucher_data;
            $order_data['totals'] = $totals;

            if (!isset($this->session->data['comment'])) {
                $this->session->data['comment'] = "";
            }
            $order_data['comment'] = $this->session->data['comment'];
            $order_data['total'] = $total;

            if (isset($this->request->cookie['tracking'])) {
                $this->load->model('affiliate/affiliate');

                $affiliate_info = $this->model_affiliate_affiliate->getAffiliateByCode($this->request->cookie['tracking']);

                $subtotal = $this->cart->getSubTotal();

                if ($affiliate_info) {
                    $order_data['affiliate_id'] = $affiliate_info['affiliate_id'];
                    $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                } else {
                    $order_data['affiliate_id'] = 0;
                    $order_data['commission'] = 0;
                }
            } else {
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
            }
            // Marketing
            if (isset($this->request->post['affiliate_id'])) {
                $order_data['marketing_id'] = 0;
                $order_data['tracking'] = '';
            } else {
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
                $order_data['marketing_id'] = 0;
                $order_data['tracking'] = '';
            }
            $order_data['language_id'] = $this->config->get('config_language_id');
            $order_data['customer_group_id'] = $this->config->get('config_customer_group_id');
            $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
            $order_data['currency_code'] = $this->session->data['currency'];
            $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
            $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

            if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
                $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
                $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
            } else {
                $order_data['forwarded_ip'] = '';
            }

            if (isset($this->request->server['HTTP_USER_AGENT'])) {
                $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
            } else {
                $order_data['user_agent'] = '';
            }

            if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
                $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
            } else {
                $order_data['accept_language'] = '';
            }

            if (!isset($this->session->data['order_id'])) {
                $this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);
            } else {
                $this->model_extension_supercheckout_supercheckout_order->editOrder($this->session->data['order_id'], $order_data);
            }

            $data['column_name'] = $this->language->get('column_name');
            $data['column_model'] = $this->language->get('column_model');
            $data['column_quantity'] = $this->language->get('column_quantity');
            $data['column_price'] = $this->language->get('column_price');
            $data['column_total'] = $this->language->get('column_total');


            $data['products'] = array();

            foreach ($this->cart->getProducts() as $product) {
                $option_data = array();

                foreach ($product['option'] as $option) {
                    if ($option['type'] != 'file') {
                        $value = $option['value'];
                    } else {
                        $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

                        if ($upload_info) {
                            $value = $upload_info['name'];
                        } else {
                            $value = '';
                        }
                    }

                    $option_data[] = array(
                        'name' => $option['name'],
                        'value' => (oc_strlen($value) > 20 ? oc_substr($value, 0, 20) . '..' : $value)
                    );
                }
                //load image if set from admin
                if (isset($data['settings']['step']['cart']['image_width']) && isset($data['settings']['step']['cart']['image_height'])) {
                    if ($product['image']) {
                        $image = $this->model_tool_image->resize($product['image'], $data['settings']['step']['cart']['image_width'], $data['settings']['step']['cart']['image_height']);
                    } else {
                        $image = '';
                    }
                } else {
                    if ($product['image']) {
                        $image = $this->model_tool_image->resize($product['image'], $this->config->get('config_image_product_width'), $this->config->get('config_image_product_height'));
                    } else {
                        $image = '';
                    }
                }

                if (version_compare(VERSION, '2.1.0.1', '<')) {
                    $data['products'][] = array(
                        'key' => $product['key'],
                        'thumb' => $image,
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => $option_data,
                        'reward' => $product['reward'],
                        'quantity' => $product['quantity'],
                        'subtract' => $product['subtract'],
                        'price' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                        'total' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                        'href' => $this->url->link('product/product', 'product_id=' . $product['product_id'].'&language=' . $this->config->get('config_language')),
                        'remove' => $this->url->link('supercheckout/supercheckout/cart', 'remove=' . $product['key'].'&language=' . $this->config->get('config_language'))
                    );
                } else {
                    $data['products'][] = array(
                        'key' => $product['cart_id'],
                        'thumb' => $image,
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => $option_data,
                        'reward' => $product['reward'],
                        'quantity' => $product['quantity'],
                        'subtract' => $product['subtract'],
                        'price' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                        'total' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                        'href' => $this->url->link('product/product', 'product_id=' . $product['product_id'].'&language=' . $this->config->get('config_language')),
                        'remove' => $this->url->link('supercheckout/supercheckout/cart', 'remove=' . $product['cart_id'].'&language=' . $this->config->get('config_language'))
                    );
                }
            }

            // Gift Voucher
            $data['vouchers'] = array();
            if (!empty($this->session->data['vouchers'])) {
                foreach ($this->session->data['vouchers'] as $voucher) {
                    $data['vouchers'][] = array(
                        'description' => $voucher['description'],
                        'amount' => $this->currency->format($voucher['amount'], $this->session->data['currency'])
                    );
                }
            }
            $data['totals'] = $total_data;
        } else {
            $data['redirect'] = $redirect;
        }

        
        if(isset($order_data['totals'])){
        foreach ($order_data['totals'] as $total_cart) {
            if ($total_cart['code'] = 'total') {
                $cart_data_total = $total_cart['value'];
            }
        }


        $data['default_cart_total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($cart_data_total, $this->session->data['currency']));
    }
        $data['sessions'] = $this->session->data;

        /**
         * Check if new template is enabled
         * If enabled, load new template or load old template
         * @date 05-02-2025
         * @modifier Amit Singh
         */
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template']) {
            $this->response->setOutput($this->load->view('extension/supercheckout/supercheckout/confirm_new', $data));
        } else {
            $this->response->setOutput($this->load->view('extension/supercheckout/supercheckout/confirm', $data));
        }

        
    }
}
