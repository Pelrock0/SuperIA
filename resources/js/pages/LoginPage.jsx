import React, { useEffect, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import SuperiaLogo from '../components/SuperiaLogo';
import { isSupported as isWebauthnSupported, probeEnabled as probeWebauthnEnabled } from '../lib/webauthnApi';

export default function LoginPage() {
    const navigate = useNavigate();
    const { login, loginWithPasskey, isAuthenticated } = useAuth();
    const [formData, setFormData] = useState({
        email: '',
        password: '',
        remember: false,
    });
    const [searchParams] = useSearchParams();
    const verified = searchParams.get('verified');
    const [showPassword, setShowPassword] = useState(false);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState('');
    const [webauthnAvailable, setWebauthnAvailable] = useState(false);
    const [webauthnStatus, setWebauthnStatus] = useState('idle');

    useEffect(() => {
        if (!isWebauthnSupported()) {
            return;
        }
        probeWebauthnEnabled().then((enabled) => {
            setWebauthnAvailable(enabled);
        }).catch(() => setWebauthnAvailable(false));
    }, []);

    if (isAuthenticated) {
        return <Navigate to="/app" replace />;
    }

    const handleBiometricLogin = async (useEmail) => {
        setWebauthnStatus('loading');
        setError('');
        try {
            await loginWithPasskey(useEmail ? formData.email : null);
            navigate('/app', { replace: true });
        } catch (err) {
            const msg = err.response?.data?.error?.message || err.message || 'Autenticacion biometrica fallida.';
            setError(msg);
            setWebauthnStatus('error');
        }
    };

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        setError('');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');
        setError('');

        try {
            await login(formData.email, formData.password, formData.remember);
            navigate('/app', { replace: true });
        } catch (err) {
            const errData = err.response?.data?.error;
            setError(errData?.message || 'Ha ocurrido un error. Intentalo de nuevo.');
            setStatus('error');
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
                            Bienvenido de nuevo
                        </h1>
                        <p className="text-center mb-8" style={{ color: '#41484c' }}>
                            Tu lista de compra inteligente te espera.
                        </p>

                        <form onSubmit={handleSubmit} className="space-y-5" data-testid="login-form">
                            {verified === 'success' && (
                                <div className="bg-green-50 text-green-700 p-3 rounded-lg text-sm" role="status">
                                    Email verificado correctamente. Ya puedes iniciar sesión.
                                </div>
                            )}
                            {verified === 'error' && (
                                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm" role="alert">
                                    El enlace de verificacion es invalido o ha expirado.
                                </div>
                            )}
                            {error && (
                                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm" role="alert">
                                    {error}
                                </div>
                            )}

                            <div>
                                <label htmlFor="email" className="block text-xs font-medium uppercase tracking-wide mb-2" style={{ color: '#41484c' }}>
                                    Email
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value={formData.email}
                                    onChange={handleChange}
                                    required
                                    className="w-full px-4 py-3 rounded-lg text-base transition-colors outline-none"
                                    style={{ border: '1px solid #c1c7cd', backgroundColor: '#f7f9fb', color: '#191c1e' }}
                                    placeholder="ejemplo@superia.es"
                                    onFocus={(e) => e.target.style.borderColor = '#002736'}
                                    onBlur={(e) => e.target.style.borderColor = '#c1c7cd'}
                                />
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-xs font-medium uppercase tracking-wide mb-2" style={{ color: '#41484c' }}>
                                    Password
                                </label>
                                <div className="relative">
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        id="password"
                                        name="password"
                                        value={formData.password}
                                        onChange={handleChange}
                                        required
                                        className="w-full px-4 py-3 rounded-lg text-base pr-12 transition-colors outline-none"
                                        style={{ border: '1px solid #c1c7cd', backgroundColor: '#f7f9fb', color: '#191c1e' }}
                                        onFocus={(e) => e.target.style.borderColor = '#002736'}
                                        onBlur={(e) => e.target.style.borderColor = '#c1c7cd'}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 p-1 opacity-50 hover:opacity-100"
                                        aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    >
                                        {showPassword ? '🙈' : '👁'}
                                    </button>
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        checked={formData.remember}
                                        onChange={handleChange}
                                        className="rounded"
                                        style={{ accentColor: '#002736' }}
                                    />
                                    <span className="text-sm" style={{ color: '#41484c' }}>Recuérdame</span>
                                </label>
                                <Link to="/forgot-password" className="text-sm hover:opacity-70 transition-opacity" style={{ color: '#41484c' }}>
                                    Olvidé mi contraseña
                                </Link>
                            </div>

                            <button
                                type="submit"
                                disabled={status === 'loading'}
                                className="w-full py-3 px-6 rounded-lg font-semibold text-white transition-colors disabled:opacity-50"
                                style={{ backgroundColor: '#002736' }}
                                onMouseOver={(e) => { if (!e.target.disabled) e.target.style.backgroundColor = '#003e54'; }}
                                onMouseOut={(e) => e.target.style.backgroundColor = '#002736'}
                            >
                                {status === 'loading' ? 'Entrando...' : 'Iniciar sesión →'}
                            </button>
                        </form>

                        {webauthnAvailable && (
                            <div className="mt-6" data-testid="webauthn-section">
                                <div className="flex items-center gap-3 my-4">
                                    <div className="flex-1 h-px" style={{ backgroundColor: '#c1c7cd' }}></div>
                                    <span className="text-xs" style={{ color: '#71787d' }}>o</span>
                                    <div className="flex-1 h-px" style={{ backgroundColor: '#c1c7cd' }}></div>
                                </div>
                                <div className="space-y-2">
                                    <button
                                        type="button"
                                        onClick={() => handleBiometricLogin(true)}
                                        disabled={!formData.email || webauthnStatus === 'loading'}
                                        className="w-full py-3 px-6 rounded-lg font-semibold transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                                        style={{ border: '1px solid #002736', color: '#002736', backgroundColor: 'transparent' }}
                                        data-testid="webauthn-login-email"
                                    >
                                        <span className="material-symbols-outlined" aria-hidden="true">fingerprint</span>
                                        {webauthnStatus === 'loading' ? 'Verificando...' : 'Entrar con biometría'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => handleBiometricLogin(false)}
                                        disabled={webauthnStatus === 'loading'}
                                        className="w-full py-3 px-6 rounded-lg font-semibold transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                                        style={{ color: '#003e54', backgroundColor: 'transparent' }}
                                        data-testid="webauthn-login-passkey"
                                    >
                                        <span className="material-symbols-outlined" aria-hidden="true">key</span>
                                        Entrar con passkey
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="text-center mt-6">
                        <a
                            href="/#waitlist"
                            className="inline-block text-sm font-medium px-4 py-2 rounded-full"
                            style={{ color: '#003e54', backgroundColor: '#c1e8ff', textDecoration: 'none' }}
                        >
                            ✦ ¿No tienes cuenta?
                        </a>
                        <p className="text-sm mt-3" style={{ color: '#41484c' }}>
                            <a href="/#waitlist" style={{ color: '#003e54', textDecoration: 'underline' }}>
                                Únete a la lista de espera
                            </a>{' '}
                            y te avisaremos cuando esté lista la experiencia <strong style={{ color: '#002736' }}>Superia</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex justify-center gap-6 py-6">
                <span className="text-xs cursor-pointer hover:opacity-70" style={{ color: '#71787d' }}>Ayuda</span>
                <span className="text-xs" style={{ color: '#71787d' }}>ES</span>
            </div>
        </div>
    );
}
