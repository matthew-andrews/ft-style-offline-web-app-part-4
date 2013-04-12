# Putting the web back into web app

This is part 4 of a [tutorial series][1] on how to make an FT style offline web app.

We [left off last time][2] with an app that delivers offline news on most modern browsers - and degrades gracefully on older browsers. But if we look back to our list of *ideas for further development* in the first tutorial we still have work to do.

## Requirements for the tutorial - Part 4

- It should work even if the user's Javascript is disabled.
- It should be crawl-able by search engines.
- The first time a user uses the app the first load should be rendered on the server side.
- We should use the [History API][3] instead of hash tag URLs so that the URL in the user's browser address page always matches page that they are viewing.

These might not sound like groundbreaking features - websites have been doing this for years - but as usual the *appcache* gets in the way.

In this version we will use a .htaccess file to use 
[Apache's mod_rewrite][4]. Mod rewrite is very widely documented and has
been around for decades so I will use it without explanation. If you wish to use a different type of web server you may need to use a different URL rewriting technology.

As always, the [full code is up on GitHub][5].

[1]:http://labs.ft.com/category/tutorial/
[2]:http://net.tutsplus.com/tutorials/other/a-deeper-look-at-mod_rewrite-for-apache/
[3]:http://diveintohtml5.info/history.html
[4]:http://labs.ft.com/2012/11/using-an-iframe-to-stop-app-cache-storing-masters/
[5]:https://github.com/matthew-andrews/ft-style-offline-web-app-part-4

### The new and changed files required in the tutorial:-

<table>
	<tr>
		<td>/.htaccess</td>
		<td></td>
	</tr>
	<tr>
		<td>/api/resources/javascript.php</td>
		<td></td>
	</tr>
	<tr>
		<td>/index.php</td>
		<td></td>
	</tr>
	<tr>
		<td>/server/application/applicationcontroller.php</td>
		<td></td>
	</tr>
	<tr>
		<td>/server/articles/article.php</td>
		<td></td>
	</tr>
	<tr>
		<td>/server/articles/articlescontroller.php</td>
		<td></td>
	</tr>
	<tr>
		<td>/server/templates.php</td>
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

### /.htaccess
```
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^([0-9])+ index.php [L]
</IfModule>
```

TODO: Explain what the .htaccess file is doing.

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

