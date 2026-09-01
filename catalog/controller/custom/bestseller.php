<?php
namespace Opencart\Catalog\Controller\Custom;

class Bestseller extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->response->redirect($this->url->link('common/home'));
	}
}
