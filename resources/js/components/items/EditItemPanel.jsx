import React, { useState } from 'react';

const UNITS = [
    { value: '', label: 'Sin unidad' },
    { value: 'kg', label: 'kg' },
    { value: 'g', label: 'g' },
    { value: 'L', label: 'L' },
    { value: 'ml', label: 'ml' },
    { value: 'ud', label: 'ud' },
    { value: 'pack', label: 'pack' },
];

const CATEGORIES = [
    { value: '', label: 'Sin categoría' },
    { value: 'frutas_verduras', label: 'Frutas y verduras' },
    { value: 'carnes_pescados', label: 'Carnes y pescados' },
    { value: 'lacteos_huevos', label: 'Lácteos y huevos' },
    { value: 'panaderia', label: 'Panadería' },
    { value: 'bebidas', label: 'Bebidas' },
    { value: 'congelados', label: 'Congelados' },
    { value: 'limpieza', label: 'Limpieza' },
    { value: 'higiene_personal', label: 'Higiene personal' },
    { value: 'conservas', label: 'Conservas' },
    { value: 'otros', label: 'Otros' },
];

export default function EditItemPanel({ item, onSave, onClose }) {
    const [formData, setFormData] = useState({
        name: item.name,
        quantity: item.quantity || '',
        unit: item.unit || '',
        category: item.category || '',
        estimated_price: item.estimated_price || '',
    });
    const [status, setStatus] = useState('idle');

    const handleChange = (e) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');

        const payload = { name: formData.name };
        if (formData.quantity !== '') payload.quantity = Number(formData.quantity);
        if (formData.unit) payload.unit = formData.unit;
        if (formData.category) payload.category = formData.category;
        if (formData.estimated_price !== '') payload.estimated_price = Number(formData.estimated_price);

        await onSave(item.id, payload);
        onClose();
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50" data-testid="edit-panel">
            <div className="bg-white rounded-t-xl sm:rounded-xl w-full sm:max-w-md p-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Editar item</h3>

                <form onSubmit={handleSubmit} className="space-y-3" data-testid="edit-form">
                    <div>
                        <label htmlFor="edit-name" className="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input type="text" id="edit-name" name="name" value={formData.name} onChange={handleChange} required maxLength={80} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="edit-quantity" className="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                            <input type="number" id="edit-quantity" name="quantity" value={formData.quantity} onChange={handleChange} min="0" step="0.01" className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label htmlFor="edit-unit" className="block text-sm font-medium text-gray-700 mb-1">Unidad</label>
                            <select id="edit-unit" name="unit" value={formData.unit} onChange={handleChange} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                {UNITS.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label htmlFor="edit-category" className="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                        <select id="edit-category" name="category" value={formData.category} onChange={handleChange} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="edit-price" className="block text-sm font-medium text-gray-700 mb-1">Precio estimado</label>
                        <input type="number" id="edit-price" name="estimated_price" value={formData.estimated_price} onChange={handleChange} min="0" step="0.01" className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button type="submit" disabled={status === 'loading'} className="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            {status === 'loading' ? 'Guardando...' : 'Guardar'}
                        </button>
                        <button type="button" onClick={onClose} className="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
