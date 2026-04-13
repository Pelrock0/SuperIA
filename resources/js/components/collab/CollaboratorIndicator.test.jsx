import { render, screen, waitFor, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('../../lib/shareApi', () => ({
    getCollaboratorsCount: vi.fn(),
}));

import CollaboratorIndicator from './CollaboratorIndicator';
import { getCollaboratorsCount } from '../../lib/shareApi';

describe('CollaboratorIndicator', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders nothing when count is zero', async () => {
        getCollaboratorsCount.mockResolvedValue(0);

        const { container } = render(<CollaboratorIndicator listId={1} />);

        await waitFor(() => expect(getCollaboratorsCount).toHaveBeenCalled());

        expect(container.querySelector('[data-testid="collaborator-indicator"]')).toBeNull();
    });

    it('renders singular text when count is 1', async () => {
        getCollaboratorsCount.mockResolvedValue(1);

        render(<CollaboratorIndicator listId={1} />);

        await waitFor(() => {
            expect(screen.getByText('1 persona viendo ahora')).toBeInTheDocument();
        });
    });

    it('renders plural text when count > 1', async () => {
        getCollaboratorsCount.mockResolvedValue(3);

        render(<CollaboratorIndicator listId={1} />);

        await waitFor(() => {
            expect(screen.getByText('3 personas viendo ahora')).toBeInTheDocument();
        });
    });

    it('fetches count on mount', async () => {
        getCollaboratorsCount.mockResolvedValue(2);

        render(<CollaboratorIndicator listId={42} />);

        await waitFor(() => expect(getCollaboratorsCount).toHaveBeenCalledWith(42));
    });

    it('ignores API failures silently', async () => {
        getCollaboratorsCount.mockRejectedValue(new Error('fail'));

        const { container } = render(<CollaboratorIndicator listId={1} />);

        await waitFor(() => expect(getCollaboratorsCount).toHaveBeenCalled());

        expect(container.querySelector('[data-testid="collaborator-indicator"]')).toBeNull();
    });
});
