import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import ProfilePage from './ProfilePage';

const mockLogout = vi.fn();
const mockRefreshUser = vi.fn();
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return { ...actual, useNavigate: () => mockNavigate };
});

vi.mock('../context/AuthContext', () => ({
    useAuth: () => ({
        user: { id: 1, name: 'Test User', email: 'test@example.com' },
        logout: mockLogout,
        refreshUser: mockRefreshUser,
    }),
}));

vi.mock('../lib/profileHistoryApi', () => ({
    fetchHistory: vi.fn().mockResolvedValue({ items: [], pagination: { page: 1, per_page: 20, total: 0 } }),
    clearHistory: vi.fn(),
    forgetProduct: vi.fn(),
}));

vi.mock('../lib/weeklySummaryApi', () => ({
    fetchLatestSummary: vi.fn(),
    dismissSummary: vi.fn(),
    convertSummaryToList: vi.fn(),
    updateWeeklySummaryEmail: vi.fn(),
}));

vi.mock('../lib/api', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: { weekly_summary_email_opted_in: false } } }),
        put: vi.fn(),
        post: vi.fn(),
    },
    getToken: vi.fn(),
    setToken: vi.fn(),
    removeToken: vi.fn(),
}));

vi.mock('../components/profile/HistoryList', () => ({
    default: () => <div data-testid="history-list">HistoryList</div>,
}));

import api from '../lib/api';
import { updateWeeklySummaryEmail } from '../lib/weeklySummaryApi';

describe('ProfilePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockRefreshUser.mockResolvedValue(undefined);
        api.get.mockResolvedValue({ data: { data: { weekly_summary_email_opted_in: false } } });
    });

    function renderPage() {
        return render(<MemoryRouter><ProfilePage /></MemoryRouter>);
    }

    it('renders profile with user info', async () => {
        renderPage();
        await waitFor(() => {
            expect(screen.getAllByText('Test User').length).toBeGreaterThan(0);
            expect(screen.getByText('test@example.com')).toBeInTheDocument();
        });
    });

    it('renders Perfil header', () => {
        renderPage();
        expect(screen.getByText('Perfil')).toBeInTheDocument();
    });

    it('renders weekly summary section', async () => {
        renderPage();
        await waitFor(() => {
            expect(screen.getByTestId('weekly-summary-section') || screen.getByTestId('email-toggle')).toBeTruthy();
        });
    });

    it('renders delete section', () => {
        renderPage();
        expect(screen.getByText(/Zona de Riesgo/i)).toBeInTheDocument();
    });

    it('toggle email opt-in calls API', async () => {
        updateWeeklySummaryEmail.mockResolvedValue({ weekly_summary_email_opted_in: true });
        const user = userEvent.setup();
        renderPage();
        await waitFor(() => expect(screen.getByTestId('email-toggle')).toBeInTheDocument());
        await user.click(screen.getByTestId('email-toggle'));
        expect(updateWeeklySummaryEmail).toHaveBeenCalledWith(true);
    });
});
