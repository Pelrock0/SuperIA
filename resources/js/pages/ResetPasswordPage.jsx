import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../lib/api';
import SuperlistiaLogo from '../components/SuperlistiaLogo';

export default function ResetPasswordPage() {
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token');
    const email = searchParams.get('email');

    const [formData, setFormData] = useState({
        password: '',
        password_confirmation: '',
    });
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState('');

    if (!token || !email) {
        return (
            <div
                className="min-h-screen flex flex-col"
                style={{
                    background: 'linear-gradient(135deg, #f7f9fb 0%, #e6f2f8 50%, #f0fdf4 100%)',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div className="flex justify-center pt-8 mb-8">
                    <Link to="/">
                        <SuperlistiaLogo size="lg" />
                    </Link>
                </div>
                <div className="flex-1 flex items-start justify-center px-4">
                    <div className="w-full max-w-md">
                        <div
                            className="rounded-2xl p-8 md:p-10 text-center"
                            data-testid="invalid-link"
                            style={{
                                backgroundColor: 'rgba(255,255,255,0.85)',
                                backdropFilter: 'blur(20px)',
                                boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                            }}
                        >
                            <h1 className="text-2xl font-bold mb-4" style={{ color: '#191c1e' }}>
                                Enlace no valido
                            </h1>
                            <p className="mb-6" style={{ color: '#41484c' }}>
                                El enlace de restablecimiento es invalido o ha expirado.
                            </p>
                            <Link
                                to="/forgot-password"
                                style={{ color: '#002736', fontWeight: 600, textDecoration: 'none' }}
                            >
                                Solicitar nuevo enlace
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    const handleChange = (e) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
        setError('');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');
        setError('');

        try {
            await api.post('/auth/reset-password', {
                token,
                email,
                ...formData,
            });
            setStatus('success');
        } catch (err) {
            const errData = err.response?.data?.error;
            setError(errData?.message || 'Ha ocurrido un error. Inténtalo de nuevo.');
            setStatus('error');
        }
    };

    if (status === 'success') {
        return (
            <div
                className="min-h-screen flex flex-col"
                style={{
                    background: 'linear-gradient(135deg, #f7f9fb 0%, #e6f2f8 50%, #f0fdf4 100%)',
                    fontFamily: "'Inter', sans-serif",
                }}
            >
                <div className="flex justify-center pt-8 mb-8">
                    <Link to="/">
                        <SuperlistiaLogo size="lg" />
                    </Link>
                </div>
                <div className="flex-1 flex items-start justify-center px-4">
                    <div className="w-full max-w-md">
                        <div
                            className="rounded-2xl p-8 md:p-10 text-center"
                            data-testid="reset-success"
                            style={{
                                backgroundColor: 'rgba(255,255,255,0.85)',
                                backdropFilter: 'blur(20px)',
                                boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                            }}
                        >
                            <div
                                style={{
                                    width: '64px',
                                    height: '64px',
                                    background: 'rgba(111, 251, 190, 0.3)',
                                    borderRadius: '9999px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    margin: '0 auto 16px',
                                }}
                            >
                                <span className="material-symbols-outlined" style={{ color: '#10b981', fontSize: '32px' }}>
                                    check_circle
                                </span>
                            </div>
                            <h1 className="text-2xl font-bold mb-4" style={{ color: '#10b981' }}>
                                Contraseña restablecida
                            </h1>
                            <p className="mb-6" style={{ color: '#41484c' }}>
                                Ya puedes iniciar sesión con tu nueva contraseña.
                            </p>
                            <Link
                                to="/login"
                                className="inline-block w-full py-3 px-6 rounded-lg font-semibold text-white text-center"
                                style={{ backgroundColor: '#002736', textDecoration: 'none' }}
                            >
                                Iniciar sesión
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div
            className="min-h-screen flex flex-col"
            style={{
                background: 'linear-gradient(135deg, #f7f9fb 0%, #e6f2f8 50%, #f0fdf4 100%)',
                fontFamily: "'Inter', sans-serif",
            }}
        >
            <div className="flex justify-center pt-8 mb-8">
                <Link to="/">
                    <SuperlistiaLogo size="lg" />
                </Link>
            </div>

            <div className="flex-1 flex items-start justify-center px-4">
                <div className="w-full max-w-md">
                    <div
                        className="rounded-2xl p-8 md:p-10"
                        style={{
                            backgroundColor: 'rgba(255,255,255,0.85)',
                            backdropFilter: 'blur(20px)',
                            boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.08)',
                        }}
                    >
                        <h1 className="text-3xl font-bold text-center mb-2" style={{ color: '#191c1e' }}>
                            Nueva contraseña
                        </h1>
                        <p className="text-center mb-8" style={{ color: '#41484c' }}>
                            Introduce tu nueva contraseña.
                        </p>

                        <form onSubmit={handleSubmit} data-testid="reset-form">
                            {error && (
                                <div
                                    role="alert"
                                    style={{
                                        background: '#ffdad6',
                                        color: '#93000a',
                                        padding: '12px',
                                        borderRadius: '12px',
                                        fontSize: '14px',
                                        marginBottom: '20px',
                                    }}
                                >
                                    {error}
                                </div>
                            )}

                            <div style={{ marginBottom: '20px' }}>
                                <label
                                    htmlFor="password"
                                    className="block text-xs font-medium uppercase tracking-wide mb-2"
                                    style={{ color: '#41484c' }}
                                >
                                    Nueva contraseña *
                                </label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    value={formData.password}
                                    onChange={handleChange}
                                    required
                                    minLength={8}
                                    className="w-full px-4 py-3 rounded-lg text-base transition-colors outline-none"
                                    style={{
                                        border: '1px solid #c1c7cd',
                                        backgroundColor: '#f7f9fb',
                                        color: '#191c1e',
                                    }}
                                    onFocus={(e) => (e.target.style.borderColor = '#002736')}
                                    onBlur={(e) => (e.target.style.borderColor = '#c1c7cd')}
                                />
                                <p className="text-xs mt-1" style={{ color: '#71787d' }}>
                                    Mínimo 8 caracteres, 1 mayúscula y 1 número
                                </p>
                            </div>

                            <div style={{ marginBottom: '24px' }}>
                                <label
                                    htmlFor="password_confirmation"
                                    className="block text-xs font-medium uppercase tracking-wide mb-2"
                                    style={{ color: '#41484c' }}
                                >
                                    Confirmar contraseña *
                                </label>
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    value={formData.password_confirmation}
                                    onChange={handleChange}
                                    required
                                    className="w-full px-4 py-3 rounded-lg text-base transition-colors outline-none"
                                    style={{
                                        border: '1px solid #c1c7cd',
                                        backgroundColor: '#f7f9fb',
                                        color: '#191c1e',
                                    }}
                                    onFocus={(e) => (e.target.style.borderColor = '#002736')}
                                    onBlur={(e) => (e.target.style.borderColor = '#c1c7cd')}
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={status === 'loading'}
                                className="w-full py-3 px-6 rounded-lg font-semibold text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                style={{ backgroundColor: '#002736' }}
                                onMouseOver={(e) => {
                                    if (!e.target.disabled) e.target.style.backgroundColor = '#003e54';
                                }}
                                onMouseOut={(e) => (e.target.style.backgroundColor = '#002736')}
                            >
                                {status === 'loading' ? 'Restableciendo...' : 'Restablecer contraseña →'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div className="flex justify-center gap-6 py-6">
                <span className="text-xs cursor-pointer hover:opacity-70" style={{ color: '#71787d' }}>
                    Ayuda
                </span>
                <span className="text-xs" style={{ color: '#71787d' }}>
                    ES
                </span>
            </div>
        </div>
    );
}
