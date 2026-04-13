import React, { useState } from 'react';

const CATEGORIES = [
    { value: '', label: 'Sin categoria' },
    { value: 'supermercado', label: 'Supermercado' },
    { value: 'mercado', label: 'Mercado' },
    { value: 'online', label: 'Online' },
    { value: 'farmacia', label: 'Farmacia' },
    { value: 'otro', label: 'Otro' },
];

const EMOJIS = ['', '🛒', '🏪', '💊', '🛍️', '🧺', '🍎', '🥩', '🧹'];

export default function CreateListModal({ onClose, onSubmit, error }) {
    const [formData, setFormData] = useState({
        name: '',
        emoji: '',
        category: '',
    });
    const [status, setStatus] = useState('idle');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');

        const payload = { name: formData.name };
        if (formData.emoji) payload.emoji = formData.emoji;
        if (formData.category) payload.category = formData.category;

        const success = await onSubmit(payload);
        if (!success) setStatus('error');
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" data-testid="create-modal">
            <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h2 className="text-xl font-bold text-gray-900 mb-4">Nueva lista</h2>

                <form onSubmit={handleSubmit} className="space-y-4" data-testid="create-form">
                    {error && (
                        <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm" role="alert">
                            {error}
                        </div>
                    )}

                    <div>
                        <label htmlFor="list-name" className="block text-sm font-medium text-gray-700 mb-1">
                            Nombre *
                        </label>
                        <input
                            type="text"
                            id="list-name"
                            value={formData.name}
                            onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                            required
                            maxLength={60}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Ej: Compra semanal"
                            autoFocus
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Emoji</label>
                        <div className="flex gap-2 flex-wrap">
                            {EMOJIS.map((emoji) => (
                                <button
                                    key={emoji || 'none'}
                                    type="button"
                                    onClick={() => setFormData((prev) => ({ ...prev, emoji }))}
                                    className={`w-10 h-10 rounded-lg border-2 text-lg flex items-center justify-center transition-colors ${
                                        formData.emoji === emoji
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    }`}
                                    aria-label={emoji || 'Sin emoji'}
                                >
                                    {emoji || '—'}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label htmlFor="list-category" className="block text-sm font-medium text-gray-700 mb-1">
                            Categoria
                        </label>
                        <select
                            id="list-category"
                            value={formData.category}
                            onChange={(e) => setFormData((prev) => ({ ...prev, category: e.target.value }))}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            {CATEGORIES.map((cat) => (
                                <option key={cat.value} value={cat.value}>
                                    {cat.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button
                            type="submit"
                            disabled={status === 'loading'}
                            className="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {status === 'loading' ? 'Creando...' : 'Crear lista'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-300 transition-colors"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
