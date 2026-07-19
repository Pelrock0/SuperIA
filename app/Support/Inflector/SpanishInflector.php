<?php

namespace App\Support\Inflector;

final class SpanishInflector
{
    private const INVARIABLES = [
        'lunes',
        'martes',
        'miercoles',
        'jueves',
        'viernes',
        'crisis',
        'tesis',
        'atlas',
        'analisis',
        'sintesis',
        'dosis',
        'hipotesis',
        'virus',
        'oasis',
        'paraguas',
        'cumpleaños',
        'abrelatas',
        'sacacorchos',
    ];

    private const ACCENT_MAP = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ç' => 'c',
    ];

    private const PLURAL_STEM_FINAL_CONSONANTS = ['n', 'r', 'l', 'd', 'j', 'z', 'x'];

    public static function normalize(string $name): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($name));
        if ($s === '') {
            return '';
        }

        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, self::ACCENT_MAP);

        $tokens = explode(' ', $s);
        $normalized = array_map(fn (string $t): string => self::normalizeToken($t), $tokens);

        return implode(' ', $normalized);
    }

    private static function normalizeToken(string $s): string
    {
        if (in_array($s, self::INVARIABLES, true)) {
            return $s;
        }

        $len = mb_strlen($s, 'UTF-8');

        if ($len >= 5 && mb_substr($s, -3, null, 'UTF-8') === 'ces') {
            return mb_substr($s, 0, -3, 'UTF-8').'z';
        }

        if ($len >= 4 && mb_substr($s, -1, null, 'UTF-8') === 's') {
            $stripS = mb_substr($s, 0, -1, 'UTF-8');
            $stripEs = mb_substr($s, -2, null, 'UTF-8') === 'es'
                ? mb_substr($s, 0, -2, 'UTF-8')
                : null;

            if ($stripEs !== null
                && in_array(mb_substr($stripEs, -1, null, 'UTF-8'), self::PLURAL_STEM_FINAL_CONSONANTS, true)) {
                return $stripEs;
            }

            return $stripS;
        }

        return $s;
    }
}
