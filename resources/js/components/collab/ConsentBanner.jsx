import React from 'react';

export default function ConsentBanner({ ownerName, onAccept }) {
    return (
        <div
            className="fixed inset-0 bg-black/60 flex items-end sm:items-center justify-center z-50 p-4"
            data-testid="consent-banner"
            role="dialog"
            aria-modal="true"
            aria-labelledby="consent-title"
        >
            <div className="bg-white rounded-t-2xl sm:rounded-2xl shadow-xl max-w-md w-full p-6">
                <h2 id="consent-title" className="text-lg font-bold text-gray-900 mb-2">
                    Lista compartida por {ownerName || 'un usuario de Superlistia'}
                </h2>
                <p className="text-sm text-gray-600 mb-4">
                    Al usar esta lista aceptas que registramos tu actividad durante 30 dias solo como proposito
                    de utilidad, no con fines publicitarios. No guardamos tu identidad.
                </p>
                <button
                    onClick={onAccept}
                    className="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-indigo-700"
                >
                    Continuar
                </button>
            </div>
        </div>
    );
}
