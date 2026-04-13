import React, { useCallback, useEffect, useState } from 'react';
import { clearHistory, fetchHistory, forgetProduct } from '../../lib/profileHistoryApi';
import ConfirmClearHistoryModal from './ConfirmClearHistoryModal';

export default function HistoryList() {
    const [items, setItems] = useState([]);
    const [pagination, setPagination] = useState({ page: 1, per_page: 20, total: 0 });
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [showClearConfirm, setShowClearConfirm] = useState(false);
    const [clearLoading, setClearLoading] = useState(false);
    const [forgetLoading, setForgetLoading] = useState(null);

    const loadPage = useCallback(async (page = 1) => {
        setIsLoading(true);
        setError('');
        try {
            const data = await fetchHistory(page);
            setItems(data.items || []);
            setPagination(data.pagination || { page: 1, per_page: 20, total: 0 });
        } catch {
            setError('Error al cargar el historial.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        loadPage(1);
    }, [loadPage]);

    const handleClearAll = async () => {
        setClearLoading(true);
        try {
            await clearHistory();
            setShowClearConfirm(false);
            setItems([]);
            setPagination({ page: 1, per_page: 20, total: 0 });
        } catch {
            setError('Error al eliminar el historial.');
        } finally {
            setClearLoading(false);
        }
    };

    const handleForget = async (productName) => {
        setForgetLoading(productName);
        try {
            await forgetProduct(productName);
            setItems((prev) => prev.filter((item) => item.producto_nombre !== productName));
        } catch {
            setError('Error al olvidar el producto.');
        } finally {
            setForgetLoading(null);
        }
    };

    const formatDate = (iso) => {
        if (!iso) return '';
        try {
            return new Date(iso).toLocaleDateString('es-ES');
        } catch {
            return '';
        }
    };

    return (
        <section
            className="bg-white rounded-lg shadow p-6"
            data-testid="history-section"
            aria-labelledby="history-section-title"
        >
            <div className="flex items-center justify-between mb-4">
                <h2 id="history-section-title" className="text-xl font-semibold text-gray-900">
                    Mi historial de productos
                </h2>
                {items.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setShowClearConfirm(true)}
                        className="text-sm text-red-600 hover:text-red-700"
                        data-testid="clear-history-button"
                    >
                        Limpiar todo
                    </button>
                )}
            </div>

            {error && (
                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm mb-4" role="alert">
                    {error}
                </div>
            )}

            {isLoading ? (
                <p className="text-sm text-gray-500" data-testid="history-loading">Cargando historial...</p>
            ) : items.length === 0 ? (
                <p className="text-sm text-gray-500" data-testid="history-empty">
                    No tienes historial de productos aun.
                </p>
            ) : (
                <ul className="divide-y divide-gray-100" data-testid="history-list">
                    {items.map((item) => (
                        <li
                            key={item.producto_nombre}
                            className="flex items-center justify-between py-3"
                            data-testid={`history-row-${item.producto_nombre}`}
                        >
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-gray-900 truncate">{item.producto_nombre}</p>
                                <p className="text-xs text-gray-500">
                                    Comprado {item.total_count} veces
                                    {item.last_purchased_at && ` · Ultima: ${formatDate(item.last_purchased_at)}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => handleForget(item.producto_nombre)}
                                disabled={forgetLoading === item.producto_nombre}
                                className="text-xs text-gray-500 hover:text-red-600 ml-4 disabled:opacity-50"
                                aria-label={`Olvidar ${item.producto_nombre}`}
                            >
                                {forgetLoading === item.producto_nombre ? 'Olvidando...' : 'Olvidar'}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {pagination.total > pagination.per_page && (
                <div className="flex items-center justify-between mt-4 text-sm">
                    <button
                        type="button"
                        onClick={() => loadPage(pagination.page - 1)}
                        disabled={pagination.page <= 1}
                        className="text-indigo-600 disabled:text-gray-300"
                    >
                        Anterior
                    </button>
                    <span className="text-gray-500">
                        Pagina {pagination.page} de {Math.ceil(pagination.total / pagination.per_page)}
                    </span>
                    <button
                        type="button"
                        onClick={() => loadPage(pagination.page + 1)}
                        disabled={pagination.page * pagination.per_page >= pagination.total}
                        className="text-indigo-600 disabled:text-gray-300"
                    >
                        Siguiente
                    </button>
                </div>
            )}

            {showClearConfirm && (
                <ConfirmClearHistoryModal
                    onConfirm={handleClearAll}
                    onCancel={() => setShowClearConfirm(false)}
                    isLoading={clearLoading}
                />
            )}
        </section>
    );
}
