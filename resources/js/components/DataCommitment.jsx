import React from 'react';

export default function DataCommitment() {
    return (
        <section style={{ padding: '8rem 3rem', overflow: 'hidden' }}>
            <div
                style={{
                    maxWidth: '1280px',
                    margin: '0 auto',
                    display: 'grid',
                    gridTemplateColumns: 'repeat(12, 1fr)',
                    gap: '24px',
                    alignItems: 'center',
                }}
            >
                {/* Shield visual */}
                <div style={{ gridColumn: 'span 5', position: 'relative' }}>
                    <div
                        style={{
                            aspectRatio: '1 / 1',
                            backgroundColor: '#003e54',
                            borderRadius: '4rem',
                            overflow: 'hidden',
                            transform: 'rotate(3deg) scale(0.95)',
                            position: 'relative',
                        }}
                    >
                        <img
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuC6zHk2z7ncYZhuLNzhCNg3FFJDgUZ7U10eRKBfAP_dhtep5qOqanfMKRzw7q0H9PY3iZ35k6CJ3CWfdzLeRvTe5aSA3owIHq9lUN7Lgvnf-RiyOJH8IAeIQHZlrd1Ibsdwna1D9Mcp9k0btUJt_JKgGNz-7kDj4QgItJ_0HPvdtBv1WTnajXWM3A0FTN4tjj_LwVpFZ0_dww0f13_ZlfZ2j0Nuc44nUYUJ6TKFbtH3TrIrkWOQ-yqEncaYGW8jNgIF90_2IUElWyE"
                            alt="close-up of server lights in a dark room representing high-end data security and encryption"
                            style={{
                                width: '100%',
                                height: '100%',
                                objectFit: 'cover',
                                filter: 'grayscale(1)',
                                opacity: 0.6,
                            }}
                        />
                        <div
                            style={{
                                position: 'absolute',
                                inset: 0,
                                background: 'linear-gradient(to top, rgba(0, 39, 54, 0.8), transparent)',
                            }}
                        />
                        <div style={{ position: 'absolute', bottom: '3rem', left: '3rem' }}>
                            <span
                                className="material-symbols-outlined"
                                style={{
                                    color: '#ffffff',
                                    fontSize: '3.75rem',
                                    fontVariationSettings: "'FILL' 1",
                                }}
                            >
                                shield_lock
                            </span>
                        </div>
                    </div>
                </div>

                {/* Text content */}
                <div
                    style={{
                        gridColumn: '7 / -1',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '2.5rem',
                    }}
                >
                    <h2
                        style={{
                            fontSize: '3rem',
                            fontWeight: 700,
                            letterSpacing: '-0.05em',
                            color: '#002736',
                            lineHeight: 1.1,
                            margin: 0,
                        }}
                    >
                        Tus listas son tuyas. <br />
                        Sin publicidad. <br />
                        <span style={{ color: '#10b981' }}>Sin venta de datos.</span>
                    </h2>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                        {['Encriptado de extremo a extremo', 'Sin rastreadores de terceros'].map((text) => (
                            <div key={text} style={{ display: 'flex', alignItems: 'flex-start', gap: '1rem' }}>
                                <div
                                    style={{
                                        marginTop: '0.25rem',
                                        width: '1.5rem',
                                        height: '1.5rem',
                                        borderRadius: '9999px',
                                        backgroundColor: '#6ffbbe',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        flexShrink: 0,
                                    }}
                                >
                                    <span
                                        className="material-symbols-outlined"
                                        style={{ fontSize: '0.75rem', color: '#002113', fontWeight: 700 }}
                                    >
                                        check
                                    </span>
                                </div>
                                <p style={{ fontSize: '1.125rem', fontWeight: 500, color: '#191c1e', margin: 0 }}>
                                    {text}
                                </p>
                            </div>
                        ))}
                    </div>

                    <p style={{ color: '#41484c', lineHeight: 1.625, margin: 0 }}>
                        Creemos que la privacidad no es una opci&oacute;n, sino un derecho fundamental. Superia est&aacute; construida sobre una arquitectura de privacidad desde el dise&ntilde;o.
                    </p>
                </div>
            </div>
        </section>
    );
}
