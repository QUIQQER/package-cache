<?php

/**
 * This file contains QUI\Cache\Parser\LazyLoading
 */

namespace QUI\Cache\Parser;

use QUI;
use QUI\Utils\StringHelper as StringUtils;

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
        $content = str_replace(
            '</body>',
            '<script async src="'.URL_OPT_DIR.'/bin/lazysizes/lazysizes.min.js" type="text/javascript"></script></body>',
            $content
        );

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

        if (strpos($attributes['src'], '.svg') !== false) {
            return $img;
        }

        $src = $attributes['src'];
        $pos = strpos($src, '__');

        if ($pos !== false) {
            $parts = mb_substr($src, $pos + 2);
            $parts = explode('x', $parts);

            if (isset($parts[0]) && !isset($attributes['width'])) {
                $attributes['width'] = (int)$parts[0];
            }

            if (isset($parts[1]) && !isset($attributes['height'])) {
                $attributes['height'] = (int)$parts[1];
            }
        }

        $attributes['data-src'] = $src;
        unset($attributes['src']);

        if (!isset($attributes['class'])) {
            $attributes['class'] = 'lazyload';
        }

        $result = $this->render($attributes);

        return $result;
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
            $img .= htmlspecialchars($key).'="'.htmlspecialchars($value).'" ';
        }

        $img .= '/>';

        return $img;
    }
}
