<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Транслитерация кириллицы → латиницы для ЧПУ-адресов (slug).
 *
 * Стандартная карта Laravel (portable-ascii) не учитывает составные
 * сочетания (ш → sh, щ → shch), поэтому задаём собственную.
 */
class Slugger
{
    protected static array $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /**
     * Кириллица → латиница.
     */
    public static function transliterate(string $text): string
    {
        return strtr(mb_strtolower(trim($text)), static::$map);
    }

    /**
     * Транслитерация + приведение к безопасному slug-виду.
     */
    public static function slug(string $text): string
    {
        return Str::slug(static::transliterate($text));
    }
}