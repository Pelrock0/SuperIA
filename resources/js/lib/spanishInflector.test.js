import { describe, it, expect } from 'vitest';
import normalize from './spanishInflector';

describe('spanishInflector', () => {
    const cases = [
        ['Pan', 'pan'],
        ['tomate', 'tomate'],
        ['cebolla', 'cebolla'],
        ['Leche', 'leche'],
        ['Manzana', 'manzana'],
        ['Papel', 'papel'],
        ['Lápiz', 'lapiz'],
        ['Agua', 'agua'],
        ['Limón', 'limon'],
        ['Flor', 'flor'],
        ['Arroz', 'arroz'],
        ['Pez', 'pez'],
        ['Mujer', 'mujer'],
        ['Red', 'red'],

        ['Lápices', 'lapiz'],
        ['Arroces', 'arroz'],
        ['Peces', 'pez'],
        ['Luces', 'luz'],

        ['Panes', 'pan'],
        ['Papeles', 'papel'],
        ['Limones', 'limon'],
        ['Flores', 'flor'],
        ['Mujeres', 'mujer'],
        ['Redes', 'red'],

        ['Tomates', 'tomate'],
        ['Cebollas', 'cebolla'],
        ['Leches', 'leche'],
        ['Manzanas', 'manzana'],
        ['Aguas', 'agua'],
        ['Pies', 'pie'],
        ['Casas', 'casa'],

        ['Lunes', 'lunes'],
        ['Martes', 'martes'],
        ['Crisis', 'crisis'],
        ['Tesis', 'tesis'],
        ['Cumpleaños', 'cumpleaños'],

        ['Pollo', 'pollo'],
        ['Polla', 'polla'],
        ['Casa', 'casa'],
        ['Caso', 'caso'],

        ['  Pan  ', 'pan'],
        ['Pan  blanco', 'pan blanco'],

        ['Mañana', 'mañana'],
        ['Niños', 'niño'],

        ['TOMATES', 'tomate'],
        ['cEbOlLaS', 'cebolla'],

        ['Plátanos', 'platano'],
        ['Pingüinos', 'pinguino'],

        ['Tomates Rojos', 'tomate rojo'],
    ];

    it.each(cases)('normalize(%j) === %j', (input, expected) => {
        expect(normalize(input)).toBe(expected);
    });

    it('returns empty string for empty input', () => {
        expect(normalize('')).toBe('');
    });

    it('returns empty string for whitespace only', () => {
        expect(normalize('   ')).toBe('');
    });

    it('returns empty string for non-string input', () => {
        expect(normalize(null)).toBe('');
        expect(normalize(undefined)).toBe('');
        expect(normalize(42)).toBe('');
    });

    it('leaves short words unchanged', () => {
        expect(normalize('Mes')).toBe('mes');
        expect(normalize('dos')).toBe('dos');
    });

    it('is idempotent', () => {
        const first = normalize('Tomates Rojos');
        const second = normalize(first);
        expect(first).toBe(second);
    });
});
