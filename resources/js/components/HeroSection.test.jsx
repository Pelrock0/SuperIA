import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import HeroSection from './HeroSection';

describe('HeroSection', () => {
    it('renders headline', () => {
        render(<HeroSection />);
        expect(screen.getByText(/La compra/)).toBeInTheDocument();
        expect(screen.getByText(/más inteligente/)).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<HeroSection />);
        expect(screen.getByText(/Listas de compra con IA/)).toBeInTheDocument();
    });
});
