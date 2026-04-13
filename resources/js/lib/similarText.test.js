import { describe, it, expect } from 'vitest';
import similarText from './similarText';

describe('similarText', () => {
    it('returns 1.0 for identical strings', () => {
        expect(similarText('Tomates', 'Tomates')).toBe(1.0);
    });

    it('is case insensitive', () => {
        expect(similarText('ARROZ', 'arroz')).toBe(1.0);
    });

    it('returns 0 for completely different strings', () => {
        expect(similarText('Leche', 'xyz')).toBeLessThan(0.3);
    });

    it('returns high score for similar strings', () => {
        expect(similarText('Tomates', 'tomates')).toBe(1.0);
        expect(similarText('Tomates', 'Tomate')).toBeGreaterThan(0.8);
    });

    it('returns moderate score for partially similar', () => {
        const score = similarText('Leche', 'Leche entera');
        expect(score).toBeGreaterThan(0.5);
        expect(score).toBeLessThan(0.9);
    });

    it('returns 0 for empty strings', () => {
        expect(similarText('', 'abc')).toBe(0.0);
        expect(similarText('abc', '')).toBe(0.0);
    });

    it('handles whitespace', () => {
        expect(similarText('  Leche  ', 'Leche')).toBe(1.0);
    });
});
