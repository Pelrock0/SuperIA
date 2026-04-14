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
                            maxWidth: '28rem',
                            aspectRatio: '9 / 19',
                            borderRadius: '3rem',
                            padding: '1rem',
                            backgroundColor: '#003e54',
                            boxShadow: '0 25px 50px -12px rgba(0,0,0,0.25)',
                            overflow: 'hidden',
                            border: '8px solid #003e54',
                        }}
                    >
                        <img
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuBX7exqSa2AhWeBiD9Aj6jusE663YCLlmtbs-CrQ6kMpnJBOiM6mW5SytwlW8WtCgMdswnMmkdgIVpWqxRLxVSAf57O-hxK-wSBzXCsjBAfNSdUARz02Qwm0UXXkP20OJtjVdu5Soqdy2ZPFnA-pene0StNrakWWRGoE3DtoXOCn_Vku2fooP549VfYNLDs1I-1DISMA2pmX3HROYwIMSupSiiaTmbSNxm5SHPZMMMN0zhWeqduU4s9hQacb2A3Olg5N6l1uWs_5Xg"
                            alt="smartphone screen showing a clean modern mobile application interface with minimalist product lists and soft pastel colors"
                            style={{
                                width: '100%',
                                height: '100%',
                                objectFit: 'cover',
                                borderRadius: '2rem',
                            }}
                        />
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
