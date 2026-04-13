import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import UndoSnackbar from './UndoSnackbar';

describe('UndoSnackbar', () => {
    it('renders message and undo button', () => {
        render(<UndoSnackbar message="Item eliminado." onUndo={vi.fn()} />);
        expect(screen.getByText('Item eliminado.')).toBeInTheDocument();
        expect(screen.getByText('Deshacer')).toBeInTheDocument();
    });

    it('calls onUndo when clicked', async () => {
        const user = userEvent.setup();
        const onUndo = vi.fn();
        render(<UndoSnackbar message="Deleted" onUndo={onUndo} />);

        await user.click(screen.getByText('Deshacer'));
        expect(onUndo).toHaveBeenCalledOnce();
    });

    it('disappears after duration', async () => {
        vi.useFakeTimers();

        render(<UndoSnackbar message="Gone" onUndo={vi.fn()} duration={1000} />);
        expect(screen.getByTestId('undo-snackbar')).toBeInTheDocument();

        await act(async () => {
            vi.advanceTimersByTime(1000);
        });

        expect(screen.queryByTestId('undo-snackbar')).not.toBeInTheDocument();

        vi.useRealTimers();
    });
});
