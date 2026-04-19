import React, { useEffect, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation, Trans } from 'react-i18next';
import { useAuth } from '../context/AuthContext';
import SuperlistiaLogo from '../components/SuperlistiaLogo';
import { isSupported as isWebauthnSupported, probeEnabled as probeWebauthnEnabled, supportsConditionalMediation, authenticateConditional, markDeviceRegistered } from '../lib/webauthnApi';

export default function LoginPage() {
    const { t, i18n } = useTranslation(['login', 'common']);
    const navigate = useNavigate();
    const { login, loginWithPasskey, isAuthenticated, refreshUser } = useAuth();
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
        const abortController = new AbortController();
        probeWebauthnEnabled().then(async (enabled) => {
            setWebauthnAvailable(enabled);
            if (!enabled) return;
            const conditional = await supportsConditionalMediation();
            if (!conditional) return;
            try {
                const result = await authenticateConditional(abortController.signal);
                if (result) {
                    markDeviceRegistered();
                    await refreshUser();
                    navigate('/app', { replace: true });
                }
            } catch {
                // silent — user can still use the button
            }
        }).catch(() => setWebauthnAvailable(false));
        return () => abortController.abort();
    }, []);

    if (isAuthenticated) {
        return <Navigate to="/app" replace />;
    }

    const handleBiometricLogin = async () => {
        setWebauthnStatus('loading');
        setError('');
        try {
            await loginWithPasskey(null);
            navigate('/app', { replace: true });
        } catch (err) {
            const msg = err.response?.data?.error?.message || err.message || t('login:webauthn_error');
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
            setError(errData?.message || t('common:generic_error'));
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
                            {t('login:title')}
                        </h1>
                        <p className="text-center mb-8" style={{ color: '#41484c' }}>
                            {t('login:subtitle')}
                        </p>

                        {webauthnAvailable && (
                            <div className="mb-6" data-testid="webauthn-section">
                                <button
                                    type="button"
                                    onClick={handleBiometricLogin}
                                    disabled={webauthnStatus === 'loading'}
                                    className="w-full py-3 px-6 rounded-lg font-semibold text-white transition-all disabled:opacity-60 flex items-center justify-center gap-2"
                                    style={{ background: 'linear-gradient(to right, #002736, #003e54)' }}
                                    data-testid="webauthn-login-passkey"
                                >
                                    <span className="material-symbols-outlined" aria-hidden="true">fingerprint</span>
                                    {webauthnStatus === 'loading' ? t('login:webauthn_verifying') : t('login:webauthn_cta')}
                                </button>
                                <div className="flex items-center gap-3 my-5">
                                    <div className="flex-1 h-px" style={{ backgroundColor: '#c1c7cd' }}></div>
                                    <span className="text-xs uppercase tracking-wide" style={{ color: '#71787d' }}>{t('login:or_with_email')}</span>
                                    <div className="flex-1 h-px" style={{ backgroundColor: '#c1c7cd' }}></div>
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-5" data-testid="login-form">
                            {verified === 'success' && (
                                <div className="bg-green-50 text-green-700 p-3 rounded-lg text-sm" role="status">
                                    {t('login:verified_success')}
                                </div>
                            )}
                            {verified === 'error' && (
                                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm" role="alert">
                                    {t('login:verified_error')}
                                </div>
                            )}
                            {error && (
                                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm" role="alert">
                                    {error}
                                </div>
                            )}

                            <div>
                                <label htmlFor="email" className="block text-xs font-medium uppercase tracking-wide mb-2" style={{ color: '#41484c' }}>
                                    {t('login:email_label')}
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value={formData.email}
                                    onChange={handleChange}
                                    required
                                    autoComplete="username webauthn"
                                    className="w-full px-4 py-3 rounded-lg text-base transition-colors outline-none"
                                    style={{ border: '1px solid #c1c7cd', backgroundColor: '#f7f9fb', color: '#191c1e' }}
                                    placeholder={t('login:email_placeholder')}
                                    onFocus={(e) => e.target.style.borderColor = '#002736'}
                                    onBlur={(e) => e.target.style.borderColor = '#c1c7cd'}
                                />
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-xs font-medium uppercase tracking-wide mb-2" style={{ color: '#41484c' }}>
                                    {t('login:password_label')}
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
                                        aria-label={showPassword ? t('login:hide_password') : t('login:show_password')}
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
                                    <span className="text-sm" style={{ color: '#41484c' }}>{t('login:remember')}</span>
                                </label>
                                <Link to="/forgot-password" className="text-sm hover:opacity-70 transition-opacity" style={{ color: '#41484c' }}>
                                    {t('login:forgot')}
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
                                {status === 'loading' ? t('login:logging_in') : t('login:submit')}
                            </button>
                        </form>

                    </div>

                    <div className="text-center mt-6">
                        <a
                            href="/#waitlist"
                            className="inline-block text-sm font-medium px-4 py-2 rounded-full"
                            style={{ color: '#003e54', backgroundColor: '#c1e8ff', textDecoration: 'none' }}
                        >
                            {t('login:no_account')}
                        </a>
                        <p className="text-sm mt-3" style={{ color: '#41484c' }}>
                            <a href="/#waitlist" style={{ color: '#003e54', textDecoration: 'underline' }}>
                                {t('login:join_waitlist')}
                            </a>{' '}
                            <Trans
                                i18nKey="login:waitlist_suffix"
                                components={{ 1: <strong style={{ color: '#002736' }} /> }}
                            />
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex justify-center gap-6 py-6">
                <span className="text-xs cursor-pointer hover:opacity-70" style={{ color: '#71787d' }}>{t('common:help')}</span>
                <span className="text-xs" style={{ color: '#71787d' }}>{i18n.language.slice(0, 2).toUpperCase()}</span>
            </div>
        </div>
    );
}
