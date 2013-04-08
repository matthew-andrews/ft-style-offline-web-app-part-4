<?php
require_once('article.php');

class ArticlesController {

	private $_article, $_templates;

	public function __construct(Templates $templates) {
		$this->_article = new Article();;
		$this->_templates = $templates;
	}

	public function showArticleList() {
		return $this->_templates->articleList($this->_article->get());
	}

	public function showArticle($id) {
		return $this->_templates->article($this->_article->get($id));
	}

}