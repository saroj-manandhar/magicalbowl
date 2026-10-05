<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Session
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Session extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function index(): void {
		$session = new \Opencart\System\Library\Session($this->config->get('session_engine'), $this->registry);
		$this->registry->set('session', $session);

		// API
		if (isset($this->request->get['route']) && substr((string)$this->request->get['route'], 0, 4) == 'api/' && isset($this->request->get['api_token'])) {
			$this->load->model('setting/api');

			$this->model_setting_api->cleanSessions();

			// Make sure the IP is allowed
			$api_info = $this->model_setting_api->getApiByToken($this->request->get['api_token']);

			if ($api_info) {
				$this->session->start($this->request->get['api_token']);

				$this->model_setting_api->updateSession($api_info['api_session_id']);
			}

			return;
		}

		/*
		We are adding the session cookie outside of the session class as I believe
		PHP messed up in a big way handling sessions. Why in the hell is it so hard to
		have more than one concurrent session using cookies!

		Is it not better to have multiple cookies when accessing parts of the system
		that requires different cookie sessions for security reasons.
		*/

		// Update the session lifetime
		if ($this->config->get('config_session_expire')) {
			$this->config->set('session_expire', $this->config->get('config_session_expire'));
		}

		// Update the session SameSite
		$this->config->set('session_samesite', $this->config->get('config_session_samesite'));

		if (isset($this->request->cookie[$this->config->get('session_name')])) {
			$session_id = $this->request->cookie[$this->config->get('session_name')];
		} else {
			$session_id = '';
		}

		$session->start($session_id);

		$option = [
			'expires'  => time() + (int)$this->config->get('config_session_expire'),
			'path'     => $this->config->get('session_path'),
			'secure'   => $this->request->server['HTTPS'],
			'httponly' => false,
			'SameSite' => $this->config->get('session_samesite')
		];

		$this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';

		if (
			str_starts_with($route, 'account/') ||
			str_starts_with($route, 'checkout/') ||
			(isset($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) ||
			!empty($this->session->data['customer_id'])
		) {
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
		} else {
			$this->response->addHeader('X-Cache-Lifetime: 600');
			if (!headers_sent()) {
				header('X-Cache-Lifetime: 600');
			}
			if (class_exists('ClpVarnish')) {
				\ClpVarnish::setCacheLifetime(600);
			}
		}

		setcookie($this->config->get('session_name'), $session->getId(), $option);
	}
}
