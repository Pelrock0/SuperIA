import React from 'react';

export default function DuplicateWarning({ matchedName, onAddAnyway, onIncrement, isLoading }) {
    return (
        <div
            className="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2"
            data-testid="duplicate-warning"
            role="alert"
        >
            <p className="text-sm text-amber-900 mb-2">
                Ya tienes <strong>{matchedName}</strong> en la lista.
            </p>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={onAddAnyway}
                    disabled={isLoading}
                    className="text-xs bg-white border border-amber-300 text-amber-800 px-3 py-1 rounded hover:bg-amber-100 disabled:opacity-50"
                    data-testid="add-anyway"
                >
                    Anadir de todas formas
                </button>
                <button
                    type="button"
                    onClick={onIncrement}
                    disabled={isLoading}
                    className="text-xs bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700 disabled:opacity-50"
                    data-testid="increment-quantity"
                >
                    Incrementar cantidad
                </button>
            </div>
        </div>
    );
}
