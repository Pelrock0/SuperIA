import React, { useCallback, useEffect, useRef, useState } from 'react';
import { registerCredential, markDeviceRegistered, markPromptDeclined } from '../../lib/webauthnApi';

function deriveDeviceName() {
    if (typeof navigator === 'undefined' || !navigator.userAgent) {
        return 'Mi dispositivo';
    }
    const ua = navigator.userAgent;
    if (/iPhone/i.test(ua)) return 'iPhone';
    if (/iPad/i.test(ua)) return 'iPad';
    if (/Android/i.test(ua)) return 'Android';
    if (/Macintosh/i.test(ua)) return 'Mac';
    if (/Windows/i.test(ua)) return 'Windows';
    if (/Linux/i.test(ua)) return 'Linux';
    return 'Mi dispositivo';
}

export default function BiometricOptInModal({ onClose }) {
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState('');
    const activateButtonRef = useRef(null);

    const dismiss = useCallback(() => {
        if (status === 'loading') return;
        markPromptDeclined();
        onClose?.();
    }, [status, onClose]);

    useEffect(() => {
        activateButtonRef.current?.focus();
    }, []);

    useEffect(() => {
        const handleKey = (e) => {
            if (e.key === 'Escape') dismiss();
        };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [dismiss]);

    const handleActivate = async () => {
        setStatus('loading');
        setError('');
        try {
            await registerCredential(deriveDeviceName());
            markDeviceRegistered();
            onClose?.();
        } catch (err) {
            const message = err?.message || 'No se pudo activar la biometría. Inténtalo de nuevo.';
            setError(message);
            setStatus('error');
        }
    };

    const handleBackdropClick = (e) => {
        if (e.target === e.currentTarget) {
            dismiss();
        }
    };

    return (
        <div
            className="fixed inset-0 bg-black/60 flex items-end sm:items-center justify-center z-50 px-0 sm:px-4"
            data-testid="biometric-optin-modal"
            onClick={handleBackdropClick}
            role="dialog"
            aria-modal="true"
            aria-labelledby="biometric-optin-title"
        >
            <div className="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-xl p-6 sm:p-8 relative">
                <div className="hidden sm:block absolute top-3 right-3">
                    <button
                        type="button"
                        onClick={dismiss}
                        disabled={status === 'loading'}
                        className="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 disabled:opacity-50 text-gray-600 flex items-center justify-center"
                        aria-label="Cerrar"
                        data-testid="biometric-optin-close"
                    >
                        ✕
                    </button>
                </div>

                <div className="mx-auto w-10 h-1 bg-gray-300 rounded-full mb-5 sm:hidden" />

                <div className="text-center">
                    <div className="text-5xl mb-3" aria-hidden="true">🔐</div>
                    <h2
                        id="biometric-optin-title"
                        className="text-xl sm:text-2xl font-bold text-gray-900 mb-2"
                    >
                        ¿Activar biometría en este dispositivo?
                    </h2>
                    <p className="text-sm sm:text-base text-gray-600 mb-6 leading-relaxed">
                        Entra más rápido la próxima vez con Face ID, Touch ID o huella. Sólo funciona con tu propio dispositivo.
                    </p>
                </div>

                {error && (
                    <div
                        className="bg-red-50 text-red-700 p-3 rounded-lg text-sm mb-4"
                        role="alert"
                        data-testid="biometric-optin-error"
                    >
                        {error}
                    </div>
                )}

                <div className="space-y-2">
                    <button
                        ref={activateButtonRef}
                        type="button"
                        onClick={handleActivate}
                        disabled={status === 'loading'}
                        className="w-full text-white py-3 px-4 rounded-lg font-semibold disabled:opacity-60 transition-all hover:opacity-90"
                        style={{ background: 'linear-gradient(to right, #002736, #003e54)' }}
                        data-testid="biometric-optin-activate"
                    >
                        {status === 'loading' ? 'Activando…' : 'Activar ahora'}
                    </button>
                    <button
                        type="button"
                        onClick={dismiss}
                        disabled={status === 'loading'}
                        className="w-full bg-gray-100 text-gray-700 py-3 px-4 rounded-lg font-medium hover:bg-gray-200 disabled:opacity-60 transition-colors"
                        data-testid="biometric-optin-dismiss"
                    >
                        Ahora no
                    </button>
                </div>

                <p className="text-center text-xs text-gray-500 mt-4">
                    Podrás activarla más tarde desde Ajustes → Seguridad.
                </p>
            </div>
        </div>
    );
}
