// html cache workers
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(
        URL_OPT_DIR + 'quiqqer/cache/bin/sw/html/html.php',
        {scope: '/'}
    ).then(function () {
        "use strict";
        console.log(window.QUIQQER_CACHE_ID);

        if (navigator.serviceWorker.controller &&
            typeof window.QUIQQER_CACHE_ID !== 'undefined') {

            navigator.serviceWorker.controller.postMessage({
                'type': 'CACHE_HASH_ID',
                'url' : window.location.pathname.toString(),
                'id'  : '1db4c16510'
            });
        }

        navigator.serviceWorker.addEventListener('message', function (event) {
            switch (event.data.command) {
                case 'getStorage':
                    console.log('getStorage');
                    require(['qui/QUI'], function (QUI) {
                        event.ports[0].postMessage(
                            QUI.Storage.get(event.data.key)
                        );
                    });
                    break;

                case 'setStorage':
                    require(['qui/QUI'], function (QUI) {
                        console.log('store');
                        console.log(event.data.key, event.data.value);
                        QUI.Storage.set(event.data.key, event.data.value);
                    });
                    break;
            }
        });
    }).catch(function (e) {
        "use strict";
        // Fail :(
        console.error(e);
    });
}
