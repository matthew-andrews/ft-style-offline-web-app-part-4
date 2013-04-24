APP.applicationController = (function () {
    'use strict';

    var fastClick,
        iOSPrivateBrowsing,
        initialRenderOnServer;

    function offlineWarning() {
        alert("This feature is only available online.");
    }

    function pageNotFound() {
        alert("That page you were looking for cannot be found.");
    }

    function showHome() {
        $("#body").html(APP.templates.home());

        // Load up the last cached copy of the news
        APP.articlesController.showArticleList();

        $('#refreshButton').click(function () {

            // If the user is offline, don't bother trying to synchronize
            if (navigator && navigator.onLine === false) {
                offlineWarning();
            } else {
                APP.articlesController.synchronizeWithServer(offlineWarning);
            }
        });
    }

    function showArticle(id) {
        $("#body").html(APP.templates.articleLoading());
        APP.articlesController.showArticle(id);
    }

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
            localStorage.removeItem('test');
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

    return {
        start: start,
        startFromServer: startFromServer,
        route: route
    };
}());
