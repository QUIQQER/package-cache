<?php

/**
 * This file contains package_quiqqer_cache_ajax_backend_clearCache
 */

/**
 * Clears the website cache
 */
function package_quiqqer_cache_ajax_backend_clearCache(): void
{
    QUI\Cache\EventCoordinator::clearCache();

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get('quiqqer/cache', 'cache.clear.success')
    );
}

QUI::$Ajax->register(
    'package_quiqqer_cache_ajax_backend_clearCache',
    [],
    'Permission::checkAdminUser'
);
