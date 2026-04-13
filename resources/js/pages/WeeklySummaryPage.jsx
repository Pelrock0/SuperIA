import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { convertSummaryToList, fetchLatestSummary } from '../lib/weeklySummaryApi';

export default function WeeklySummaryPage() {
    const [summary, setSummary] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [converting, setConverting] = useState(false);
    const [converted, setConverted] = useState(false);
    const navigate = useNavigate();

    const load = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await fetchLatestSummary();
            setSummary(data);
            setError('');
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

    const handleConvert = async () => {
        if (!summary) return;
        setConverting(true);
        setError('');
        try {
            const list = await convertSummaryToList(summary.id);
            setConverted(true);
            setTimeout(() => navigate(`/app/listas/${list.id}`), 1500);
        } catch (err) {
            const code = err.response?.data?.error?.code;
            if (code === 'FREEMIUM_LIMIT') {
                setError('Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista primero.');
            } else {
                setError('Error al convertir el resumen en lista.');
            }
        } finally {
            setConverting(false);
        }
    };

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
            {/* TopAppBar */}
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
                        {converted && (
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
                                Lista creada. Redirigiendo...
                            </div>
                        )}

                        {/* Subtitle & Context */}
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

                        {/* Products */}
                        <div data-testid="summary-content">
                            {summary.products && summary.products.length > 0 ? (
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
                                            Sugerido
                                        </span>
                                    </div>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                        {summary.products.map((product, idx) => (
                                            <div
                                                key={idx}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'space-between',
                                                    background: '#ffffff',
                                                    padding: '20px',
                                                    borderRadius: '16px',
                                                    boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                                                    transition: 'all 0.2s',
                                                }}
                                            >
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                                    <input
                                                        type="checkbox"
                                                        defaultChecked
                                                        readOnly
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
                                                            style={{
                                                                display: 'block',
                                                                color: '#191c1e',
                                                                fontWeight: 700,
                                                                fontSize: '18px',
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
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ) : (
                                <p style={{ color: '#71787d', fontSize: '14px' }}>Sin productos sugeridos esta semana.</p>
                            )}
                        </div>

                        {/* Actions */}
                        <div style={{ marginTop: '48px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '24px' }}>
                            <button
                                type="button"
                                onClick={handleConvert}
                                disabled={converting || converted}
                                data-testid="convert-to-list"
                                style={{
                                    width: '100%',
                                    background: '#003e54',
                                    color: '#ffffff',
                                    fontWeight: 700,
                                    padding: '20px',
                                    borderRadius: '16px',
                                    border: 'none',
                                    boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                                    cursor: converting || converted ? 'not-allowed' : 'pointer',
                                    opacity: converting || converted ? 0.5 : 1,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: '12px',
                                    fontSize: '16px',
                                    fontFamily: "'Inter', sans-serif",
                                    transition: 'all 0.2s',
                                }}
                            >
                                <span className="material-symbols-outlined">shopping_basket</span>
                                {converting
                                    ? 'Creando lista...'
                                    : converted
                                        ? 'Lista creada'
                                        : `Crear lista con ${summary.products?.length || 0} productos`}
                            </button>
                        </div>
                    </>
                )}
            </main>
        </div>
    );
}
