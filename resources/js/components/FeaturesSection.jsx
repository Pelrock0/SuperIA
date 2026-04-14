import React from 'react';

const features = [
    {
        title: 'IA que te sugiere',
        description: 'Anticipamos tus necesidades analizando tus patrones de consumo semanales.',
        badge: 'Inteligencia Activa',
        icon: 'auto_awesome',
        iconBg: '#6ffbbe',
        iconColor: '#002113',
        badgeBg: 'rgba(111, 251, 190, 0.4)',
        badgeColor: '#10b981',
        hoverBorder: 'rgba(111, 251, 190, 0.3)',
    },
    {
        title: 'Listas compartidas',
        description: 'Sincronización en tiempo real para que todos en casa sepan qué falta en la despensa.',
        badge: 'Colaborativo',
        icon: 'groups',
        iconBg: '#b3ebff',
        iconColor: '#001f27',
        badgeBg: 'rgba(179, 235, 255, 0.4)',
        badgeColor: '#005c70',
        hoverBorder: 'rgba(80, 217, 254, 0.3)',
    },
    {
        title: 'Historial y estadísticas',
        description: 'Visualiza tu gasto y optimiza tu presupuesto mensual con gráficos intuitivos.',
        badge: 'Analíticas',
        icon: 'bar_chart',
        iconBg: '#c1e8ff',
        iconColor: '#001e2b',
        badgeBg: 'rgba(193, 232, 255, 0.4)',
        badgeColor: '#174c62',
        hoverBorder: 'rgba(193, 232, 255, 0.3)',
    },
];

export default function FeaturesSection() {
    const [hoveredIndex, setHoveredIndex] = React.useState(null);

    return (
        <section
            id="features"
            className="features-section"
            style={{
                padding: '8rem 3rem',
                backgroundColor: '#f2f4f6',
            }}
        >
            <div style={{ maxWidth: '1280px', margin: '0 auto' }}>
                <div style={{ display: 'flex', flexDirection: 'column', marginBottom: '4rem', maxWidth: '42rem' }}>
                    <span
                        style={{
                            color: '#10b981',
                            fontWeight: 700,
                            textTransform: 'uppercase',
                            letterSpacing: '0.1em',
                            fontSize: '0.875rem',
                            marginBottom: '1rem',
                        }}
                    >
                        Experiencia Premium
                    </span>
                    <h2
                        style={{
                            fontSize: '2.25rem',
                            fontWeight: 700,
                            letterSpacing: '-0.025em',
                            color: '#002736',
                            margin: 0,
                        }}
                    >
                        Dise&ntilde;ado para la vida moderna.
                    </h2>
                </div>
                <div
                    className="features-grid"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, 1fr)',
                        gap: '2rem',
                    }}
                >
                    {features.map((feature, index) => (
                        <div
                            key={feature.title}
                            onMouseEnter={() => setHoveredIndex(index)}
                            onMouseLeave={() => setHoveredIndex(null)}
                            style={{
                                backgroundColor: '#ffffff',
                                padding: '2.5rem',
                                borderRadius: '1.5rem',
                                boxShadow: '0 24px 48px -12px rgba(0, 39, 54, 0.06)',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: '1.5rem',
                                transition: 'transform 300ms, border-color 300ms',
                                transform: hoveredIndex === index ? 'translateY(-0.5rem)' : 'translateY(0)',
                                border: `1px solid ${hoveredIndex === index ? feature.hoverBorder : 'transparent'}`,
                            }}
                        >
                            <div
                                style={{
                                    width: '3.5rem',
                                    height: '3.5rem',
                                    borderRadius: '1rem',
                                    backgroundColor: feature.iconBg,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <span
                                    className="material-symbols-outlined"
                                    style={{ fontSize: '1.875rem', color: feature.iconColor }}
                                >
                                    {feature.icon}
                                </span>
                            </div>
                            <h3 style={{ fontSize: '1.5rem', fontWeight: 700, color: '#002736', margin: 0 }}>
                                {feature.title}
                            </h3>
                            <p style={{ color: '#41484c', lineHeight: 1.625, margin: 0 }}>
                                {feature.description}
                            </p>
                            <div style={{ marginTop: 'auto', paddingTop: '1rem' }}>
                                <span
                                    style={{
                                        fontWeight: 700,
                                        fontSize: '0.875rem',
                                        backgroundColor: feature.badgeBg,
                                        color: feature.badgeColor,
                                        padding: '0.25rem 0.75rem',
                                        borderRadius: '9999px',
                                    }}
                                >
                                    {feature.badge}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
