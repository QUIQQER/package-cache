<?php

/**
 * This file contains QUI\Cache\Parser\LazyLoading
 */

namespace QUI\Cache\Parser;

use QUI;
use QUI\Utils\StringHelper as StringUtils;

use function htmlspecialchars;
use function preg_replace_callback;

/**
 * Class LazyLoading
 *
 * @package QUI\Cache
 */
class LazyLoading extends QUI\Utils\Singleton
{
    /**
     * Add lazy loading part to html
     *
     * @param $content
     * @return string
     */
    public function parse($content): string
    {
        // parse images
        return preg_replace_callback(
            '#<img([^>]*)>#i',
            [&$this, "images"],
            $content
        );
    }

    /**
     * @param $output
     * @return string
     */
    public function images($output): string
    {
        $img = $output[0];
        $imgData = $output[1];
        $attributes = StringUtils::getHTMLAttributes($img);

        if (!isset($attributes['src'])) {
            return $img;
        }

        if (isset($attributes['loading']) && $attributes['loading'] === 'lazy' && isset($attributes['data-src'])) {
            return $img;
        }

        if (
            str_contains($attributes['src'], '.svg')
            || str_contains($attributes['src'], 'data:')
        ) {
            return $img;
        }

        $attributes['loading'] = 'lazy';

        if (!isset($attributes['class'])) {
            $attributes['class'] = 'lazyload';
        } elseif (!str_contains($attributes['class'], 'lazyload')) {
            $attributes['class'] = $attributes['class'] . ' lazyload';
        }

        return $this->render($attributes);
    }

    /**
     * @param $attributes
     * @return string
     */
    protected function render($attributes): string
    {
        // image string
        $img = '<img ';

        foreach ($attributes as $key => $value) {
            $img .= htmlspecialchars($key) . '="' . htmlspecialchars($value) . '" ';
        }

        $img .= '/>';

        return $img;
    }
}
