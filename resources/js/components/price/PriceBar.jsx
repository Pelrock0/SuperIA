import React, { useState } from 'react';

export default function PriceBar({ estimate, onRecalculate, isCalculating }) {
    const [expanded, setExpanded] = useState(false);

    if (!estimate && !isCalculating) {
        return null;
    }

    const formatPrice = (value) => {
        if (value === null || value === undefined) return 'sin datos';
        return `${Number(value).toFixed(2).replace('.', ',')}€`;
    };

    const hasData = estimate && estimate.resolved_count > 0;

    return (
        <section className="bg-white border border-gray-200 rounded-lg p-4 mt-6" data-testid="price-bar">
            <div className="flex items-center justify-between">
                <div className="flex-1 min-w-0">
                    {isCalculating ? (
                        <p className="text-sm text-gray-500" data-testid="price-calculating">Calculando precios...</p>
                    ) : hasData ? (
                        <button
                            type="button"
                            onClick={() => setExpanded(!expanded)}
                            className="text-left w-full"
                            data-testid="price-toggle"
                        >
                            <p className="text-sm font-semibold text-gray-900">
                                Estimacion: {formatPrice(estimate.total_min)} — {formatPrice(estimate.total_max)}
                            </p>
                            <p className="text-xs text-gray-500 mt-1">
                                {estimate.resolved_count} de {estimate.resolved_count + estimate.unresolved_count} productos con precio
                                {estimate.unresolved_count > 0 && ` · ${estimate.unresolved_count} sin datos`}
                            </p>
                        </button>
                    ) : (
                        <p className="text-sm text-gray-500" data-testid="no-price-data">Sin datos de precio</p>
                    )}
                </div>

                <button
                    type="button"
                    onClick={onRecalculate}
                    disabled={isCalculating}
                    className="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200 disabled:opacity-50 ml-3 flex-shrink-0"
                    data-testid="recalculate-button"
                >
                    {isCalculating ? '...' : 'Recalcular'}
                </button>
            </div>

            {expanded && hasData && (
                <div className="mt-3 pt-3 border-t border-gray-100 space-y-1" data-testid="price-breakdown">
                    {estimate.items.map((item) => (
                        <div key={item.item_id} className="flex justify-between text-xs text-gray-600">
                            <span className="truncate flex-1">{item.name}</span>
                            <span className="ml-2 flex-shrink-0">
                                {item.min !== null
                                    ? item.min === item.max
                                        ? formatPrice(item.min)
                                        : `${formatPrice(item.min)} — ${formatPrice(item.max)}`
                                    : 'sin datos'}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
