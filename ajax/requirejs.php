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
function package_quiqqer_cache_ajax_requirejs($packages, $requireConfig)
{
    $packages      = json_decode($packages, true);
    $requireConfig = json_decode($requireConfig, true);

    try
    {
        return QUI\Cache\AMDOptimizer::optimize($packages, $requireConfig);

    } catch (QUI\Exception $Exception) {
        return '';
    }
}

QUI::$Ajax->register(
    'package_quiqqer_cache_ajax_requirejs',
    array('packages', 'requireConfig')
);
