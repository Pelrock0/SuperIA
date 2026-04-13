import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import DashboardPage from './DashboardPage';

const mockNavigate = vi.fn();
const mockLogout = vi.fn();

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return { ...actual, useNavigate: () => mockNavigate };
});

vi.mock('../context/AuthContext', () => ({
    useAuth: () => ({
        user: { id: 1, name: 'Test User' },
        logout: mockLogout,
    }),
}));

vi.mock('../lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    getToken: vi.fn(),
    setToken: vi.fn(),
    removeToken: vi.fn(),
}));

vi.mock('../components/dashboard/ReplenishmentBanner', () => ({ default: () => null }));
vi.mock('../components/dashboard/WeeklySummaryBanner', () => ({ default: () => null }));

import api from '../lib/api';

describe('DashboardPage', () => {
    beforeEach(() => vi.clearAllMocks());

    function renderPage() {
        return render(<MemoryRouter><DashboardPage /></MemoryRouter>);
    }

    it('shows loading state initially', () => {
        api.get.mockImplementation(() => new Promise(() => {}));
        renderPage();
        expect(screen.getByTestId('dashboard-loading')).toBeInTheDocument();
    });

    it('shows empty state when no lists', async () => {
        api.get.mockResolvedValueOnce({ data: { data: { active: [], archived: [] } } });
        renderPage();
        await waitFor(() => {
            expect(screen.getByText('Sin listas todavia')).toBeInTheDocument();
            expect(screen.getByText('Crear lista')).toBeInTheDocument();
        });
    });

    it('shows greeting with user first name', async () => {
        api.get.mockResolvedValueOnce({ data: { data: { active: [], archived: [] } } });
        renderPage();
        await waitFor(() => {
            expect(screen.getByText('Hola, Test')).toBeInTheDocument();
        });
    });

    it('shows active lists as cards', async () => {
        api.get.mockResolvedValueOnce({
            data: { data: {
                active: [{ id: 1, name: 'Mi lista', emoji: '🛒', status: 'active', is_shared: false, items_total: 3, items_completed: 1, updated_at: new Date().toISOString() }],
                archived: [],
            }},
        });
        renderPage();
        await waitFor(() => {
            expect(screen.getByText('Mi lista')).toBeInTheDocument();
            expect(screen.getByText('1 / 3 articulos')).toBeInTheDocument();
        });
    });

    it('shows archived section', async () => {
        api.get.mockResolvedValueOnce({
            data: { data: {
                active: [{ id: 1, name: 'Activa', status: 'active', is_shared: false, items_total: 0, items_completed: 0, updated_at: new Date().toISOString() }],
                archived: [{ id: 2, name: 'Archivada', status: 'archived', is_shared: false, items_total: 0, items_completed: 0, updated_at: new Date().toISOString() }],
            }},
        });
        renderPage();
        await waitFor(() => {
            expect(screen.getByText('Activa')).toBeInTheDocument();
            expect(screen.getByText('Archivada')).toBeInTheDocument();
            expect(screen.getByText(/Archivadas/)).toBeInTheDocument();
        });
    });

    it('shows AI concierge banner with generate link', async () => {
        api.get.mockResolvedValueOnce({
            data: { data: {
                active: [{ id: 1, name: 'Test', status: 'active', is_shared: false, items_total: 0, items_completed: 0, updated_at: new Date().toISOString() }],
                archived: [],
            }},
        });
        renderPage();
        await waitFor(() => {
            expect(screen.getByTestId('generate-with-ai')).toBeInTheDocument();
            expect(screen.getByText('AI Concierge')).toBeInTheDocument();
        });
    });

    it('shows shared badge for shared lists', async () => {
        api.get.mockResolvedValueOnce({
            data: { data: {
                active: [{ id: 1, name: 'Shared', status: 'active', is_shared: true, items_total: 5, items_completed: 2, updated_at: new Date().toISOString() }],
                archived: [],
            }},
        });
        renderPage();
        await waitFor(() => {
            expect(screen.getByText('COMPARTIDA')).toBeInTheDocument();
        });
    });
});
