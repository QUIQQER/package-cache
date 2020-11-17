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
        $script = "
        <script>
            if ('loading' in HTMLImageElement.prototype) {
              var images = document.querySelectorAll('img.lazyload');
              
              images.forEach(function (img) {
                  img.src = img.getAttribute('data-src');
              });
            } else {
                if (typeof require !== 'undefined') {
                    require([window.URL_OPT_DIR +'bin/lazysizes/lazysizes.min.js']);     
                }
            }
        </script>
       
        <style> .lazyload-no-js {display: none;} </style>
        <noscript><style> 
        .lazyload { display: none; } 
        .lazyload-no-js { display: inherit} 
        </style></noscript>
        
        ";

        $content = \str_replace('</body>', $script, $content);

        // parse images
        $content = \preg_replace_callback(
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

        if (\strpos($attributes['src'], '.svg') !== false
            || \strpos($attributes['src'], 'data:') !== false) {
            return $img;
        }

        $attributes['loading']  = 'lazy';
        $attributes['data-src'] = $attributes['src'];
        $attributes['src']      = URL_OPT_DIR.'quiqqer/cache/bin/images/placeholder.gif';

        if (!isset($attributes['class'])) {
            $attributes['class'] = 'lazyload';
        } elseif (\strpos($attributes['class'], 'lazyload') === false) {
            $attributes['class'] = $attributes['class'].' lazyload';
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
            $img .= \htmlspecialchars($key).'="'.\htmlspecialchars($value).'" ';
        }

        $img .= '/>';

        // noscript
        if (!isset($attributes['class'])) {
            $attributes['class'] = '';
        }

        $attributes['class'] = \str_replace('lazyload', '', $attributes['class']);
        $attributes['class'] = \trim($attributes['class']);

        $attributes['class'] = $attributes['class'].' lazyload-no-js';
        $attributes['src']   = $attributes['data-src'];


        $img .= '<noscript><img ';

        foreach ($attributes as $key => $value) {
            $img .= \htmlspecialchars($key).'="'.\htmlspecialchars($value).'" ';
        }

        $img .= '/></noscript>';


        return $img;
    }
}
