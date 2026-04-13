import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
    addSharedItem,
    deleteSharedItem,
    fetchSharedList,
    sendHeartbeat,
    toggleSharedItem,
    updateSharedItem,
} from '../lib/sharedListApi';
import ConsentBanner from '../components/collab/ConsentBanner';
import RevokedLinkView from '../components/collab/RevokedLinkView';
import AddItemInput from '../components/items/AddItemInput';
import EditItemPanel from '../components/items/EditItemPanel';

const CATEGORY_LABELS = {
    frutas_verduras: 'Frutas y verduras',
    carnes_pescados: 'Carnes y pescados',
    lacteos_huevos: 'Lacteos y huevos',
    panaderia: 'Panaderia',
    bebidas: 'Bebidas',
    congelados: 'Congelados',
    limpieza: 'Limpieza',
    higiene_personal: 'Higiene personal',
    conservas: 'Conservas',
    otros: 'Otros',
};

const HEARTBEAT_INTERVAL_MS = 10000;

function getSessionStorage() {
    try {
        return typeof window !== 'undefined' ? window.sessionStorage : null;
    } catch {
        return null;
    }
}

function readSessionFlag(key) {
    const store = getSessionStorage();
    if (!store) return null;
    try {
        return store.getItem(key);
    } catch {
        return null;
    }
}

function writeSessionFlag(key, value) {
    const store = getSessionStorage();
    if (!store) return;
    try {
        store.setItem(key, value);
    } catch {
        // ignorar
    }
}

function generateUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    const hex = (n) => n.toString(16).padStart(2, '0');
    const bytes = new Uint8Array(16);
    for (let i = 0; i < 16; i += 1) {
        bytes[i] = Math.floor(Math.random() * 256);
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    return (
        `${hex(bytes[0])}${hex(bytes[1])}${hex(bytes[2])}${hex(bytes[3])}-` +
        `${hex(bytes[4])}${hex(bytes[5])}-` +
        `${hex(bytes[6])}${hex(bytes[7])}-` +
        `${hex(bytes[8])}${hex(bytes[9])}-` +
        `${hex(bytes[10])}${hex(bytes[11])}${hex(bytes[12])}${hex(bytes[13])}${hex(bytes[14])}${hex(bytes[15])}`
    );
}

