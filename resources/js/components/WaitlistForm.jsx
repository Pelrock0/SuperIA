import React, { useState } from 'react';
import api from '../lib/api';

const COMPANION_OPTIONS = [
    { value: 'solo', label: 'Solo' },
    { value: 'pareja', label: 'Pareja' },
    { value: 'familia', label: 'Familia' },
    { value: 'companeros', label: 'Compañeros' },
];

export default function WaitlistForm() {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        shopping_companion: '',
    });
    const [status, setStatus] = useState('idle');
    const [position, setPosition] = useState(null);
    const [errors, setErrors] = useState({});

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
        setErrors({ ...errors, [e.target.name]: undefined });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatus('loading');
        setErrors({});

        try {
            const payload = { name: formData.name, email: formData.email };
            if (formData.shopping_companion) {
                payload.shopping_companion = formData.shopping_companion;
            }
            const response = await api.post('/waitlist', payload);
            setPosition(response.data.position);
            setStatus('success');
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors || {});
            } else if (error.response?.status === 429) {
                setErrors({ general: 'Has alcanzado el límite de intentos. Inténtalo más tarde.' });
            } else {
                setErrors({ general: 'Ha ocurrido un error. Inténtalo de nuevo.' });
            }
            setStatus('error');
        }
    };

    if (status === 'success') {
        return (
            <div
                data-testid="waitlist-success"
                style={{
                    maxWidth: '56rem',
                    margin: '0 auto',
                    backgroundColor: '#f2f4f6',
                    borderRadius: '3rem',
                    padding: '5rem',
                    position: 'relative',
                    zIndex: 10,
                    textAlign: 'center',
                }}
            >
                <h3 style={{ fontSize: '1.5rem', fontWeight: 700, marginBottom: '0.5rem', color: '#003e54' }}>
                    &iexcl;Est&aacute;s en la lista!
                </h3>
                <p style={{ color: '#41484c' }}>
                    Eres aproximadamente el n&uacute;mero <strong>{position}</strong> en la lista de espera.
                </p>
                <p style={{ fontSize: '0.875rem', marginTop: '0.5rem', color: '#71787d' }}>
                    Te enviaremos un email de confirmaci&oacute;n.
                </p>
            </div>
        );
    }

    return (
        <div
            style={{
                maxWidth: '56rem',
                margin: '0 auto',
                backgroundColor: '#f2f4f6',
                borderRadius: '3rem',
                padding: '3rem',
                position: 'relative',
                zIndex: 10,
            }}
        >
            {/* Responsive padding handled via container */}
            <div style={{ padding: '2rem' }}>
                <div style={{ textAlign: 'center', marginBottom: '4rem' }}>
                    <h2
                        style={{
                            fontSize: '2.25rem',
                            fontWeight: 700,
                            letterSpacing: '-0.025em',
                            color: '#002736',
                            marginBottom: '1rem',
                        }}
                    >
                        S&eacute; el primero en probarlo
                    </h2>
                    <p style={{ color: '#41484c', margin: 0 }}>
                        Lanzaremos la beta privada muy pronto. Reserva tu plaza.
                    </p>
                </div>

                <form onSubmit={handleSubmit} data-testid="waitlist-form" style={{ display: 'flex', flexDirection: 'column', gap: '3rem' }}>
                    {errors.general && (
                        <div
                            role="alert"
                            style={{
                                backgroundColor: '#fef2f2',
                                color: '#b91c1c',
                                padding: '0.75rem',
                                borderRadius: '0.5rem',
                                fontSize: '0.875rem',
                            }}
                        >
                            {errors.general}
                        </div>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '2rem' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                            <label
                                htmlFor="name"
                                style={{
                                    fontSize: '0.75rem',
                                    fontWeight: 700,
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.1em',
                                    color: '#71787d',
                                }}
                            >
                                Nombre completo
                            </label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value={formData.name}
                                onChange={handleChange}
                                required
                                maxLength={100}
                                placeholder="Ej. Ana García"
                                style={{
                                    width: '100%',
                                    backgroundColor: '#ffffff',
                                    border: 'none',
                                    borderRadius: '0.75rem',
                                    padding: '1rem',
                                    fontSize: '1rem',
                                    color: '#191c1e',
                                    outline: 'none',
                                    boxSizing: 'border-box',
                                }}
                            />
                            {errors.name && (
                                <p style={{ color: '#dc2626', fontSize: '0.875rem', marginTop: '0.25rem' }}>
                                    {errors.name[0]}
                                </p>
                            )}
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                            <label
                                htmlFor="email"
                                style={{
                                    fontSize: '0.75rem',
                                    fontWeight: 700,
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.1em',
                                    color: '#71787d',
                                }}
                            >
                                Correo electr&oacute;nico
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value={formData.email}
                                onChange={handleChange}
                                required
                                maxLength={255}
                                placeholder="ana@ejemplo.com"
                                style={{
                                    width: '100%',
                                    backgroundColor: '#ffffff',
                                    border: 'none',
                                    borderRadius: '0.75rem',
                                    padding: '1rem',
                                    fontSize: '1rem',
                                    color: '#191c1e',
                                    outline: 'none',
                                    boxSizing: 'border-box',
                                }}
                            />
                            {errors.email && (
                                <p style={{ color: '#dc2626', fontSize: '0.875rem', marginTop: '0.25rem' }}>
                                    {errors.email[0]}
                                </p>
                            )}
                        </div>
                    </div>

                    <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
                        <legend
                            style={{
                                fontSize: '0.75rem',
                                fontWeight: 700,
                                textTransform: 'uppercase',
                                letterSpacing: '0.1em',
                                color: '#71787d',
                                textAlign: 'center',
                                width: '100%',
                                marginBottom: '1.5rem',
                            }}
                        >
                            &iquest;Con qui&eacute;n haces la compra?
                        </legend>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '1rem' }}>
                            {COMPANION_OPTIONS.map((opt) => (
                                <label
                                    key={opt.value}
                                    style={{ cursor: 'pointer' }}
                                    data-testid={`companion-${opt.value}`}
                                >
                                    <input
                                        type="radio"
                                        name="shopping_companion"
                                        value={opt.value}
                                        checked={formData.shopping_companion === opt.value}
                                        onChange={handleChange}
                                        style={{ display: 'none' }}
                                    />
                                    <div
                                        style={{
                                            padding: '1rem',
                                            textAlign: 'center',
                                            borderRadius: '0.75rem',
                                            backgroundColor:
                                                formData.shopping_companion === opt.value
                                                    ? 'rgba(179, 235, 255, 0.2)'
                                                    : '#ffffff',
                                            border: `2px solid ${
                                                formData.shopping_companion === opt.value
                                                    ? '#50d9fe'
                                                    : 'transparent'
                                            }`,
                                            transition: 'all 200ms',
                                        }}
                                    >
                                        <span style={{ display: 'block', fontWeight: 700, color: '#002736' }}>
                                            {opt.label}
                                        </span>
                                    </div>
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    <div style={{ display: 'flex', justifyContent: 'center', paddingTop: '2rem' }}>
                        <button
                            type="submit"
                            disabled={status === 'loading'}
                            style={{
                                backgroundColor: '#002736',
                                color: '#ffffff',
                                fontSize: '1.125rem',
                                fontWeight: 700,
                                padding: '1.25rem 3rem',
                                borderRadius: '1rem',
                                border: 'none',
                                boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1)',
                                cursor: status === 'loading' ? 'not-allowed' : 'pointer',
                                opacity: status === 'loading' ? 0.5 : 1,
                                transition: 'transform 150ms',
                            }}
                            onMouseOver={(e) => {
                                if (status !== 'loading') e.currentTarget.style.transform = 'scale(0.95)';
                            }}
                            onMouseOut={(e) => {
                                e.currentTarget.style.transform = 'scale(1)';
                            }}
                        >
                            {status === 'loading' ? 'Enviando...' : 'Apuntarme'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
