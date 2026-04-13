import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import ConfirmClearHistoryModal from './ConfirmClearHistoryModal';

describe('ConfirmClearHistoryModal', () => {
    it('renders title and disclaimer text', () => {
        render(<ConfirmClearHistoryModal onConfirm={vi.fn()} onCancel={vi.fn()} isLoading={false} />);

        expect(screen.getByRole('heading', { name: /eliminar historial completo/i })).toBeInTheDocument();
        expect(screen.getByText(/no se puede deshacer/i)).toBeInTheDocument();
    });

    it('calls onConfirm when primary button clicked', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();

        render(<ConfirmClearHistoryModal onConfirm={onConfirm} onCancel={vi.fn()} isLoading={false} />);

        await user.click(screen.getByRole('button', { name: /eliminar todo/i }));

        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it('calls onCancel when cancel button clicked', async () => {
        const user = userEvent.setup();
        const onCancel = vi.fn();

        render(<ConfirmClearHistoryModal onConfirm={vi.fn()} onCancel={onCancel} isLoading={false} />);

        await user.click(screen.getByRole('button', { name: /cancelar/i }));

        expect(onCancel).toHaveBeenCalledTimes(1);
    });

    it('disables buttons while loading', () => {
        render(<ConfirmClearHistoryModal onConfirm={vi.fn()} onCancel={vi.fn()} isLoading={true} />);

        expect(screen.getByRole('button', { name: /eliminando/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: /cancelar/i })).toBeDisabled();
    });

    it('has modal dialog role', () => {
        render(<ConfirmClearHistoryModal onConfirm={vi.fn()} onCancel={vi.fn()} isLoading={false} />);

        const dialog = screen.getByRole('dialog');
        expect(dialog).toHaveAttribute('aria-modal', 'true');
    });
});
