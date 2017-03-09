/**
 * @module package/quiqqer/cache/bin/Storage
 *
 * Main Cache Storage
 *
 * @require URL_OPT_DIR + bin/dexie/dist/dexie.min.js
 */
define('package/quiqqer/cache/bin/Storage', [

    URL_OPT_DIR + 'bin/dexie/dist/dexie.min.js'

], function (Dexie) {
    "use strict";

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