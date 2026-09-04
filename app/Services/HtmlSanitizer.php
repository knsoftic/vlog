<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitises rich-text content saved from the CMS editor (stored-XSS protection while allowing
 * safe embeds from YouTube / Vimeo).
 */
class HtmlSanitizer
{
    protected ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        return $this->purifier()->purify($html);
    }

    public function stripAll(?string $html): string
    {
        return trim(strip_tags((string) $html));
    }

    protected function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }
        $config = HTMLPurifier_Config::createDefault();
        $cache = storage_path('app/purifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('HTML.Allowed', implode(',', [
            'p[class|style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'span[class|style]', 'div[class|style]',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote[cite]', 'pre[class]', 'code[class]', 'hr',
            'ul[class]', 'ol[class|start]', 'li',
            'a[href|title|target|rel]', 'img[src|alt|title|width|height|class|loading]',
            'table[class]', 'thead', 'tbody', 'tr', 'th[colspan|rowspan]', 'td[colspan|rowspan]',
            'figure[class]', 'figcaption', 'iframe[src|width|height|frameborder|allowfullscreen|allow|title|loading]',
        ]));
        $config->set('CSS.AllowedProperties', ['text-align', 'font-weight', 'font-style', 'text-decoration', 'color', 'background-color', 'width', 'height', 'max-width']);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
        $config->set('HTML.TargetBlank', false);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $def = $config->getHTMLDefinition(true);
        if ($def) {
            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
            $def->addAttribute('iframe', 'loading', 'Enum#lazy,eager');
            $def->addAttribute('iframe', 'allow', 'Text');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
            $def->addAttribute('iframe', 'title', 'Text');
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Block', 'Flow', 'Common');
        }
        $this->purifier = new HTMLPurifier($config);
        return $this->purifier;
    }
}
