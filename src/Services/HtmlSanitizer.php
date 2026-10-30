<?php

declare(strict_types=1);

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Čišćenje HTML-a iz rich-text uređivača (Quill) PRIJE spremanja u bazu.
 *
 * Radi po "bijeloj listi": zadržava SAMO navedene oznake i atribute, a sve
 * ostalo briše (<script>, <iframe>, <style>, onclick="", style="", javascript: URL-ove...).
 * Zato se sadržaj vijesti poslije smije ispisati bez escapinga (vijesti/show.php).
 */
final class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        return trim(self::purifier()->purify($html));
    }

    /** Je li nakon čišćenja ostao ikakav vidljivi tekst (prazan <p><br></p> se ne računa). */
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

        // Točno one oznake koje nudi alatna traka uređivača
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,h2,h3,ul,ol,li,blockquote,a[href|title]');
        // Poveznice samo na http(s) i e-mail; javascript:, data: i sl. se brišu
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // Vanjske poveznice: novi prozor i rel="noopener noreferrer" (dodaje ga Purifier)
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        // Purifier sprema obrađenu konfiguraciju u cache (brže). Mapa je izvan webroota.
        $cache = BASE_PATH . '/storage/cache/htmlpurifier';
        if (!is_dir($cache)) {
            mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);

        return self::$purifier = new HTMLPurifier($config);
    }
}
