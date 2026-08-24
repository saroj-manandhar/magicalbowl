<?php

namespace Opencart\Catalog\Controller\Extension\Supercheckout\Supercheckout;

class PaymentDisplay extends \Opencart\System\Engine\Controller
{

    public function index($ajax = 1)
    {
        $this->load->model('checkout/order');
        /**
         * Check if the supercheckout new template is enabled then check if the template file exists or not
         * If the file exists then set the default theme to the current theme
         * @date 06-02-2025
         * @modifier Amit Singh
         */
        if (isset($data['settings']['general']['supercheckout_enable_new_template']) && $data['settings']['general']['supercheckout_enable_new_template'] && file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/payment_display_new.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } elseif (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/supercheckout/payment_display.tpl')) {
            $data['default_theme'] = $this->config->get('config_template');
        } else {
            $data['default_theme'] = 'default';
        }

        //Getting payment method for displaying on supercheckout page

        // Validate if payment method has been set.
        if (isset($this->session->data['payment_method']['code'])) {
            $code_parts = explode('.', $this->session->data['payment_method']['code']);
            $code = !empty($code_parts[0]) ? $code_parts[0] : '';
        } else {
            $code = '';
        }

        $extension_info = $this->model_setting_extension->getExtensionByCode('payment', $code);

        if ($extension_info) {
            $data['payment'] = $this->load->controller('extension/' . $extension_info['extension'] . '/payment/' . $extension_info['code']);
        } else {
            $data['payment'] = '';
        }

        $data['sessions'] = $this->session->data;
        if ($ajax == 0) {
            return $this->load->view('extension/supercheckout/supercheckout/payment_display', $data);
        } else {
            $this->response->setOutput($this->load->view('extension/supercheckout/supercheckout/payment_display', $data));
        }
    }
}
