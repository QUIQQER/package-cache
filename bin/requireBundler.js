/**
 * require js ajax bundler - local storage cache
 */
(function () {
    "use strict";

    var oldLoad = requirejs.load;

    // amd locale storage
    requirejs.load = function (context, moduleName, url) {

        if (typeof QUI === 'undefined' ||
            typeof QUI.Storage === 'undefined') {

            return oldLoad.apply(requirejs, arguments);
        }

        var storage = QUI.Storage.get(url);

        if (storage) {
            new Element('script', {
                html                : storage,
                'data-requiremodule': moduleName
            }).inject(document.head);

            context.completeLoad(moduleName);
            return;
        }

        new Request({
            method   : 'get',
            url      : url,
            onSuccess: function (responseText) {

                new Element('script', {
                    html                : responseText,
                    'data-requiremodule': moduleName
                }).inject(document.head);


                context.completeLoad(moduleName);

                QUI.Storage.set(url, responseText);
            }
        }).send();
    };
    //
    //
    //requirejs.onResourceLoad = function (context, map, depArray) {
    //
    //    if (map.prefix && map.name.match('.css')) {
    //
    //        console.log(map);
    //
    //    }
    //};


    //
    //// overwrite require with ajax combine calls
    //window.require = function (needle, callback) {
    //
    //    console.info(needle);
    //
    //    var phpNeedle = [];
    //    var cssNeedle = [];
    //    var url       = '';
    //
    //    for (var i = 0, len = needle.length; i < len; i++) {
    //        url = needle[i];
    //
    //        // is loaded?
    //        if (require.specified(url)) {
    //            continue;
    //        }
    //
    //        if (!url.match('!')) {
    //            phpNeedle.push(url);
    //        }
    //
    //        if (url.match('css!')) {
    //            cssNeedle.push(url);
    //        }
    //    }
    //
    //    //console.log(cssNeedle);
    //    console.warn(phpNeedle);
    //
    //    if (!phpNeedle.length) {
    //        requirejs(needle, callback);
    //        return;
    //    }
    //
    //    requirejs(needle, callback);
    //
    //    //requirejs(['Ajax'], function (Ajax) {
    //    //
    //    //    Ajax.get('package_quiqqer_cache_ajax_requirejs', function (result) {
    //    //
    //    //        if (!result) {
    //    //            console.warn('Result is empty for', needle);
    //    //            requirejs(needle, callback);
    //    //            return;
    //    //        }
    //    //
    //    //        new Element('script', {
    //    //            html : result
    //    //        }).inject(document.head);
    //    //
    //    //
    //    //        requirejs(needle, callback);
    //    //
    //    //    }, {
    //    //        'package'    : 'quiqqer/cache',
    //    //        packages     : JSON.encode(needle),
    //    //        requireConfig: JSON.encode(requirejs.s.contexts._.config)
    //    //    });
    //    //
    //    //});
    //};
    //
    //// clone requirejs functions
    //for (var f in requirejs) {
    //    window.require[f] = requirejs[f];
    //}

}());
