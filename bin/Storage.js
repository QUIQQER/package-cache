/**
 * @module package/quiqqer/cache/bin/Storage
 *
 * Main Cache Storage
 *
 * @require URL_OPT_DIR + bin/dexie/dist/dexie.min.js
 */

var needled;

if (typeof window.QUIQQER_JS_IS_CACHED === 'undefined' || !window.QUIQQER_JS_IS_CACHED) {
    needled = [
        'qui/lib/polyfills/Promise',
        URL_OPT_DIR + 'bin/dexie/dist/dexie.min.js'
    ];
} else {
    needled = ['placeholder'];

    if (typeof window.Promise === 'undefined') {
        needled.push('qui/lib/polyfills/Promise');
    }

    define('placeholder', function () {
    });
}

define('package/quiqqer/cache/bin/Storage', needled, function (Dexie) {
    "use strict";

    if (typeof Dexie === 'undefined') {
        Dexie = window.Dexie;
    }

    var DataBase   = new Dexie('quiqqer-storage'),
        LastUpdate = QUIQQER.lu;

    // Define a schema
    DataBase.version(1).stores({
        cache: 'name, data'
    });

    var setLastUpdate = function () {
        return DataBase.cache.put({
            name: '__TIME__',
            data: LastUpdate
        });
    };

    // timestamp
    DataBase.cache.get('__TIME__').then(function (result) {
        if (typeof result === 'undefined') {
            return setLastUpdate();
        }

        // clear the cache, because we have new versions
        if (result.data != LastUpdate) {
            DataBase.cache.clear();
        }
    }).catch(setLastUpdate);

    return {
        /**
         * Return
         * @param key
         */
        getItem: function (key) {
            return DataBase.cache.get(key).then(function (result) {
                return result.data;
            });
        },

        /**
         * Set data to a key
         * @param {String} key
         * @param {String} value
         * @returns {Promise}
         */
        setItem: function (key, value) {
            return DataBase.cache.put({
                name: key,
                data: value
            });
        }
    };
});