/**
 * HTML Cache with a Service Worker
 */
const version = 'v1::';

/**
 * Is the reuqets a html get request
 *
 * @param request
 * @returns {boolean}
 */
function isHTMLRequest (request) {
    "use strict";

    if (request.method !== 'GET') {
        return false;
    }

    let parts = request.url.split('/'),
        last  = parts[parts.length - 1];

    if (last.indexOf('.html') !== -1) {
        return true;
    }

    return last.indexOf('.') === -1;
}

/**
 * Fetch and cache a request
 *
 * @param request
 * @param cacheName
 */
function fetchAndCache (request, cacheName) {
    "use strict";

    return fetch(request).then(function (response) {
        return caches.open(cacheName)
            .then(cache => cache.put(request, response.clone()))
            .then(() => response);
    });
}

/**
 *
 * @param key
 * @param value
 * @returns {Promise}
 */
function setStorage (key, value) {
    "use strict";

    return new Promise(function (resolve, reject) {
        clients.matchAll().then(function (clients) {
            if (!clients.length) {
                return reject();
            }

            clients[0].postMessage({
                command: 'setStorage',
                key    : key,
                value  : value
            });
        });
    });
}

/**
 * return the storage value from the client
 *
 * @param key
 * @returns {Promise}
 */
function getStorage (key) {
    "use strict";

    return new Promise(function (resolve, reject) {
        let Chan = new MessageChannel();

        Chan.port1.onmessage = function (event) {
            if (!event.data || "error" in event.data) {
                return resolve(false);
            }

            resolve(event.data);
        };

        clients.matchAll().then(function (clients) {
            if (!clients.length) {
                return reject();
            }

            clients[0].postMessage({
                command: 'getStorage',
                key    : key
            }, [Chan.port2]);
        });
    });
}

let EXPECTED_CACHES = {};

self.addEventListener('install', function (event) {
    "use strict";

    // console.log('SW install');
    // Perform install step:  loading each required file into cache
    // console.log(REQUIRED_FILES);

    console.log('HTML Cache WORKER: install');

    //
    // event.waitUntil(
    //     caches.open(CACHE_NAME).then(function (cache) {
    //         // Add all offline dependencies to the cache
    //         return cache.addAll(REQUIRED_FILES);
    //     }).then(function () {
    //         // At this point everything has been cached
    //         return self.skipWaiting();
    //     }).catch(function (e) {
    //         console.error(e);
    //     })
    // );
});

self.addEventListener('fetch', function (event) {
    "use strict";

    const request = event.request;

    if (isHTMLRequest(request)) {
        let parts     = request.url.split('/'),
            cacheName = '/' + parts[parts.length - 1];

        console.debug('isHTMLRequest');
        console.debug(cacheName);
        console.debug(EXPECTED_CACHES);

        if (cacheName in EXPECTED_CACHES) {
            // check if new version exists

        }

        return caches.open(cacheName)
            .then(function (cache) {
                return cache.match(request.url);
            })
            .then(function (response) {
                if (response) {
                    return response;
                }
                return fetchAndCache(request, cacheName);
            });
    }
});

self.addEventListener('activate', function (event) {
    "use strict";

    console.log('HTML Cache WORKER: activate');

    getStorage('EXPECTED_CACHES').then(function (result) {
        if (result) {
            EXPECTED_CACHES = result;
        }
    }, function () {
    });

    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return !key.startsWith(version);
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            console.log('WORKER: activate completed.');
        })
    );
});

self.addEventListener('message', function (event) {
    "use strict";

    if ("type" in event.data) {
        switch (event.data.type) {
            case 'CACHE_HASH_ID':
                getStorage('EXPECTED_CACHES').then(function (result) {
                    if (result) {
                        EXPECTED_CACHES = result;
                    }
                }, function () {
                }).then(function () {
                    let currentCacheId  = event.data.id,
                        currentCacheUrl = event.data.url,
                        oldId           = false;

                    if (!(currentCacheUrl in EXPECTED_CACHES)) {
                        EXPECTED_CACHES[currentCacheUrl] = currentCacheId;
                        return;
                    }

                    if (currentCacheUrl in EXPECTED_CACHES) {
                        oldId = EXPECTED_CACHES[currentCacheUrl];
                    }

                    // invalidate cache
                    if (oldId && oldId != currentCacheId) {
                        delete EXPECTED_CACHES[currentCacheUrl];

                        setStorage('EXPECTED_CACHES', EXPECTED_CACHES);
                    }
                });

                break;
        }
    }
});
