/**
 * require js ajax bundler - local storage cache
 */

/* @global requirejs */
(function () {
    "use strict";

    if ("QUIQQER_CACHE_CACHESETTING" in window && QUIQQER_CACHE_CACHESETTING === 0) {
        return;
    }

    var oldLoad = requirejs.load;

    requirejs.config({
        map: {
            '*': {
                'css' : URL_OPT_DIR + 'quiqqer/cache/bin/css-cache.js',
                'text': URL_OPT_DIR + 'quiqqer/cache/bin/text-cache.js'
            }
        }
    });

    // amd locale storage
    requirejs.load = function (context, moduleName, url) {
        if (url.match('/packages/quiqqer/cache/bin/css-cache')) {
            return oldLoad.apply(requirejs, arguments);
        }

        if (url.match('/packages/quiqqer/cache/bin/text-cache')) {
            return oldLoad.apply(requirejs, arguments);
        }

        if (url.match('/packages//quiqqer/cache/bin/Storage.js')) {
            return oldLoad.apply(requirejs, arguments);
        }

        if (url.match('/packages/bin/dexie/dist/dexie.min.js')) {
            return oldLoad.apply(requirejs, arguments);
        }

        var loadWithRequest = function (url) {
            return new Promise(function (resolve, reject) {
                new Request({
                    method   : 'get',
                    url      : url,
                    onSuccess: resolve,
                    onFailure: reject
                }).send();
            });
        };

        var onError = function () {
            console.error(arguments);
            oldLoad.apply(requirejs, arguments);
        };

        var onSuccess = function (content) {
            eval.call(window, content);
            context.completeLoad(moduleName);
        };

        // load storage
        requirejs(['package/quiqqer/cache/bin/Storage'], function (Storage) {
            Storage.getItem(url)
                   .then(onSuccess)
                   .catch(function () {
                       // load via request
                       loadWithRequest(url).then(function (content) {
                           Storage.setItem(url, content).catch(function () {
                           });

                           onSuccess(content);
                       }).catch(onError);
                   });
        }, onError);
    };

    // debug
    //
    //requirejs.onResourceLoad = function (context, map, depArray) {
    //
    //    if (map.prefix && map.name.match('.css')) {
    //
    //        console.log(map);
    //
    //    }
    //};

}());
