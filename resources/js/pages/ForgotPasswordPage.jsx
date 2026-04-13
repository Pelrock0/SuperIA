import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';
import SuperiaLogo from '../components/SuperiaLogo';

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState('idle');
    const [message, setMessage] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');

        try {
            const response = await api.post('/auth/forgot-password', { email });
            setMessage(response.data.data.message);
            setStatus('success');
        } catch {
            setMessage('Si el email está registrado, recibirás un enlace de recuperación.');
            setStatus('success');
        }
    };

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
                    <SuperiaLogo size="lg" />
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
                            Recuperar contraseña
                        </h1>
                        <p className="text-center mb-8" style={{ color: '#41484c' }}>
                            Introduce tu email y te enviaremos un enlace para restablecerla.
                        </p>

                        {status === 'success' ? (
                            <div className="text-center" data-testid="forgot-success">
                                <div
                                    style={{
                                        background: 'rgba(111, 251, 190, 0.3)',
                                        color: '#002a1a',
                                        padding: '16px',
                                        borderRadius: '12px',
                                        marginBottom: '24px',
                                        fontSize: '14px',
                                    }}
                                >
                                    {message}
                                </div>
                                <Link
                                    to="/login"
                                    style={{
                                        color: '#002736',
                                        fontWeight: 600,
                                        textDecoration: 'none',
                                    }}
                                >
                                    Volver a iniciar sesión
                                </Link>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} data-testid="forgot-form">
                                <div style={{ marginBottom: '20px' }}>
                                    <label
                                        htmlFor="email"
                                        className="block text-xs font-medium uppercase tracking-wide mb-2"
                                        style={{ color: '#41484c' }}
                                    >
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        id="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        required
                                        className="w-full px-4 py-3 rounded-lg text-base transition-colors outline-none"
                                        style={{
                                            border: '1px solid #c1c7cd',
                                            backgroundColor: '#f7f9fb',
                                            color: '#191c1e',
                                        }}
                                        placeholder="tu@email.com"
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
                                    {status === 'loading' ? 'Enviando...' : 'Enviar enlace  →'}
                                </button>

                                <p className="text-center text-sm mt-5" style={{ color: '#41484c' }}>
                                    <Link
                                        to="/login"
                                        style={{ color: '#002736', fontWeight: 500, textDecoration: 'none' }}
                                    >
                                        Volver a iniciar sesión
                                    </Link>
                                </p>
                            </form>
                        )}
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
