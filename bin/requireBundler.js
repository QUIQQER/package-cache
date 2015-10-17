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
    var Storage = window.localStorage || null;

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

        if (!Storage) {
            console.warn('No locale storage');
            return oldLoad.apply(requirejs, arguments);
        }

        if (url.match('/packages/quiqqer/cache/bin/css-cache')) {
            return oldLoad.apply(requirejs, arguments);
        }

        if (url.match('/packages/quiqqer/cache/bin/text-cache')) {
            return oldLoad.apply(requirejs, arguments);
        }

        // cache
        try {
            var storage = Storage.getItem(url);

            if (storage) {
                console.info(url);
                eval.call(window, storage);

                context.completeLoad(moduleName);
                return;
            }
        } catch (e) {
            //return (useImportLoad ? importLoad : linkLoad)(req.toUrl(cssId + '.css'), load);
        }

        return new Request({
            method   : 'get',
            url      : url,
            onSuccess: function (responseText) {

                // hmm :-/ no better way?
                try {
                    eval.call(responseText);
                } catch (e) {
                    console.error(e, moduleName, url, responseText);
                }

                try {
                    Storage.setItem(url, responseText);
                } catch (e) {
                    // maybe QUOTA_REACHED
                    console.error(e, moduleName, url);

                    Storage.clear();
                }

                context.completeLoad(moduleName);
            }
        }).send();
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
