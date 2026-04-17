import React, { useEffect, useState } from 'react';
import {
    isSupported as isWebauthnSupported,
    listCredentials,
    registerCredential,
    renameCredential,
    deleteCredential,
    markDeviceRegistered,
} from '../../lib/webauthnApi';

export default function WebauthnCredentialsList() {
    const [credentials, setCredentials] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [registering, setRegistering] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [editingName, setEditingName] = useState('');
    const [available, setAvailable] = useState(true);

    const load = async () => {
        setLoading(true);
        try {
            const data = await listCredentials();
            setCredentials(data);
            setAvailable(true);
        } catch (err) {
            if (err.response?.status === 404) {
                setAvailable(false);
            } else {
                setError('No se pudo cargar la lista de dispositivos.');
            }
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!isWebauthnSupported()) {
            setAvailable(false);
            setLoading(false);
            return;
        }
        load();
    }, []);

    const handleAdd = async () => {
        setError('');
        setRegistering(true);
        try {
            const name = defaultDeviceName();
            await registerCredential(name);
            markDeviceRegistered();
            await load();
        } catch (err) {
            setError(err.message || 'No se pudo registrar el dispositivo.');
        } finally {
            setRegistering(false);
        }
    };

    const handleRenameStart = (cred) => {
        setEditingId(cred.id);
        setEditingName(cred.name);
    };

    const handleRenameSave = async (id) => {
        const trimmed = editingName.trim();
        if (trimmed.length < 1 || trimmed.length > 50) {
            setError('El nombre debe tener entre 1 y 50 caracteres.');
            return;
        }
        try {
            await renameCredential(id, trimmed);
            setEditingId(null);
            setEditingName('');
            await load();
        } catch {
            setError('No se pudo renombrar el dispositivo.');
        }
    };

    const handleDelete = async (id, name) => {
        if (!window.confirm(`¿Revocar "${name}"? No podrás usar este dispositivo para entrar.`)) {
            return;
        }
        try {
            await deleteCredential(id);
            await load();
        } catch {
            setError('No se pudo revocar el dispositivo.');
        }
    };

    if (!available) {
        return null;
    }

    return (
        <section data-testid="webauthn-credentials-section" className="mt-8">
            <h3 className="text-lg font-semibold mb-2" style={{ color: '#191c1e' }}>
                Dispositivos biométricos
            </h3>
            <p className="text-sm mb-4" style={{ color: '#41484c' }}>
                Usa tu huella, Face ID o Windows Hello para entrar sin contraseña desde dispositivos confiables.
            </p>

            {error && (
                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm mb-3" role="alert">
                    {error}
                </div>
            )}

            {loading ? (
                <div className="text-sm" style={{ color: '#71787d' }}>Cargando...</div>
            ) : credentials.length === 0 ? (
                <div className="p-4 rounded-lg" style={{ backgroundColor: '#f2f4f6', border: '1px dashed #c1c7cd' }}>
                    <p className="text-sm mb-3" style={{ color: '#41484c' }}>
                        No tienes dispositivos biométricos registrados.
                    </p>
                    <button
                        type="button"
                        onClick={handleAdd}
                        disabled={registering}
                        className="px-4 py-2 rounded-lg font-semibold text-white disabled:opacity-50"
                        style={{ backgroundColor: '#002736' }}
                        data-testid="webauthn-add-first"
                    >
                        {registering ? 'Registrando...' : 'Añadir mi primer dispositivo'}
                    </button>
                </div>
            ) : (
                <div className="space-y-2">
                    {credentials.map((cred) => (
                        <div
                            key={cred.id}
                            className="flex items-center gap-3 p-3 rounded-lg"
                            style={{ backgroundColor: '#f2f4f6', border: '1px solid #c1c7cd' }}
                            data-testid={`webauthn-credential-${cred.id}`}
                        >
                            <span className="material-symbols-outlined" aria-hidden="true" style={{ color: '#002736' }}>
                                fingerprint
                            </span>
                            <div className="flex-1 min-w-0">
                                {editingId === cred.id ? (
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            value={editingName}
                                            onChange={(e) => setEditingName(e.target.value)}
                                            maxLength={50}
                                            className="flex-1 px-2 py-1 rounded text-sm border"
                                            style={{ borderColor: '#c1c7cd', backgroundColor: '#ffffff' }}
                                            aria-label="Nuevo nombre"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => handleRenameSave(cred.id)}
                                            className="text-sm font-semibold px-2 py-1"
                                            style={{ color: '#002736' }}
                                        >
                                            Guardar
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setEditingId(null); setEditingName(''); }}
                                            className="text-sm px-2 py-1"
                                            style={{ color: '#71787d' }}
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                ) : (
                                    <>
                                        <div className="font-medium truncate" style={{ color: '#191c1e' }}>{cred.name}</div>
                                        <div className="text-xs" style={{ color: '#71787d' }}>
                                            {cred.last_used_at
                                                ? `Usado por última vez: ${new Date(cred.last_used_at).toLocaleDateString('es-ES')}`
                                                : 'Nunca usado aún'}
                                        </div>
                                    </>
                                )}
                            </div>
                            {editingId !== cred.id && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => handleRenameStart(cred)}
                                        className="text-sm px-2 py-1"
                                        style={{ color: '#41484c' }}
                                        aria-label={`Renombrar ${cred.name}`}
                                    >
                                        Renombrar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(cred.id, cred.name)}
                                        className="text-sm px-2 py-1"
                                        style={{ color: '#ba1a1a' }}
                                        aria-label={`Revocar ${cred.name}`}
                                        data-testid={`webauthn-revoke-${cred.id}`}
                                    >
                                        Revocar
                                    </button>
                                </>
                            )}
                        </div>
                    ))}
                    <button
                        type="button"
                        onClick={handleAdd}
                        disabled={registering}
                        className="mt-2 px-4 py-2 rounded-lg font-semibold disabled:opacity-50"
                        style={{ border: '1px solid #002736', color: '#002736' }}
                        data-testid="webauthn-add-another"
                    >
                        {registering ? 'Registrando...' : 'Añadir otro dispositivo'}
                    </button>
                </div>
            )}
        </section>
    );
}

function defaultDeviceName() {
    const ua = typeof navigator !== 'undefined' ? navigator.userAgent : '';
    let platform = 'Dispositivo';
    if (/iPhone/.test(ua)) platform = 'iPhone';
    else if (/iPad/.test(ua)) platform = 'iPad';
    else if (/Android/.test(ua)) platform = 'Android';
    else if (/Mac OS X/.test(ua)) platform = 'Mac';
    else if (/Windows/.test(ua)) platform = 'Windows';

    let browser = '';
    if (/Edg\//.test(ua)) browser = 'Edge';
    else if (/Chrome\//.test(ua)) browser = 'Chrome';
    else if (/Firefox\//.test(ua)) browser = 'Firefox';
    else if (/Safari\//.test(ua)) browser = 'Safari';

    return browser ? `${platform} — ${browser}` : platform;
}
