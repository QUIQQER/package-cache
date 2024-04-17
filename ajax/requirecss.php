<?php

/**
 * This file contains package_quiqqer_cache_ajax_requirecss
 */

use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the optimized css
 *
 * @param string $cssfile - Wanted CSS File
 * @param string $requireConfig - JSON array, require js config
 *
 * @return string
 */
function package_quiqqer_cache_ajax_requirecss($cssfile, string $requireConfig): string
{
    try {
        $minified = QUI\Cache\Optimizer::optimizeCSS($cssfile);

        echo $minified;
        exit;
    } catch (QUI\Exception) {
        $Response = QUI::getGlobalResponse();
        $Response->setStatusCode(
            Response::HTTP_NOT_FOUND
        );

        echo '';
        exit;
    }
}

QUI::$Ajax->register(
    'package_quiqqer_cache_ajax_requirecss',
    ['cssfile', 'requireConfig']
);
