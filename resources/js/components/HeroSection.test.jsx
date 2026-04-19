import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import HeroSection from './HeroSection';

describe('HeroSection', () => {
    it('renders headline', () => {
        render(<HeroSection />);
        expect(screen.getByText(/La lista de la compra con IA/)).toBeInTheDocument();
        expect(screen.getByText(/Sin anuncios/)).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<HeroSection />);
        expect(screen.getByText(/Aprende lo que compras/)).toBeInTheDocument();
        expect(screen.getByText(/Hecha en España/)).toBeInTheDocument();
    });
});
