import React from 'react';

export default function ConfirmClearHistoryModal({ onConfirm, onCancel, isLoading }) {
    return (
        <div
            className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4"
            data-testid="clear-history-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="clear-history-title"
        >
            <div className="bg-white rounded-lg shadow-xl max-w-sm w-full p-6">
                <h3 id="clear-history-title" className="text-lg font-bold text-gray-900 mb-2">
                    Eliminar historial completo
                </h3>
                <p className="text-sm text-gray-600 mb-4">
                    Se eliminara tu historial completo. Esta accion no se puede deshacer.
                </p>
                <div className="flex gap-3">
                    <button
                        onClick={onConfirm}
                        disabled={isLoading}
                        className="flex-1 bg-red-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-700 disabled:opacity-50"
                    >
                        {isLoading ? 'Eliminando...' : 'Eliminar todo'}
                    </button>
                    <button
                        onClick={onCancel}
                        disabled={isLoading}
                        className="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-300"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    );
}
