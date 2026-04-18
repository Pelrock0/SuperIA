import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import api from '../lib/api';
import AddItemInput from '../components/items/AddItemInput';
import AddItemModal from '../components/items/AddItemModal';
import ItemRow from '../components/items/ItemRow';
import EditItemPanel from '../components/items/EditItemPanel';
import UndoSnackbar from '../components/items/UndoSnackbar';
import ShareListModal from '../components/collab/ShareListModal';
import CollaboratorIndicator from '../components/collab/CollaboratorIndicator';
import ActivityLogView from '../components/collab/ActivityLogView';
import ComplementaryChip from '../components/items/ComplementaryChip';
import PriceBar from '../components/price/PriceBar';
import ConfirmPriceModal from '../components/price/ConfirmPriceModal';
import { estimatePrices, confirmPrices } from '../lib/priceApi';

const CATEGORY_LABELS = {
    frutas_verduras: 'Frutas y verduras',
    carnes_pescados: 'Carnes y pescados',
    lacteos_huevos: 'Lácteos y huevos',
    panaderia: 'Panadería',
    bebidas: 'Bebidas',
    congelados: 'Congelados',
    limpieza: 'Limpieza',
    higiene_personal: 'Higiene personal',
    conservas: 'Conservas',
    otros: 'Otros',
};

const CATEGORY_EMOJIS = {
    frutas_verduras: '\uD83E\uDD66',
    carnes_pescados: '\uD83E\uDD69',
    lacteos_huevos: '\uD83E\uDD5B',
    panaderia: '\uD83C\uDF5E',
    bebidas: '\uD83E\uDD64',
    congelados: '\u2744\uFE0F',
    limpieza: '\uD83E\uDDF9',
    higiene_personal: '\uD83E\uDDF4',
    conservas: '\uD83E\uDD6B',
    otros: '\uD83D\uDCE6',
};

const UNIT_LABELS = { kg: 'kg', g: 'g', L: 'L', ml: 'ml', ud: 'ud', pack: 'pack' };

/* ─── Design tokens ─── */
const T = {
    primary: '#002736',
    primaryContainer: '#003e54',
    onSurface: '#191c1e',
    onSurfaceVariant: '#41484c',
    surfaceContainerLowest: '#ffffff',
    surfaceContainerLow: '#f2f4f6',
    surfaceContainerHigh: '#e6e8ea',
    outlineVariant: '#c1c7cd',
    outline: '#71787d',
    secondaryContainer: '#50d9fe',
    tertiaryFixed: '#6ffbbe',
    onTertiaryContainer: '#10b981',
    background: '#f7f9fb',
};

