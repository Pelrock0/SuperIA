import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import PrivacyPage from './PrivacyPage';

describe('PrivacyPage', () => {
    it('renders privacy policy title', () => {
        render(
            <BrowserRouter>
                <PrivacyPage />
            </BrowserRouter>
        );
        expect(screen.getByText('Política de Privacidad')).toBeInTheDocument();
    });

    it('renders RGPD rights section', () => {
        render(
            <BrowserRouter>
                <PrivacyPage />
            </BrowserRouter>
        );
        expect(screen.getByText(/Acceso:/)).toBeInTheDocument();
        expect(screen.getByText(/Rectificación:/)).toBeInTheDocument();
        expect(screen.getByText(/Supresión:/)).toBeInTheDocument();
        expect(screen.getByText(/Portabilidad:/)).toBeInTheDocument();
        expect(screen.getByText(/Oposición:/)).toBeInTheDocument();
        expect(screen.getByText(/Limitación:/)).toBeInTheDocument();
    });

    it('renders data collection info', () => {
        render(
            <BrowserRouter>
                <PrivacyPage />
            </BrowserRouter>
        );
        expect(screen.getByText('1. Datos que recogemos')).toBeInTheDocument();
        expect(screen.getByText('6. Cookies y tracking')).toBeInTheDocument();
    });

    it('renders back link to landing', () => {
        render(
            <BrowserRouter>
                <PrivacyPage />
            </BrowserRouter>
        );
        expect(screen.getByText(/Volver a Superia/)).toBeInTheDocument();
    });
});
