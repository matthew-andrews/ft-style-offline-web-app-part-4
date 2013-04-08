<?php
require_once('server/articles/articlescontroller.php');

class ApplicationController {

	private $_templates;

	public function __construct(Templates $templates) {
		$this->_templates = $templates;
	}

	public function showHome() {
	    $articlesController = new ArticlesController($this->_templates);
	    return $this->_templates->home($articlesController->showArticleList());
	}

	public function showArticle($id) {
		$articlesController = new ArticlesController($this->_templates);
	    return $articlesController->showArticle($id);
	}

	public function route($page) {
		$page = trim($page, '/');
	    if (strlen($page)) {
	        if (intval($page) > 0) {
	            return $this->showArticle(intval($page));
	        }
	    } else {
	        return $this->showHome();
	    }
	}

}