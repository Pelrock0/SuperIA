import React from 'react';
import { Link } from 'react-router-dom';

export default function RevokedLinkView() {
    return (
        <div
            className="min-h-screen flex items-center justify-center bg-gray-50 px-4"
            data-testid="revoked-link"
        >
            <div className="max-w-md text-center bg-white rounded-2xl shadow-sm p-8">
                <div className="text-5xl mb-4" aria-hidden="true">🔒</div>
                <h1 className="text-xl font-bold text-gray-900 mb-2">Enlace no disponible</h1>
                <p className="text-sm text-gray-600 mb-6">
                    Este enlace ya no esta activo. Pide uno nuevo al propietario de la lista.
                </p>
                <Link
                    to="/"
                    className="inline-block bg-indigo-600 text-white py-2 px-5 rounded-lg font-medium hover:bg-indigo-700"
                >
                    Ir a Superlistia
                </Link>
            </div>
        </div>
    );
}
