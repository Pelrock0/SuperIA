import React, { useEffect, useState } from 'react';
import { fetchComplements } from '../../lib/complementsApi';

const AUTO_HIDE_MS = 30000;

export default function ComplementaryChip({ productName, listId, onAccept, onDismiss }) {
    const [suggestions, setSuggestions] = useState([]);
    const [dismissed, setDismissed] = useState(false);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            try {
                const data = await fetchComplements(productName, listId);
                if (!cancelled) {
                    setSuggestions(data.suggestions || []);
                    setIsLoading(false);
                }
            } catch {
                if (!cancelled) setIsLoading(false);
            }
        };

        load();

        return () => {
            cancelled = true;
        };
    }, [productName, listId]);

    useEffect(() => {
        if (dismissed || isLoading || suggestions.length === 0) return undefined;

        const timer = setTimeout(() => {
            setDismissed(true);
            if (onDismiss) onDismiss();
        }, AUTO_HIDE_MS);

        return () => clearTimeout(timer);
    }, [dismissed, isLoading, suggestions.length, onDismiss]);

    const handleAccept = (suggestion) => {
        setDismissed(true);
        if (onAccept) onAccept(suggestion);
    };

    const handleDismiss = () => {
        setDismissed(true);
        if (onDismiss) onDismiss();
    };

    if (dismissed || isLoading || suggestions.length === 0) {
        return null;
    }

    return (
        <div
            className="flex flex-wrap items-center gap-2 mt-2 ml-8"
            data-testid="complementary-chip"
        >
            <span className="text-xs text-gray-500">Tambien:</span>
            {suggestions.map((s) => (
                <button
                    key={s.nombre}
                    type="button"
                    onClick={() => handleAccept(s)}
                    className="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full hover:bg-indigo-100"
                    data-testid={`complement-option-${s.nombre}`}
                >
                    <span>+</span>
                    <span>{s.nombre}</span>
                </button>
            ))}
            <button
                type="button"
                onClick={handleDismiss}
                className="text-xs text-gray-400 hover:text-gray-600 ml-1"
                aria-label="Descartar sugerencias complementarias"
            >
                &times;
            </button>
        </div>
    );
}
