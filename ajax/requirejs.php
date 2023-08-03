<?php

/**
 * This file contains package_quiqqer_cache_ajax_requirejs
 */

/**
 * Returns the combined packages
 *
 * @param string $packages - JSON array - wanted modules / packages
 * @param string $requireConfig - JSON array, require js config
 *
 * @return string
 */
function package_quiqqer_cache_ajax_requirejs($packages, $requireConfig): string
{
    $packages = json_decode($packages, true);
    $requireConfig = json_decode($requireConfig, true);

    try {
        return QUI\Cache\Optimizer::optimizeAMD(
            $packages,
            $requireConfig
        );
    } catch (QUI\Exception $Exception) {
        return '';
    }
}

QUI::$Ajax->register(
    'package_quiqqer_cache_ajax_requirejs',
    ['packages', 'requireConfig']
);
