import React, { useCallback, useEffect, useRef, useState } from 'react';
import { fetchSuggestions } from '../../lib/suggestionsApi';

const FAST_DEBOUNCE_MS = 150;
const AI_DEBOUNCE_MS = 2000;
const MIN_QUERY_LENGTH = 2;
const LOCAL_LOW_THRESHOLD = 3;

export default function ItemAutocomplete({
    value,
    onChange,
    onSelect,
    placeholder = 'Añadir producto...',
    inputId = 'autocomplete-input',
    disabled = false,
}) {
    const [suggestions, setSuggestions] = useState([]);
    const [aiLimitReached, setAiLimitReached] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [isOpen, setIsOpen] = useState(false);

    const latestQueryIdRef = useRef(0);
    const fastTimerRef = useRef(null);
    const aiTimerRef = useRef(null);

    const trimmed = value.trim();
    const listboxId = `${inputId}-listbox`;

    const cancelTimers = useCallback(() => {
        if (fastTimerRef.current) {
            clearTimeout(fastTimerRef.current);
            fastTimerRef.current = null;
        }
        if (aiTimerRef.current) {
            clearTimeout(aiTimerRef.current);
            aiTimerRef.current = null;
        }
    }, []);

    useEffect(() => {
        if (trimmed.length < MIN_QUERY_LENGTH) {
            cancelTimers();
            setSuggestions([]);
            setAiLimitReached(false);
            setIsOpen(false);
            setActiveIndex(-1);
            return undefined;
        }

        const queryId = ++latestQueryIdRef.current;
        const queryText = trimmed;

        cancelTimers();

        fastTimerRef.current = setTimeout(async () => {
            try {
                const data = await fetchSuggestions(queryText, { includeAi: false });
                if (queryId !== latestQueryIdRef.current) return;
                setSuggestions(data.suggestions || []);
                setAiLimitReached(!!data.ai_limit_reached);
                setIsOpen((data.suggestions || []).length > 0);
                setActiveIndex(-1);
            } catch {
                // ignore transient failures
            }
        }, FAST_DEBOUNCE_MS);

        aiTimerRef.current = setTimeout(async () => {
            if (queryId !== latestQueryIdRef.current) return;
            if (suggestions.length >= LOCAL_LOW_THRESHOLD) return;
            try {
                const data = await fetchSuggestions(queryText, { includeAi: true });
                if (queryId !== latestQueryIdRef.current) return;
                setSuggestions(data.suggestions || []);
                setAiLimitReached(!!data.ai_limit_reached);
                setIsOpen((data.suggestions || []).length > 0 || !!data.ai_limit_reached);
            } catch {
                // ignore
            }
        }, AI_DEBOUNCE_MS);

        return () => cancelTimers();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [trimmed]);

    useEffect(() => () => cancelTimers(), [cancelTimers]);

    const handleSelect = (suggestion) => {
        onSelect(suggestion);
        setSuggestions([]);
        setIsOpen(false);
        setActiveIndex(-1);
    };

    const handleKeyDown = (e) => {
        if (!isOpen || suggestions.length === 0) {
            if (e.key === 'Escape') {
                setIsOpen(false);
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex((i) => (i + 1) % suggestions.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex((i) => (i <= 0 ? suggestions.length - 1 : i - 1));
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0) {
                e.preventDefault();
                handleSelect(suggestions[activeIndex]);
            } else {
                // No suggestion selected — close dropdown and let form submit
                setIsOpen(false);
            }
        } else if (e.key === 'Escape') {
            setIsOpen(false);
            setActiveIndex(-1);
        }
    };

    return (
        <div className="relative">
            <input
                type="text"
                id={inputId}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={handleKeyDown}
                onFocus={() => suggestions.length > 0 && setIsOpen(true)}
                onBlur={() => setTimeout(() => setIsOpen(false), 150)}
                placeholder={placeholder}
                maxLength={80}
                disabled={disabled}
                enterKeyHint="send"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded={isOpen}
                aria-controls={listboxId}
                aria-activedescendant={activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                aria-label="Nombre del producto"
            />

            {isOpen && suggestions.length > 0 && (
                <ul
                    id={listboxId}
                    role="listbox"
                    className="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto"
                    data-testid="autocomplete-dropdown"
                >
                    {suggestions.map((s, idx) => (
                        <li
                            id={`${listboxId}-option-${idx}`}
                            key={`${s.source}-${s.name}-${idx}`}
                            role="option"
                            aria-selected={idx === activeIndex}
                            onMouseDown={(e) => {
                                e.preventDefault();
                                handleSelect(s);
                            }}
                            onMouseEnter={() => setActiveIndex(idx)}
                            className={`px-4 py-2 cursor-pointer text-sm flex items-center justify-between ${
                                idx === activeIndex ? 'bg-indigo-50' : 'hover:bg-gray-50'
                            }`}
                            data-testid={`autocomplete-option-${idx}`}
                        >
                            <span className="flex-1 truncate">
                                <span className="font-medium text-gray-900">{s.name}</span>
                                {(s.quantity || s.unit) && (
                                    <span className="text-xs text-gray-500 ml-2">
                                        {s.quantity}{s.unit ? ` ${s.unit}` : ''}
                                    </span>
                                )}
                            </span>
                            <SourceBadge source={s.source} />
                        </li>
                    ))}
                </ul>
            )}

            {isOpen && aiLimitReached && suggestions.length < LOCAL_LOW_THRESHOLD && (
                <p
                    className="absolute z-20 mt-1 w-full text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded px-3 py-1"
                    data-testid="ai-limit-hint"
                >
                    Has alcanzado tu limite diario de sugerencias IA
                </p>
            )}
        </div>
    );
}

function SourceBadge({ source }) {
    if (source === 'history') {
        return <span className="text-xs text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">Historial</span>;
    }
    if (source === 'ai') {
        return <span className="text-xs text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded">IA</span>;
    }
    return <span className="text-xs text-gray-400">&#8203;</span>;
}
