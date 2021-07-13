/**
 * require js ajax bundler - local storage cache
 */

/* @global requirejs */
(function () {
    "use strict";

    if ("QUIQQER_CACHE_CACHESETTING" in window && QUIQQER_CACHE_CACHESETTING === 0) {
        return;
    }

    // ie11 workaround
    // no bundler for IE11
    // old browser workaround
    if (typeof window.Promise === 'undefined' || typeof localStorage !== 'undefined') {
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

        if (url.match('/packages/quiqqer/cache/bin/Storage.js')) {
            return oldLoad.apply(requirejs, arguments);
        }

        if (url.match('/packages/bin/quiqqer-asset/dexie/dexie/dist/dexie.min.js')) {
            return oldLoad.apply(requirejs, arguments);
        }

        // @todo
        if (url.match('skel.min.js') || url.match('skel-layers.min.js') || url.match('/packages/quiqqer/template-helios/bin/js/init.js')) {
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


                           if (moduleName === 'SecondLevelDomains') {
                               try {
                                   // bug fix, because URIjs defines empty define module definition
                                   // requirejs blows up
                                   eval.call(
                                       window,
                                       content.replace('define(factory)', '')
                                   );

                                   context.completeLoad(moduleName);
                               } catch (e) {
                               }
                               return;
                           }

                           onSuccess(content);
                       }).catch(function (err) {
                           console.error(url);
                           console.error(err);
                           onError(arguments);
                       });
                   });
        }, onError);
    };

}());
