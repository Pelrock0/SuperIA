import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import DataCommitment from './DataCommitment';

describe('DataCommitment', () => {
    it('renders commitment headline', () => {
        render(<DataCommitment />);
        expect(screen.getByText(/Tus listas son tuyas/)).toBeInTheDocument();
        expect(screen.getByText(/Sin publicidad/)).toBeInTheDocument();
        expect(screen.getByText(/Sin venta de datos/)).toBeInTheDocument();
    });

    it('renders privacy commitments with check marks', () => {
        render(<DataCommitment />);
        expect(screen.getByText('Encriptado de extremo a extremo')).toBeInTheDocument();
        expect(screen.getByText('Sin rastreadores de terceros')).toBeInTheDocument();
    });

    it('renders privacy description', () => {
        render(<DataCommitment />);
        expect(screen.getByText(/privacidad no es una opción/)).toBeInTheDocument();
    });
});
