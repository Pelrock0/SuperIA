import React, { useState } from 'react';

export default function ConfirmPriceModal({ items = [], onConfirm, onDismiss }) {
    const [total, setTotal] = useState('');
    const [showPerItem, setShowPerItem] = useState(false);
    const [itemPrices, setItemPrices] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleItemPrice = (itemId, value) => {
        setItemPrices((prev) => ({ ...prev, [itemId]: value }));
    };

    const handleSubmit = async () => {
        setIsSubmitting(true);
        const perItemData = Object.entries(itemPrices)
            .filter(([, v]) => v !== '' && !isNaN(parseFloat(v)))
            .map(([id, price]) => ({ item_id: parseInt(id, 10), price: parseFloat(price) }));

        await onConfirm(total ? parseFloat(total) : null, perItemData);
        setIsSubmitting(false);
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" data-testid="confirm-price-modal">
            <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6 max-h-[80vh] overflow-y-auto">
                <h2 className="text-lg font-semibold text-gray-900 mb-2">Cuanto pagaste?</h2>
                <p className="text-sm text-gray-500 mb-4">
                    Opcional. Ayuda a mejorar las estimaciones futuras.
                </p>

                <div className="mb-4">
                    <label htmlFor="total-price" className="block text-sm font-medium text-gray-700 mb-1">
                        Total de la compra
                    </label>
                    <div className="flex items-center gap-2">
                        <input
                            type="number"
                            id="total-price"
                            value={total}
                            onChange={(e) => setTotal(e.target.value)}
                            placeholder="0,00"
                            min="0"
                            step="0.01"
                            className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                            data-testid="total-price-input"
                        />
                        <span className="text-gray-500">EUR</span>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={() => setShowPerItem(!showPerItem)}
                    className="text-xs text-indigo-600 hover:text-indigo-700 mb-3"
                    data-testid="toggle-per-item"
                >
                    {showPerItem ? 'Ocultar desglose' : 'Desglosar por producto'}
                </button>

                {showPerItem && (
                    <div className="space-y-2 mb-4" data-testid="per-item-section">
                        {items.map((item) => (
                            <div key={item.id} className="flex items-center gap-2">
                                <span className="text-sm text-gray-700 flex-1 truncate">{item.name}</span>
                                <input
                                    type="number"
                                    value={itemPrices[item.id] || ''}
                                    onChange={(e) => handleItemPrice(item.id, e.target.value)}
                                    placeholder="0,00"
                                    min="0"
                                    step="0.01"
                                    className="w-24 px-2 py-1 border border-gray-300 rounded text-sm text-right"
                                    data-testid={`item-price-${item.id}`}
                                />
                                <span className="text-xs text-gray-400">EUR</span>
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex gap-3 mt-4">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={isSubmitting || (!total && Object.keys(itemPrices).length === 0)}
                        className="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50"
                        data-testid="submit-prices"
                    >
                        {isSubmitting ? 'Guardando...' : 'Guardar'}
                    </button>
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-300"
                        data-testid="dismiss-prices"
                    >
                        Ahora no
                    </button>
                </div>
            </div>
        </div>
    );
}