export default function ListDetailPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [list, setList] = useState(null);
    const [items, setItems] = useState({});
    const [counters, setCounters] = useState({ items_total: 0, items_completed: 0 });
    const [isLoading, setIsLoading] = useState(true);
    const [addLoading, setAddLoading] = useState(false);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showMoreMenu, setShowMoreMenu] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [deletedItem, setDeletedItem] = useState(null);
    const [showClearConfirm, setShowClearConfirm] = useState(false);
    const [showShareModal, setShowShareModal] = useState(false);
    const [complementFor, setComplementFor] = useState(null);
    const [error, setError] = useState('');
    const [priceEstimate, setPriceEstimate] = useState(null);
    const [isPriceCalculating, setIsPriceCalculating] = useState(false);
    const [showConfirmPrice, setShowConfirmPrice] = useState(false);
    const [priceConfirmedThisSession, setPriceConfirmedThisSession] = useState(false);
    const undoTimerRef = useRef(null);

    const fetchList = useCallback(async () => {
        try {
            const [listRes, itemsRes] = await Promise.all([
                api.get(`/lists/${id}`),
                api.get(`/lists/${id}/items`),
            ]);
            setList(listRes.data.data);
            setItems(itemsRes.data.data.items);
            setCounters(itemsRes.data.data.counters);
        } catch {
            setError('Error al cargar la lista.');
        } finally {
            setIsLoading(false);
        }
    }, [id]);

    useEffect(() => {
        fetchList();
        return () => {
            if (undoTimerRef.current) clearTimeout(undoTimerRef.current);
        };
    }, [fetchList]);

    const handleAdd = async (data) => {
        setAddLoading(true);
        try {
            const response = await api.post(`/lists/${id}/items`, data);
            setCounters(response.data.data.counters);
            setComplementFor(data.name);
            await fetchList();
            return true;
        } catch {
            setError('Error al añadir el item.');
            return false;
        } finally {
            setAddLoading(false);
        }
    };

    const handleComplementAccept = async (suggestion) => {
        const payload = { name: suggestion.nombre };
        if (suggestion.unidad_tipica) payload.unit = suggestion.unidad_tipica;
        if (suggestion.categoria) payload.category = suggestion.categoria;
        if (suggestion.cantidad_tipica != null) payload.quantity = Number(suggestion.cantidad_tipica);

        setComplementFor(null);
        try {
            await api.post(`/lists/${id}/items`, payload);
            await fetchList();
        } catch {
            setError('Error al añadir el complementario.');
        }
    };

    const handleToggle = async (itemId) => {
        try {
            const response = await api.patch(`/lists/${id}/items/${itemId}/toggle`);
            const newCounters = response.data.data.counters;
            setCounters(newCounters);
            await fetchList();

            // HU-702: prompt for price when 100% purchased
            if (
                newCounters.items_total > 0 &&
                newCounters.items_completed === newCounters.items_total &&
                !priceConfirmedThisSession
            ) {
                setShowConfirmPrice(true);
            }
        } catch {
            setError('Error al actualizar el item.');
        }
    };

    const handleEdit = async (itemId, data) => {
        try {
            await api.put(`/lists/${id}/items/${itemId}`, data);
            await fetchList();
        } catch {
            setError('Error al editar el item.');
        }
    };

    const handleDelete = async (item) => {
        try {
            const response = await api.delete(`/lists/${id}/items/${item.id}`);
            setCounters(response.data.data.counters);
            setDeletedItem(item);

            if (undoTimerRef.current) clearTimeout(undoTimerRef.current);
            undoTimerRef.current = setTimeout(() => setDeletedItem(null), 5000);

            await fetchList();
        } catch {
            setError('Error al eliminar el item.');
        }
    };

    const handleUndo = async () => {
        if (!deletedItem) return;
        if (undoTimerRef.current) clearTimeout(undoTimerRef.current);

        const data = {
            name: deletedItem.name,
            quantity: deletedItem.quantity,
            unit: deletedItem.unit,
            category: deletedItem.category,
            estimated_price: deletedItem.estimated_price,
        };

        setDeletedItem(null);

        try {
            await api.post(`/lists/${id}/items`, data);
            await fetchList();
        } catch {
            setError('Error al deshacer la eliminacion.');
        }
    };

    const handleClearCompleted = async () => {
        setShowClearConfirm(false);
        try {
            const response = await api.delete(`/lists/${id}/items/completed`);
            setCounters(response.data.data.counters);
            await fetchList();
        } catch {
            setError('Error al limpiar items comprados.');
        }
    };

    const handleArchiveList = async () => {
        setShowMoreMenu(false);
        try {
            await api.patch(`/lists/${id}/archive`);
            navigate('/app');
        } catch {
            setError('Error al archivar la lista.');
        }
    };

    const handleDeleteList = async () => {
        setShowMoreMenu(false);
        if (!window.confirm('¿Seguro que quieres eliminar esta lista? Esta accion no se puede deshacer.')) return;
        try {
            await api.delete(`/lists/${id}`);
            navigate('/app');
        } catch {
            setError('Error al eliminar la lista.');
        }
    };

    const handleRecalculatePrices = async () => {
        setIsPriceCalculating(true);
        try {
            const result = await estimatePrices(id);
            setPriceEstimate(result);
        } catch {
            setError('Error al calcular precios.');
        } finally {
            setIsPriceCalculating(false);
        }
    };

    const handleConfirmPrices = async (total, itemPrices) => {
        try {
            await confirmPrices(id, total, itemPrices);
            setShowConfirmPrice(false);
            setPriceConfirmedThisSession(true);
            await handleRecalculatePrices();
        } catch {
            setError('Error al guardar precios.');
        }
    };

    /* ─── Loading ─── */
    if (isLoading) {
        return (
            <div
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: T.background,
                    fontFamily: "'Inter', sans-serif",
                }}
                data-testid="list-loading"
            >
                <div style={{ color: T.onSurfaceVariant }} role="status" aria-live="polite">
                    Cargando lista...
                </div>
            </div>
        );
    }

    /* ─── Not found ─── */
    if (!list) {
        return (
            <div
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: T.background,
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div style={{ textAlign: 'center' }}>
                    <p style={{ color: T.onSurfaceVariant, marginBottom: 16 }}>Lista no encontrada.</p>
                    <Link to="/app" style={{ color: T.secondaryContainer, textDecoration: 'none', fontWeight: 600 }}>
                        Volver al dashboard
                    </Link>
                </div>
            </div>
        );
    }

    /* ─── Derived state ─── */
    const allCompleted = counters.items_total > 0 && counters.items_completed === counters.items_total;
    const hasItems = counters.items_total > 0;
    const categoryKeys = Object.keys(items).filter((k) => items[k].length > 0);

    const pendingCategories = categoryKeys.filter((k) => items[k].some((i) => !i.is_purchased));
    const purchasedItems = categoryKeys.reduce((acc, k) => {
        const bought = items[k].filter((i) => i.is_purchased);
        return acc.concat(bought);
    }, []);

    const progressPct = counters.items_total > 0
        ? Math.round((counters.items_completed / counters.items_total) * 100)
        : 0;

    /* ─── Render helpers ─── */
    const renderItemCard = (item, isPurchased) => (
        <div
            key={item.id}
            data-testid="item-row"
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                padding: '14px 16px',
                background: T.surfaceContainerLowest,
                borderRadius: 16,
                boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                marginBottom: 8,
                opacity: isPurchased ? 0.6 : 1,
                transition: 'opacity 0.2s',
            }}
        >
            {/* Checkbox */}
            <button
                onClick={() => handleToggle(item.id)}
                aria-label={`Marcar ${item.name} como ${item.is_purchased ? 'pendiente' : 'comprado'}`}
                style={{
                    width: 24,
                    height: 24,
                    minWidth: 24,
                    borderRadius: 6,
                    border: isPurchased ? 'none' : `2px solid ${T.outlineVariant}`,
                    background: isPurchased ? T.onTertiaryContainer : 'transparent',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                    padding: 0,
                    transition: 'background 0.15s, border 0.15s',
                }}
            >
                {isPurchased && (
                    <span className="material-symbols-outlined" style={{ fontSize: 16, color: '#fff', fontWeight: 600 }}>
                        check
                    </span>
                )}
            </button>

            {/* Name & price — tappable to edit */}
            <button
                onClick={() => setEditingItem(item)}
                style={{
                    flex: 1,
                    minWidth: 0,
                    background: 'none',
                    border: 'none',
                    padding: 0,
                    textAlign: 'left',
                    cursor: 'pointer',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <span
                    style={{
                        display: 'block',
                        fontSize: 15,
                        fontWeight: 500,
                        color: isPurchased ? T.onSurfaceVariant : T.onSurface,
                        textDecoration: isPurchased ? 'line-through' : 'none',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                    }}
                >
                    {item.name}
                </span>
                {item.estimated_price != null && (
                    <span style={{ fontSize: 12, color: T.outline }}>
                        ~{item.estimated_price}\u20AC
                    </span>
                )}
            </button>

            {/* Quantity badge */}
            {item.quantity != null && (
                <span
                    style={{
                        background: T.surfaceContainerLow,
                        borderRadius: 8,
                        padding: '4px 10px',
                        fontSize: 12,
                        fontWeight: 700,
                        color: T.onSurfaceVariant,
                        whiteSpace: 'nowrap',
                    }}
                >
                    {item.quantity}{item.unit ? ` ${UNIT_LABELS[item.unit] || item.unit}` : ''}
                </span>
            )}

            {/* Delete */}
            <button
                onClick={() => handleDelete(item)}
                aria-label={`Eliminar ${item.name}`}
                style={{
                    background: 'none',
                    border: 'none',
                    padding: 4,
                    cursor: 'pointer',
                    color: T.outlineVariant,
                    display: 'flex',
                    alignItems: 'center',
                    opacity: 0.5,
                    transition: 'opacity 0.15s, color 0.15s',
                }}
                onMouseEnter={(e) => { e.currentTarget.style.opacity = 1; e.currentTarget.style.color = '#ef4444'; }}
                onMouseLeave={(e) => { e.currentTarget.style.opacity = 0.5; e.currentTarget.style.color = T.outlineVariant; }}
            >
                <span className="material-symbols-outlined" style={{ fontSize: 20 }}>close</span>
            </button>
        </div>
    );

    return (
        <div
            style={{
                minHeight: '100vh',
                background: T.background,
                fontFamily: "'Inter', sans-serif",
                position: 'relative',
                paddingBottom: 100,
            }}
        >
            {/* ─── NAV ─── */}
            <header
                style={{
                    position: 'sticky',
                    top: 0,
                    zIndex: 40,
                    background: T.surfaceContainerLowest,
                    borderBottom: `1px solid ${T.outlineVariant}`,
                }}
            >
                <div style={{ maxWidth: 640, margin: '0 auto', padding: '12px 16px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        {/* Back */}
                        <button
                            onClick={() => navigate('/app')}
                            style={{
                                background: 'none',
                                border: 'none',
                                padding: 4,
                                cursor: 'pointer',
                                display: 'flex',
                                alignItems: 'center',
                                color: T.onSurface,
                            }}
                            aria-label="Volver al dashboard"
                        >
                            <span className="material-symbols-outlined" style={{ fontSize: 24 }}>arrow_back</span>
                        </button>

                        {/* List name */}
                        <h1
                            style={{
                                flex: 1,
                                fontSize: 18,
                                fontWeight: 700,
                                color: T.onSurface,
                                margin: 0,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {list.emoji && <span style={{ marginRight: 6 }}>{list.emoji}</span>}
                            {list.name}
                        </h1>

                        {list.is_shared && <CollaboratorIndicator listId={list.id} />}

                        {/* Share */}
                        <button
                            onClick={() => setShowShareModal(true)}
                            data-testid="share-button"
                            style={{
                                background: 'none',
                                border: 'none',
                                padding: 4,
                                cursor: 'pointer',
                                display: 'flex',
                                alignItems: 'center',
                                color: T.onSurfaceVariant,
                            }}
                        >
                            <span className="material-symbols-outlined" style={{ fontSize: 22 }}>share</span>
                        </button>

                        {/* More options menu */}
                        <div style={{ position: 'relative' }}>
                            <button
                                onClick={() => setShowMoreMenu(!showMoreMenu)}
                                style={{
                                    background: 'none', border: 'none', padding: 4,
                                    cursor: 'pointer', display: 'flex', alignItems: 'center',
                                    color: T.onSurfaceVariant,
                                }}
                            >
                                <span className="material-symbols-outlined" style={{ fontSize: 22 }}>more_vert</span>
                            </button>
                            {showMoreMenu && (
                                <>
                                    <div onClick={() => setShowMoreMenu(false)} style={{ position: 'fixed', inset: 0, zIndex: 30 }} />
                                    <div style={{
                                        position: 'absolute', right: 0, top: '100%', marginTop: 4,
                                        background: '#ffffff', borderRadius: 12, padding: 8, minWidth: 200,
                                        boxShadow: '0 8px 32px rgba(0,39,54,0.12)', zIndex: 31,
                                        fontFamily: "'Inter', sans-serif",
                                    }}>
                                        {counters.items_completed > 0 && (
                                            <button
                                                onClick={() => { setShowMoreMenu(false); setShowClearConfirm(true); }}
                                                style={{
                                                    display: 'flex', alignItems: 'center', gap: 10, width: '100%',
                                                    padding: '10px 12px', background: 'none', border: 'none',
                                                    borderRadius: 8, cursor: 'pointer', fontSize: 14, color: T.onSurface,
                                                    textAlign: 'left',
                                                }}
                                                onMouseOver={(e) => e.currentTarget.style.background = '#f2f4f6'}
                                                onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                            >
                                                <span className="material-symbols-outlined" style={{ fontSize: 20, color: T.outline }}>cleaning_services</span>
                                                Limpiar comprados
                                            </button>
                                        )}
                                        <button
                                            onClick={handleArchiveList}
                                            style={{
                                                display: 'flex', alignItems: 'center', gap: 10, width: '100%',
                                                padding: '10px 12px', background: 'none', border: 'none',
                                                borderRadius: 8, cursor: 'pointer', fontSize: 14, color: T.onSurface,
                                                textAlign: 'left',
                                            }}
                                            onMouseOver={(e) => e.currentTarget.style.background = '#f2f4f6'}
                                            onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                        >
                                            <span className="material-symbols-outlined" style={{ fontSize: 20, color: T.outline }}>archive</span>
                                            Archivar lista
                                        </button>
                                        <button
                                            onClick={handleDeleteList}
                                            style={{
                                                display: 'flex', alignItems: 'center', gap: 10, width: '100%',
                                                padding: '10px 12px', background: 'none', border: 'none',
                                                borderRadius: 8, cursor: 'pointer', fontSize: 14, color: '#ba1a1a',
                                                textAlign: 'left',
                                            }}
                                            onMouseOver={(e) => e.currentTarget.style.background = '#ffdad6'}
                                            onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                        >
                                            <span className="material-symbols-outlined" style={{ fontSize: 20 }}>delete</span>
                                            Eliminar lista
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>

                    {/* ─── PROGRESS ─── */}
                    {hasItems && (
                        <div style={{ marginTop: 10 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 6,
                                }}
                            >
                                <span style={{ fontSize: 13, color: T.onSurfaceVariant, fontWeight: 500 }}>
                                    {counters.items_completed} de {counters.items_total} items comprados
                                </span>
                                <span style={{ fontSize: 13, color: T.onSurfaceVariant, fontWeight: 600 }}>
                                    {progressPct}%
                                </span>
                            </div>
                            <div
                                style={{
                                    height: 4,
                                    borderRadius: 2,
                                    background: T.surfaceContainerHigh,
                                    overflow: 'hidden',
                                }}
                            >
                                <div
                                    style={{
                                        height: '100%',
                                        width: `${progressPct}%`,
                                        background: T.secondaryContainer,
                                        borderRadius: 2,
                                        transition: 'width 0.3s ease',
                                    }}
                                    role="progressbar"
                                    aria-valuenow={counters.items_completed}
                                    aria-valuemin={0}
                                    aria-valuemax={counters.items_total}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </header>

            {/* ─── MAIN ─── */}
            <main style={{ maxWidth: 640, margin: '0 auto', padding: '20px 16px 0' }}>
                {error && (
                    <div
                        role="alert"
                        style={{
                            background: '#fef2f2',
                            color: '#b91c1c',
                            padding: '10px 14px',
                            borderRadius: 12,
                            fontSize: 14,
                            marginBottom: 16,
                        }}
                    >
                        {error}
                    </div>
                )}

                {!hasItems ? (
                    <p
                        data-testid="empty-items"
                        style={{
                            textAlign: 'center',
                            color: T.onSurfaceVariant,
                            padding: '48px 0',
                            fontSize: 15,
                        }}
                    >
                        Esta lista está vacía. Añade tu primer producto.
                    </p>
                ) : (
                    <>
                        {allCompleted && (
                            <div
                                data-testid="all-completed"
                                style={{
                                    textAlign: 'center',
                                    padding: '14px 0',
                                    marginBottom: 16,
                                    background: '#ecfdf5',
                                    borderRadius: 12,
                                }}
                            >
                                <span style={{ color: T.onTertiaryContainer, fontWeight: 600, fontSize: 14 }}>
                                    Lista completada!
                                </span>
                            </div>
                        )}

                        {/* ─── Pending items by category ─── */}
                        {pendingCategories.map((category) => {
                            const pending = items[category].filter((i) => !i.is_purchased);
                            if (pending.length === 0) return null;
                            return (
                                <div key={category} style={{ marginBottom: 20 }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 6,
                                            marginBottom: 8,
                                            paddingLeft: 4,
                                        }}
                                    >
                                        <span style={{ fontSize: 18 }}>{CATEGORY_EMOJIS[category] || '\uD83D\uDCE6'}</span>
                                        <span
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: T.onSurfaceVariant,
                                                textTransform: 'uppercase',
                                                letterSpacing: '0.04em',
                                            }}
                                        >
                                            {CATEGORY_LABELS[category] || category}
                                        </span>
                                    </div>
                                    {pending.map((item) => renderItemCard(item, false))}
                                </div>
                            );
                        })}

                        {/* ─── Purchased items section ─── */}
                        {purchasedItems.length > 0 && (
                            <div style={{ marginTop: 28, marginBottom: 20 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        marginBottom: 12,
                                        paddingLeft: 4,
                                    }}
                                >
                                    <span className="material-symbols-outlined" style={{ fontSize: 20, color: T.onTertiaryContainer }}>
                                        shopping_cart
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 600,
                                            color: T.onSurfaceVariant,
                                        }}
                                    >
                                        Ya en el carro
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: T.outline,
                                            fontWeight: 500,
                                        }}
                                    >
                                        ({purchasedItems.length})
                                    </span>
                                </div>
                                {purchasedItems.map((item) => renderItemCard(item, true))}
                            </div>
                        )}
                    </>
                )}

                {/* Complementary chip */}
                {complementFor && (
                    <div style={{ marginBottom: 16 }}>
                        <ComplementaryChip
                            key={complementFor}
                            productName={complementFor}
                            listId={parseInt(id, 10)}
                            onAccept={handleComplementAccept}
                            onDismiss={() => setComplementFor(null)}
                        />
                    </div>
                )}

                {/* Price bar */}
                <PriceBar
                    estimate={priceEstimate}
                    onRecalculate={handleRecalculatePrices}
                    isCalculating={isPriceCalculating}
                />

                {/* Activity log */}
                {list.is_shared && (
                    <div style={{ marginTop: 24 }}>
                        <ActivityLogView listId={list.id} />
                    </div>
                )}
            </main>

            {/* ─── STICKY FOOTER — Add item input ─── */}
            <div
                style={{
                    position: 'fixed',
                    bottom: 0,
                    left: 0,
                    right: 0,
                    zIndex: 30,
                    background: 'rgba(247, 249, 251, 0.85)',
                    backdropFilter: 'blur(12px)',
                    WebkitBackdropFilter: 'blur(12px)',
                    borderTop: `1px solid ${T.outlineVariant}`,
                    padding: '10px 16px',
                    paddingBottom: 'max(10px, env(safe-area-inset-bottom))',
                }}
            >
                <div style={{ maxWidth: 640, margin: '0 auto', display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div
                        onClick={() => setShowAddModal(true)}
                        data-testid="add-item-trigger"
                        style={{
                            flex: 1, background: '#f2f4f6', borderRadius: 16,
                            padding: '16px 24px', cursor: 'pointer',
                            color: '#71787d', fontSize: 14, fontWeight: 500,
                        }}
                    >
                        Añadir item...
                    </div>
                    <button
                        onClick={() => setShowAddModal(true)}
                        style={{
                            width: 48, height: 48, borderRadius: 16,
                            background: '#002736', color: '#ffffff',
                            border: 'none', cursor: 'pointer',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}
                    >
                        <span className="material-symbols-outlined">add</span>
                    </button>
                </div>
            </div>

            {/* ─── AI sparkle FAB ─── */}
            <button
                onClick={() => navigate('/app/generar')}
                aria-label="Sugerencias IA"
                style={{
                    position: 'fixed',
                    bottom: 80,
                    right: 20,
                    zIndex: 35,
                    width: 56,
                    height: 56,
                    borderRadius: '50%',
                    background: T.onTertiaryContainer,
                    color: '#fff',
                    border: 'none',
                    boxShadow: '0 4px 14px rgba(16, 185, 129, 0.35)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    transition: 'transform 0.15s, box-shadow 0.15s',
                }}
                onMouseEnter={(e) => { e.currentTarget.style.transform = 'scale(1.08)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.transform = 'scale(1)'; }}
            >
                <span className="material-symbols-outlined" style={{ fontSize: 28 }}>auto_awesome</span>
            </button>

            {/* ─── MODALS / PANELS ─── */}

            {editingItem && (
                <EditItemPanel
                    item={editingItem}
                    onSave={handleEdit}
                    onClose={() => setEditingItem(null)}
                />
            )}

            {deletedItem && (
                <UndoSnackbar
                    message={`"${deletedItem.name}" eliminado.`}
                    onUndo={handleUndo}
                />
            )}

            {showShareModal && (
                <ShareListModal listId={list.id} onClose={() => {
                    setShowShareModal(false);
                    fetchList();
                }} />
            )}

            {showClearConfirm && (
                <div
                    data-testid="clear-confirm"
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(0,0,0,0.4)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 50,
                        padding: 16,
                    }}
                >
                    <div
                        style={{
                            background: T.surfaceContainerLowest,
                            borderRadius: 20,
                            boxShadow: '0 8px 30px rgba(0,0,0,0.12)',
                            maxWidth: 360,
                            width: '100%',
                            padding: 24,
                        }}
                    >
                        <p style={{ color: T.onSurface, marginBottom: 20, fontSize: 15, lineHeight: 1.5 }}>
                            Se eliminar\u00E1n {counters.items_completed} items comprados. \u00BFContinuar?
                        </p>
                        <div style={{ display: 'flex', gap: 12 }}>
                            <button
                                onClick={handleClearCompleted}
                                style={{
                                    flex: 1,
                                    background: '#dc2626',
                                    color: '#fff',
                                    padding: '10px 16px',
                                    borderRadius: 12,
                                    border: 'none',
                                    fontWeight: 600,
                                    fontSize: 14,
                                    cursor: 'pointer',
                                    fontFamily: "'Inter', sans-serif",
                                }}
                            >
                                Limpiar
                            </button>
                            <button
                                onClick={() => setShowClearConfirm(false)}
                                style={{
                                    background: T.surfaceContainerHigh,
                                    color: T.onSurface,
                                    padding: '10px 16px',
                                    borderRadius: 12,
                                    border: 'none',
                                    fontWeight: 600,
                                    fontSize: 14,
                                    cursor: 'pointer',
                                    fontFamily: "'Inter', sans-serif",
                                }}
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showConfirmPrice && (
                <ConfirmPriceModal
                    items={Object.values(items).flat()}
                    onConfirm={handleConfirmPrices}
                    onDismiss={() => setShowConfirmPrice(false)}
                />
            )}

            {showAddModal && (
                <AddItemModal
                    listId={id}
                    existingItems={Object.values(items).flat()}
                    onAdd={handleAdd}
                    onIncrementExisting={async (itemId, qty) => {
                        try {
                            await api.patch(`/lists/${id}/items/${itemId}/increment-quantity`, { quantity: qty });
                            await fetchList();
                        } catch {
                            setError('Error al incrementar la cantidad.');
                        }
                    }}
                    onClose={() => setShowAddModal(false)}
                />
            )}
        </div>
    );
}
