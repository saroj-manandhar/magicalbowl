<?php

namespace Opencart\Catalog\Model\Extension\Supercheckout\Supercheckout;

class Order extends \Opencart\System\Engine\Model
{

    public function editCustomerId($order_id, $data)
    {

        $this->db->query("UPDATE `" . DB_PREFIX . "order` set customer_id = '" . (int) $data['customer_id'] . "', customer_group_id = '" . (int) $data['customer_group_id'] . "' , custom_field = '" . $this->db->escape(isset($data['custom_feilds']['account']) ? json_encode($data['custom_feilds']['account']) : '') . "' , date_modified = NOW() WHERE order_id = '" . (int) $order_id . "'");
    }

    public function editOrder($order_id, $data)
    {
        $this->load->model('localisation/country');

        $this->load->model('localisation/zone');
        /**
         * Loaded address format model for fetching address format on the checkout page.
         * @modifier Himanshu Vishwakarma
         * @date 05-08-2025
         */
        $country_info = $this->model_localisation_country->getCountry((int)$data['shipping_country_id']);

        if(isset($country_info['address_format_id']) && !isset($country_info['address_format'])){
            $this->load->model('localisation/address_format');
            $address_format_country = $this->model_localisation_address_format->getAddressFormat((int) $country_info['address_format_id']);
            $country_info['address_format'] = $address_format_country['address_format'];
        }
	
        if ($country_info) {
            $shipping_country = $country_info['name'];
            $shipping_address_format = $country_info['address_format'];
        } else {
            $shipping_country = '';
            $shipping_address_format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
        }

        $zone_info = $this->model_localisation_zone->getZone((int) $data['shipping_zone_id']);

        if ($zone_info) {
            $shipping_zone = $zone_info['name'];
        } else {
            $shipping_zone = '';
        }

        /**
         * Loaded address format model for fetching address format on the checkout page.
         * @modifier Himanshu Vishwakarma
         * @date 05-08-2025
         */
        $country_info = $this->model_localisation_country->getCountry((int)$data['payment_country_id']);

        if(isset($country_info['address_format_id']) && !isset($country_info['address_format'])){
            $this->load->model('localisation/address_format');
            $address_format_country = $this->model_localisation_address_format->getAddressFormat((int) $country_info['address_format_id']);
            $country_info['address_format'] = $address_format_country['address_format'];
        }

        if ($country_info) {
            $payment_country = $country_info['name'];
            $payment_address_format = $country_info['address_format'];
        } else {
            $payment_country = '';
            $payment_address_format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
        }

        $zone_info = $this->model_localisation_zone->getZone((int) $data['payment_zone_id']);

        if ($zone_info) {
            $payment_zone = $zone_info['name'];
        } else {
            $payment_zone = '';
        }

        $data_total = (float) $data['total'];

        $payment_method_details = array(
            'code' => $this->db->escape($data['payment_code']),
            'name' => $this->db->escape($data['payment_method'])
        );

        $shipping_method_details = array(
            'code' => $this->db->escape($data['shipping_code']),
            'name' => $this->db->escape($data['shipping_method'])
        );

        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `invoice_prefix` = '" . $this->db->escape((string)$data['invoice_prefix']) . "', `store_id` = '" . (int)$data['store_id'] . "', `store_name` = '" . $this->db->escape((string)$data['store_name']) . "', `store_url` = '" . $this->db->escape((string)$data['store_url']) . "', customer_id = '" . (int) $data['customer_id'] . "', `customer_group_id` = '" . (int)$data['customer_group_id'] . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', `payment_address_id` = '" . (int)$data['payment_address_id'] . "', payment_firstname = '" . $this->db->escape($data['payment_firstname']) . "', payment_lastname = '" . $this->db->escape($data['payment_lastname']) . "', payment_company = '" . $this->db->escape($data['payment_company']) . "', payment_address_1 = '" . $this->db->escape($data['payment_address_1']) . "', payment_address_2 = '" . $this->db->escape($data['payment_address_2']) . "', payment_city = '" . $this->db->escape($data['payment_city']) . "', payment_postcode = '" . $this->db->escape($data['payment_postcode']) . "', payment_country = '" . $this->db->escape($payment_country) . "', payment_country_id = '" . (int) $data['payment_country_id'] . "', payment_zone = '" . $this->db->escape($payment_zone) . "', payment_zone_id = '" . (int) $data['payment_zone_id'] . "', payment_address_format = '" . $this->db->escape($payment_address_format) . "', payment_custom_field = '" . $this->db->escape(json_encode($data['payment_custom_field'])) . "', payment_method = '" . json_encode($payment_method_details) . "', `shipping_address_id` = '" . (int)$data['shipping_address_id'] . "', shipping_firstname = '" . $this->db->escape($data['shipping_firstname']) . "', shipping_lastname = '" . $this->db->escape($data['shipping_lastname']) . "',  shipping_company = '" . $this->db->escape($data['shipping_company']) . "', shipping_address_1 = '" . $this->db->escape($data['shipping_address_1']) . "', shipping_address_2 = '" . $this->db->escape($data['shipping_address_2']) . "', shipping_city = '" . $this->db->escape($data['shipping_city']) . "', shipping_postcode = '" . $this->db->escape($data['shipping_postcode']) . "', shipping_country = '" . $this->db->escape($shipping_country) . "', shipping_country_id = '" . (int) $data['shipping_country_id'] . "', shipping_zone = '" . $this->db->escape($shipping_zone) . "', shipping_zone_id = '" . (int) $data['shipping_zone_id'] . "', shipping_address_format = '" . $this->db->escape($shipping_address_format) . "', shipping_custom_field = '" . $this->db->escape(json_encode($data['shipping_custom_field'])) . "', shipping_method = '" . json_encode($shipping_method_details) . "', comment = '" . $this->db->escape($data['comment']) . "', total = '" . $data_total . "', affiliate_id  = '" . (int) $data['affiliate_id'] . "',currency_id  = '" . (int) $data['currency_id'] . "',language_id  = '" . (int) $data['language_id'] . "',currency_code = '" . $this->db->escape($data['currency_code']) . "',currency_value = '" . (float) $data['currency_value'] . "', date_modified = NOW() WHERE order_id = '" . (int) $order_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int) $order_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int) $order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_subscription` WHERE `order_id` = '" . (int)$order_id . "'");

        if (isset($data['products'])) {

            foreach ($data['products'] as $order_product) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_product SET  order_id = '" . (int) $order_id . "', product_id = '" . (int) $order_product['product_id'] . "', `master_id` = '" . (int)$order_product['master_id'] . "', name = '" . $this->db->escape($order_product['name']) . "', model = '" . $this->db->escape($order_product['model']) . "', quantity = '" . (int) $order_product['quantity'] . "', price = '" . (float) $order_product['price'] . "', total = '" . (float) $order_product['total'] . "', tax = '" . (float) $order_product['tax'] . "', reward = '" . (int) $order_product['reward'] . "'");
                $order_product_id = $this->db->getLastId();

                if (isset($order_product['option'])) {
                    foreach ($order_product['option'] as $order_option) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "order_option SET  order_id = '" . (int) $order_id . "', order_product_id = '" . (int) $order_product_id . "', product_option_id = '" . (int) $order_option['product_option_id'] . "', product_option_value_id = '" . (int) $order_option['product_option_value_id'] . "', name = '" . $this->db->escape($order_option['name']) . "', `value` = '" . $this->db->escape($order_option['value']) . "', `type` = '" . $this->db->escape($order_option['type']) . "'");
                    }
                }

