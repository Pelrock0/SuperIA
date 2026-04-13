import React from 'react';

const UNIT_LABELS = { kg: 'kg', g: 'g', L: 'L', ml: 'ml', ud: 'ud', pack: 'pack' };

export default function ItemRow({ item, onToggle, onEdit, onDelete }) {
    return (
        <div
            className={`flex items-center gap-3 py-2 px-3 rounded-lg hover:bg-gray-50 group ${
                item.is_purchased ? 'opacity-60' : ''
            }`}
            data-testid="item-row"
        >
            <input
                type="checkbox"
                checked={item.is_purchased}
                onChange={() => onToggle(item.id)}
                className="h-5 w-5 text-indigo-600 border-gray-300 rounded cursor-pointer"
                aria-label={`Marcar ${item.name} como ${item.is_purchased ? 'pendiente' : 'comprado'}`}
            />

            <button
                onClick={() => onEdit(item)}
                className="flex-1 text-left min-w-0"
            >
                <span className={`block truncate ${item.is_purchased ? 'line-through text-gray-400' : 'text-gray-900'}`}>
                    {item.name}
                </span>
                {(item.quantity || item.estimated_price) && (
                    <span className="text-xs text-gray-500">
                        {item.quantity && `${item.quantity}${item.unit ? UNIT_LABELS[item.unit] || item.unit : ''}`}
                        {item.quantity && item.estimated_price && ' · '}
                        {item.estimated_price && `~${item.estimated_price}€`}
                    </span>
                )}
            </button>

            <button
                onClick={() => onDelete(item)}
                className="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity p-1"
                aria-label={`Eliminar ${item.name}`}
            >
                ✕
            </button>
        </div>
    );
}
