<?php

/**
 * This file contains QUI\Cache\Parser\HTTP2ServerPush
 */

namespace QUI\Cache\Parser;

use QUI;
use QUI\Utils\StringHelper as StringUtils;
use Symfony\Component\HttpFoundation\Response;

use function strpos;

/**
 * Class HTTP2ServerPush
 * EXPERIMENTAL
 *
 * @package QUI\Cache\Parser
 */
class HTTP2ServerPush
{
    /**
     * @param string $output
     * @param Response|null $Response
     */
    public static function parseImages(string $output, null|Response $Response = null): void
    {
        /*
        if ($Response === null) {
            $Response = QUI::getGlobalResponse();
        }

        return;
        \preg_match_all('#<img([^>]*)>#i', $output, $images);

        $images = $images[1];

        foreach ($images as $image) {
            $image = \str_replace("\n", ' ', $image);
            $attributes = StringUtils::getHTMLAttributes($image);

            if (empty($attributes['src'])) {
                continue;
            }

            $src = $attributes['src'];

            if (str_contains($src, 'data:')) {
                continue;
            }

            $Response->headers->set(
                'link',
                '<' . $src . '>; rel=preload; as=image',
                false
            );
        }
        */
    }

    /**
     * @param string $output
     * @param Response|null $Response
     */
    public static function parseCSS(string $output, null|Response $Response = null): void
    {
        /*
        if ($Response === null) {
            $Response = QUI::getGlobalResponse();
        }

        return;
        \preg_match_all('/<link[^>]+href="([^"]*)"[^>]*>/Uis', $output, $matches);

        foreach ($matches as $match) {
            if (
                strpos($match[0], 'rel') !== false
                && strpos($match[0], 'rel="stylesheet"') === false
            ) {
                continue;
            }

            if (strpos($match[0], 'alternate') !== false) {
                continue;
            }

            if (strpos($match[0], 'next') !== false) {
                continue;
            }

            if (strpos($match[0], 'prev') !== false) {
                continue;
            }

            $file = CMS_DIR . $match[1];
            $file = \str_replace("\n", ' ', $file);

            if (!\file_exists($file)) {
                $parse = \parse_url($file);
                $file = $parse['path'];
            }

            if (!\file_exists($file)) {
                continue;
            }

            $Response->headers->set(
                'link',
                '<' . $file . '>; rel=preload; as=style',
                false
            );
        }
        */
    }
}
