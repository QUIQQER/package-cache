<?php

/**
 * This file contains package_quiqqer_cache_ajax_requirecss
 */

/**
 * Returns the optimized css
 *
 * @param string $cssfile - Wanted CSS File
 * @param string $requireConfig - JSON array, require js config
 *
 * @return string
 */
function package_quiqqer_cache_ajax_requirecss($cssfile, $requireConfig): string
{
    try {
        $minified = QUI\Cache\Optimizer::optimizeCSS($cssfile);

        echo $minified;
        exit;
    } catch (QUI\Exception $Exception) {
        $Response = QUI::getGlobalResponse();
        $Response->setStatusCode(
            \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND
        );

        echo '';
        exit;
    }
}

QUI::$Ajax->register(
    'package_quiqqer_cache_ajax_requirecss',
    ['cssfile', 'requireConfig']
);
