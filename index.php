<?php
// Detect the app root (taken from api/resources/index.php)
$appRoot = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
$appRoot = '/' . ltrim($appRoot . '/', '/');
$appcacheUpdate = isset($_COOKIE['appcacheUpdate']);
ini_set('display_errors', 'On');
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
