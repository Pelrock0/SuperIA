import React from 'react';

export default function EmptyState({ onCreateClick }) {
    return (
        <div className="text-center py-16" data-testid="empty-state">
            <div className="text-6xl mb-4">🛒</div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Bienvenido a Superlistia</h2>
            <p className="text-gray-600 mb-8">Crea tu primera lista de compra para empezar.</p>
            <button
                onClick={onCreateClick}
                className="bg-indigo-600 text-white py-3 px-8 rounded-lg font-medium hover:bg-indigo-700 transition-colors"
            >
                Crear mi primera lista
            </button>
        </div>
    );
}
