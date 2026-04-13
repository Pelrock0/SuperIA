import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchHistory, duplicateList, fetchStats } from '../lib/historyApi';
import StatsSection from '../components/history/StatsSection';

export default function HistoryPage() {
    const navigate = useNavigate();
    const [lists, setLists] = useState([]);
    const [meta, setMeta] = useState(null);
    const [stats, setStats] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [duplicating, setDuplicating] = useState(null);

    const load = useCallback(async (page = 1) => {
        setIsLoading(true);
        try {
            const [historyRes, statsRes] = await Promise.all([
                fetchHistory(page),
                fetchStats(),
            ]);
            setLists(historyRes.data);
            setMeta(historyRes.meta);
            setStats(statsRes);
            setError('');
        } catch {
            setError('Error al cargar el historial.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const handleDuplicate = async (listId) => {
        setDuplicating(listId);
        setError('');
        try {
            const newList = await duplicateList(listId);
            navigate(`/app/listas/${newList.id}`);
        } catch (err) {
            const code = err.response?.data?.error?.code;
            if (code === 'FREEMIUM_LIMIT') {
                setError('Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista primero.');
            } else {
                setError('Error al duplicar la lista.');
            }
        } finally {
            setDuplicating(null);
        }
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    const formatPrice = (value) => {
        if (value === null || value === undefined) return null;
        return `${Number(value).toFixed(0)}€`;
    };

    if (isLoading) {
        return (
            <div
                data-testid="history-loading"
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: '#f7f9fb',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div role="status" aria-live="polite" style={{ color: '#71787d' }}>Cargando historial...</div>
            </div>
        );
    }

    return (
        <div style={{ minHeight: '100vh', background: '#f7f9fb', fontFamily: "'Inter', sans-serif", paddingBottom: '128px' }}>
            {/* TopAppBar */}
            <header
                style={{
                    position: 'fixed',
                    top: 0,
                    width: '100%',
                    zIndex: 50,
                    background: 'rgba(247, 249, 251, 0.7)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '0 24px',
                    height: '64px',
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                    <button
                        className="material-symbols-outlined"
                        onClick={() => navigate('/app')}
                        style={{
                            color: '#002736',
                            background: 'none',
                            border: 'none',
                            cursor: 'pointer',
                            padding: 0,
                            fontSize: '24px',
                        }}
                    >
                        arrow_back
                    </button>
                    <h1 style={{ color: '#002736', fontWeight: 700, fontSize: '18px', letterSpacing: '-0.025em', margin: 0 }}>
                        Historial de Compras
                    </h1>
                </div>
                <span className="material-symbols-outlined" style={{ color: '#41484c' }}>filter_list</span>
            </header>

            <main style={{ paddingTop: '96px', padding: '96px 24px 0', maxWidth: '672px', margin: '0 auto' }}>
                {error && (
                    <div
                        role="alert"
                        data-testid="history-error"
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

                <StatsSection stats={stats} />

                {lists.length === 0 ? (
                    <div data-testid="history-empty" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '80px 40px', textAlign: 'center' }}>
                        <div
                            style={{
                                width: '96px',
                                height: '96px',
                                background: '#f2f4f6',
                                borderRadius: '9999px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginBottom: '24px',
                            }}
                        >
                            <span className="material-symbols-outlined" style={{ fontSize: '40px', color: '#71787d' }}>history</span>
                        </div>
                        <p style={{ color: '#191c1e', fontWeight: 700, fontSize: '18px', margin: '0 0 8px 0' }}>
                            Historial vacio
                        </p>
                        <p style={{ color: '#41484c', fontSize: '14px', margin: 0 }}>
                            Completa tu primera lista para ver el historial y optimizar tus compras.
                        </p>
                    </div>
                ) : (
                    <div data-testid="history-list">
                        {lists.map((list) => (
                            <div
                                key={list.id}
                                data-testid={`history-card-${list.id}`}
                                style={{
                                    background: '#ffffff',
                                    padding: '20px',
                                    borderRadius: '12px',
                                    boxShadow: '0 4px 24px rgba(0, 39, 54, 0.04)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: '16px',
                                    transition: 'background 0.2s',
                                    cursor: 'pointer',
                                }}
                                onClick={() => navigate(`/app/listas/${list.id}`)}
                            >
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                    <span style={{ fontWeight: 700, color: '#002736', letterSpacing: '-0.025em' }}>
                                        {list.emoji ? `${list.emoji} ` : ''}{list.name}
                                    </span>
                                    <span style={{ fontSize: '14px', color: '#41484c', fontWeight: 500 }}>
                                        {formatDate(list.updated_at)}
                                    </span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                    <div
                                        style={{
                                            background: '#e6e8ea',
                                            padding: '8px 16px',
                                            borderRadius: '9999px',
                                        }}
                                    >
                                        <span style={{ fontSize: '12px', fontWeight: 700, color: '#002736' }}>
                                            {list.items_total || 0} items
                                            {list.price_total ? ` · ${formatPrice(list.price_total)}` : ''}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={(e) => { e.stopPropagation(); handleDuplicate(list.id); }}
                                        disabled={duplicating === list.id}
                                        data-testid={`duplicate-${list.id}`}
                                        style={{
                                            background: 'linear-gradient(to right, #002736, #003e54)',
                                            color: '#ffffff',
                                            fontWeight: 700,
                                            padding: '8px 16px',
                                            borderRadius: '12px',
                                            border: 'none',
                                            fontSize: '12px',
                                            letterSpacing: '0.025em',
                                            cursor: duplicating === list.id ? 'not-allowed' : 'pointer',
                                            opacity: duplicating === list.id ? 0.5 : 1,
                                            boxShadow: '0 4px 12px rgba(0, 39, 54, 0.1)',
                                            fontFamily: "'Inter', sans-serif",
                                        }}
                                    >
                                        {duplicating === list.id ? '...' : 'Usar como base'}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {meta && meta.last_page > 1 && (
                    <div style={{ display: 'flex', justifyContent: 'center', gap: '8px', marginTop: '24px' }}>
                        {Array.from({ length: meta.last_page }, (_, i) => i + 1).map((page) => (
                            <button
                                key={page}
                                onClick={() => load(page)}
                                style={{
                                    padding: '8px 16px',
                                    borderRadius: '12px',
                                    fontSize: '14px',
                                    fontWeight: 700,
                                    border: 'none',
                                    cursor: 'pointer',
                                    background: page === meta.current_page ? '#002736' : '#f2f4f6',
                                    color: page === meta.current_page ? '#ffffff' : '#41484c',
                                    fontFamily: "'Inter', sans-serif",
                                }}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                )}
            </main>
        </div>
    );
}
