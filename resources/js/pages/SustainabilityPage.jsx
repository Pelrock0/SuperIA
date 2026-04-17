import React from 'react';
import { Link } from 'react-router-dom';

export default function SustainabilityPage() {
    return (
        <div className="min-h-screen bg-white">
            <header className="bg-gray-50 py-6 px-4 border-b">
                <div className="max-w-3xl mx-auto">
                    <Link to="/" className="text-indigo-600 hover:text-indigo-800 font-medium">
                        ← Volver a Superlistia
                    </Link>
                </div>
            </header>

            <main className="max-w-3xl mx-auto py-12 px-4 prose prose-gray">
                <h1>Sostenibilidad</h1>
                <p className="text-gray-500">Nuestro compromiso con un consumo responsable</p>

                <h2>Nuestra misión</h2>
                <p>
                    Superlistia nace con la convicción de que la tecnología puede ayudar a las familias
                    a comprar mejor, desperdiciar menos y ser más conscientes de su consumo. No
                    somos solo una app de listas de compra: somos una herramienta para un hogar
                    más sostenible.
                </p>

                <h2>Reducción del desperdicio alimentario</h2>
                <p>
                    En España se desperdician más de <strong>1.300 millones de kilos de alimentos
                    al año</strong> en los hogares. Superlistia ayuda a combatir este problema:
                </p>
                <ul>
                    <li>
                        <strong>Listas inteligentes:</strong> las sugerencias de IA se ajustan al
                        número real de personas del hogar, evitando compras excesivas
                    </li>
                    <li>
                        <strong>Historial de consumo:</strong> al ver lo que realmente compras y
                        consumes, puedes identificar patrones de desperdicio
                    </li>
                    <li>
                        <strong>Resúmenes semanales:</strong> estadísticas que te ayudan a optimizar
                        tus compras semana a semana
                    </li>
                </ul>

                <h2>Privacidad como valor sostenible</h2>
                <p>
                    La sostenibilidad también es digital. Superlistia no utiliza cookies de tracking,
                    no vende datos a terceros y no bombardea con publicidad. Un modelo de negocio
                    limpio que respeta tanto tu privacidad como los recursos digitales.
                </p>

                <h2>Infraestructura responsable</h2>
                <p>Nos comprometemos a:</p>
                <ul>
                    <li>
                        <strong>Eficiencia energética:</strong> elegimos proveedores de hosting que
                        utilicen energías renovables o compensen sus emisiones de carbono
                    </li>
                    <li>
                        <strong>Código optimizado:</strong> una aplicación más ligera consume menos
                        recursos de servidor y menos batería en tu dispositivo
                    </li>
                    <li>
                        <strong>Sin bloatware:</strong> no cargamos SDKs de tracking, redes
                        publicitarias ni scripts innecesarios
                    </li>
                </ul>

                <h2>Consumo local</h2>
                <p>
                    Superlistia está diseñada para el contexto español. Las sugerencias de IA priorizan
                    productos de temporada y formatos habituales en supermercados locales, fomentando
                    un consumo más cercano y sostenible.
                </p>

                <h2>Nuestros objetivos</h2>
                <ul>
                    <li>Ayudar a nuestros usuarios a reducir su desperdicio alimentario un 20%</li>
                    <li>Alcanzar neutralidad de carbono en nuestra infraestructura para 2027</li>
                    <li>Incorporar métricas de impacto ambiental en los resúmenes semanales</li>
                    <li>Colaborar con iniciativas locales de alimentación sostenible</li>
                </ul>

                <h2>Contacto</h2>
                <p>
                    Si tienes ideas o propuestas relacionadas con sostenibilidad, escríbenos a:<br />
                    Email: <strong>sostenibilidad@superlistia.com</strong>
                </p>
            </main>

            <footer className="py-8 px-4 bg-gray-900 text-gray-400 text-sm">
                <div className="max-w-3xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p>© {new Date().getFullYear()} Superlistia. Todos los derechos reservados.</p>
                    <div className="flex gap-6">
                        <Link to="/privacy" className="hover:text-white transition-colors">
                            Política de privacidad
                        </Link>
                        <Link to="/sustainability" className="hover:text-white transition-colors">
                            Sostenibilidad
                        </Link>
                        <Link to="/" className="hover:text-white transition-colors">
                            Inicio
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
