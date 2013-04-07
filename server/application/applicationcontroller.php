<?php
require_once('server/articles/articlescontroller.php');

class ApplicationController {

	public function showHome() {
	    $articlesController = new ArticlesController();
	    return Templates::home($articlesController->showArticleList());
	}

	public function showArticle($id) {
		$articlesController = new ArticlesController();
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