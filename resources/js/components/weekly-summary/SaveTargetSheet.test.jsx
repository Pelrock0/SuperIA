import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import SaveTargetSheet from './SaveTargetSheet';

describe('SaveTargetSheet', () => {
    let onClose;
    let onConfirm;

    beforeEach(() => {
        onClose = vi.fn();
        onConfirm = vi.fn();
    });

    const renderSheet = (overrides = {}) =>
        render(
            <SaveTargetSheet
                isOpen
                onClose={onClose}
                onConfirm={onConfirm}
                activeLists={[
                    { id: 1, name: 'Compra', emoji: '🛒', items_total: 4 },
                    { id: 2, name: 'Cena', emoji: '🥗', items_total: 2 },
                ]}
                selectedCount={3}
                {...overrides}
            />,
        );

    it('does not render when isOpen is false', () => {
        render(
            <SaveTargetSheet
                isOpen={false}
                onClose={onClose}
                onConfirm={onConfirm}
                activeLists={[]}
                selectedCount={0}
            />,
        );
        expect(screen.queryByTestId('save-target-sheet')).not.toBeInTheDocument();
    });

    it('renders the title and the selected count', () => {
        renderSheet();
        expect(screen.getByText('Guardar en…')).toBeInTheDocument();
        expect(screen.getByText('3 items seleccionados')).toBeInTheDocument();
    });

    it('renders the selected count in singular form for one item', () => {
        renderSheet({ selectedCount: 1 });
        expect(screen.getByText('1 item seleccionado')).toBeInTheDocument();
    });

    it('shows empty-state copy when there are no active lists', () => {
        renderSheet({ activeLists: [] });
        expect(screen.getByTestId('save-target-empty')).toBeInTheDocument();
    });

    it('confirm button is disabled until a destination is chosen', async () => {
        renderSheet();
        const confirm = screen.getByTestId('save-target-confirm');
        expect(confirm).toBeDisabled();
        expect(confirm).toHaveTextContent('Selecciona una lista');

        await userEvent.click(screen.getByTestId('save-target-list-1'));
        expect(confirm).toBeEnabled();
        expect(confirm).toHaveTextContent('Guardar');
    });

    it('confirms with the chosen existing list', async () => {
        renderSheet();
        await userEvent.click(screen.getByTestId('save-target-list-2'));
        await userEvent.click(screen.getByTestId('save-target-confirm'));
        expect(onConfirm).toHaveBeenCalledWith({ targetListId: 2 });
    });

    it('confirm CTA shows the chosen list name', async () => {
        renderSheet();
        await userEvent.click(screen.getByTestId('save-target-list-1'));
        expect(screen.getByTestId('save-target-confirm')).toHaveTextContent('Guardar en "Compra"');
    });

    it('confirm CTA shows the new-list label when creating a new list', async () => {
        renderSheet();
        await userEvent.click(screen.getByTestId('save-target-new-list'));
        expect(screen.getByTestId('save-target-confirm')).toHaveTextContent('Guardar en nueva lista');
    });

    it('traps Tab focus inside the dialog', async () => {
        renderSheet();
        const focusables = [
            screen.getByTestId('save-target-list-1'),
            screen.getByTestId('save-target-list-2'),
            screen.getByTestId('save-target-new-list'),
            screen.getByTestId('save-target-cancel'),
        ];
        // Move focus to the last focusable, then Tab → should wrap to the first.
        focusables[focusables.length - 1].focus();
        await userEvent.tab();
        expect(document.activeElement).toBe(focusables[0]);
    });

    it('traps Shift+Tab focus inside the dialog', async () => {
        renderSheet();
        const first = screen.getByTestId('save-target-list-1');
        first.focus();
        await userEvent.tab({ shift: true });
        // Should wrap to the last focusable (cancel button when confirm is disabled).
        expect(document.activeElement).toBe(screen.getByTestId('save-target-cancel'));
    });

    it('uses centered modal layout on desktop viewports', () => {
        const originalMatchMedia = window.matchMedia;
        window.matchMedia = vi.fn().mockImplementation((query) => ({
            matches: query.includes('min-width: 768px'),
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            addListener: vi.fn(),
            removeListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }));
        try {
            renderSheet();
            const sheet = screen.getByTestId('save-target-sheet');
            // Drag handle should not be present on desktop.
            expect(sheet.querySelector('[aria-hidden="true"][style*="cursor"]')).toBeNull();
            // Border radius should be uniform (modal), not bottom-sheet shape.
            expect(sheet.style.borderRadius).toBe('24px');
        } finally {
            window.matchMedia = originalMatchMedia;
        }
    });

    it('confirms with null targetListId for new-list path', async () => {
        renderSheet();
        await userEvent.click(screen.getByTestId('save-target-new-list'));
        await userEvent.click(screen.getByTestId('save-target-confirm'));
        expect(onConfirm).toHaveBeenCalledWith({ targetListId: null });
    });

    it('disables new-list option when there are 3 active lists', () => {
        renderSheet({
            activeLists: [
                { id: 1, name: 'A', items_total: 1 },
                { id: 2, name: 'B', items_total: 2 },
                { id: 3, name: 'C', items_total: 3 },
            ],
        });
        expect(screen.getByTestId('save-target-new-list')).toBeDisabled();
    });

    it('cancel button calls onClose', async () => {
        renderSheet();
        await userEvent.click(screen.getByTestId('save-target-cancel'));
        expect(onClose).toHaveBeenCalled();
    });

    it('clicking the backdrop calls onClose', async () => {
        renderSheet();
        await userEvent.click(screen.getByTestId('save-target-backdrop'));
        expect(onClose).toHaveBeenCalled();
    });

    it('Escape key calls onClose', async () => {
        renderSheet();
        await userEvent.keyboard('{Escape}');
        expect(onClose).toHaveBeenCalled();
    });

    it('shows loading label while submitting', () => {
        renderSheet({ isSubmitting: true });
        expect(screen.getByTestId('save-target-confirm')).toHaveTextContent('Guardando…');
    });
});
