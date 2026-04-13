import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import HistoryList from '../components/profile/HistoryList';
import { updateWeeklySummaryEmail } from '../lib/weeklySummaryApi';

/* ── Design tokens (Stitch / Material 3) ── */
const T = {
    primary: '#002736',
    primaryContainer: '#003e54',
    onSurface: '#191c1e',
    onSurfaceVariant: '#41484c',
    surfaceContainerLowest: '#ffffff',
    surfaceContainerLow: '#f2f4f6',
    surfaceContainerHigh: '#e6e8ea',
    outline: '#71787d',
    outlineVariant: '#c1c7cd',
    error: '#ba1a1a',
    errorContainer: '#ffdad6',
    background: '#f7f9fb',
    onTertiaryContainer: '#10b981',
    tertiaryFixed: '#6ffbbe',
    secondary: '#00677d',
    primaryFixedDim: '#9ecde8',
    white: '#ffffff',
};

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return parts[0][0].toUpperCase();
}

export default function ProfilePage() {
    const { user, logout, refreshUser } = useAuth();
    const navigate = useNavigate();

    const [name, setName] = useState(user?.name || '');
    const [nameStatus, setNameStatus] = useState('idle');
    const [nameMessage, setNameMessage] = useState('');
    const [editingName, setEditingName] = useState(false);

    const [passwordData, setPasswordData] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [passwordStatus, setPasswordStatus] = useState('idle');
    const [passwordMessage, setPasswordMessage] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [showPasswordForm, setShowPasswordForm] = useState(false);

    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deletePassword, setDeletePassword] = useState('');
    const [deleteStatus, setDeleteStatus] = useState('idle');
    const [deleteError, setDeleteError] = useState('');

    const [emailOptIn, setEmailOptIn] = useState(false);
    const [emailOptInLoading, setEmailOptInLoading] = useState(false);

    const handleNameSubmit = async (e) => {
        e.preventDefault();
        setNameStatus('loading');
        setNameMessage('');

        try {
            await api.put('/profile', { name });
            await refreshUser();
            setNameMessage('Perfil actualizado correctamente.');
            setNameStatus('success');
            setEditingName(false);
        } catch {
            setNameMessage('Error al actualizar el perfil.');
            setNameStatus('error');
        }
    };

    const handlePasswordChange = (e) => {
        setPasswordData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
        setPasswordError('');
    };

    const handlePasswordSubmit = async (e) => {
        e.preventDefault();
        setPasswordStatus('loading');
        setPasswordMessage('');
        setPasswordError('');

        try {
            const response = await api.put('/profile/password', passwordData);
            setPasswordMessage(response.data.data.message);
            setPasswordStatus('success');
            setPasswordData({ current_password: '', password: '', password_confirmation: '' });
        } catch (err) {
            const errData = err.response?.data?.error;
            setPasswordError(errData?.message || 'Error al cambiar la contraseña.');
            setPasswordStatus('error');
        }
    };

    useEffect(() => {
        api.get('/profile').then((res) => {
            const data = res.data?.data;
            if (data && typeof data.weekly_summary_email_opted_in !== 'undefined') {
                setEmailOptIn(!!data.weekly_summary_email_opted_in);
            }
        }).catch(() => {});
    }, []);

    const handleEmailOptInToggle = async () => {
        const newValue = !emailOptIn;
        setEmailOptIn(newValue);
        setEmailOptInLoading(true);
        try {
            await updateWeeklySummaryEmail(newValue);
        } catch {
            setEmailOptIn(!newValue);
        } finally {
            setEmailOptInLoading(false);
        }
    };

    const handleDeleteAccount = async (e) => {
        e.preventDefault();
        setDeleteStatus('loading');
        setDeleteError('');

        try {
            await api.post('/auth/delete-account', { password: deletePassword });
            await logout();
            navigate('/', { replace: true });
        } catch (err) {
            const errData = err.response?.data?.error;
            setDeleteError(errData?.message || 'Error al eliminar la cuenta.');
            setDeleteStatus('error');
        }
    };

    const initials = getInitials(user?.name);

    return (
        <div style={{ minHeight: '100vh', background: T.background, fontFamily: 'Inter, sans-serif', paddingBottom: 96 }}>
            {/* ── Header ── */}
            <header
                style={{
                    position: 'sticky',
                    top: 0,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '0 24px',
                    height: 64,
                    background: 'rgba(247,249,251,0.7)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    zIndex: 50,
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <span
                        className="material-symbols-outlined"
                        style={{ color: T.primary, cursor: 'pointer', fontSize: 24 }}
                        onClick={() => navigate(-1)}
                    >
                        arrow_back
                    </span>
                    <h1 style={{ fontWeight: 700, fontSize: 18, letterSpacing: '-0.01em', color: T.primary, margin: 0 }}>
                        Perfil
                    </h1>
                </div>
                <div
                    style={{
                        width: 32,
                        height: 32,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderRadius: 8,
                        background: T.primaryContainer,
                    }}
                >
                    <span
                        className="material-symbols-outlined"
                        style={{ color: T.tertiaryFixed, fontVariationSettings: "'FILL' 1" }}
                    >
                        eco
                    </span>
                </div>
            </header>

            <main style={{ paddingTop: 16, paddingLeft: 24, paddingRight: 24, maxWidth: 672, margin: '0 auto' }}>
                {/* ── Profile Header / Avatar ── */}
                <section style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center', padding: '24px 0', gap: 16 }}>
                    <div style={{ position: 'relative' }}>
                        <div
                            style={{
                                width: 96,
                                height: 96,
                                borderRadius: '50%',
                                background: 'radial-gradient(at 0% 0%, #c1e8ff 0%, transparent 50%), radial-gradient(at 100% 0%, #6ffbbe 0%, transparent 50%)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                boxShadow: '0 8px 24px rgba(0,39,54,0.12)',
                                border: `4px solid ${T.white}`,
                            }}
                        >
                            <span style={{ color: T.primary, fontWeight: 800, fontSize: 30, letterSpacing: '-0.04em', fontFamily: 'Inter, sans-serif' }}>
                                {initials}
                            </span>
                        </div>
                        <div
                            style={{
                                position: 'absolute',
                                bottom: 0,
                                right: 0,
                                padding: 6,
                                background: T.primary,
                                borderRadius: '50%',
                                border: `2px solid ${T.white}`,
                                color: T.white,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                cursor: 'pointer',
                            }}
                            onClick={() => setEditingName(true)}
                        >
                            <span className="material-symbols-outlined" style={{ fontSize: 14 }}>edit</span>
                        </div>
                    </div>
                    <div>
                        <h2 style={{ fontWeight: 800, fontSize: 24, color: T.primary, letterSpacing: '-0.02em', margin: 0 }}>
                            {user?.name || 'Usuario'}
                        </h2>
                        <p style={{ color: T.onSurfaceVariant, fontWeight: 500, fontSize: 14, margin: '4px 0 0' }}>
                            {user?.email || ''}
                        </p>
                    </div>
                </section>

                {/* ── DATOS PERSONALES ── */}
                <section style={{ marginTop: 8 }}>
                    <h3 style={{ fontWeight: 700, fontSize: 10, textTransform: 'uppercase', letterSpacing: '0.1em', color: T.outline, padding: '0 8px', marginBottom: 16 }}>
                        Datos personales
                    </h3>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: 16 }}>
                        {/* Name Card */}
                        {!editingName ? (
                            <div
                                style={{
                                    background: T.surfaceContainerLowest,
                                    padding: 20,
                                    borderRadius: 12,
                                    border: '1px solid transparent',
                                }}
                            >
                                <label style={{ display: 'block', fontSize: 10, fontWeight: 700, color: T.outlineVariant, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>
                                    Nombre Completo
                                </label>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ color: T.primary, fontWeight: 600, fontSize: 18 }}>{user?.name || 'Usuario'}</span>
                                    <button
                                        type="button"
                                        onClick={() => setEditingName(true)}
                                        style={{
                                            padding: 8,
                                            background: T.surfaceContainerLow,
                                            borderRadius: 8,
                                            color: T.primaryContainer,
                                            border: 'none',
                                            cursor: 'pointer',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <span className="material-symbols-outlined" style={{ fontSize: 20 }}>edit_square</span>
                                    </button>
                                </div>
                                {nameMessage && !editingName && (
                                    <p style={{ color: nameStatus === 'success' ? T.onTertiaryContainer : T.error, fontSize: 13, marginTop: 8 }} role="status" data-testid="name-message">
                                        {nameMessage}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div style={{ background: T.surfaceContainerLowest, padding: 20, borderRadius: 12 }}>
                                <form onSubmit={handleNameSubmit} data-testid="profile-form" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                    <label style={{ display: 'block', fontSize: 10, fontWeight: 700, color: T.outlineVariant, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 2 }}>
                                        Nombre Completo
                                    </label>
                                    <input
                                        type="text"
                                        id="profile-name"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        required
                                        maxLength={255}
                                        style={{
                                            width: '100%',
                                            padding: '10px 14px',
                                            border: `1px solid ${T.outlineVariant}`,
                                            borderRadius: 8,
                                            fontSize: 16,
                                            fontFamily: 'Inter, sans-serif',
                                            color: T.onSurface,
                                            outline: 'none',
                                            boxSizing: 'border-box',
                                        }}
                                    />
                                    {nameMessage && (
                                        <p style={{ color: nameStatus === 'success' ? T.onTertiaryContainer : T.error, fontSize: 13, margin: 0 }} role="status" data-testid="name-message">
                                            {nameMessage}
                                        </p>
                                    )}
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <button
                                            type="submit"
                                            disabled={nameStatus === 'loading'}
                                            style={{
                                                padding: '10px 20px',
                                                background: T.primary,
                                                color: T.white,
                                                border: 'none',
                                                borderRadius: 10,
                                                fontWeight: 600,
                                                fontSize: 14,
                                                cursor: 'pointer',
                                                opacity: nameStatus === 'loading' ? 0.5 : 1,
                                                fontFamily: 'Inter, sans-serif',
                                            }}
                                        >
                                            {nameStatus === 'loading' ? 'Guardando...' : 'Guardar'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setEditingName(false); setName(user?.name || ''); setNameMessage(''); }}
                                            style={{
                                                padding: '10px 20px',
                                                background: T.surfaceContainerLow,
                                                color: T.onSurfaceVariant,
                                                border: 'none',
                                                borderRadius: 10,
                                                fontWeight: 600,
                                                fontSize: 14,
                                                cursor: 'pointer',
                                                fontFamily: 'Inter, sans-serif',
                                            }}
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}

                        {/* Email (read-only, hidden in display-only) */}
                        <input type="hidden" id="profile-email" value={user?.email || ''} disabled />

                        {/* Password Action Button / Form */}
                        {!showPasswordForm ? (
                            <button
                                type="button"
                                onClick={() => setShowPasswordForm(true)}
                                style={{
                                    width: '100%',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    background: T.primary,
                                    padding: 20,
                                    borderRadius: 12,
                                    border: 'none',
                                    cursor: 'pointer',
                                    boxShadow: '0 4px 16px rgba(0,39,54,0.15)',
                                }}
                            >
                                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                                    <div
                                        style={{
                                            width: 40,
                                            height: 40,
                                            borderRadius: 8,
                                            background: 'rgba(0,62,84,0.2)',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <span className="material-symbols-outlined" style={{ color: T.white }}>lock_reset</span>
                                    </div>
                                    <span style={{ color: T.white, fontWeight: 700, fontSize: 16, fontFamily: 'Inter, sans-serif' }}>Cambiar contraseña</span>
                                </div>
                                <span className="material-symbols-outlined" style={{ color: T.primaryFixedDim }}>chevron_right</span>
                            </button>
                        ) : (
                            <div style={{ background: T.surfaceContainerLowest, padding: 20, borderRadius: 12 }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                                    <h4 style={{ fontWeight: 700, fontSize: 16, color: T.primary, margin: 0 }}>Cambiar contraseña</h4>
                                    <button
                                        type="button"
                                        onClick={() => { setShowPasswordForm(false); setPasswordData({ current_password: '', password: '', password_confirmation: '' }); setPasswordError(''); setPasswordMessage(''); }}
                                        style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 4 }}
                                    >
                                        <span className="material-symbols-outlined" style={{ fontSize: 20, color: T.outline }}>close</span>
                                    </button>
                                </div>
                                <form onSubmit={handlePasswordSubmit} data-testid="password-form" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                    {passwordError && (
                                        <div role="alert" style={{ background: '#fef2f2', color: '#b91c1c', padding: 12, borderRadius: 8, fontSize: 13 }}>{passwordError}</div>
                                    )}
                                    {passwordMessage && (
                                        <div role="status" data-testid="password-message" style={{ background: '#f0fdf4', color: '#15803d', padding: 12, borderRadius: 8, fontSize: 13 }}>{passwordMessage}</div>
                                    )}
                                    <div>
                                        <label htmlFor="current_password" style={{ display: 'block', fontSize: 10, fontWeight: 700, color: T.outlineVariant, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>
                                            Contrasena actual
                                        </label>
                                        <input type="password" id="current_password" name="current_password" value={passwordData.current_password} onChange={handlePasswordChange} required
                                            style={{ width: '100%', padding: '10px 14px', border: `1px solid ${T.outlineVariant}`, borderRadius: 8, fontSize: 14, fontFamily: 'Inter, sans-serif', color: T.onSurface, outline: 'none', boxSizing: 'border-box' }}
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="password" style={{ display: 'block', fontSize: 10, fontWeight: 700, color: T.outlineVariant, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>
                                            Nueva contraseña
                                        </label>
                                        <input type="password" id="password" name="password" value={passwordData.password} onChange={handlePasswordChange} required minLength={8}
                                            style={{ width: '100%', padding: '10px 14px', border: `1px solid ${T.outlineVariant}`, borderRadius: 8, fontSize: 14, fontFamily: 'Inter, sans-serif', color: T.onSurface, outline: 'none', boxSizing: 'border-box' }}
                                        />
                                        <p style={{ color: T.outline, fontSize: 11, marginTop: 4 }}>Mínimo 8 caracteres, 1 mayúscula y 1 número</p>
                                    </div>
                                    <div>
                                        <label htmlFor="confirm_password" style={{ display: 'block', fontSize: 10, fontWeight: 700, color: T.outlineVariant, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>
                                            Confirmar nueva contraseña
                                        </label>
                                        <input type="password" id="confirm_password" name="password_confirmation" value={passwordData.password_confirmation} onChange={handlePasswordChange} required
                                            style={{ width: '100%', padding: '10px 14px', border: `1px solid ${T.outlineVariant}`, borderRadius: 8, fontSize: 14, fontFamily: 'Inter, sans-serif', color: T.onSurface, outline: 'none', boxSizing: 'border-box' }}
                                        />
                                    </div>
                                    <button type="submit" disabled={passwordStatus === 'loading'}
                                        style={{
                                            padding: '12px 20px',
                                            background: T.primary,
                                            color: T.white,
                                            border: 'none',
                                            borderRadius: 10,
                                            fontWeight: 700,
                                            fontSize: 14,
                                            cursor: 'pointer',
                                            opacity: passwordStatus === 'loading' ? 0.5 : 1,
                                            fontFamily: 'Inter, sans-serif',
                                        }}
                                    >
                                        {passwordStatus === 'loading' ? 'Cambiando...' : 'Cambiar contraseña'}
                                    </button>
                                </form>
                            </div>
                        )}
                    </div>
                </section>

                {/* ── PRIVACIDAD Y DATOS ── */}
                <section style={{ marginTop: 32 }}>
                    <h3 style={{ fontWeight: 700, fontSize: 10, textTransform: 'uppercase', letterSpacing: '0.1em', color: T.outline, padding: '0 8px', marginBottom: 16 }}>
                        Privacidad y datos
                    </h3>
                    <div style={{ background: T.surfaceContainerLow, borderRadius: 12, overflow: 'hidden', padding: 8, display: 'flex', flexDirection: 'column', gap: 4 }}>
                        {/* Weekly summary email toggle */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: 16,
                                background: T.surfaceContainerLowest,
                                borderRadius: 8,
                            }}
                            data-testid="weekly-summary-section"
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <span className="material-symbols-outlined" style={{ color: T.secondary }}>mail</span>
                                <div>
                                    <span style={{ fontWeight: 500, color: T.primary, fontSize: 14 }}>Resumen semanal por email</span>
                                    <p style={{ color: T.onSurfaceVariant, fontSize: 12, margin: '2px 0 0' }}>Cada lunes recibiras lo que necesitas comprar.</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={emailOptIn}
                                onClick={handleEmailOptInToggle}
                                disabled={emailOptInLoading}
                                data-testid="email-toggle"
                                style={{
                                    position: 'relative',
                                    display: 'inline-flex',
                                    height: 28,
                                    width: 48,
                                    flexShrink: 0,
                                    cursor: emailOptInLoading ? 'not-allowed' : 'pointer',
                                    borderRadius: 9999,
                                    border: 'none',
                                    padding: 2,
                                    transition: 'background-color 200ms',
                                    background: emailOptIn ? T.onTertiaryContainer : '#d1d5db',
                                    opacity: emailOptInLoading ? 0.5 : 1,
                                }}
                            >
                                <span
                                    style={{
                                        display: 'inline-block',
                                        height: 24,
                                        width: 24,
                                        borderRadius: '50%',
                                        background: T.white,
                                        boxShadow: '0 1px 3px rgba(0,0,0,0.15)',
                                        transition: 'transform 200ms',
                                        transform: emailOptIn ? 'translateX(20px)' : 'translateX(0)',
                                    }}
                                />
                            </button>
                        </div>

                        {/* Ver mis datos */}
                        <div
                            onClick={async () => {
                                try {
                                    const res = await api.get('/profile/my-data');
                                    const d = res.data?.data;
                                    alert(
                                        `Datos almacenados:\n\n` +
                                        `Nombre: ${d.profile.name}\n` +
                                        `Email: ${d.profile.email}\n` +
                                        `Cuenta creada: ${d.profile.created_at}\n` +
                                        `Plan: ${d.profile.plan}\n\n` +
                                        `Listas activas: ${d.stats.lists_active}\n` +
                                        `Listas archivadas: ${d.stats.lists_archived}\n` +
                                        `Productos en historial: ${d.stats.products_in_history}\n` +
                                        `Operaciones IA: ${d.stats.ai_operations_total}\n` +
                                        `Resumenes semanales: ${d.stats.weekly_summaries}\n\n` +
                                        `Email resumen semanal: ${d.settings.weekly_summary_email_opted_in ? 'Activado' : 'Desactivado'}`
                                    );
                                } catch { alert('Error al cargar tus datos.'); }
                            }}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: 16,
                                background: T.surfaceContainerLowest,
                                borderRadius: 8,
                                cursor: 'pointer',
                            }}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <span className="material-symbols-outlined" style={{ color: T.secondary }}>visibility</span>
                                <span style={{ fontWeight: 500, color: T.primary, fontSize: 14 }}>Ver mis datos</span>
                            </div>
                            <span className="material-symbols-outlined" style={{ color: T.outline, fontSize: 20 }}>arrow_forward</span>
                        </div>

                        {/* Limpiar historial */}
                        <div
                            onClick={() => {
                                if (window.confirm('¿Seguro que quieres limpiar todo tu historial de productos? Esta accion no se puede deshacer.')) {
                                    api.delete('/profile/history').then(() => {
                                        alert('Historial eliminado correctamente.');
                                    }).catch(() => {
                                        alert('Error al limpiar el historial.');
                                    });
                                }
                            }}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: 16,
                                background: T.surfaceContainerLowest,
                                borderRadius: 8,
                                cursor: 'pointer',
                            }}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <span className="material-symbols-outlined" style={{ color: T.secondary }}>history</span>
                                <span style={{ fontWeight: 500, color: T.primary, fontSize: 14 }}>Limpiar historial de productos</span>
                            </div>
                            <span className="material-symbols-outlined" style={{ color: T.outline, fontSize: 20 }}>arrow_forward</span>
                        </div>

                        {/* Exportar datos — RGPD data portability */}
                        <div
                            onClick={() => {
                                api.get('/profile/export').then((res) => {
                                    const data = res.data?.data;
                                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                                    const url = URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = `superia-export-${new Date().toISOString().slice(0,10)}.json`;
                                    a.click();
                                    URL.revokeObjectURL(url);
                                }).catch(() => {
                                    alert('Error al exportar los datos.');
                                });
                            }}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: 16,
                                background: T.surfaceContainerLowest,
                                borderRadius: 8,
                                cursor: 'pointer',
                            }}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <span className="material-symbols-outlined" style={{ color: T.secondary }}>ios_share</span>
                                <span style={{ fontWeight: 500, color: T.primary, fontSize: 14 }}>Exportar mis datos</span>
                            </div>
                            <span className="material-symbols-outlined" style={{ color: T.outline, fontSize: 20 }}>arrow_forward</span>
                        </div>
                    </div>
                </section>

                {/* ── History List (existing component) ── */}
                <div style={{ marginTop: 32 }}>
                    <HistoryList />
                </div>

                {/* ── ZONA DE RIESGO ── */}
                <section style={{ marginTop: 48, marginBottom: 32 }}>
                    <div
                        style={{
                            background: 'rgba(255,218,214,0.2)',
                            border: `1px solid rgba(186,26,26,0.1)`,
                            borderRadius: 12,
                            padding: 24,
                        }}
                    >
                        <h3 style={{ fontWeight: 800, fontSize: 18, color: T.error, marginBottom: 8, display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span className="material-symbols-outlined" style={{ color: T.error }}>warning</span>
                            Zona de Riesgo
                        </h3>
                        <p style={{ color: T.onSurfaceVariant, fontSize: 14, marginBottom: 24, lineHeight: 1.6 }}>
                            Esta accion es permanente e irreversible. Una vez eliminada, toda tu informacion, listas y preferencias de IA seran borradas de nuestros servidores.
                        </p>

                        {!showDeleteConfirm ? (
                            <button
                                onClick={() => setShowDeleteConfirm(true)}
                                data-testid="delete-trigger"
                                style={{
                                    width: '100%',
                                    padding: '16px 0',
                                    background: T.error,
                                    color: T.white,
                                    fontWeight: 700,
                                    fontSize: 15,
                                    borderRadius: 12,
                                    border: 'none',
                                    cursor: 'pointer',
                                    boxShadow: '0 8px 24px rgba(186,26,26,0.2)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    fontFamily: 'Inter, sans-serif',
                                }}
                            >
                                <span className="material-symbols-outlined" style={{ fontSize: 18 }}>delete_forever</span>
                                Eliminar cuenta
                            </button>
                        ) : (
                            <form onSubmit={handleDeleteAccount} data-testid="delete-form" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                {deleteError && (
                                    <div role="alert" style={{ background: '#fef2f2', color: '#b91c1c', padding: 12, borderRadius: 8, fontSize: 13 }}>{deleteError}</div>
                                )}
                                <div>
                                    <label htmlFor="delete_password" style={{ display: 'block', fontSize: 10, fontWeight: 700, color: T.outlineVariant, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>
                                        Introduce tu contraseña para confirmar
                                    </label>
                                    <input
                                        type="password"
                                        id="delete_password"
                                        value={deletePassword}
                                        onChange={(e) => { setDeletePassword(e.target.value); setDeleteError(''); }}
                                        required
                                        style={{ width: '100%', padding: '10px 14px', border: `1px solid ${T.error}`, borderRadius: 8, fontSize: 14, fontFamily: 'Inter, sans-serif', color: T.onSurface, outline: 'none', boxSizing: 'border-box' }}
                                    />
                                </div>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    <button
                                        type="submit"
                                        disabled={deleteStatus === 'loading'}
                                        style={{
                                            flex: 1,
                                            padding: '12px 0',
                                            background: T.error,
                                            color: T.white,
                                            fontWeight: 700,
                                            fontSize: 14,
                                            borderRadius: 10,
                                            border: 'none',
                                            cursor: 'pointer',
                                            opacity: deleteStatus === 'loading' ? 0.5 : 1,
                                            fontFamily: 'Inter, sans-serif',
                                        }}
                                    >
                                        {deleteStatus === 'loading' ? 'Eliminando...' : 'Confirmar eliminacion'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setShowDeleteConfirm(false); setDeletePassword(''); setDeleteError(''); }}
                                        style={{
                                            flex: 1,
                                            padding: '12px 0',
                                            background: T.surfaceContainerLow,
                                            color: T.onSurfaceVariant,
                                            fontWeight: 600,
                                            fontSize: 14,
                                            borderRadius: 10,
                                            border: 'none',
                                            cursor: 'pointer',
                                            fontFamily: 'Inter, sans-serif',
                                        }}
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </section>

                {/* ── Version Footer ── */}
                <footer style={{ textAlign: 'center', padding: '24px 0' }}>
                    <p style={{ color: T.outline, fontSize: 11, fontWeight: 500, letterSpacing: '0.1em', textTransform: 'uppercase' }}>
                        Version 1.2.0
                    </p>
                </footer>
            </main>
        </div>
    );
}
