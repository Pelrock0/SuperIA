import React, { useState } from 'react';
import { Link } from 'react-router-dom';

export default function ListCard({ list, onArchive, onRestore, onDelete }) {
    const [showMenu, setShowMenu] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const isArchived = list.status === 'archived';

    const handleAction = (action) => {
        setShowMenu(false);
        if (action === 'delete') {
            setShowDeleteConfirm(true);
        } else if (action === 'archive') {
            onArchive(list.id);
        } else if (action === 'restore') {
            onRestore(list.id);
        }
    };

    const confirmDelete = () => {
        setShowDeleteConfirm(false);
        onDelete(list.id);
    };

    const categoryLabels = {
        supermercado: 'Supermercado',
        mercado: 'Mercado',
        online: 'Online',
        farmacia: 'Farmacia',
        otro: 'Otro',
    };

    return (
        <div className="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow" data-testid="list-card">
            <div className="flex items-start justify-between">
                <Link to={`/app/listas/${list.id}`} className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                        {list.emoji && <span className="text-xl">{list.emoji}</span>}
                        <h3 className="font-semibold text-gray-900 truncate">{list.name}</h3>
                    </div>
                    {list.category && (
                        <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                            {categoryLabels[list.category] || list.category}
                        </span>
                    )}
                    <div className="mt-2 text-sm text-gray-500">
                        {list.items_completed} de {list.items_total} items
                    </div>
                    <div className="mt-1 text-xs text-gray-400">
                        {new Date(list.updated_at).toLocaleDateString('es-ES')}
                    </div>
                </Link>

                <div className="relative ml-2">
                    <button
                        onClick={() => setShowMenu(!showMenu)}
                        className="p-1 text-gray-400 hover:text-gray-600 rounded"
                        aria-label="Opciones de lista"
                    >
                        &#8942;
                    </button>
                    {showMenu && (
                        <div className="absolute right-0 mt-1 w-40 bg-white border rounded-lg shadow-lg z-10" data-testid="list-menu">
                            {isArchived ? (
                                <button
                                    onClick={() => handleAction('restore')}
                                    className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Restaurar
                                </button>
                            ) : (
                                <button
                                    onClick={() => handleAction('archive')}
                                    className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Archivar
                                </button>
                            )}
                            <button
                                onClick={() => handleAction('delete')}
                                className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                            >
                                Eliminar
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {showDeleteConfirm && (
                <div className="mt-3 p-3 bg-red-50 rounded-lg" data-testid="delete-confirm">
                    <p className="text-sm text-red-700 mb-2">
                        Esta accion eliminara la lista permanentemente. Continuar?
                        {list.is_shared && ' Esta lista esta compartida. Los colaboradores perderan el acceso.'}
                    </p>
                    <div className="flex gap-2">
                        <button
                            onClick={confirmDelete}
                            className="bg-red-600 text-white px-3 py-1 rounded text-sm font-medium hover:bg-red-700"
                        >
                            Eliminar
                        </button>
                        <button
                            onClick={() => setShowDeleteConfirm(false)}
                            className="bg-gray-200 text-gray-700 px-3 py-1 rounded text-sm hover:bg-gray-300"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
