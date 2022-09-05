<?php

/**
 * This file contains QUI\Cache\Parser\LazyLoading
 */

namespace QUI\Cache\Parser;

use QUI;
use QUI\Utils\StringHelper as StringUtils;

use function htmlspecialchars;
use function preg_replace_callback;
use function strpos;

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
     * @return mixed
     */
    public function parse($content)
    {
        // parse images
        $content = preg_replace_callback(
            '#<img([^>]*)>#i',
            [&$this, "images"],
            $content
        );

        return $content;
    }

    /**
     * @param $output
     * @return string
     */
    public function images($output)
    {
        $img        = $output[0];
        $imgData    = $output[1];
        $attributes = StringUtils::getHTMLAttributes($img);

        if (!isset($attributes['src'])) {
            return $img;
        }

        if (isset($attributes['loading']) && $attributes['loading'] === 'lazy' && isset($attributes['data-src'])) {
            return $img;
        }

        if (strpos($attributes['src'], '.svg') !== false
            || strpos($attributes['src'], 'data:') !== false) {
            return $img;
        }

        $attributes['loading'] = 'lazy';

        if (!isset($attributes['class'])) {
            $attributes['class'] = 'lazyload';
        } elseif (strpos($attributes['class'], 'lazyload') === false) {
            $attributes['class'] = $attributes['class'] . ' lazyload';
        }

        return $this->render($attributes);
    }

    /**
     * @param $attributes
     * @return string
     */
    protected function render($attributes)
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
