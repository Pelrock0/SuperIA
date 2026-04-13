import React, { useCallback, useEffect, useState } from 'react';
import {
    acceptReplenishment,
    fetchReplenishmentSuggestions,
    ignoreReplenishment,
    silenceReplenishment,
} from '../../lib/replenishmentApi';
import SelectListModal from './SelectListModal';

export default function ReplenishmentBanner({ activeLists = [], onAction }) {
    const [suggestions, setSuggestions] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [pendingAccept, setPendingAccept] = useState(null);
    const [actionInProgress, setActionInProgress] = useState(null);

    const load = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await fetchReplenishmentSuggestions();
            setSuggestions(data);
            setError('');
        } catch {
            setError('Error al cargar las sugerencias.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const removeFromList = (productoNombre) => {
        setSuggestions((prev) => prev.filter((s) => s.producto_nombre !== productoNombre));
    };

    const handleAcceptDirect = async (productoNombre, listId) => {
        setActionInProgress(productoNombre);
        try {
            await acceptReplenishment(productoNombre, listId);
            removeFromList(productoNombre);
            if (onAction) await onAction();
        } catch {
            setError('Error al anadir el producto.');
        } finally {
            setActionInProgress(null);
        }
    };

    const handleAcceptClick = (productoNombre) => {
        if (activeLists.length === 0) return;
        if (activeLists.length === 1) {
            handleAcceptDirect(productoNombre, activeLists[0].id);
            return;
        }
        setPendingAccept(productoNombre);
    };

    const handleSelectList = async (listId) => {
        const productoNombre = pendingAccept;
        setPendingAccept(null);
        if (productoNombre) {
            await handleAcceptDirect(productoNombre, listId);
        }
    };

    const handleIgnore = async (productoNombre) => {
        setActionInProgress(productoNombre);
        try {
            await ignoreReplenishment(productoNombre);
            removeFromList(productoNombre);
        } catch {
            setError('Error al ignorar la sugerencia.');
        } finally {
            setActionInProgress(null);
        }
    };

    const handleSilence = async (productoNombre) => {
        setActionInProgress(productoNombre);
        try {
            await silenceReplenishment(productoNombre);
            removeFromList(productoNombre);
        } catch {
            setError('Error al silenciar el producto.');
        } finally {
            setActionInProgress(null);
        }
    };

    if (isLoading) {
        return null;
    }

    if (suggestions.length === 0 && !error) {
        return null;
    }

    return (
        <section
            className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6"
            data-testid="replenishment-banner"
            aria-labelledby="replenishment-title"
        >
            <h2 id="replenishment-title" className="text-sm font-semibold text-amber-900 mb-3">
                Reposicion sugerida
            </h2>

            {error && (
                <div className="bg-red-50 text-red-700 p-2 rounded text-sm mb-3" role="alert">
                    {error}
                </div>
            )}

            <div className="space-y-2">
                {suggestions.map((s) => (
                    <div
                        key={s.producto_nombre}
                        className="bg-white border border-amber-100 rounded-lg p-3"
                        data-testid={`replenishment-card-${s.producto_nombre}`}
                    >
                        <div className="flex items-start justify-between gap-2 mb-2">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-gray-900 truncate">{s.producto_nombre}</p>
                                <p className="text-xs text-gray-500">{s.frequency_label}</p>
                                <p className="text-xs text-gray-400">
                                    Hace {s.days_since_last} {s.days_since_last === 1 ? 'dia' : 'dias'}
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-2 flex-wrap">
                            <button
                                type="button"
                                onClick={() => handleAcceptClick(s.producto_nombre)}
                                disabled={actionInProgress === s.producto_nombre || activeLists.length === 0}
                                className="text-xs bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 disabled:opacity-50"
                                data-testid={`accept-${s.producto_nombre}`}
                            >
                                Anadir
                            </button>
                            <button
                                type="button"
                                onClick={() => handleIgnore(s.producto_nombre)}
                                disabled={actionInProgress === s.producto_nombre}
                                className="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200 disabled:opacity-50"
                                data-testid={`ignore-${s.producto_nombre}`}
                            >
                                Ignorar
                            </button>
                            <button
                                type="button"
                                onClick={() => handleSilence(s.producto_nombre)}
                                disabled={actionInProgress === s.producto_nombre}
                                className="text-xs bg-red-50 text-red-600 px-3 py-1 rounded hover:bg-red-100 disabled:opacity-50"
                                data-testid={`silence-${s.producto_nombre}`}
                            >
                                Silenciar
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            {pendingAccept && (
                <SelectListModal
                    lists={activeLists}
                    productName={pendingAccept}
                    onSelect={handleSelectList}
                    onCancel={() => setPendingAccept(null)}
                />
            )}
        </section>
    );
}
