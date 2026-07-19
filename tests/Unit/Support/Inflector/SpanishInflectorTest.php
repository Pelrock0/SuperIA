<?php

namespace Tests\Unit\Support\Inflector;

use App\Support\Inflector\SpanishInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpanishInflectorTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, SpanishInflector::normalize($input));
    }

    public static function normalizationCases(): array
    {
        return [
            'singular unchanged: pan' => ['Pan', 'pan'],
            'singular unchanged: tomate' => ['tomate', 'tomate'],
            'singular unchanged: cebolla' => ['cebolla', 'cebolla'],
            'singular unchanged: leche' => ['Leche', 'leche'],
            'singular unchanged: manzana' => ['Manzana', 'manzana'],
            'singular unchanged: papel' => ['Papel', 'papel'],
            'singular unchanged: lapiz' => ['Lápiz', 'lapiz'],
            'singular unchanged: agua' => ['Agua', 'agua'],
            'singular unchanged: limon' => ['Limón', 'limon'],
            'singular unchanged: flor' => ['Flor', 'flor'],
            'singular unchanged: arroz' => ['Arroz', 'arroz'],
            'singular unchanged: pez' => ['Pez', 'pez'],
            'singular unchanged: mujer' => ['Mujer', 'mujer'],
            'singular unchanged: red' => ['Red', 'red'],

            'R1 -ces to -z: lapices' => ['Lápices', 'lapiz'],
            'R1 -ces to -z: arroces' => ['Arroces', 'arroz'],
            'R1 -ces to -z: peces' => ['Peces', 'pez'],
            'R1 -ces to -z: luces' => ['Luces', 'luz'],

            'R2 strip -es (n): panes' => ['Panes', 'pan'],
            'R2 strip -es (l): papeles' => ['Papeles', 'papel'],
            'R2 strip -es (n): limones' => ['Limones', 'limon'],
            'R2 strip -es (r): flores' => ['Flores', 'flor'],
            'R2 strip -es (r): mujeres' => ['Mujeres', 'mujer'],
            'R2 strip -es (d): redes' => ['Redes', 'red'],

            'R2 strip -s (vowel): tomates' => ['Tomates', 'tomate'],
            'R2 strip -s (vowel): cebollas' => ['Cebollas', 'cebolla'],
            'R2 strip -s (vowel): leches' => ['Leches', 'leche'],
            'R2 strip -s (vowel): manzanas' => ['Manzanas', 'manzana'],
            'R2 strip -s (vowel): aguas' => ['Aguas', 'agua'],
            'R2 strip -s fallback: pies' => ['Pies', 'pie'],
            'R2 strip -s (cas/casas)' => ['Casas', 'casa'],

            'invariable: lunes' => ['Lunes', 'lunes'],
            'invariable: martes' => ['Martes', 'martes'],
            'invariable: crisis' => ['Crisis', 'crisis'],
            'invariable: tesis' => ['Tesis', 'tesis'],
            'invariable: cumpleaños' => ['Cumpleaños', 'cumpleaños'],

            'no-match: pollo' => ['Pollo', 'pollo'],
            'no-match: polla' => ['Polla', 'polla'],
            'no-match: casa' => ['Casa', 'casa'],
            'no-match: caso' => ['Caso', 'caso'],

            'whitespace trim' => ['  Pan  ', 'pan'],
            'whitespace collapse' => ['Pan  blanco', 'pan blanco'],

            'ñ preserved' => ['Mañana', 'mañana'],
            'ñ preserved with plural' => ['Niños', 'niño'],

            'all uppercase' => ['TOMATES', 'tomate'],
            'mixed case' => ['cEbOlLaS', 'cebolla'],

            'multiple accents stripped' => ['Plátanos', 'platano'],
            'umlaut stripped' => ['Pingüinos', 'pinguino'],
        ];
    }

    public function test_empty_string_returns_empty(): void
    {
        $this->assertSame('', SpanishInflector::normalize(''));
    }

    public function test_whitespace_only_returns_empty(): void
    {
        $this->assertSame('', SpanishInflector::normalize('   '));
    }

    public function test_short_word_unchanged(): void
    {
        $this->assertSame('mes', SpanishInflector::normalize('Mes'));
        $this->assertSame('dos', SpanishInflector::normalize('dos'));
    }

    public function test_returns_pure_function_same_input_same_output(): void
    {
        $a = SpanishInflector::normalize('Tomates Rojos');
        $b = SpanishInflector::normalize('Tomates Rojos');
        $this->assertSame($a, $b);
        $this->assertSame('tomate rojo', $a);
    }
}
