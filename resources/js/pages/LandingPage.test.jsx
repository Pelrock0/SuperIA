import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import LandingPage from './LandingPage';

vi.mock('../lib/api', () => ({
    default: { post: vi.fn() },
}));

describe('LandingPage', () => {
    it('renders hero, features, waitlist form, and data commitment', () => {
        render(
            <BrowserRouter>
                <LandingPage />
            </BrowserRouter>
        );

        expect(screen.getByText(/La lista de la compra con IA/)).toBeInTheDocument();
        expect(screen.getByText('IA que te sugiere')).toBeInTheDocument();
        expect(screen.getByText('Listas compartidas')).toBeInTheDocument();
        expect(screen.getByText('Historial y estadísticas')).toBeInTheDocument();
        expect(screen.getByTestId('waitlist-form')).toBeInTheDocument();
        expect(screen.getByText(/Tus listas son tuyas/)).toBeInTheDocument();
    });

    it('renders footer with privacy link', () => {
        render(
            <BrowserRouter>
                <LandingPage />
            </BrowserRouter>
        );

        expect(screen.getByText('Política de privacidad')).toBeInTheDocument();
    });
});