                if ($order_product['subscription']) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "order_subscription` SET `order_id` = '" . (int)$order_id . "', `order_product_id` = '" . (int)$order_product_id . "', `subscription_plan_id` = '" . (int)$order_product['subscription']['subscription_plan_id'] . "', `trial_price` = '" . (float)$order_product['subscription']['trial_price'] . "', `trial_tax` = '" . (float)$order_product['subscription']['trial_tax'] . "', `trial_frequency` = '" . $this->db->escape($order_product['subscription']['trial_frequency']) . "', `trial_cycle` = '" . (int)$order_product['subscription']['trial_cycle'] . "', `trial_duration` = '" . (int)$order_product['subscription']['trial_duration'] . "', `trial_remaining` = '" . (int)$order_product['subscription']['trial_remaining'] . "', `trial_status` = '" . (int)$order_product['subscription']['trial_status'] . "', `price` = '" . (float)$order_product['subscription']['price'] . "', `tax` = '" . (float)$order_product['subscription']['tax'] . "', `frequency` = '" . $this->db->escape($order_product['subscription']['frequency']) . "', `cycle` = '" . (int)$order_product['subscription']['cycle'] . "', `duration` = '" . (int)$order_product['subscription']['duration'] . "'");
                }
            }
        }

        //$this->db->query("DELETE FROM " . DB_PREFIX . "order_voucher WHERE order_id = '" . (int) $order_id . "'");
        if (isset($data['vouchers'])) {
            foreach ($data['vouchers'] as $order_voucher) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_voucher SET  order_id = '" . (int) $order_id . "', voucher_id = '" . (isset($order_voucher['voucher_id']) ? (int) $order_voucher['voucher_id'] : 0) . "', description = '" . $this->db->escape($order_voucher['description']) . "', code = '" . $this->db->escape($order_voucher['code']) . "', from_name = '" . $this->db->escape($order_voucher['from_name']) . "', from_email = '" . $this->db->escape($order_voucher['from_email']) . "', to_name = '" . $this->db->escape($order_voucher['to_name']) . "', to_email = '" . $this->db->escape($order_voucher['to_email']) . "', voucher_theme_id = '" . (int) $order_voucher['voucher_theme_id'] . "', message = '" . $this->db->escape($order_voucher['message']) . "', amount = '" . (float) $order_voucher['amount'] . "'");
                $order_voucher_id = $this->db->getLastId();
                $voucher_id = $this->db->query("SELECT voucher_id FROM " . DB_PREFIX . "voucher WHERE order_id = " . $order_id);
                $voucher_id = $voucher_id->row['voucher_id'];
                $this->db->query("UPDATE " . DB_PREFIX . "order_voucher SET voucher_id = '" . (int) $voucher_id . "' WHERE order_voucher_id = '" . (int)$order_voucher_id . "'");
            }
        }

        // Get the total
        $total = 0;
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int) $order_id . "'");
        if (isset($data['totals'])) {
            foreach ($data['totals'] as $order_total) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET  order_id = '" . (int) $order_id . "', code = '" . $this->db->escape($order_total['code']) . "', `extension` = '" . $this->db->escape($order_total['extension']) . "', title = '" . $this->db->escape($order_total['title']) . "',  `value` = '" . (float) $order_total['value'] . "', sort_order = '" . (int) $order_total['sort_order'] . "'");
                $total += $order_total['value'];
            }
        }
    }
}
