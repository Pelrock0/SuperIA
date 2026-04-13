import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import EmptyState from './EmptyState';

describe('EmptyState', () => {
    it('renders welcome message and CTA', () => {
        render(<EmptyState onCreateClick={vi.fn()} />);
        expect(screen.getByText(/bienvenido a superia/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /crear mi primera lista/i })).toBeInTheDocument();
    });

    it('calls onCreateClick when button clicked', async () => {
        const user = userEvent.setup();
        const onClick = vi.fn();
        render(<EmptyState onCreateClick={onClick} />);

        await user.click(screen.getByRole('button', { name: /crear mi primera lista/i }));
        expect(onClick).toHaveBeenCalledOnce();
    });
});
