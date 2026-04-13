import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import RevokedLinkView from './RevokedLinkView';

describe('RevokedLinkView', () => {
    it('renders unavailable heading', () => {
        render(
            <MemoryRouter>
                <RevokedLinkView />
            </MemoryRouter>
        );

        expect(screen.getByRole('heading', { name: /enlace no disponible/i })).toBeInTheDocument();
    });

    it('renders retry message', () => {
        render(
            <MemoryRouter>
                <RevokedLinkView />
            </MemoryRouter>
        );

        expect(screen.getByText(/pide uno nuevo al propietario/i)).toBeInTheDocument();
    });

    it('links back to home', () => {
        render(
            <MemoryRouter>
                <RevokedLinkView />
            </MemoryRouter>
        );

        expect(screen.getByRole('link', { name: /ir a superia/i })).toHaveAttribute('href', '/');
    });
});
