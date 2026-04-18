import React from 'react';

export default function SelectListModal({ lists, onSelect, onCancel, productName }) {
    return (
        <div
            className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4"
            data-testid="select-list-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="select-list-title"
        >
            <div className="bg-white rounded-lg shadow-xl max-w-sm w-full p-6">
                <h3 id="select-list-title" className="text-lg font-bold text-gray-900 mb-2">
                    Añadir a que lista?
                </h3>
                {productName && (
                    <p className="text-sm text-gray-600 mb-4">
                        Elige la lista donde quieres añadir <span className="font-medium">{productName}</span>.
                    </p>
                )}

                <ul className="divide-y divide-gray-100 mb-4">
                    {lists.map((list) => (
                        <li key={list.id}>
                            <button
                                type="button"
                                onClick={() => onSelect(list.id)}
                                className="w-full text-left px-3 py-2 hover:bg-indigo-50 rounded flex items-center gap-2"
                                data-testid={`select-list-option-${list.id}`}
                            >
                                {list.emoji && <span>{list.emoji}</span>}
                                <span className="text-sm text-gray-900">{list.name}</span>
                            </button>
                        </li>
                    ))}
                </ul>

                <button
                    type="button"
                    onClick={onCancel}
                    className="w-full bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-300"
                >
                    Cancelar
                </button>
            </div>
        </div>
    );
}
