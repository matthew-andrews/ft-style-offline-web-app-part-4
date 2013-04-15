# Putting the web back into web app

This is part 4 of a [tutorial series][A1] on how to make an FT style offline web app.

We [left off last time][A2] with an app that delivers offline news on most modern browsers - and degrades gracefully on older browsers. But if we look back to our list of **ideas for further development** in the [first tutorial][A3] we still have work to do to make it work just as well as a website as it does as an app.

[A1]:http://labs.ft.com/category/tutorial/
[A2]:http://net.tutsplus.com/tutorials/other/a-deeper-look-at-mod_rewrite-for-apache/
[A3]:http://labs.ft.com/2012/08/basic-offline-html5-web-app/

## Requirements for the tutorial - Part 4

- It should work even if the user's Javascript is disabled.
- It should be crawl-able by search engines.
- The first time a user uses the app the first load should be rendered on the server side to improve the speed of that first load.
- We should use the [History API][B1] instead of hash tag URLs so that the URL in the user's browser address page always matches the page that they are viewing.

These might not sound like groundbreaking features - websites have been doing the first three since forever - but as usual the *HTML5 application cache* gets in the way.

In this tutorial we will use [Apache's mod_rewrite][B2] via an .htaccess file in order to map all the article URLs (which are of the form yourapp.com/[integer]) to **/index.php**.

Mod rewrite is already very widely documented so I will use it without much explanation. If you wish to use a different type of web server you may need to use a different URL rewriting technology.

As always, the [full code is up on GitHub][B3].

[B1]:http://diveintohtml5.info/history.html
[B2]:http://httpd.apache.org/docs/current/mod/mod_rewrite.html
[B3]:https://github.com/matthew-andrews/ft-style-offline-web-app-part-4

## On and off - not always a switch

Product managers often come up with feature requests with descriptions along the lines of: *when the app is online do this, when it's offline do that*. Unfortunately, it's rarely that black and white.

Connections can be weak and very slow, they can be flaky - disconnecting frequently. Devices can even be tricked into thinking they're online when they're not - they could be behind a captive portal in a coffee shop or hotel; or the device could have a perfect connection to a wifi router that isn't connected to anything else. Requests to your website may be being blocked or somehow mangled by a government or corporate proxy. Or, of course, the device could actually be completely offline.

Rather than thinking in terms of offline and online, the way to deliver the best possible user experience during that initial app boot phase up is to aim to deliver an app startup time that is consistent and unaffected by the connection type or speed. In order to achieve this we're going to need some more app cache hacking.

## More app cache hacking

The only way you can have a consistent start up time in the face of a wild west of internet connection possibilities is to ensure your web app will prefer, **whenever possible** load from the device's local application cache. To do this you have to ensure that the page which your app starts on is **explicitly cached** in the application cache manifest.

At FT Labs we refer to this kind of application cache behaviour as *prefer offline*, where the application cache - if populated - will *always* be the preferred source for the app to load itself from, rather than the network. This means that the amount of time the demo app will take to start up will always be the same - no matter what kind of connection the user's device has to the internet.

We can achieve this either by listing the root of the app explicitly in the `CACHE` section of the app cache manifest. Or, as we do in our demo app, listing it as the 2nd part of a fallback within the `FALLBACK` section.

(In an ideal world we would like for the application cache to be configured so that every request (even ones not listed in the application cache) are *always* prefer offline unless we say otherwise but that is not currently possible with the current spec or any browser implementation :-(. Given the home page is the likeliest entry point to your application, we prioritise that.)

This means the web server must serve the root of your application (in the FT's case that's `http://app.ft.com/`) with the bootstrap code - explained in the [first tutorial][C1].

However, in order to achieve the server side render of the HTML for the first time the user visits the app (which makes for a much faster initial app start) that same application root URL sometimes also needs, in the case of our demo app, to return the current latest blog posts.

To summarise:

- When the app cache is doing its thing yourapp.com/ must be the bootstrap.
- Otherwise yourapp.com/ should be the rendered HTML content.

Given we have no Javascript control over either these requests (the former is made by the application cache's update mechanism, the latter by the user typing the URL into their browser / clicking a link from another site) we only have one solution.

[C1]:labs.ft.com/2012/08/basic-offline-html5-web-app/

### The solution

We already have quite precise control over how and when the application cache is loaded through the iframe (implemented in the previous tutorial) so solving this problem is actually quite easy.

Before adding the iframe, simply set a cookie and when we receive a notification from the Javascript within that iframe (which is listening to the application cache events) that the application cache has finished updating we unset the cookie.

Then on the server side, within **/index.php**, if that cookie is set return the bootstrap otherwise return the latest blog posts.

This is, of course, a monumental hack. But with the app cache as it is today it's the best we can do.

## Getting Started

Start by cloning ([or downloading][E1]) the GitHub repository from Part 3.

`git clone git://github.com/matthew-andrews/ft-style-offline-web-app-part-3.git`

[E1]:https://github.com/matthew-andrews/ft-style-offline-web-app-part-3

### The new and changed files required in the tutorial:-

<table>
	<tr>
		<td><strong>/.htaccess</strong></td>
	</tr>
	<tr>
		<td><strong>/api/resources/javascript.php</strong></td>
	</tr>
	<tr>
		<td>/index.php</td>
	</tr>
	<tr>
		<td><strong>/server/application/applicationcontroller.php</strong></td>
	</tr>
	<tr>
		<td><strong>/server/articles/article.php</strong></td>
	</tr>
	<tr>
		<td><strong>/server/articles/articlescontroller.php</strong></td>
	</tr>
	<tr>
		<td><strong>/server/templates.php</strong></td>
	</tr>
	<tr>
		<td>/source/appcache.js</td>
	</tr>
	<tr>
		<td>/source/application/applicationcontroller.js</td>
	</tr>
	<tr>
		<td>/source/templates.js</td>
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

As I explained above in the **More app cache hacking** section this file needs to be able to return the latest blog items for ordinary requests and it should return the bootstrap we created in [Tutorial 1][G1] when requested during an application cache update.

We detect whether the cookie is set at the top of the file via the PHP `isset` function:-

`$appcacheUpdate = isset($_COOKIE['appcacheUpdate']);`

The code we return if we're not doing an app cache update can be a normal web page that follows all the best practises, such as:

- including the Javascript through a normal script tag that points to an external resource (we'll create this file in just a minute):-

`<script type="text/javascript" src="<?php $appRoot; ?>api/resources/javascript.php"></script>`

- and including the CSS again by pulling it in in the normal way from an external URL:-

`<link href="<?php echo $appRoot; ?>css/global.css" media="all" rel="stylesheet" type="text/css" />`

[G1]:http://labs.ft.com/2012/08/basic-offline-html5-web-app/

### /api/resources/javascript.php

```
<?php
$js = '';
$js = $js . file_get_contents('../../libraries/client/fastclick.js');
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

This is almost the same logic as **/api/resources/index.php**, except that it only returns Javascript. ( **/api/resources/index.php** returns JSON encoded object with two keys - one, `js` containing the demo web app's Javascript and another, `css` containing its css).

## Re-implement the demo app in PHP

The next step is to take the code we've written in Javascript and make it so that it will run on our server. The easiest way to do this (where easiest for the purposes of this tutorial means not adding dependencies on any more technologies) is to rewrite it in PHP.

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

To keep things as simple as possible I've copied the same structure as the Javascript file - giving the files, classes and their methods all the same names wherever possible.

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

Much like **/sources/articles/article.js** the role of this class is to just return article data. If the `get` function is passed a parameter (and ID of the article) it will return a single article, otherwise it will return all the articles in the RSS feed.

To simplify the explanations in this tutorial I've not bothered to combine some of the logic in this file with the logic in **/api/articles/index.php** - even though it is almost exactly the same. The only difference is **/api/article/index.php** outputs a json encoded object - used by the Javascript in the demo app, whereas this file will be used by other PHP files to get a PHP associative array containing article data.

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

This code should look very similar to the corresponding Javascript file in **/sources/articles/articlescontroller.js**.

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

In this file we've reimplemented the logic inside **/sources/templates.js**. Rather than copying this file directly you could choose to copy the Javascript file and manually port the code to PHP. If you do this make sure to be careful replacing `+`'s with `.`'s.

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

As we discussed above with the **More hacking the app cache** section, we have two changes to make inside **/source/appcache.js**.

The first change is to set the cookie inside the `innerLoad` function:-

```
function innerLoad() {

	// 5 minutes in the future
	var cookieExpires = new Date(new Date().getTime() + 60 * 5 * 1000);
	document.cookie = "appcacheUpdate=1;expires=" + cookieExpires.toGMTString();
	var iframe = document.createElement('IFRAME');
	iframe.setAttribute('style', 'width:0px; height:0px; visibility:hidden; position:absolute; border:none');
	iframe.src = APP_ROOT + 'manifest.html';
	iframe.id = 'appcacheloader';
	document.body.appendChild(iframe);
}
```

And the second change is to unset that same cookie once we know the appcache update process is complete:-

```
function logEvent(evtcode, hasChecked) {
	var s = statuses[evtcode], loaderEl, cookieExpires;
	if (hasChecked || s === 'timeout') {
		if (s === 'uncached' || s === 'idle' || s === 'obsolete' || s === 'timeout' || s === 'updateready') {
			loaderEl = document.getElementById('appcacheloader');
			loaderEl.parentNode.removeChild(loaderEl);

			// Remove appcacheUpdate cookie
			cookieExpires = new Date(new Date().getTime() - 60 * 5 * 1000);
			document.cookie = "appcacheUpdate=;expires=" + cookieExpires.toGMTString();
		}
	}
}
```

### /source/application/applicationcontroller.js

The changes to the application controller are a little more complicated. We want to make some changes to existing code, and add a few new methods. I'm just going to post the changes in this tutorial - for the full code please [view the file on the GitHub project][Q1].

[Q1]:https://github.com/matthew-andrews/ft-style-offline-web-app-part-4/blob/tutorial/source/application/applicationcontroller.js

We need to make the following changes to the applicationcontroller.js file:

- Add a new new class variable `initialRenderOnServer`:-
`var fastClick, iOSPrivateBrowsing, initialRenderOnServer;`
- Replace the `route` function with one that is able to understand real URLs instead of hash tag URLs.

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

Notice that we're also adding our code to hook into the **HTML5 History API** here.

- Replace the `initialise` and `start` methods with ones that are able to boot the app either from our locally cached bootstrap (discussed in [Tutorial 1][R1]) or from the network.

[R1]:labs.ft.com/2012/08/basic-offline-html5-web-app/

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
        APP.appcache.start();
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

- We need to add a new function that handles the app if it is starting up after being downloaded directly from the server (as opposed to from the app cache).

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

- And finally, we need to update our public API to expose the `route` and newly added `startFromServer` methods.

```
return {
    start: start,
    startFromServer: startFromServer,
    route: route
};
```



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

The majority of the changes here are changing the URLs inside the each of the `<a href="">`'s from using a hashtag URL to using a real URL (e.g. yourapp.com/#5 becomes yourapp/5).

There's also a small change to the way the error handler works - previously it just redirected the user to the error page by calling `window.location = '#error';` but now since we aren't using hashtag URLs we have to call the route function instead.

## Wrapping up

In this tutorial we've made our demo web app work just as well as a website as it does as an app. But in doing so we've created a bit of a monster.

If we look back again at the PHP server side code and compare it to the client side Javascript there is a huge amount of duplicated logic. With the exception of the client side specific code (such as the local database and app cache logic) the contents of the **/sources** folder, used for client side Javascript is almost exactly the same at the **/server** folder, used for PHP.

Having to maintain two sets of files with identical logic wastes time as every feature or bug fix has to be implemented twice.

The codebase would be a lot neater and more manageable if we were able to *just* write the Javascript versions and then run that Javascript code on **both the server and client**.

In 2009 Ryan Dahl created [NodeJS][Z1], which allows developers to do just that. In the next tutorial we will bid farewell to the web technologies of the past (PHP and Apache) and rebuild the app with the latest tools and techniques (Node, NPM, Grunt, BusterJS and more…).

Finally, if you think you’d like to work on this sort of thing and live (or would like to live) in London, [we’re hiring][Z2]!

By [Matt Andrews][Z3] - @andrewsmatt on [Twitter][Z4] and [Weibo][Z5]

[Z1]:http://nodejs.org
[Z2]:http://labs.ft.com/jobs
[Z3]:http://mattandre.ws
[Z4]:http://twitter.com/andrewsmatt
[Z5]:http://weibo.com/andrewsmatt