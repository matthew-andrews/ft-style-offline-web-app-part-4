<?php
class Templates {

    public static function application($content) {
		return '<div id="window"><div id="header"><h1>FT Tech Blog</h1></div><div id="body">' . $content .'</div></div>';
    }

    public static function home($headlines) {
        return '<button id="refreshButton">Refresh the news!</button><div id="headlines">' . $headlines . '</div></div>';
    }

    public static function articleList($articles) {
		$output = '';

        if (count($articles) === 0) {
            return '<p><i>No articles have been found, maybe you haven\'t <b>refreshed the news</b>?</i></p>';
        }
        for ($i = 0, $l = count($articles); $i < $l; $i = $i + 1) {
            $output .= '<li><a href="/' . $articles[$i]['id'] . '"><b>' . $articles[$i]['headline'] . '</b><br />By ' . $articles[$i]['author'] . ' on ' . $articles[$i]['date'] . '</a></li>';
        }
        return '<ul>' . $output . '</ul>';
    }

    public static function article($articleData) {
        return '<a href="/">Go back home</a><h2>' . $articleData['headline'] . '</h2><h3>By ' . $articleData['author'] . ' on ' . $articleData['date'] . '</h3>' . $articleData['body'];
    }

	public static function articleLoading() {
        return '<a href="/">Go back home</a><br /><br />Please wait&hellip;';
    }
}