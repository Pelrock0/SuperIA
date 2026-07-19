import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/suggestionsApi', () => ({
    fetchSuggestions: vi.fn().mockResolvedValue({ suggestions: [], ai_limit_reached: false }),
}));

import AddItemInput from './AddItemInput';
import { fetchSuggestions } from '../../lib/suggestionsApi';

describe('AddItemInput', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        fetchSuggestions.mockResolvedValue({ suggestions: [], ai_limit_reached: false });
    });

    it('submits plain name when no suggestion selected', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'Manzanas');
        await user.click(screen.getByRole('button', { name: /añadir/i }));

        expect(onAdd).toHaveBeenCalledWith({ name: 'Manzanas' });
    });

    it('pre-fills quantity/unit/category when suggestion is selected', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        const suggestion = { source: 'history', name: 'Leche entera', quantity: 1, unit: 'L', category: 'lacteos_huevos' };
        fetchSuggestions.mockResolvedValue({ suggestions: [suggestion], ai_limit_reached: false });

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'le');
        await waitFor(() => screen.getByTestId('autocomplete-option-0'));

        await user.click(screen.getByTestId('autocomplete-option-0'));

        expect(screen.getByTestId('prefilled-hint')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /añadir/i }));

        expect(onAdd).toHaveBeenCalledWith({
            name: 'Leche entera',
            quantity: 1,
            unit: 'L',
            category: 'lacteos_huevos',
        });
    });

    it('clears prefilled data when user edits the name after selection', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        const suggestion = { source: 'history', name: 'Leche', quantity: 1, unit: 'L', category: 'lacteos_huevos' };
        fetchSuggestions.mockResolvedValue({ suggestions: [suggestion], ai_limit_reached: false });

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'le');
        await waitFor(() => screen.getByTestId('autocomplete-option-0'));

        await user.click(screen.getByTestId('autocomplete-option-0'));
        expect(screen.getByTestId('prefilled-hint')).toBeInTheDocument();

        await user.type(screen.getByRole('combobox'), ' con cacao');
        expect(screen.queryByTestId('prefilled-hint')).toBeNull();

        await user.click(screen.getByRole('button', { name: /añadir/i }));

        expect(onAdd).toHaveBeenCalledWith({ name: 'Leche con cacao' });
    });

    it('disables submit when name is empty', () => {
        render(<AddItemInput onAdd={vi.fn()} isLoading={false} />);

        expect(screen.getByRole('button', { name: /añadir producto/i })).toBeDisabled();
    });

    it('disables submit while loading', () => {
        render(<AddItemInput onAdd={vi.fn()} isLoading={true} />);

        expect(screen.getByRole('button', { name: /añadir producto/i })).toBeDisabled();
    });

    it('clears the input after successful submit', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'Pan');
        await user.click(screen.getByRole('button', { name: /añadir/i }));

        await waitFor(() => expect(screen.getByRole('combobox')).toHaveValue(''));
    });

    it('does not clear the input if onAdd returns false', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(false);

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'Pan');
        await user.click(screen.getByRole('button', { name: /añadir/i }));

        await waitFor(() => expect(onAdd).toHaveBeenCalled());
        expect(screen.getByRole('combobox')).toHaveValue('Pan');
    });

    describe('duplicate detection vs active items only', () => {
        it('does not show duplicate warning when matching item is purchased', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn().mockResolvedValue(true);
            const existingItems = [
                { id: 1, name: 'Pan', is_purchased: true },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'Pan');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            expect(screen.queryByTestId('duplicate-warning')).toBeNull();
            expect(onAdd).toHaveBeenCalledWith({ name: 'Pan' });
        });

        it('does not show duplicate warning when matching purchased item is plural variant', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn().mockResolvedValue(true);
            const existingItems = [
                { id: 1, name: 'Panes', is_purchased: true },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'pan');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            expect(screen.queryByTestId('duplicate-warning')).toBeNull();
            expect(onAdd).toHaveBeenCalledWith({ name: 'pan' });
        });

        it('shows duplicate warning when matching pending item exists', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn();
            const existingItems = [
                { id: 1, name: 'Pan', is_purchased: false },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'Pan');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();
            expect(onAdd).not.toHaveBeenCalled();
        });

        it('shows duplicate warning when input is plural variant of a pending item', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn();
            const existingItems = [
                { id: 1, name: 'Pan', is_purchased: false },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'Panes');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();
        });

        it('mixed list shows warning only against pending match, ignores purchased homonym', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn();
            const existingItems = [
                { id: 1, name: 'Panes', is_purchased: true },
                { id: 2, name: 'Pan', is_purchased: false },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'Pan');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            const warning = screen.getByTestId('duplicate-warning');
            expect(warning).toBeInTheDocument();
            expect(warning).toHaveTextContent('Pan');
        });

        it('falls back to fuzzy match for typos against pending items', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn();
            const existingItems = [
                { id: 1, name: 'Tomate cherry', is_purchased: false },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'Tomate cheery');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();
        });

        it('does not match unrelated short names (pollo vs polla)', async () => {
            const user = userEvent.setup();
            const onAdd = vi.fn().mockResolvedValue(true);
            const existingItems = [
                { id: 1, name: 'Pollo', is_purchased: false },
            ];

            render(<AddItemInput onAdd={onAdd} isLoading={false} existingItems={existingItems} />);

            await user.type(screen.getByRole('combobox'), 'Polla');
            await user.click(screen.getByRole('button', { name: /añadir/i }));

            expect(screen.queryByTestId('duplicate-warning')).toBeNull();
            expect(onAdd).toHaveBeenCalledWith({ name: 'Polla' });
        });
    });
});
