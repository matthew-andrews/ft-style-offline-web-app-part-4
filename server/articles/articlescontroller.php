<?php
require_once('article.php');

class ArticlesController {

	private $_article;

	public function __construct() {
		$this->_article = new Article();;
	}

	public function showArticleList() {
		return Templates::articleList($this->_article->get());
	}

	public function showArticle($id) {
		return Templates::article($this->_article->get($id));
	}

}