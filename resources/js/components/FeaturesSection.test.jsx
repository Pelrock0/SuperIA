import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import FeaturesSection from './FeaturesSection';

describe('FeaturesSection', () => {
    it('renders 3 features with titles', () => {
        render(<FeaturesSection />);
        expect(screen.getByText('IA que te sugiere')).toBeInTheDocument();
        expect(screen.getByText('Listas compartidas')).toBeInTheDocument();
        expect(screen.getByText('Historial y estadísticas')).toBeInTheDocument();
    });

    it('renders section heading and subtitle', () => {
        render(<FeaturesSection />);
        expect(screen.getByText('Experiencia Premium')).toBeInTheDocument();
        expect(screen.getByText('Diseñado para la vida moderna.')).toBeInTheDocument();
    });

    it('renders feature badges', () => {
        render(<FeaturesSection />);
        expect(screen.getByText('Inteligencia Activa')).toBeInTheDocument();
        expect(screen.getByText('Colaborativo')).toBeInTheDocument();
        expect(screen.getByText('Analíticas')).toBeInTheDocument();
    });
});
