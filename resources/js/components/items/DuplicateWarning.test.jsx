import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import DuplicateWarning from './DuplicateWarning';

describe('DuplicateWarning', () => {
    it('renders matched product name', () => {
        render(<DuplicateWarning matchedName="Tomates" onAddAnyway={vi.fn()} onIncrement={vi.fn()} />);
        expect(screen.getByText(/Ya tienes/)).toBeInTheDocument();
        expect(screen.getByText('Tomates')).toBeInTheDocument();
    });

    it('calls onAddAnyway when button clicked', async () => {
        const onAddAnyway = vi.fn();
        const user = userEvent.setup();
        render(<DuplicateWarning matchedName="Tomates" onAddAnyway={onAddAnyway} onIncrement={vi.fn()} />);
        await user.click(screen.getByTestId('add-anyway'));
        expect(onAddAnyway).toHaveBeenCalled();
    });

    it('calls onIncrement when button clicked', async () => {
        const onIncrement = vi.fn();
        const user = userEvent.setup();
        render(<DuplicateWarning matchedName="Tomates" onAddAnyway={vi.fn()} onIncrement={onIncrement} />);
        await user.click(screen.getByTestId('increment-quantity'));
        expect(onIncrement).toHaveBeenCalled();
    });

    it('disables buttons when loading', () => {
        render(<DuplicateWarning matchedName="X" onAddAnyway={vi.fn()} onIncrement={vi.fn()} isLoading={true} />);
        expect(screen.getByTestId('add-anyway')).toBeDisabled();
        expect(screen.getByTestId('increment-quantity')).toBeDisabled();
    });

    it('has alert role', () => {
        render(<DuplicateWarning matchedName="X" onAddAnyway={vi.fn()} onIncrement={vi.fn()} />);
        expect(screen.getByRole('alert')).toBeInTheDocument();
    });
});
