import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/shareApi', () => ({
    getActivityLog: vi.fn(),
}));

import ActivityLogView from './ActivityLogView';
import { getActivityLog } from '../../lib/shareApi';

describe('ActivityLogView', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders collapsed by default', () => {
        render(<ActivityLogView listId={1} />);

        expect(screen.getByText(/actividad reciente/i)).toBeInTheDocument();
        expect(getActivityLog).not.toHaveBeenCalled();
        expect(screen.queryByTestId('activity-loading')).toBeNull();
    });

    it('fetches and displays entries when expanded', async () => {
        const user = userEvent.setup();
        getActivityLog.mockResolvedValue([
            {
                id: 1,
                actor_type: 'anonymous',
                action: 'item_checked',
                item_name: 'Pan',
                created_at: new Date().toISOString(),
            },
            {
                id: 2,
                actor_type: 'owner',
                action: 'item_added',
                item_name: 'Leche',
                created_at: new Date(Date.now() - 120000).toISOString(),
            },
        ]);

        render(<ActivityLogView listId={5} />);

        await user.click(screen.getByRole('button', { name: /actividad reciente/i }));

        await waitFor(() => {
            expect(getActivityLog).toHaveBeenCalledWith(5);
            expect(screen.getByText(/marco "Pan" como comprado/)).toBeInTheDocument();
            expect(screen.getByText(/anadio "Leche"/)).toBeInTheDocument();
        });
        expect(screen.getByText('Colaborador')).toBeInTheDocument();
        expect(screen.getByText('Propietario')).toBeInTheDocument();
    });

    it('shows empty state when no entries', async () => {
        const user = userEvent.setup();
        getActivityLog.mockResolvedValue([]);

        render(<ActivityLogView listId={1} />);

        await user.click(screen.getByRole('button', { name: /actividad reciente/i }));

        await waitFor(() => {
            expect(screen.getByTestId('activity-empty')).toBeInTheDocument();
        });
    });

    it('shows loading state before first fetch resolves', async () => {
        const user = userEvent.setup();
        getActivityLog.mockImplementation(() => new Promise(() => {}));

        render(<ActivityLogView listId={1} />);

        await user.click(screen.getByRole('button', { name: /actividad reciente/i }));

        expect(screen.getByTestId('activity-loading')).toBeInTheDocument();
    });

    it('renders all action labels', async () => {
        const user = userEvent.setup();
        getActivityLog.mockResolvedValue([
            { id: 1, actor_type: 'owner', action: 'item_added', item_name: 'A', created_at: new Date().toISOString() },
            { id: 2, actor_type: 'owner', action: 'item_checked', item_name: 'B', created_at: new Date().toISOString() },
            { id: 3, actor_type: 'owner', action: 'item_unchecked', item_name: 'C', created_at: new Date().toISOString() },
            { id: 4, actor_type: 'owner', action: 'item_edited', item_name: 'D', created_at: new Date().toISOString() },
            { id: 5, actor_type: 'owner', action: 'item_deleted', item_name: 'E', created_at: new Date().toISOString() },
            { id: 6, actor_type: 'owner', action: 'list_cleared', item_name: 'F', created_at: new Date().toISOString() },
        ]);

        render(<ActivityLogView listId={1} />);

        await user.click(screen.getByRole('button', { name: /actividad reciente/i }));

        await waitFor(() => {
            expect(screen.getByText(/anadio "A"/)).toBeInTheDocument();
            expect(screen.getByText(/marco "B"/)).toBeInTheDocument();
            expect(screen.getByText(/desmarco "C"/)).toBeInTheDocument();
            expect(screen.getByText(/edito "D"/)).toBeInTheDocument();
            expect(screen.getByText(/elimino "E"/)).toBeInTheDocument();
            expect(screen.getByText(/limpio los items comprados/)).toBeInTheDocument();
        });
    });

    it('formats relative timestamps', async () => {
        const user = userEvent.setup();
        const now = Date.now();
        getActivityLog.mockResolvedValue([
            { id: 1, actor_type: 'owner', action: 'item_added', item_name: 'Recent', created_at: new Date(now - 30000).toISOString() },
            { id: 2, actor_type: 'owner', action: 'item_added', item_name: 'Old', created_at: new Date(now - 5 * 60000).toISOString() },
            { id: 3, actor_type: 'owner', action: 'item_added', item_name: 'Hours', created_at: new Date(now - 3 * 60 * 60000).toISOString() },
            { id: 4, actor_type: 'owner', action: 'item_added', item_name: 'Days', created_at: new Date(now - 2 * 24 * 60 * 60000).toISOString() },
            { id: 5, actor_type: 'owner', action: 'item_added', item_name: 'NoTime', created_at: null },
        ]);

        render(<ActivityLogView listId={1} />);

        await user.click(screen.getByRole('button', { name: /actividad reciente/i }));

        await waitFor(() => {
            expect(screen.getByText(/hace unos segundos/)).toBeInTheDocument();
            expect(screen.getByText(/hace 5 min/)).toBeInTheDocument();
            expect(screen.getByText(/hace 3 h/)).toBeInTheDocument();
            expect(screen.getByText(/hace 2 d/)).toBeInTheDocument();
        });
    });
});
