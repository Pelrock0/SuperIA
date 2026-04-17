import React from 'react';

export default function HeroSection() {
    return (
        <section
            className="hero-section"
            style={{
                position: 'relative',
                minHeight: '921px',
                display: 'flex',
                alignItems: 'center',
                overflow: 'hidden',
                backgroundColor: '#f7f9fb',
                paddingTop: '3rem',
            }}
        >
            <div
                className="hero-grid"
                style={{
                    maxWidth: '1280px',
                    margin: '0 auto',
                    padding: '0 3rem',
                    display: 'grid',
                    gridTemplateColumns: 'repeat(12, 1fr)',
                    gap: '24px',
                    width: '100%',
                }}
            >
                {/* Left column - text */}
                <div
                    className="hero-text"
                    style={{
                        gridColumn: 'span 6',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'center',
                        gap: '2rem',
                        zIndex: 10,
                    }}
                >
                    <h1
                        style={{
                            fontSize: 'clamp(3.75rem, 5vw, 4.5rem)',
                            fontWeight: 800,
                            letterSpacing: '-0.05em',
                            color: '#002736',
                            lineHeight: 0.95,
                            margin: 0,
                        }}
                    >
                        La compra, <br />
                        <span style={{ color: '#50d9fe' }}>m&aacute;s inteligente</span>
                    </h1>
                    <p
                        style={{
                            fontSize: '1.25rem',
                            color: '#41484c',
                            maxWidth: '32rem',
                            lineHeight: 1.625,
                            margin: 0,
                        }}
                    >
                        Listas de compra con IA que aprende de ti. Redescubre el placer de organizar tu hogar con el asistente digital definitivo.
                    </p>
                    <div className="hero-cta" style={{ display: 'flex', gap: '1rem' }}>
                        <a
                            href="#waitlist"
                            style={{
                                background: 'linear-gradient(to bottom right, #003E54, #00B4D8)',
                                color: '#ffffff',
                                padding: '1rem 2rem',
                                borderRadius: '0.75rem',
                                fontWeight: 700,
                                boxShadow: '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)',
                                textDecoration: 'none',
                                display: 'inline-block',
                                transition: 'transform 150ms ease-in-out',
                            }}
                            onMouseOver={(e) => (e.currentTarget.style.transform = 'scale(0.95)')}
                            onMouseOut={(e) => (e.currentTarget.style.transform = 'scale(1)')}
                        >
                            &Uacute;nete a la lista de espera
                        </a>
                    </div>
                </div>

                {/* Right column - phone mockup */}
                <div
                    className="hero-phone"
                    style={{
                        gridColumn: 'span 6',
                        position: 'relative',
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                    }}
                >
                    <div
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: '20rem',
                            borderRadius: '2.5rem',
                            padding: '12px',
                            backgroundColor: '#003e54',
                            boxShadow: '0 25px 50px -12px rgba(0,0,0,0.25)',
                            overflow: 'hidden',
                        }}
                    >
                        {/* Phone screen */}
                        <div
                            style={{
                                backgroundColor: '#f7f9fb',
                                borderRadius: '2rem',
                                overflow: 'hidden',
                                fontFamily: "'Inter', sans-serif",
                            }}
                        >
                            {/* Status bar */}
                            <div style={{ padding: '8px 20px 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '11px', fontWeight: 600, color: '#002736' }}>
                                <span>9:41</span>
                                <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                                    <span style={{ fontSize: '10px' }}>●●●●</span>
                                    <span style={{ fontSize: '10px' }}>WiFi</span>
                                    <span style={{ fontSize: '10px' }}>🔋</span>
                                </div>
                            </div>

                            {/* App header */}
                            <div style={{ padding: '12px 20px 8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <div style={{ width: '28px', height: '28px', backgroundColor: '#002736', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <span style={{ color: '#fff', fontWeight: 800, fontSize: '16px' }}>S</span>
                                    </div>
                                    <span style={{ fontWeight: 700, fontSize: '18px', color: '#002736', letterSpacing: '-0.03em' }}>Superlistia</span>
                                </div>
                                <div style={{ display: 'flex', gap: '8px', opacity: 0.4 }}>
                                    <span className="material-symbols-outlined" style={{ fontSize: '18px', color: '#002736' }}>history</span>
                                    <span className="material-symbols-outlined" style={{ fontSize: '18px', color: '#002736' }}>settings</span>
                                </div>
                            </div>

                            {/* Greeting */}
                            <div style={{ padding: '8px 20px 12px' }}>
                                <h3 style={{ margin: 0, fontSize: '22px', fontWeight: 800, color: '#002736', letterSpacing: '-0.03em' }}>Hola, Laura</h3>
                                <p style={{ margin: '2px 0 0', fontSize: '12px', color: '#71787d' }}>Tienes 2 listas activas.</p>
                            </div>

                            {/* AI card */}
                            <div style={{ margin: '0 16px 12px', borderRadius: '16px', background: 'linear-gradient(135deg, #002736, #003e54)', padding: '16px', position: 'relative', overflow: 'hidden' }}>
                                <div style={{ display: 'flex', gap: '6px', alignItems: 'center', marginBottom: '8px' }}>
                                    <span style={{ fontSize: '9px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#002736', backgroundColor: '#6ffbbe', padding: '2px 8px', borderRadius: '9999px' }}>AI Concierge</span>
                                    <span className="material-symbols-outlined" style={{ fontSize: '14px', color: '#6ffbbe' }}>auto_awesome</span>
                                </div>
                                <p style={{ margin: 0, fontSize: '14px', fontWeight: 700, color: '#fff' }}>Genera listas con IA</p>
                                <p style={{ margin: '4px 0 10px', fontSize: '10px', color: 'rgba(255,255,255,0.65)', lineHeight: 1.4 }}>Describe lo que necesitas y la IA creara tu lista.</p>
                                <span style={{ fontSize: '11px', fontWeight: 700, color: '#002736', backgroundColor: '#6ffbbe', padding: '6px 14px', borderRadius: '8px', display: 'inline-block' }}>Generar lista ✨</span>
                                <div style={{ position: 'absolute', right: '-8px', bottom: '-8px', opacity: 0.1 }}>
                                    <span className="material-symbols-outlined" style={{ fontSize: '72px', color: '#fff' }}>shopping_basket</span>
                                </div>
                            </div>

                            {/* List card */}
                            <div style={{ margin: '0 16px 8px', borderRadius: '16px', backgroundColor: '#fff', padding: '14px 16px', boxShadow: '0 1px 3px rgba(0,39,54,0.06)' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <div style={{ width: '32px', height: '32px', backgroundColor: '#f2f4f6', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <span className="material-symbols-outlined" style={{ fontSize: '16px', color: '#41484c' }}>shopping_cart</span>
                                        </div>
                                        <div>
                                            <p style={{ margin: 0, fontSize: '14px', fontWeight: 700, color: '#002736' }}>🛒 Semanal</p>
                                            <p style={{ margin: 0, fontSize: '10px', color: '#71787d' }}>3 / 8 articulos</p>
                                        </div>
                                    </div>
                                    <span style={{ fontSize: '9px', color: '#71787d', backgroundColor: '#f2f4f6', padding: '2px 8px', borderRadius: '9999px' }}>Hoy</span>
                                </div>
                                {/* Mini item list */}
                                <div style={{ borderTop: '1px solid #f2f4f6', paddingTop: '8px', display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                    {[
                                        { name: 'Leche entera', cat: 'Lacteos', done: true },
                                        { name: 'Pan integral', cat: 'Panaderia', done: true },
                                        { name: 'Pollo', cat: 'Carnes', done: true },
                                        { name: 'Manzanas', cat: 'Frutas', done: false },
                                        { name: 'Arroz', cat: 'Otros', done: false },
                                    ].map((item, i) => (
                                        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <div style={{ width: '14px', height: '14px', borderRadius: '4px', border: `1.5px solid ${item.done ? '#002736' : '#c1c7cd'}`, backgroundColor: item.done ? '#002736' : 'transparent', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                                {item.done && <span style={{ color: '#fff', fontSize: '9px', lineHeight: 1 }}>✓</span>}
                                            </div>
                                            <span style={{ fontSize: '11px', color: item.done ? '#a3a9ae' : '#191c1e', textDecoration: item.done ? 'line-through' : 'none', flex: 1 }}>{item.name}</span>
                                            <span style={{ fontSize: '8px', color: '#71787d', backgroundColor: '#f2f4f6', padding: '1px 6px', borderRadius: '9999px' }}>{item.cat}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Second list teaser */}
                            <div style={{ margin: '0 16px 16px', borderRadius: '16px', backgroundColor: '#fff', padding: '12px 16px', boxShadow: '0 1px 3px rgba(0,39,54,0.06)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <div style={{ width: '32px', height: '32px', backgroundColor: '#f2f4f6', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <span className="material-symbols-outlined" style={{ fontSize: '16px', color: '#41484c' }}>shopping_cart</span>
                                </div>
                                <div style={{ flex: 1 }}>
                                    <p style={{ margin: 0, fontSize: '14px', fontWeight: 700, color: '#002736' }}>🏠 Limpieza</p>
                                    <p style={{ margin: 0, fontSize: '10px', color: '#71787d' }}>0 / 4 articulos</p>
                                </div>
                                <span style={{ fontSize: '9px', color: '#005236', backgroundColor: '#6ffbbe', padding: '2px 8px', borderRadius: '9999px', fontWeight: 600 }}>COMPARTIDA</span>
                            </div>

                            {/* Bottom nav hint */}
                            <div style={{ height: '4px' }} />
                        </div>
                    </div>
                    {/* Decorative background blur */}
                    <div
                        style={{
                            position: 'absolute',
                            top: '-5rem',
                            right: '-5rem',
                            width: '20rem',
                            height: '20rem',
                            backgroundColor: 'rgba(80, 217, 254, 0.2)',
                            borderRadius: '9999px',
                            filter: 'blur(100px)',
                            zIndex: -1,
                        }}
                    />
                </div>
            </div>
        </section>
    );
}
