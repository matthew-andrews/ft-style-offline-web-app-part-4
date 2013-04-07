<?php

class Article {

	public function get($articleId = null) {
		$rss = new SimpleXMLElement(file_get_contents('http://www.guardian.co.uk/technology/mobilephones/rss'));

		if ($articleId) {
		  $xpath = '/rss/channel/item['. $articleId .']';
		} else {
		  $xpath = '/rss/channel/item';
		}
		$items = $rss->xpath($xpath);

		if ($items) {
		  $output = array();
		  foreach ($items as $id => $item) {

		    // This will be encoded as an object, not an array, by json_encode
		    $output[] = array(
		      'id' => $id + 1,
		      'headline' => strval($item->title),
		      'date' => strval($item->pubDate),
		      'body' => strval(strip_tags($item->description,'<p><br>')),
		      'author' => strval($item->children('http://purl.org/dc/elements/1.1/')->creator)
		    );
		  }

		  if ($articleId > 0) {
		    return $output[0];
		  } else {
		    return $output;
		  }
		}
	}

}