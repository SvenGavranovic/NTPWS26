<?php

declare(strict_types=1);

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Čišćenje HTML-a iz uređivača prije spremanja (HTMLPurifier). */
final class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        return trim(self::purifier()->purify($html));
    }

    /** Prazan <p><br></p> se ne računa kao sadržaj. */
    public static function isEmpty(string $html): bool
    {
        $tekst = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\u{00A0}", ' ', $tekst)) === '';
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,h2,h3,ul,ol,li,blockquote,a[href|title]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $cache = BASE_PATH . '/storage/cache/htmlpurifier';
        if (!is_dir($cache)) {
            mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);

        return self::$purifier = new HTMLPurifier($config);
    }
}
