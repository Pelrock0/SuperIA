import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    fetchActiveLists,
    fetchLatestSummary,
    saveSummarySelection,
} from '../lib/weeklySummaryApi';
import SaveTargetSheet from '../components/weekly-summary/SaveTargetSheet';

export default function WeeklySummaryPage() {
    const [summary, setSummary] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const [selected, setSelected] = useState(() => new Set());
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [activeLists, setActiveLists] = useState([]);
    const [isSaving, setIsSaving] = useState(false);
    const navigate = useNavigate();

    const products = summary?.products ?? [];
    const productCount = products.length;
    const selectedCount = selected.size;
    const canSave = selectedCount > 0 && !isSaving;

    const load = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await fetchLatestSummary();
            setSummary(data);
            setError('');
            const indices = Array.from({ length: data?.products?.length ?? 0 }, (_, i) => i);
            setSelected(new Set(indices));
        } catch (err) {
            const code = err.response?.data?.error?.code;
            if (code === 'NO_SUMMARY_THIS_WEEK' || code === 'DISMISSED') {
                setSummary(null);
            } else {
                setError('Error al cargar el resumen semanal.');
            }
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const toggleItem = (index) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(index)) {
                next.delete(index);
            } else {
                next.add(index);
            }
            return next;
        });
    };

    const openSheet = async () => {
        if (!canSave) {
            return;
        }
        setError('');
        try {
            const lists = await fetchActiveLists();
            setActiveLists(lists);
            setIsSheetOpen(true);
        } catch (err) {
            setError('No se pudieron cargar las listas. Inténtalo de nuevo.');
        }
    };

    const closeSheet = () => {
        if (isSaving) {
            return;
        }
        setIsSheetOpen(false);
    };

    const handleConfirm = async ({ targetListId }) => {
        if (!summary || isSaving) {
            return;
        }
        const indices = Array.from(selected).sort((a, b) => a - b);
        if (indices.length === 0) {
            return;
        }
        setIsSaving(true);
        setError('');
        try {
            const result = await saveSummarySelection(summary.id, {
                selected_indices: indices,
                target_list_id: targetListId,
            });
            setIsSheetOpen(false);

            if (result.summary?.is_actioned) {
                setSuccessMessage(`✓ ${indices.length} items guardados. Redirigiendo…`);
                setTimeout(() => navigate(`/app/listas/${result.list.id}`), 1500);
                return;
            }

            const remaining = result.summary?.remaining_items ?? [];
            const targetName = result.list?.name ?? 'la lista';
            setSummary({
                id: summary.id,
                week_start_date: summary.week_start_date,
                products: remaining,
            });
            setSelected(new Set(remaining.map((_, i) => i)));
            setSuccessMessage(
                `✓ ${indices.length} ${indices.length === 1 ? 'item añadido' : 'items añadidos'} a "${targetName}". Quedan ${remaining.length} ${remaining.length === 1 ? 'pendiente' : 'pendientes'}.`,
            );
        } catch (err) {
            const code = err.response?.data?.error?.code;
            if (code === 'FREEMIUM_LIMIT') {
                setError('Has alcanzado el límite de 3 listas activas. Archiva una lista o elige una existente.');
            } else if (err.response?.status === 422) {
                setError('Selección inválida. Recarga la página y vuelve a intentarlo.');
            } else if (err.response?.status === 404) {
                setError('Lista no disponible. Recarga la página.');
            } else {
                setError('No se pudieron guardar los items. Inténtalo de nuevo.');
            }
        } finally {
            setIsSaving(false);
        }
    };

    const ctaLabel = useMemo(() => {
        if (isSaving) return 'Guardando…';
        if (selectedCount === 0) return 'Selecciona al menos un item';
        return `Guardar ${selectedCount} ${selectedCount === 1 ? 'item' : 'items'}`;
    }, [isSaving, selectedCount]);

    if (isLoading) {
        return (
            <div
                data-testid="summary-loading"
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: '#f7f9fb',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div role="status" aria-live="polite" style={{ color: '#71787d' }}>Cargando resumen...</div>
            </div>
        );
    }

    return (
        <div style={{ minHeight: '100vh', background: '#f7f9fb', fontFamily: "'Inter', sans-serif", paddingBottom: '128px' }}>
            <header
                style={{
                    position: 'sticky',
                    top: 0,
                    zIndex: 50,
                    background: 'rgba(247, 249, 251, 0.7)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '16px 24px',
                    width: '100%',
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                    <button
                        className="material-symbols-outlined"
                        onClick={() => navigate('/app')}
                        style={{
                            color: '#003e54',
                            background: 'none',
                            border: 'none',
                            cursor: 'pointer',
                            padding: '8px',
                            borderRadius: '9999px',
                            fontSize: '24px',
                        }}
                    >
                        menu
                    </button>
                    <h1 style={{ fontWeight: 700, letterSpacing: '-0.025em', fontSize: '20px', color: '#003e54', margin: 0 }}>
                        Tu compra de esta semana
                    </h1>
                </div>
                <div
                    aria-hidden="true"
                    style={{
                        width: '40px',
                        height: '40px',
                        borderRadius: '9999px',
                        background: '#e6e8ea',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <span className="material-symbols-outlined" style={{ color: '#41484c' }}>account_circle</span>
                </div>
            </header>

            <main style={{ maxWidth: '672px', margin: '0 auto', padding: '32px 24px' }}>
                {successMessage && (
                    <div
                        role="status"
                        data-testid="convert-success"
                        style={{
                            background: 'rgba(111, 251, 190, 0.3)',
                            color: '#002a1a',
                            padding: '12px 16px',
                            borderRadius: '12px',
                            fontSize: '14px',
                            marginBottom: '24px',
                        }}
                    >
                        {successMessage}
                    </div>
                )}

                {error && (
                    <div
                        role="alert"
                        data-testid="summary-error"
                        style={{
                            background: '#ffdad6',
                            color: '#93000a',
                            padding: '12px 16px',
                            borderRadius: '12px',
                            fontSize: '14px',
                            marginBottom: '24px',
                        }}
                    >
                        {error}
                    </div>
                )}

                {!summary ? (
                    <div data-testid="no-summary" style={{ textAlign: 'center', padding: '80px 40px' }}>
                        <div
                            aria-hidden="true"
                            style={{
                                width: '96px',
                                height: '96px',
                                background: '#f2f4f6',
                                borderRadius: '9999px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                margin: '0 auto 24px',
                            }}
                        >
                            <span className="material-symbols-outlined" style={{ fontSize: '40px', color: '#71787d' }}>
                                calendar_today
                            </span>
                        </div>
                        <p style={{ color: '#191c1e', fontWeight: 700, fontSize: '18px', margin: '0 0 8px 0' }}>
                            No hay resumen disponible esta semana.
                        </p>
                        <p style={{ color: '#41484c', fontSize: '14px', margin: 0 }}>
                            Vuelve el lunes para ver tu resumen personalizado.
                        </p>
                    </div>
                ) : (
                    <>
                        <section style={{ marginBottom: '40px', marginLeft: '8px' }}>
                            <p style={{ color: '#71787d', fontWeight: 500, letterSpacing: '0.025em', fontSize: '14px', margin: 0 }}>
                                Semana del {summary.week_start_date}
                            </p>
                            <div
                                style={{
                                    marginTop: '16px',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: '12px',
                                    background: 'rgba(111, 251, 190, 0.3)',
                                    padding: '12px',
                                    borderRadius: '12px',
                                }}
                            >
                                <span
                                    aria-hidden="true"
                                    className="material-symbols-outlined"
                                    style={{ fontSize: '14px', color: '#002113', fontVariationSettings: "'FILL' 1" }}
                                >
                                    auto_awesome
                                </span>
                                <span
                                    style={{
                                        fontSize: '12px',
                                        fontWeight: 700,
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.1em',
                                        color: '#002113',
                                    }}
                                >
                                    IA Sugerencia Inteligente
                                </span>
                            </div>
                        </section>

                        <div data-testid="summary-content">
                            {productCount > 0 ? (
                                <section style={{ marginBottom: '48px' }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'flex-end',
                                            justifyContent: 'space-between',
                                            marginBottom: '24px',
                                            marginLeft: '8px',
                                        }}
                                    >
                                        <h2
                                            style={{
                                                color: '#003e54',
                                                fontWeight: 900,
                                                fontSize: '24px',
                                                letterSpacing: '-0.05em',
                                                textTransform: 'uppercase',
                                                margin: 0,
                                            }}
                                        >
                                            Reposicion
                                        </h2>
                                        <span
                                            style={{
                                                fontSize: '12px',
                                                fontWeight: 700,
                                                color: '#003e54',
                                                opacity: 0.6,
                                                textTransform: 'uppercase',
                                                letterSpacing: '0.2em',
                                            }}
                                        >
                                            {productCount} items
                                        </span>
                                    </div>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                        {products.map((product, idx) => {
                                            const isChecked = selected.has(idx);
                                            const labelId = `summary-item-name-${idx}`;
                                            return (
                                                <label
                                                    key={`${idx}-${product.nombre}`}
                                                    data-testid={`summary-item-${idx}`}
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'space-between',
                                                        background: '#ffffff',
                                                        padding: '20px',
                                                        borderRadius: '16px',
                                                        boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                                                        opacity: isChecked ? 1 : 0.45,
                                                        transition: 'opacity 0.2s',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onChange={() => toggleItem(idx)}
                                                            aria-checked={isChecked}
                                                            aria-labelledby={labelId}
                                                            data-testid={`summary-item-checkbox-${idx}`}
                                                            style={{
                                                                width: '24px',
                                                                height: '24px',
                                                                borderRadius: '8px',
                                                                border: '2px solid #003e54',
                                                                accentColor: '#003e54',
                                                                cursor: 'pointer',
                                                            }}
                                                        />
                                                        <div>
                                                            <span
                                                                id={labelId}
                                                                style={{
                                                                    display: 'block',
                                                                    color: isChecked ? '#191c1e' : '#71787d',
                                                                    fontWeight: 700,
                                                                    fontSize: '18px',
                                                                    textDecoration: isChecked ? 'none' : 'line-through',
                                                                }}
                                                            >
                                                                {product.nombre}
                                                            </span>
                                                            {product.reason && (
                                                                <span
                                                                    style={{
                                                                        color: '#9ca3af',
                                                                        fontSize: '12px',
                                                                        fontWeight: 500,
                                                                        textTransform: 'uppercase',
                                                                        letterSpacing: '-0.025em',
                                                                    }}
                                                                >
                                                                    {product.reason}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {(product.cantidad_tipica || product.unidad_tipica) && (
                                                        <div
                                                            style={{
                                                                background: '#f2f4f6',
                                                                padding: '6px 16px',
                                                                borderRadius: '9999px',
                                                            }}
                                                        >
                                                            <span style={{ color: '#003e54', fontWeight: 900, fontSize: '14px' }}>
                                                                {product.cantidad_tipica && `${product.cantidad_tipica} `}
                                                                {product.unidad_tipica || ''}
                                                            </span>
                                                        </div>
                                                    )}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </section>
                            ) : (
                                <p style={{ color: '#71787d', fontSize: '14px' }}>Sin productos sugeridos esta semana.</p>
                            )}
                        </div>

                        <div style={{ marginTop: '48px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '24px' }}>
                            <button
                                type="button"
                                onClick={openSheet}
                                disabled={!canSave}
                                data-testid="convert-to-list"
                                aria-live="polite"
                                style={{
                                    width: '100%',
                                    background: canSave ? '#003e54' : '#cbd5e0',
                                    color: '#ffffff',
                                    fontWeight: 700,
                                    padding: '20px',
                                    borderRadius: '16px',
                                    border: 'none',
                                    boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                                    cursor: canSave ? 'pointer' : 'not-allowed',
                                    opacity: canSave ? 1 : 0.6,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: '12px',
                                    fontSize: '16px',
                                    fontFamily: "'Inter', sans-serif",
                                    transition: 'all 0.2s',
                                }}
                            >
                                <span aria-hidden="true" className="material-symbols-outlined">shopping_basket</span>
                                {ctaLabel}
                            </button>
                        </div>
                    </>
                )}
            </main>

            <SaveTargetSheet
                isOpen={isSheetOpen}
                onClose={closeSheet}
                onConfirm={handleConfirm}
                activeLists={activeLists}
                selectedCount={selectedCount}
                isSubmitting={isSaving}
            />
        </div>
    );
}
