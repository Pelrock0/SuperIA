import React, { useState, useEffect } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import api from '../lib/api';
import SuperlistiaLogo from '../components/SuperlistiaLogo';

export default function RegisterPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get('token');

    const [invitation, setInvitation] = useState(null);
    const [tokenError, setTokenError] = useState(null);
    const [formData, setFormData] = useState({
        name: '',
        password: '',
        password_confirmation: '',
        privacy_accepted: false,
    });
    const [status, setStatus] = useState('idle');
    const [errors, setErrors] = useState({});
    const [successMessage, setSuccessMessage] = useState('');

    useEffect(() => {
        if (!token) {
            setTokenError('No se ha proporcionado un enlace de invitacion valido.');
            return;
        }

        api.get(`/auth/invitation/${token}`)
            .then((response) => {
                setInvitation(response.data.data);
                setFormData((prev) => ({ ...prev, name: response.data.data.name || '' }));
            })
            .catch(() => {
                setTokenError('El enlace de invitacion es invalido o ha expirado.');
            });
    }, [token]);

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        setErrors((prev) => ({ ...prev, [name]: undefined }));
    };

    const getPasswordStrength = () => {
        const p = formData.password;
        if (p.length === 0) return { level: 0, label: '', color: '' };
        if (p.length < 6) return { level: 1, label: 'Débil', color: '#ba1a1a' };
        if (p.length < 8 || !/[A-Z]/.test(p) || !/[0-9]/.test(p)) return { level: 2, label: 'Media', color: '#00677d' };
        return { level: 3, label: 'Fuerte', color: '#10b981' };
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');
        setErrors({});

        try {
            const response = await api.post('/auth/register', {
                token,
                ...formData,
            });
            setSuccessMessage(response.data.data.message);
            setStatus('success');
        } catch (error) {
            if (error.response?.status === 422) {
                const errData = error.response.data;
                setErrors(errData.errors || { general: errData.error?.message });
            } else {
                setErrors({ general: 'Ha ocurrido un error. Intentalo de nuevo.' });
            }
            setStatus('error');
        }
    };

    const strength = getPasswordStrength();

    if (tokenError) {
        return (
            <div className="min-h-screen flex items-center justify-center px-4" style={{ backgroundColor: '#f7f9fb' }}>
                <div className="max-w-md w-full text-center" data-testid="token-error">
                    <SuperlistiaLogo size="lg" />
                    <h1 className="text-2xl font-bold mt-8 mb-4" style={{ color: '#003e54' }}>Enlace no valido</h1>
                    <p className="mb-6" style={{ color: '#41484c' }}>{tokenError}</p>
                    <Link to="/" className="font-bold hover:opacity-70" style={{ color: '#002736' }}>
                        Volver a la pagina principal
                    </Link>
                </div>
            </div>
        );
    }

    if (!invitation) {
        return (
            <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: '#f7f9fb' }} data-testid="loading">
                <div style={{ color: '#41484c' }}>Verificando invitacion...</div>
            </div>
        );
    }

    if (status === 'success') {
        return (
            <div className="min-h-screen flex items-center justify-center px-4" style={{ backgroundColor: '#f7f9fb' }}>
                <div className="max-w-md w-full text-center" data-testid="register-success">
                    <SuperlistiaLogo size="lg" />
                    <h1 className="text-2xl font-bold mt-8 mb-4" style={{ color: '#10b981' }}>Registro exitoso</h1>
                    <p className="mb-6" style={{ color: '#41484c' }}>{successMessage}</p>
                    <Link to="/login" className="font-bold hover:opacity-70" style={{ color: '#002736' }}>
                        Ir a iniciar sesión
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex flex-col items-center justify-center p-4" style={{ backgroundColor: '#f7f9fb', fontFamily: "'Inter', sans-serif" }}>
            <main className="w-full max-w-md">
                {/* Logo */}
                <div className="flex justify-center mb-8">
                    <SuperlistiaLogo size="lg" />
                </div>

                {/* Card */}
                <div className="rounded-xl overflow-hidden" style={{ backgroundColor: '#ffffff', boxShadow: '0 4px 24px 0 rgba(0,39,54,0.06)' }}>
                    {/* Hero image */}
                    <div className="h-32 w-full relative" style={{ backgroundColor: '#003e54' }}>
                        <div className="absolute inset-0 bg-gradient-to-t from-white to-transparent" />
                    </div>

                    <div className="px-8 pb-10 pt-4">
                        <div className="mb-8">
                            <h1 className="text-2xl font-bold tracking-tight mb-2" style={{ color: '#003e54' }}>
                                ¡Te hemos reservado tu plaza!
                            </h1>
                            <p className="text-sm leading-relaxed" style={{ color: '#41484c' }}>
                                Crea tu cuenta y empieza a hacer la compra más inteligente.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6" data-testid="register-form">
                            {errors.general && (
                                <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm" role="alert">
                                    {typeof errors.general === 'string' ? errors.general : errors.general[0]}
                                </div>
                            )}

                            {/* Email (read-only) */}
                            <div>
                                <label htmlFor="email" className="block text-[10px] font-bold uppercase tracking-wider mb-1.5 ml-1" style={{ color: '#41484c' }}>
                                    Email de invitación
                                </label>
                                <div className="relative">
                                    <input
                                        type="email"
                                        id="email"
                                        value={invitation.email}
                                        disabled
                                        className="w-full rounded-xl px-4 py-3 cursor-not-allowed"
                                        style={{ backgroundColor: '#f2f4f6', border: 'none', color: '#71787d' }}
                                    />
                                    <span className="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-lg" style={{ color: '#71787d' }}>lock</span>
                                </div>
                            </div>

                            {/* Name */}
                            <div>
                                <label htmlFor="name" className="block text-[10px] font-bold uppercase tracking-wider mb-1.5 ml-1" style={{ color: '#41484c' }}>
                                    Nombre
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value={formData.name}
                                    onChange={handleChange}
                                    required
                                    maxLength={255}
                                    placeholder="Tu nombre completo"
                                    className="w-full rounded-xl px-4 py-3 transition-all outline-none"
                                    style={{ backgroundColor: '#f2f4f6', border: 'none', color: '#002736' }}
                                    onFocus={(e) => e.target.style.boxShadow = '0 0 0 2px rgba(0,39,54,0.1)'}
                                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                                />
                                {errors.name && <p className="text-red-600 text-sm mt-1">{errors.name[0] || errors.name}</p>}
                            </div>

                            {/* Password */}
                            <div>
                                <label htmlFor="password" className="block text-[10px] font-bold uppercase tracking-wider mb-1.5 ml-1" style={{ color: '#41484c' }}>
                                    Contraseña
                                </label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    value={formData.password}
                                    onChange={handleChange}
                                    required
                                    minLength={8}
                                    placeholder="••••••••"
                                    className="w-full rounded-xl px-4 py-3 transition-all outline-none"
                                    style={{ backgroundColor: '#f2f4f6', border: 'none', color: '#002736' }}
                                    onFocus={(e) => e.target.style.boxShadow = '0 0 0 2px rgba(0,39,54,0.1)'}
                                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                                />
                                {formData.password.length > 0 && (
                                    <div className="mt-3 px-1">
                                        <div className="flex justify-between items-center mb-1.5">
                                            <span className="text-[10px] font-medium" style={{ color: '#41484c' }}>Seguridad de la contraseña</span>
                                            <span className="text-[10px] font-bold uppercase tracking-tight" style={{ color: strength.color }}>{strength.label}</span>
                                        </div>
                                        <div className="flex gap-1.5 h-1">
                                            {[1, 2, 3, 4].map((i) => (
                                                <div
                                                    key={i}
                                                    className="flex-1 rounded-full"
                                                    style={{ backgroundColor: i <= strength.level ? '#50d9fe' : '#e6e8ea' }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {errors.password && <p className="text-red-600 text-sm mt-1">{errors.password[0] || errors.password}</p>}
                            </div>

                            {/* Confirm Password */}
                            <div>
                                <label htmlFor="password_confirmation" className="block text-[10px] font-bold uppercase tracking-wider mb-1.5 ml-1" style={{ color: '#41484c' }}>
                                    Confirmar Contraseña
                                </label>
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    value={formData.password_confirmation}
                                    onChange={handleChange}
                                    required
                                    placeholder="••••••••"
                                    className="w-full rounded-xl px-4 py-3 transition-all outline-none"
                                    style={{ backgroundColor: '#f2f4f6', border: 'none', color: '#002736' }}
                                    onFocus={(e) => e.target.style.boxShadow = '0 0 0 2px rgba(0,39,54,0.1)'}
                                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                                />
                            </div>

                            {/* Privacy checkbox */}
                            <div className="flex items-start gap-3 pt-2">
                                <input
                                    type="checkbox"
                                    id="privacy_accepted"
                                    name="privacy_accepted"
                                    checked={formData.privacy_accepted}
                                    onChange={handleChange}
                                    required
                                    className="w-5 h-5 rounded mt-0.5"
                                    style={{ accentColor: '#002736' }}
                                />
                                <label htmlFor="privacy_accepted" className="text-xs leading-normal" style={{ color: '#41484c' }}>
                                    Acepto la{' '}
                                    <Link to="/privacy" className="font-bold underline" style={{ color: '#002736' }} target="_blank">
                                        política de privacidad
                                    </Link> y los términos de servicio.
                                </label>
                            </div>
                            {errors.privacy_accepted && <p className="text-red-600 text-sm">{errors.privacy_accepted[0] || errors.privacy_accepted}</p>}

                            {/* Submit */}
                            <div className="pt-4">
                                <button
                                    type="submit"
                                    disabled={status === 'loading'}
                                    className="w-full font-bold py-4 px-6 rounded-xl text-white flex items-center justify-center gap-2 transition-all hover:opacity-90 active:scale-[0.98] disabled:opacity-50"
                                    style={{ background: 'linear-gradient(to right, #002736, #003e54)', boxShadow: '0 4px 12px rgba(0,39,54,0.1)' }}
                                >
                                    <span>{status === 'loading' ? 'Creando cuenta...' : 'Crear mi cuenta'}</span>
                                    {status !== 'loading' && <span className="material-symbols-outlined text-lg">arrow_forward</span>}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Footer */}
                <p className="mt-8 text-center text-sm" style={{ color: '#41484c' }}>
                    ¿Ya tienes cuenta?{' '}
                    <Link to="/login" className="font-bold hover:opacity-70 transition-colors" style={{ color: '#002736' }}>
                        Iniciar sesión
                    </Link>
                </p>
            </main>
        </div>
    );
}
