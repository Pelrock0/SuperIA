import React, { useState } from 'react';
import ItemAutocomplete from './ItemAutocomplete';
import DuplicateWarning from './DuplicateWarning';
import similarText from '../../lib/similarText';

const DUPLICATE_THRESHOLD = 0.80;

export default function AddItemInput({ onAdd, onIncrementExisting, isLoading, existingItems = [] }) {
    const [name, setName] = useState('');
    const [prefilled, setPrefilled] = useState(null);
    const [duplicateMatch, setDuplicateMatch] = useState(null);
    const [pendingPayload, setPendingPayload] = useState(null);

    const findDuplicate = (newName) => {
        const trimmed = newName.trim();
        if (trimmed === '') return null;
        for (const item of existingItems) {
            if (similarText(trimmed, item.name) > DUPLICATE_THRESHOLD) {
                return item;
            }
        }
        return null;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;

        const payload = { name: name.trim() };
        if (prefilled) {
            if (prefilled.quantity != null) payload.quantity = Number(prefilled.quantity);
            if (prefilled.unit) payload.unit = prefilled.unit;
            if (prefilled.category) payload.category = prefilled.category;
        }

        const match = findDuplicate(payload.name);
        if (match) {
            setDuplicateMatch(match);
            setPendingPayload(payload);
            return;
        }

        const success = await onAdd(payload);
        if (success) {
            setName('');
            setPrefilled(null);
        }
    };

    const handleAddAnyway = async () => {
        if (!pendingPayload) return;
        setDuplicateMatch(null);
        const success = await onAdd(pendingPayload);
        if (success) {
            setName('');
            setPrefilled(null);
            setPendingPayload(null);
        }
    };

    const handleIncrement = async () => {
        if (!duplicateMatch || !pendingPayload) return;
        setDuplicateMatch(null);
        const qty = pendingPayload.quantity ?? 1;
        if (onIncrementExisting) {
            await onIncrementExisting(duplicateMatch.id, qty);
        }
        setName('');
        setPrefilled(null);
        setPendingPayload(null);
    };

    const handleSelect = (suggestion) => {
        setName(suggestion.name);
        setPrefilled({
            name: suggestion.name,
            quantity: suggestion.quantity,
            unit: suggestion.unit,
            category: suggestion.category,
        });
    };

    const handleChange = (next) => {
        setName(next);
        if (prefilled && next !== prefilled.name) {
            setPrefilled(null);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex gap-2 items-start" data-testid="add-item-form">
            <div className="flex-1">
                <ItemAutocomplete
                    value={name}
                    onChange={handleChange}
                    onSelect={handleSelect}
                    inputId="add-item-input"
                />
                {prefilled && (prefilled.unit || prefilled.quantity || prefilled.category) && (
                    <p className="text-xs text-gray-500 mt-1" data-testid="prefilled-hint">
                        {prefilled.quantity}{prefilled.unit ? ` ${prefilled.unit}` : ''}
                        {prefilled.category ? ` · ${prefilled.category.replace('_', ' ')}` : ''}
                    </p>
                )}
            </div>
            <button
                type="submit"
                disabled={isLoading || !name.trim()}
                className="bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors shrink-0 flex items-center justify-center w-10 h-10 sm:w-auto sm:h-auto sm:px-4 sm:py-2"
                aria-label="Anadir producto"
            >
                <span className="material-symbols-outlined sm:hidden" style={{ fontSize: '20px' }}>add</span>
                <span className="hidden sm:inline">{isLoading ? '...' : 'Anadir'}</span>
            </button>

            {duplicateMatch && (
                <DuplicateWarning
                    matchedName={duplicateMatch.name}
                    onAddAnyway={handleAddAnyway}
                    onIncrement={handleIncrement}
                    isLoading={isLoading}
                />
            )}
        </form>
    );
}
