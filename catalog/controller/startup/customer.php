<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Customer
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Customer extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->registry->set('customer', new \Opencart\System\Library\Cart\Customer($this->registry));

		// Customer Group
		if (isset($this->session->data['customer'])) {
			$this->config->set('config_customer_group_id', $this->session->data['customer']['customer_group_id']);
		} elseif ($this->customer->isLogged()) {
			// Logged in customers
			$this->config->set('config_customer_group_id', $this->customer->getGroupId());
		}

		if ($this->customer->isLogged()) {
			$this->response->addHeader('X-Accel-Expires: 0');
			$this->response->addHeader('X-Cache-Lifetime: 0');
			$this->response->addHeader('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0');
			$this->response->addHeader('Pragma: no-cache');
			if (!headers_sent()) {
				header('X-Accel-Expires: 0');
				header('X-Cache-Lifetime: 0');
				header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0');
				header('Pragma: no-cache');
			}
			if (class_exists('ClpVarnish')) {
				\ClpVarnish::setCacheLifetime(0);
			}
		}
	}
}
