import React from 'react';
import { Link } from 'react-router-dom';
import HeroSection from '../components/HeroSection';
import FeaturesSection from '../components/FeaturesSection';
import DataCommitment from '../components/DataCommitment';
import WaitlistForm from '../components/WaitlistForm';
import SuperiaLogo from '../components/SuperiaLogo';

export default function LandingPage() {
    return (
        <div style={{ minHeight: '100vh', backgroundColor: '#f7f9fb', fontFamily: "'Inter', sans-serif" }}>
            {/* TopAppBar */}
            <header
                className="landing-header"
                style={{
                    position: 'fixed',
                    top: 0,
                    width: '100%',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    paddingLeft: '2rem',
                    paddingRight: '2rem',
                    height: '5rem',
                    maxWidth: '100%',
                    margin: '0 auto',
                    zIndex: 50,
                    backgroundColor: 'rgba(248, 250, 252, 0.7)',
                    backdropFilter: 'blur(24px)',
                    WebkitBackdropFilter: 'blur(24px)',
                }}
            >
                <SuperiaLogo />
                <nav className="landing-nav" style={{ display: 'flex', alignItems: 'center', gap: '2rem' }}>
                    <a
                        href="#features"
                        style={{
                            color: '#003E54',
                            borderBottom: '2px solid #003E54',
                            paddingBottom: '0.25rem',
                            fontWeight: 700,
                            textDecoration: 'none',
                        }}
                    >
                        Funciones
                    </a>
                    <a href="#waitlist" style={{ color: '#64748b', fontWeight: 500, textDecoration: 'none' }}>
                        Lista de espera
                    </a>
                </nav>
                <div className="landing-auth" style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <Link
                        to="/login"
                        className="auth-login-link"
                        style={{
                            color: '#003E54',
                            fontWeight: 600,
                            textDecoration: 'none',
                            fontSize: '0.9rem',
                        }}
                    >
                        Iniciar sesi&oacute;n
                    </Link>
                    <a
                        href="#waitlist"
                        className="auth-waitlist-btn"
                        style={{
                            backgroundColor: '#003E54',
                            color: '#ffffff',
                            fontWeight: 600,
                            fontSize: '0.9rem',
                            padding: '0.5rem 1.25rem',
                            borderRadius: '9999px',
                            textDecoration: 'none',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        <span className="auth-waitlist-full">Unirse a la waitlist</span>
                        <span className="auth-waitlist-short">Waitlist</span>
                    </a>
                </div>
            </header>

            <main style={{ paddingTop: '5rem' }}>
                <HeroSection />
                <FeaturesSection />
                <DataCommitment />

                <section id="waitlist" className="waitlist-section" style={{ padding: '8rem 3rem', backgroundColor: '#ffffff', position: 'relative', overflow: 'hidden' }}>
                    <WaitlistForm />
                    {/* Background element */}
                    <div
                        style={{
                            position: 'absolute',
                            bottom: 0,
                            left: 0,
                            width: '100%',
                            height: '50%',
                            backgroundColor: '#ffffff',
                            zIndex: 0,
                        }}
                    />
                </section>
            </main>

            {/* Footer */}
            <footer
                className="landing-footer"
                style={{
                    backgroundColor: '#f1f5f9',
                    width: '100%',
                    display: 'flex',
                    flexWrap: 'wrap',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '3rem',
                    gap: '1.5rem',
                }}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                    <div style={{ fontSize: '1.125rem', fontWeight: 700, color: '#003E54' }}>
                        Superia Digital Concierge
                    </div>
                    <p style={{ color: '#64748b', fontSize: '0.875rem' }}>
                        &copy; {new Date().getFullYear()} Superia Digital Concierge
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '2rem' }}>
                    <Link
                        to="/privacy"
                        style={{ color: '#64748b', fontSize: '0.875rem', fontWeight: 500, textDecoration: 'none', opacity: 0.8 }}
                    >
                        Pol&iacute;tica de privacidad
                    </Link>
                    <Link
                        to="/terms"
                        style={{ color: '#64748b', fontSize: '0.875rem', fontWeight: 500, textDecoration: 'none', opacity: 0.8 }}
                    >
                        T&eacute;rminos de servicio
                    </Link>
                    <Link
                        to="/legal"
                        style={{ color: '#64748b', fontSize: '0.875rem', fontWeight: 500, textDecoration: 'none', opacity: 0.8 }}
                    >
                        Aviso legal
                    </Link>
                    <Link
                        to="/sustainability"
                        style={{ color: '#64748b', fontSize: '0.875rem', fontWeight: 500, textDecoration: 'none', opacity: 0.8 }}
                    >
                        Sostenibilidad
                    </Link>
                </div>
            </footer>
        </div>
    );
}
