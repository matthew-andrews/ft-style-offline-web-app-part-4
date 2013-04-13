# Putting the web back into web app

This is part 4 of a [tutorial series][A1] on how to make an FT style offline web app.

We [left off last time][A2] with an app that delivers offline news on most modern browsers - and degrades gracefully on older browsers. But if we look back to our list of **ideas for further development** in the first tutorial we still have work to do to make it work just as well as a website as it does as an app.

[A1]:http://labs.ft.com/category/tutorial/
[A2]:http://net.tutsplus.com/tutorials/other/a-deeper-look-at-mod_rewrite-for-apache/

## Requirements for the tutorial - Part 4

- It should work even if the user's Javascript is disabled.
- It should be crawl-able by search engines.
- The first time a user uses the app the first load should be rendered on the server side.
- We should use the [History API][B1] instead of hash tag URLs so that the URL in the user's browser address page always matches page that they are viewing.

These might not sound like groundbreaking features - websites have been doing the first three forever - but as usual the *appcache* gets in the way.

// TODO Explain the problem of trying to serve two different **index.php**'s.

In this version we will use a .htaccess file to use 
[Apache's mod_rewrite][B2]. Mod rewrite is very widely documented and has
been around for decades so I will use it without explanation. If you wish to use a different type of web server you may need to use a different URL rewriting technology.

As always, the [full code is up on GitHub][B3].

[B1]:http://diveintohtml5.info/history.html
[B2]:http://labs.ft.com/2012/11/using-an-iframe-to-stop-app-cache-storing-masters/
[B3]:https://github.com/matthew-andrews/ft-style-offline-web-app-part-4

### The new and changed files required in the tutorial:-

<table>
	<tr>
		<td><strong>/.htaccess</strong></td>
		<td></td>
	</tr>
	<tr>
		<td><strong>/api/resources/javascript.php</strong></td>
		<td></td>
	</tr>
	<tr>
		<td>/index.php</td>
		<td></td>
	</tr>
	<tr>
		<td><strong>/server/application/applicationcontroller.php</strong></td>
		<td></td>
	</tr>
	<tr>
		<td><strong>/server/articles/article.php</strong></td>
		<td></td>
	</tr>
	<tr>
		<td><strong>/server/articles/articlescontroller.php</strong></td>
		<td></td>
	</tr>
	<tr>
		<td><strong>/server/templates.php</strong></td>
		<td></td>
	</tr>
	<tr>
		<td>/source/appcache.js</td>
		<td></td>
	</tr>
	<tr>
		<td>/source/application/applicationcontroller.js</td>
		<td></td>
	</tr>
	<tr>
		<td>/source/templates.js</td>
		<td>We need to update the templates to be real URLs rather than hash tag URLs.</td>
	</tr>
</table>

New files are highlighted in bold, changes to existing files not in bold.

### /.htaccess
```
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^([0-9]+) index.php [L]
</IfModule>
```

A simple .htaccess rewrite rule matching the style used in the client side app code. This should ensure all requests to the main page of the app or article will be pushed through the index.php file.

### /api/resources/javascript.php

```
<?php
$js = '';
$js .= file_get_contents('../../libraries/client/fastclick.js');
$js = $js . 'var APP={}; (function (APP) {';
$js = $js . file_get_contents('../../source/application/applicationcontroller.js');
$js = $js . file_get_contents('../../source/articles/articlescontroller.js');
$js = $js . file_get_contents('../../source/articles/article.js');
$js = $js . file_get_contents('../../source/datastores/network.js');
$js = $js . file_get_contents('../../source/datastores/indexeddb.js');
$js = $js . file_get_contents('../../source/datastores/websql.js');
$js = $js . file_get_contents('../../source/templates.js');
$js = $js . file_get_contents('../../source/appcache.js');
$js = $js . '}(APP)),';

// Detect and set the absolute path to the root of the web app
// First get a clean version of the current directory (will include api/resources)
$appRoot = trim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Strip of api/resources from the end of the path
$appRoot = trim(preg_replace('/api\/resources$/i', '', $appRoot), '/');

// Ensure the path starts and ends with a slash or just / if on the root of domain
$appRoot = '/' . ltrim($appRoot . '/', '/');

echo $js . 'APP_ROOT = "' . $appRoot . '";';
```

// TODO Explain the javascript

### /index.php

```
<?php
// Detect the app root (taken from api/resources/index.php)
$appRoot = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
$appRoot = '/' . ltrim($appRoot . '/', '/');
$appcacheUpdate = isset($_COOKIE['appcacheUpdate']);
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,minimum-scale=1.0,user-scalable=no" />
		<script type="text/javascript" src="<?php echo $appRoot; ?>jquery.min.js"></script>
		<?php if ($appcacheUpdate) { ?>
		<script type="text/javascript">
			$(document).ready(function () {

				var APP_START_FAILED = "I'm sorry, the app can't start right now.";
				function startWithResources(resources, storeResources) {

					// Try to execute the Javascript
					try {
						eval(resources.js);
						APP.applicationController.start(resources, storeResources);

					// If the Javascript fails to launch, stop execution!
					} catch (e) {
						if (typeof console !== "undefined") {
							console.log(e);
						}
						alert(APP_START_FAILED);
					}
				}
				function startWithOnlineResources(resources) {
					startWithResources(resources, true);
				}

				function startWithOfflineResources(e) {
					var resources;

					// If we have resources saved from a previous visit, use them
					if (localStorage && localStorage.resources) {
						resources = JSON.parse(localStorage.resources);
						startWithResources(resources, false);

					// Otherwise, apologize and let the user know
					} else {
						alert(APP_START_FAILED);
					}
				}

				// If we know the device is offline, don't try to load new resources
				if (navigator && navigator.onLine === false) {
					startWithOfflineResources();

				// Otherwise, download resources, eval them, if successful push them into local storage.
				} else {
					$.ajax({
						url: '<?php echo $appRoot; ?>api/resources/',
						success: startWithOnlineResources,
						error: startWithOfflineResources,
						dataType: 'json'
					});
				}

			});
		</script>
		<?php } else { ?>
		<link href="<?php echo $appRoot; ?>css/global.css" media="all" rel="stylesheet" type="text/css" />
		<script type="text/javascript" src="<?php $appRoot; ?>api/resources/javascript.php"></script>
		<script type="text/javascript">
		$(document).ready(function () {
			APP.applicationController.startFromServer();
		});
		</script>
		<?php } ?>
		<title>News</title>
	</head>
<body>
	<?php if ($appcacheUpdate) { ?>
	<div id="loading">Loading&hellip;</div>
	<?php } else {
		require_once('server/templates.php');
		$templates = new Templates($appRoot);
		require_once('server/application/applicationcontroller.php');
		$applicationController = new ApplicationController($templates);
		echo Templates::application($applicationController->route(preg_replace('/^'. preg_quote($appRoot, '/') . '/', '', $_SERVER['REQUEST_URI'])));
	} ?>
</body>
</html>
```

// TODO Explain the changes to index.php


## Reimplement the demo app in PHP

The next step is to take the code we've written and make it so that it will run on our server. The easiest way to do this (where easiest means - not adding dependencies on any more technologies) is to rewrite it in PHP.

### /server/application/applicationcontroller.php

```
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
```

// TODO Explain the application controller

### /server/articles/article.php

```
<?php

class Article {

	public function get($articleId = null) {
		$rss = new SimpleXMLElement(file_get_contents('http://feeds2.feedburner.com/ft/tech-blog'));

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
```

Much like **/sources/articles/article.js** the role of this class is to just return article data. If the ```get``` function is passed a parameter (and ID of the article) it will return a single article, otherwise it will return all the articles in the RSS feed.

To make writing the explanations for the tutorial simpler to explain, I've not bothered to combine some of the logic in this file with the logic in **/api/articles/index.php** - even though it is almost exactly the same. The only difference is **/api/article/index.php** outputs a json encoded object - used by the Javascript in the demo app, whereas this file is will be used by other PHP files to get a PHP associative array containing article data.

### /server/articles/articlescontroller.php

```
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
```

This code should look very similar to its corresponding Javascript file in **/sources/articles/articlescontroller.js**.

### /server/templates.php

```
<?php
class Templates {

    private $_appRoot;

    public function __construct($appRoot) {
        $this->_appRoot = $appRoot;
    }

    public function application($content) {
		return '<div id="window"><div id="header"><h1>FT Tech Blog</h1></div><div id="body">' . $content .'</div></div>';
    }

    public function home($headlines) {
        return '<button id="refreshButton">Refresh the news!</button><div id="headlines">' . $headlines . '</div></div>';
    }

    public function articleList($articles) {
		$output = '';

        if (count($articles) === 0) {
            return '<p><i>No articles have been found, maybe you haven\'t <b>refreshed the news</b>?</i></p>';
        }
        for ($i = 0, $l = count($articles); $i < $l; $i = $i + 1) {
            $output .= '<li><a href="' . $this->_appRoot . $articles[$i]['id'] . '"><b>' . $articles[$i]['headline'] . '</b><br />By ' . $articles[$i]['author'] . ' on ' . $articles[$i]['date'] . '</a></li>';
        }
        return '<ul>' . $output . '</ul>';
    }

    public function article($articleData) {
        return '<a href="' . $this->_appRoot . '">Go back home</a><h2>' . $articleData['headline'] . '</h2><h3>By ' . $articleData['author'] . ' on ' . $articleData['date'] . '</h3>' . $articleData['body'];
    }

	public function articleLoading() {
        return '<a href="' . $this->_appRoot . '">Go back home</a><br /><br />Please wait&hellip;';
    }
}
```

In this file we've reimplemented the logic inside **/sources/templates.js**. Rather than copying this file directly you could choose to copy the Javascript file and manually port the code to PHP. If you do this make sure to be careful replacing ```+```'s with ```.```'s.

For example:-

```
output = output + '<li><a href="' + APP_ROOT + articles[i].id + '"><b>' + articles[i].headline + '</b><br />By ' + articles[i].author + ' on ' + articles[i].date + '</a></li>';
```

Becomes:-

```
$output .= '<li><a href="' . $this->_appRoot . $articles[$i]['id'] . '"><b>' . $articles[$i]['headline'] . '</b><br />By ' . $articles[$i]['author'] . ' on ' . $articles[$i]['date'] . '</a></li>';
```

## Some updates to the client side code

### /source/appcache.js

// TODO Explain the cookie hack solution.

// We want root URL of the application to be explicitly application cached so that the URL that users always comes from the application cache. (This means that is guaranteed to load reliably fast - otherwise on poor quality mobile data connections or captive portal wifi connections, for example, the app would load very slowly - or may never load).

// Make sure mention that we don't want to risk storing *content* in the application cache. Firstly it's a waste because we already store this inside local database.

// iOS6 home screen workaround for prefer offline on the home screen'd page.

### /source/application/applicationcontroller.js

The changes to the application controller are a little more complicated.

// TODO Explain the changes:-

- A new class variable:-
```var fastClick, iOSPrivateBrowsing, initialRenderOnServer;```

- A new routing function that is able to understand real URLs rather than hash tag URLs.

```
function route(page) {
    if (page) {
        page = page.replace(new RegExp('^' + APP_ROOT), '');
    } else {
        page = '';
    }
    if (page.length > 0) {
        if (parseInt(page, 10) > 0) {
            showArticle(parseInt(page, 10));
        } else {
            pageNotFound();
            page = APP_ROOT + 'error';
        }
    } else {
        showHome();
    }
    window.history.pushState(null, null, APP_ROOT + page);
}
````

Notice that we're also adding our code to hook into the History API here.

- New ```initialise``` and ```start``` methods.

```
function initialize(resources) {

    // Listen to the URL link clicks
    $(document).on('click', 'a', function (event) {
        event.stopPropagation();
        event.preventDefault();
        route(this.getAttribute('href'));
    });

    // Set up FastClick
    fastClick = new FastClick(document.body);

    // Initalise appcache if app not in private browsing mode
    if (!iOSPrivateBrowsing) {
        APP.appcache.start(iOSPrivateBrowsing);
    }

    // If we don't have resources, trigger a
    // synchronize but from then on stop because
    // this means the data in the dom has been freshly
    // loaded from the server.
    if (initialRenderOnServer) {
    	return APP.articlesController.synchronizeWithServer();
    }

    // Inject CSS Into the DOM
    $("head").append("<style>" + resources.css + "</style>");

    // Create app elements
    $("body").append(APP.templates.application());

    // Remove our loading splash screen
    $("#loading").remove();

    route();
}

// This is to our webapp what main() is to C, $(document).ready is to jQuery, etc
function start(resources, storeResources, contentAlreadyLoaded) {
	initialRenderOnServer = contentAlreadyLoaded;

    // Try to detect whether iOS private browsing mode is enabled
    try {
        localStorage.test = '';
        localStorage.removeItem('item');
    } catch (exception) {
        if (exception.code === 22) {
            iOSPrivateBrowsing = true;
        }
    }

    if (iOSPrivateBrowsing) {
        return APP.network.start(function networkSuccess() {
            APP.database = APP.network;
            initialize(resources);
        });
    }

    // As a bare minimum we need History API to
    // run the advanced features of this app
    if (!historyAPI()) return;

    window.addEventListener("popstate", function(e) {
        route(location.pathname);
    });

    // When indexedDB available, use it!
    APP.indexedDB.start(function indexedDBSuccess() {
        APP.database = APP.indexedDB;
        initialize(resources);

        // When indexedDB is not available, fallback to trying websql
    }, function indexedDBFailure() {
        APP.webSQL.start(function webSQLSuccess() {
            APP.database = APP.webSQL;
            initialize(resources);

        // When webSQL not available, fall back to using the network
        }, function webSQLFailure() {
            APP.network.start(function networkSuccess() {
                APP.database = APP.network;
                initialize(resources);
            });
        });
    });

    if (storeResources && window['localStorage']) {
        localStorage.resources = JSON.stringify(resources);
    }
}
```

- A new function that handles the app if it is starting up after being downloaded directly from the server (as opposed to from the app cache).

```
function startFromServer() {

    // As a bare minimum we need History API to
    // run the advanced features of this app
    if (!historyAPI()) return;
    $.ajax('api/resources/', {
        dataType: 'json',
        success: function (data) {
            start(data, true, true);
        }
    });
}
```

- We've borrowed a little code from the very excellent Modernizr - to detect whether we can *actually use* the HTML5 History API.

```
// Detection of history API, 'borrowed' from Modernizr
function historyAPI() {
    var ua = navigator.userAgent;

    // We only want Android 2, stock browser, and not Chrome which identifies
    // itself as 'Mobile Safari' as well
    if (ua.indexOf('Android 2') !== -1 &&
        ua.indexOf('Mobile Safari') !== -1 &&
            ua.indexOf('Chrome') === -1) {
        return false;
    }

    // Return the regular check
    if (window.history && 'pushState' in history) {
        return true;
    }
}
```

- And finally, updated our public API 


### /source/templates.js

```
APP.templates = (function () {
    'use strict';

    function application() {
        return '<div id="window"><div id="header"><h1>FT Tech Blog</h1></div><div id="body"></div></div>';
    }

    function home() {
        return '<button id="refreshButton">Refresh the news!</button><div id="headlines"></div></div>';

    }

    function articleList(articles) {
        var i, l, output = '';

        if (!articles.length) {
            return '<p><i>No articles have been found, maybe you haven\'t <b>refreshed the news</b>?</i></p>';
        }
        for (i = 0, l = articles.length; i < l; i = i + 1) {
            output = output + '<li><a href="' + APP_ROOT + articles[i].id + '"><b>' + articles[i].headline + '</b><br />By ' + articles[i].author + ' on ' + articles[i].date + '</a></li>';
        }
        return '<ul>' + output + '</ul>';
    }

    function article(articleData) {

        // If the data is not in the right form, redirect to an error
        if (!articleData) {
            APP.applicationController.route(APP_ROOT + 'error');
            return;
        }
        return '<a href="' + APP_ROOT + '">Go back home</a><h2>' + articleData.headline + '</h2><h3>By ' + articleData.author + ' on ' + articleData.date + '</h3>' + articleData.body;
    }

    function articleLoading() {
        return '<a href="' + APP_ROOT  + '">Go back home</a><br /><br />Please wait&hellip;';
    }

    return {
        application: application,
        home: home,
        articleList: articleList,
        article: article,
        articleLoading: articleLoading
    };
}());
```

The majority of the changes here are changing the URLs inside the each of the ```<a href="">```'s from using a hashtag URL to using a real URL (e.g. yourapp.com/#5 becomes yourapp/5).

// TODO - Update this file so that error handling isn't here.
// Captured an issue to handle error routing better - #6 (I think).

## Wrapping up

In this tutorial we've made our demo app (that's actually a website) work just as well as a website as it does as an app. But in doing so we've created a bit of a monster.

If we look back again at the PHP server side code and compare it to the client side Javascript there is a huge amount of duplicated logic. With the exception of the client side specific code (such as the local database and app cache logic) the contents of the **/sources** folder, used for client side Javascript is almost exactly the same at the **/server** folder, used for PHP.

Having to maintain two sets of files with identical logic wastes a time - as every feature or bug fix has to be implemented twice.

The codebase would be a lot neater and more manageable if we were able to use *just* write Javascript and then run that Javascript code on **both the server and client**.

In 2009 Ryan Dahl created [NodeJS][Z1], which allows developers to do just that. In the next tutorial we will bid farewell to the web technologies of the past (PHP, .htaccess, jQuery) and rebuild the app with the latest tools and latest techniques.

Finally, if you think you’d like to work on this sort of thing and live (or would like to live) in London, [we’re hiring][Z2]!

By [Matt Andrews][Z3] - @andrewsmatt on [Twitter][Z4] and [Weibo][Z5]

[Z1]:http://nodejs.org
[Z2]:http://labs.ft.com/jobs
[Z3]:http://mattandre.ws
[Z4]:http://twitter.com/andrewsmatt
[Z5]:http://weibo.com/andrewsmatt