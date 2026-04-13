import React, { useState } from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('../../lib/suggestionsApi', () => ({
    fetchSuggestions: vi.fn(),
}));

import ItemAutocomplete from './ItemAutocomplete';
import { fetchSuggestions } from '../../lib/suggestionsApi';

function ControlledAutocomplete({ onSelect = vi.fn(), initial = '' }) {
    const [value, setValue] = useState(initial);
    return (
        <ItemAutocomplete
            value={value}
            onChange={setValue}
            onSelect={(s) => {
                setValue(s.name);
                onSelect(s);
            }}
        />
    );
}

describe('ItemAutocomplete', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('does not fetch on input with less than 2 chars', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({ suggestions: [], ai_limit_reached: false });

        render(<ControlledAutocomplete />);

        await user.type(screen.getByRole('combobox'), 'l');

        await new Promise((r) => setTimeout(r, 300));
        expect(fetchSuggestions).not.toHaveBeenCalled();
    });

    it('fetches suggestions after 2+ chars', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({
            suggestions: [
                { source: 'history', name: 'Leche entera', quantity: 1, unit: 'L', category: 'lacteos_huevos' },
            ],
            ai_limit_reached: false,
        });

        render(<ControlledAutocomplete />);

        await user.type(screen.getByRole('combobox'), 'le');

        await waitFor(() => {
            expect(fetchSuggestions).toHaveBeenCalledWith('le', { includeAi: false });
            expect(screen.getByTestId('autocomplete-dropdown')).toBeInTheDocument();
            expect(screen.getByText('Leche entera')).toBeInTheDocument();
        });
    });

    it('shows history source badge', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({
            suggestions: [
                { source: 'history', name: 'Leche', quantity: null, unit: null, category: null },
            ],
            ai_limit_reached: false,
        });

        render(<ControlledAutocomplete />);
        await user.type(screen.getByRole('combobox'), 'le');

        await waitFor(() => {
            expect(screen.getByText('Historial')).toBeInTheDocument();
        });
    });

    it('shows ai source badge for ai-sourced suggestions', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({
            suggestions: [
                { source: 'ai', name: 'Xilitol', quantity: null, unit: null, category: null },
            ],
            ai_limit_reached: false,
        });

        render(<ControlledAutocomplete />);
        await user.type(screen.getByRole('combobox'), 'xi');

        await waitFor(() => {
            expect(screen.getByText('IA')).toBeInTheDocument();
        });
    });

    it('hides dropdown when no suggestions', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({ suggestions: [], ai_limit_reached: false });

        render(<ControlledAutocomplete />);
        await user.type(screen.getByRole('combobox'), 'xy');

        await waitFor(() => expect(fetchSuggestions).toHaveBeenCalled());
        expect(screen.queryByTestId('autocomplete-dropdown')).toBeNull();
    });

    it('selects suggestion on click', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        const suggestion = { source: 'catalog', name: 'Pan integral', quantity: 1, unit: 'ud', category: 'panaderia' };
        fetchSuggestions.mockResolvedValue({ suggestions: [suggestion], ai_limit_reached: false });

        render(<ControlledAutocomplete onSelect={onSelect} />);

        await user.type(screen.getByRole('combobox'), 'pa');
        await waitFor(() => screen.getByText('Pan integral'));

        await user.click(screen.getByTestId('autocomplete-option-0'));

        expect(onSelect).toHaveBeenCalledWith(suggestion);
    });

    it('navigates with arrow keys and selects with Enter', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        fetchSuggestions.mockResolvedValue({
            suggestions: [
                { source: 'history', name: 'Uno', quantity: null, unit: null, category: null },
                { source: 'history', name: 'Dos', quantity: null, unit: null, category: null },
                { source: 'history', name: 'Tres', quantity: null, unit: null, category: null },
            ],
            ai_limit_reached: false,
        });

        render(<ControlledAutocomplete onSelect={onSelect} />);
        await user.type(screen.getByRole('combobox'), 'un');
        await waitFor(() => screen.getByTestId('autocomplete-dropdown'));

        await user.keyboard('{ArrowDown}{ArrowDown}{Enter}');

        expect(onSelect).toHaveBeenCalledWith(expect.objectContaining({ name: 'Dos' }));
    });

    it('dismisses on Escape', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({
            suggestions: [
                { source: 'history', name: 'Uno', quantity: null, unit: null, category: null },
            ],
            ai_limit_reached: false,
        });

        render(<ControlledAutocomplete />);
        await user.type(screen.getByRole('combobox'), 'un');
        await waitFor(() => screen.getByTestId('autocomplete-dropdown'));

        await user.keyboard('{Escape}');

        expect(screen.queryByTestId('autocomplete-dropdown')).toBeNull();
    });

    it('ignores out-of-order responses', async () => {
        const user = userEvent.setup();
        let resolveFirst;
        let resolveSecond;
        fetchSuggestions
            .mockImplementationOnce(() => new Promise((r) => { resolveFirst = r; }))
            .mockImplementationOnce(() => new Promise((r) => { resolveSecond = r; }));

        render(<ControlledAutocomplete />);

        await user.type(screen.getByRole('combobox'), 'le');
        await new Promise((r) => setTimeout(r, 200));

        await user.type(screen.getByRole('combobox'), 'ch');
        await new Promise((r) => setTimeout(r, 200));

        resolveSecond({
            suggestions: [{ source: 'history', name: 'Lechuga', quantity: null, unit: null, category: null }],
            ai_limit_reached: false,
        });

        resolveFirst({
            suggestions: [{ source: 'history', name: 'Stale', quantity: null, unit: null, category: null }],
            ai_limit_reached: false,
        });

        await waitFor(() => {
            expect(screen.getByText('Lechuga')).toBeInTheDocument();
        });
        expect(screen.queryByText('Stale')).toBeNull();
    });

    it('has combobox aria attributes', async () => {
        const user = userEvent.setup();
        fetchSuggestions.mockResolvedValue({
            suggestions: [{ source: 'history', name: 'Leche', quantity: null, unit: null, category: null }],
            ai_limit_reached: false,
        });

        render(<ControlledAutocomplete />);
        const input = screen.getByRole('combobox');
        expect(input).toHaveAttribute('aria-autocomplete', 'list');

        await user.type(input, 'le');
        await waitFor(() => expect(input).toHaveAttribute('aria-expanded', 'true'));
    });
});
