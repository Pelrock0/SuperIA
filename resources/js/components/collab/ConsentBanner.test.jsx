import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import ConsentBanner from './ConsentBanner';

describe('ConsentBanner', () => {
    it('renders owner name in heading', () => {
        render(<ConsentBanner ownerName="Maria" onAccept={vi.fn()} />);
        expect(screen.getByText(/lista compartida por maria/i)).toBeInTheDocument();
    });

    it('falls back to default text when owner name missing', () => {
        render(<ConsentBanner ownerName={null} onAccept={vi.fn()} />);
        expect(screen.getByText(/lista compartida por un usuario de superia/i)).toBeInTheDocument();
    });

    it('renders retention disclosure', () => {
        render(<ConsentBanner ownerName="Maria" onAccept={vi.fn()} />);
        expect(screen.getByText(/30 dias/)).toBeInTheDocument();
        expect(screen.getByText(/no con fines publicitarios/)).toBeInTheDocument();
    });

    it('calls onAccept when Continuar clicked', async () => {
        const onAccept = vi.fn();
        const user = userEvent.setup();

        render(<ConsentBanner ownerName="Maria" onAccept={onAccept} />);
        await user.click(screen.getByRole('button', { name: /continuar/i }));

        expect(onAccept).toHaveBeenCalledTimes(1);
    });

    it('renders as modal dialog', () => {
        render(<ConsentBanner ownerName="Maria" onAccept={vi.fn()} />);
        const dialog = screen.getByRole('dialog');
        expect(dialog).toHaveAttribute('aria-modal', 'true');
    });
});
