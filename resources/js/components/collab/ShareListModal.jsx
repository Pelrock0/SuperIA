import React, { useEffect, useState } from 'react';
import { createShareToken, listShareTokens, revokeShareToken } from '../../lib/shareApi';

export default function ShareListModal({ listId, onClose }) {
    const [tokens, setTokens] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [selectedMode, setSelectedMode] = useState('edit');
    const [generatingMode, setGeneratingMode] = useState(null);
    const [copiedId, setCopiedId] = useState(null);
    const [error, setError] = useState('');

    const loadTokens = async () => {
        try {
            const data = await listShareTokens(listId);
            setTokens(data);
        } catch {
            setError('Error al cargar los enlaces.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => { loadTokens(); }, [listId]);

    const activeToken = tokens.find(t => t.mode === selectedMode);

    const handleGenerate = async () => {
        setGeneratingMode(selectedMode);
        setError('');
        try {
            const token = await createShareToken(listId, selectedMode);
            setTokens(prev => [...prev, token]);
        } catch (e) {
            setError(e?.response?.status === 429 ? 'Demasiadas peticiones.' : 'Error al generar el enlace.');
        } finally {
            setGeneratingMode(null);
        }
    };

    const handleRevoke = async () => {
        if (!activeToken) return;
        try {
            await revokeShareToken(listId, activeToken.id);
            setTokens(prev => prev.filter(t => t.id !== activeToken.id));
        } catch {
            setError('Error al revocar el enlace.');
        }
    };

    const handleCopy = async () => {
        if (!activeToken) return;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(activeToken.url);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = activeToken.url;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            setCopiedId(activeToken.id);
            setTimeout(() => setCopiedId(null), 2000);
        } catch {
            setError('No se pudo copiar.');
        }
    };

    const shareUrl = activeToken?.url || '';
    const shareText = `Te comparto mi lista de compra en Superia: ${shareUrl}`;
    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
    const emailUrl = `mailto:?subject=${encodeURIComponent('Lista compartida - Superia')}&body=${encodeURIComponent(shareText)}`;

    return (
        <>
            {/* Backdrop */}
            <div onClick={onClose} style={{ position: 'fixed', inset: 0, zIndex: 40, background: 'rgba(0,39,54,0.4)', backdropFilter: 'blur(2px)' }} />

            {/* Bottom sheet */}
            <div
                data-testid="share-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="share-modal-title"
                style={{
                    position: 'fixed', bottom: 0, left: '50%', transform: 'translateX(-50%)',
                    zIndex: 50, width: '100%', maxWidth: 480,
                    background: '#ffffff', borderRadius: '32px 32px 0 0',
                    boxShadow: '0 -8px 40px -10px rgba(0,39,54,0.2)',
                    fontFamily: "'Inter', sans-serif",
                    maxHeight: '85vh', overflowY: 'auto',
                }}
            >
                {/* Handle */}
                <div style={{ display: 'flex', justifyContent: 'center', padding: '16px 0 8px' }}>
                    <div style={{ width: 48, height: 6, background: '#c1c7cd', borderRadius: 9999 }} />
                </div>

                <div style={{ padding: '0 24px 32px' }}>
                    <h2 id="share-modal-title" style={{ fontSize: 24, fontWeight: 800, color: '#002736', letterSpacing: '-0.02em', marginBottom: 24 }}>
                        Compartir lista
                    </h2>

                    {error && (
                        <div role="alert" style={{ background: '#ffdad6', color: '#93000a', padding: '10px 14px', borderRadius: 12, fontSize: 13, marginBottom: 16 }}>
                            {error}
                        </div>
                    )}

                    {isLoading ? (
                        <p data-testid="share-loading" style={{ color: '#71787d', fontSize: 14 }}>Cargando enlaces...</p>
                    ) : (
                        <>
                            {/* Link section */}
                            <div style={{ marginBottom: 24 }}>
                                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#71787d', marginBottom: 8 }}>
                                    Enlace de acceso
                                </label>

                                {activeToken ? (
                                    <div style={{
                                        display: 'flex', alignItems: 'center', gap: 8,
                                        background: '#f2f4f6', borderRadius: 16, padding: '14px 16px',
                                    }}>
                                        <span style={{ flex: 1, fontSize: 14, color: '#191c1e', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {activeToken.url.replace(/^https?:\/\//, '')}
                                        </span>
                                        <button
                                            onClick={handleCopy}
                                            style={{
                                                width: 40, height: 40, borderRadius: 12, border: 'none', cursor: 'pointer',
                                                background: '#002736', color: '#ffffff',
                                                display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                                            }}
                                        >
                                            <span className="material-symbols-outlined" style={{ fontSize: 20 }}>
                                                {copiedId === activeToken.id ? 'check' : 'content_copy'}
                                            </span>
                                        </button>
                                    </div>
                                ) : (
                                    <button
                                        onClick={handleGenerate}
                                        disabled={!!generatingMode}
                                        style={{
                                            width: '100%', padding: '14px 16px', borderRadius: 16,
                                            background: '#f2f4f6', border: '2px dashed #c1c7cd',
                                            color: '#41484c', fontSize: 14, fontWeight: 600, cursor: 'pointer',
                                        }}
                                    >
                                        {generatingMode ? 'Generando...' : 'Generar enlace'}
                                    </button>
                                )}
                            </div>

                            {/* Share buttons */}
                            {activeToken && (
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 24 }}>
                                    <a
                                        href={whatsappUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        data-testid="share-whatsapp"
                                        style={{
                                            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10,
                                            padding: '14px 16px', borderRadius: 16,
                                            background: '#ffffff', border: '1px solid #e6e8ea',
                                            textDecoration: 'none', color: '#191c1e', fontWeight: 600, fontSize: 14,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#25D366">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                        WhatsApp
                                    </a>
                                    <a
                                        href={emailUrl}
                                        data-testid="share-email"
                                        style={{
                                            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10,
                                            padding: '14px 16px', borderRadius: 16,
                                            background: '#ffffff', border: '1px solid #e6e8ea',
                                            textDecoration: 'none', color: '#191c1e', fontWeight: 600, fontSize: 14,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <span className="material-symbols-outlined" style={{ color: '#71787d' }}>mail</span>
                                        Email
                                    </a>
                                </div>
                            )}

                            {/* Permissions toggle */}
                            <div style={{ marginBottom: 24 }}>
                                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#71787d', marginBottom: 8 }}>
                                    Permisos
                                </label>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 0, background: '#f2f4f6', borderRadius: 12, padding: 4 }}>
                                    <button
                                        onClick={() => setSelectedMode('edit')}
                                        style={{
                                            padding: '12px 16px', borderRadius: 10, border: 'none', cursor: 'pointer',
                                            fontWeight: 700, fontSize: 14,
                                            background: selectedMode === 'edit' ? '#ffffff' : 'transparent',
                                            color: selectedMode === 'edit' ? '#002736' : '#71787d',
                                            boxShadow: selectedMode === 'edit' ? '0 2px 8px rgba(0,39,54,0.08)' : 'none',
                                        }}
                                    >
                                        Puede editar
                                    </button>
                                    <button
                                        onClick={() => setSelectedMode('read_only')}
                                        style={{
                                            padding: '12px 16px', borderRadius: 10, border: 'none', cursor: 'pointer',
                                            fontWeight: 700, fontSize: 14,
                                            background: selectedMode === 'read_only' ? '#ffffff' : 'transparent',
                                            color: selectedMode === 'read_only' ? '#002736' : '#71787d',
                                            boxShadow: selectedMode === 'read_only' ? '0 2px 8px rgba(0,39,54,0.08)' : 'none',
                                        }}
                                    >
                                        Solo puede ver
                                    </button>
                                </div>
                            </div>

                            {/* Revoke */}
                            {activeToken && (
                                <>
                                    <div style={{ borderTop: '1px solid #e6e8ea', marginBottom: 16 }} />
                                    <button
                                        onClick={handleRevoke}
                                        data-testid="revoke-button"
                                        style={{
                                            width: '100%', textAlign: 'center',
                                            fontSize: 14, fontWeight: 700, color: '#ba1a1a',
                                            background: 'none', border: 'none', cursor: 'pointer', padding: 8,
                                        }}
                                    >
                                        Revocar enlace
                                    </button>
                                </>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