export default function SharedListPage() {
    const { tokenParam } = useParams();
    const [status, setStatus] = useState('loading');
    const [list, setList] = useState(null);
    const [items, setItems] = useState({});
    const [counters, setCounters] = useState({ items_total: 0, items_completed: 0 });
    const [mode, setMode] = useState('read_only');
    const [consented, setConsented] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [error, setError] = useState('');
    const heartbeatRef = useRef(null);

    const isEdit = mode === 'edit';
    const consentKey = `superia:consent:${tokenParam}`;
    const sessionKey = `superia:session:${tokenParam}`;

    const loadList = useCallback(async () => {
        try {
            const data = await fetchSharedList(tokenParam);
            setList(data.list);
            setItems(data.items || {});
            setCounters(data.counters || { items_total: 0, items_completed: 0 });
            setMode(data.mode);
            setStatus('ready');
        } catch (e) {
            if (e?.response?.status === 410) {
                setStatus('revoked');
            } else {
                setError('Error al cargar la lista.');
                setStatus('error');
            }
        }
    }, [tokenParam]);

    useEffect(() => {
        setConsented(readSessionFlag(consentKey) === '1');
        loadList();
    }, [loadList, consentKey]);

    useEffect(() => {
        if (!consented || status !== 'ready') return undefined;

        let sessionUuid = readSessionFlag(sessionKey);
        if (!sessionUuid) {
            sessionUuid = generateUuid();
            writeSessionFlag(sessionKey, sessionUuid);
        }

        let cancelled = false;

        const beat = async () => {
            try {
                await sendHeartbeat(tokenParam, sessionUuid);
            } catch {
                // ignorar fallos transitorios
            }
        };

        const schedule = () => {
            if (cancelled) return;
            heartbeatRef.current = setTimeout(async () => {
                if (document.visibilityState === 'visible') {
                    await beat();
                }
                schedule();
            }, HEARTBEAT_INTERVAL_MS);
        };

        beat();
        schedule();

        return () => {
            cancelled = true;
            if (heartbeatRef.current) {
                clearTimeout(heartbeatRef.current);
                heartbeatRef.current = null;
            }
        };
    }, [consented, status, tokenParam, sessionKey]);

    const handleConsent = () => {
        writeSessionFlag(consentKey, '1');
        setConsented(true);
    };

    const handleAdd = async (data) => {
        if (!isEdit) return false;
        try {
            await addSharedItem(tokenParam, data);
            await loadList();
            return true;
        } catch {
            setError('Error al anadir el item.');
            return false;
        }
    };

    const handleToggle = async (itemId) => {
        if (!isEdit) return;
        try {
            await toggleSharedItem(tokenParam, itemId);
            await loadList();
        } catch {
            setError('Error al actualizar el item.');
        }
    };

    const handleEdit = async (itemId, data) => {
        if (!isEdit) return;
        try {
            await updateSharedItem(tokenParam, itemId, data);
            await loadList();
        } catch {
            setError('Error al editar el item.');
        }
    };

    const handleDelete = async (item) => {
        if (!isEdit) return;
        try {
            await deleteSharedItem(tokenParam, item.id);
            await loadList();
        } catch {
            setError('Error al eliminar el item.');
        }
    };

    if (status === 'loading') {
        return (
            <div
                data-testid="shared-loading"
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: '#f7f9fb',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div role="status" aria-live="polite" style={{ color: '#71787d' }}>Cargando lista...</div>
            </div>
        );
    }

    if (status === 'revoked') {
        return <RevokedLinkView />;
    }

    if (status === 'error' || !list) {
        return (
            <div
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: '0 16px',
                    background: '#f7f9fb',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div style={{ textAlign: 'center' }}>
                    <p style={{ color: '#41484c', marginBottom: '16px' }}>{error || 'No se pudo cargar la lista.'}</p>
                    <Link to="/" style={{ color: '#00677d', fontWeight: 700, textDecoration: 'none' }}>
                        Ir a Superia
                    </Link>
                </div>
            </div>
        );
    }

    const categoryKeys = Object.keys(items).filter((k) => items[k].length > 0);
    const hasItems = counters.items_total > 0;

    return (
        <div style={{ minHeight: '100vh', background: '#f7f9fb', fontFamily: "'Inter', sans-serif" }}>
            {/* Top AppBar */}
            <header style={{ width: '100%', position: 'sticky', top: 0, zIndex: 50, background: '#f7f9fb' }}>
                {/* URL bar hint */}
                <div
                    style={{
                        background: '#f2f4f6',
                        padding: '6px 16px',
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                        gap: '8px',
                    }}
                >
                    <span className="material-symbols-outlined" style={{ fontSize: '14px', color: '#71787d' }}>lock</span>
                    <span style={{ fontSize: '11px', fontWeight: 500, letterSpacing: '-0.025em', color: '#71787d' }}>
                        superia.io/shared/{tokenParam}
                    </span>
                </div>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        padding: '16px 24px',
                        width: '100%',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div
                            style={{
                                position: 'relative',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                width: '32px',
                                height: '32px',
                                background: '#002736',
                                borderRadius: '12px',
                                overflow: 'hidden',
                            }}
                        >
                            <span style={{ color: '#ffffff', fontWeight: 800, fontSize: '20px', lineHeight: 1 }}>S</span>
                            <div
                                style={{
                                    position: 'absolute',
                                    top: '4px',
                                    right: '4px',
                                    width: '8px',
                                    height: '8px',
                                    background: '#6ffbbe',
                                    borderRadius: '9999px',
                                    boxShadow: '0 1px 2px rgba(0,0,0,0.1)',
                                }}
                            />
                        </div>
                        <h1
                            style={{
                                fontWeight: 700,
                                letterSpacing: '-0.05em',
                                fontSize: '24px',
                                color: '#002736',
                                margin: 0,
                            }}
                        >
                            Superia
                        </h1>
                    </div>
                    <span className="material-symbols-outlined" style={{ color: '#002736' }}>account_circle</span>
                </div>
                {/* Guest mode banner */}
                <div
                    style={{
                        background: '#50d9fe',
                        color: '#005c70',
                        padding: '12px 24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className="material-symbols-outlined" style={{ fontSize: '20px' }}>share</span>
                        <p style={{ fontSize: '12px', fontWeight: 700, letterSpacing: '-0.025em', margin: 0 }}>
                            Lista compartida por {list.owner_name || 'propietario'} · {isEdit ? 'Puedes editar items' : 'Solo puedes marcar items'}
                        </p>
                    </div>
                    {!isEdit && (
                        <span
                            data-testid="read-only-badge"
                            style={{
                                fontSize: '10px',
                                fontWeight: 700,
                                textTransform: 'uppercase',
                                letterSpacing: '0.1em',
                                background: 'rgba(0, 92, 112, 0.15)',
                                padding: '4px 8px',
                                borderRadius: '9999px',
                            }}
                        >
                            Solo lectura
                        </span>
                    )}
                </div>
            </header>

            <main style={{ maxWidth: '672px', margin: '0 auto', padding: '32px 24px 128px' }}>
                {error && (
                    <div
                        role="alert"
                        style={{
                            background: '#ffdad6',
                            color: '#93000a',
                            padding: '12px 16px',
                            borderRadius: '12px',
                            fontSize: '14px',
                            marginBottom: '16px',
                        }}
                    >
                        {error}
                    </div>
                )}

                {/* List Header */}
                <div style={{ marginBottom: '40px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                        <span
                            style={{
                                fontSize: '10px',
                                fontWeight: 700,
                                textTransform: 'uppercase',
                                letterSpacing: '0.1em',
                                color: '#005236',
                                background: '#6ffbbe',
                                padding: '2px 8px',
                                borderRadius: '9999px',
                            }}
                        >
                            {isEdit ? 'Lectura y Edicion' : 'Lectura y Marcado'}
                        </span>
                    </div>
                    <h2
                        style={{
                            fontSize: '36px',
                            fontWeight: 800,
                            letterSpacing: '-0.05em',
                            color: '#002736',
                            margin: '0 0 8px 0',
                        }}
                    >
                        {list.emoji && <span style={{ marginRight: '8px' }}>{list.emoji}</span>}
                        {list.name}
                    </h2>
                    <p style={{ color: '#41484c', fontWeight: 500, margin: 0 }}>
                        {counters.items_completed} de {counters.items_total} items comprados
                    </p>
                </div>

                {isEdit && (
                    <div style={{ marginBottom: '24px' }}>
                        <AddItemInput onAdd={handleAdd} isLoading={false} />
                    </div>
                )}

                {!hasItems ? (
                    <div data-testid="shared-empty" style={{ textAlign: 'center', padding: '32px 0' }}>
                        <div
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                width: '48px',
                                height: '48px',
                                background: '#f2f4f6',
                                borderRadius: '9999px',
                                marginBottom: '16px',
                            }}
                        >
                            <span className="material-symbols-outlined" style={{ color: '#71787d' }}>inventory_2</span>
                        </div>
                        <p style={{ fontSize: '12px', fontWeight: 700, color: '#71787d', textTransform: 'uppercase', letterSpacing: '0.1em', margin: 0 }}>
                            Esta lista esta vacia.
                        </p>
                    </div>
                ) : (
                    categoryKeys.map((category) => (
                        <section key={category} style={{ marginBottom: '40px' }}>
                            {/* Category header */}
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '12px',
                                    marginBottom: '24px',
                                }}
                            >
                                <div
                                    style={{
                                        height: '1px',
                                        flex: 1,
                                        background: 'linear-gradient(to right, transparent, #c1c7cd, transparent)',
                                        opacity: 0.3,
                                    }}
                                />
                                <h3
                                    style={{
                                        fontSize: '11px',
                                        fontWeight: 800,
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.2em',
                                        color: '#71787d',
                                        margin: 0,
                                    }}
                                >
                                    {CATEGORY_LABELS[category] || category}
                                </h3>
                                <div
                                    style={{
                                        height: '1px',
                                        flex: 1,
                                        background: 'linear-gradient(to right, transparent, #c1c7cd, transparent)',
                                        opacity: 0.3,
                                    }}
                                />
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                {items[category].map((item) => (
                                    <div
                                        key={item.id}
                                        data-testid="shared-item-row"
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            padding: '20px',
                                            background: item.is_purchased ? 'rgba(242, 244, 246, 0.5)' : '#ffffff',
                                            borderRadius: '20px',
                                            transition: 'all 0.2s',
                                            cursor: isEdit ? 'pointer' : 'default',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={item.is_purchased}
                                            disabled={!isEdit}
                                            onChange={() => handleToggle(item.id)}
                                            aria-label={`${item.name} (${item.is_purchased ? 'comprado' : 'pendiente'})`}
                                            style={{
                                                width: '24px',
                                                height: '24px',
                                                borderRadius: '8px',
                                                border: `2px solid ${item.is_purchased ? '#002736' : '#c1c7cd'}`,
                                                accentColor: '#002736',
                                                cursor: isEdit ? 'pointer' : 'not-allowed',
                                                opacity: !isEdit ? 0.4 : 1,
                                            }}
                                        />

                                        {isEdit ? (
                                            <button
                                                onClick={() => setEditingItem(item)}
                                                style={{
                                                    flex: 1,
                                                    textAlign: 'left',
                                                    minWidth: 0,
                                                    marginLeft: '16px',
                                                    background: 'none',
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    padding: 0,
                                                    fontFamily: "'Inter', sans-serif",
                                                }}
                                            >
                                                <ItemLabel item={item} />
                                            </button>
                                        ) : (
                                            <div style={{ flex: 1, textAlign: 'left', minWidth: 0, marginLeft: '16px' }}>
                                                <ItemLabel item={item} />
                                            </div>
                                        )}

                                        {isEdit && (
                                            <button
                                                onClick={() => handleDelete(item)}
                                                aria-label={`Eliminar ${item.name}`}
                                                style={{
                                                    color: '#ba1a1a',
                                                    background: 'none',
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    padding: '4px',
                                                    opacity: 0.4,
                                                    fontSize: '20px',
                                                }}
                                            >
                                                <span className="material-symbols-outlined" style={{ fontSize: '20px' }}>close</span>
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </section>
                    ))
                )}

                {/* End of list */}
                <div style={{ textAlign: 'center', padding: '32px 0' }}>
                    <div
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            width: '48px',
                            height: '48px',
                            background: '#f2f4f6',
                            borderRadius: '9999px',
                            marginBottom: '16px',
                        }}
                    >
                        <span className="material-symbols-outlined" style={{ color: '#71787d' }}>inventory_2</span>
                    </div>
                    <p style={{ fontSize: '12px', fontWeight: 700, color: '#71787d', textTransform: 'uppercase', letterSpacing: '0.1em', margin: 0 }}>
                        Fin de la lista compartida
                    </p>
                </div>
            </main>

            {/* Bottom Action Banner */}
            <div
                style={{
                    position: 'fixed',
                    bottom: 0,
                    left: 0,
                    width: '100%',
                    zIndex: 50,
                    background: 'rgba(255, 255, 255, 0.7)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    boxShadow: '0 -4px 24px rgba(0, 39, 54, 0.1)',
                    borderRadius: '32px 32px 0 0',
                    overflow: 'hidden',
                }}
            >
                <div style={{ padding: '24px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '16px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div
                            style={{
                                width: '40px',
                                height: '40px',
                                background: '#003e54',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                borderRadius: '12px',
                            }}
                        >
                            <span className="material-symbols-outlined" style={{ color: '#7aa9c3' }}>storm</span>
                        </div>
                        <p style={{ fontSize: '14px', fontWeight: 700, color: '#002736', margin: 0 }}>
                            ¿Quieres crear tus propias listas? Es gratis
                        </p>
                    </div>
                    <Link
                        to="/"
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: '8px',
                            width: '100%',
                            maxWidth: '672px',
                            background: 'linear-gradient(to right, #002736, #003e54)',
                            color: '#ffffff',
                            fontWeight: 700,
                            padding: '16px',
                            borderRadius: '16px',
                            textDecoration: 'none',
                            boxShadow: '0 8px 20px rgba(0, 39, 54, 0.2)',
                            fontSize: '16px',
                            fontFamily: "'Inter', sans-serif",
                        }}
                    >
                        Empezar con Superia
                        <span className="material-symbols-outlined" style={{ fontSize: '20px' }}>arrow_forward</span>
                    </Link>
                </div>
                <div style={{ height: '16px' }} />
            </div>

            {!consented && <ConsentBanner ownerName={list.owner_name} onAccept={handleConsent} />}

            {isEdit && editingItem && (
                <EditItemPanel
                    item={editingItem}
                    onSave={handleEdit}
                    onClose={() => setEditingItem(null)}
                />
            )}
        </div>
    );
}

function ItemLabel({ item }) {
    return (
        <>
            <span
                style={{
                    display: 'block',
                    fontSize: '18px',
                    fontWeight: 700,
                    color: item.is_purchased ? '#71787d' : '#002736',
                    textDecoration: item.is_purchased ? 'line-through' : 'none',
                    opacity: item.is_purchased ? 0.5 : 1,
                    transition: 'all 0.2s',
                }}
            >
                {item.name}
            </span>
            {(item.quantity || item.estimated_price) && (
                <span style={{ fontSize: '14px', color: '#41484c', fontWeight: 500, opacity: item.is_purchased ? 0.5 : 1 }}>
                    {item.quantity && `${item.quantity}${item.unit || ''}`}
                    {item.quantity && item.estimated_price && ' · '}
                    {item.estimated_price && `~${item.estimated_price}€`}
                </span>
            )}
        </>
    );
}
