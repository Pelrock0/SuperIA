import React from 'react';
import { Link } from 'react-router-dom';

export default function LegalPage() {
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
                <h1>Aviso Legal</h1>
                <p className="text-gray-500">Última actualización: abril 2026</p>

                <h2>1. Datos identificativos</h2>
                <p>
                    En cumplimiento del artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios
                    de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE), se informan
                    los siguientes datos:
                </p>
                <ul>
                    <li><strong>Titular:</strong> TX APPS</li>
                    <li><strong>Domicilio:</strong> Barcelona, España</li>
                    <li><strong>Email de contacto:</strong> legal@superlistia.com</li>
                    <li><strong>Actividad:</strong> Desarrollo y operación de la plataforma digital Superlistia</li>
                </ul>

                <h2>2. Objeto</h2>
                <p>
                    El presente sitio web tiene como finalidad poner a disposición de los usuarios
                    la plataforma Superlistia, un asistente digital de listas de compra con inteligencia
                    artificial.
                </p>

                <h2>3. Propiedad intelectual e industrial</h2>
                <p>
                    Todos los contenidos del sitio web, incluyendo textos, imágenes, diseño gráfico,
                    código fuente, logotipos y marcas, son propiedad de TX APPS o se utilizan con
                    la debida autorización, y están protegidos por las leyes de propiedad intelectual
                    e industrial.
                </p>
                <p>
                    Queda prohibida la reproducción, distribución, comunicación pública o transformación
                    de estos contenidos sin autorización expresa del titular.
                </p>

                <h2>4. Condiciones de uso</h2>
                <p>
                    El usuario se compromete a utilizar el sitio web y sus servicios de conformidad con
                    la ley, la moral, el orden público y los presentes términos. El uso del sitio web
                    se rige por los <Link to="/terms">Términos de Servicio</Link>.
                </p>

                <h2>5. Protección de datos</h2>
                <p>
                    El tratamiento de datos personales se realiza conforme al Reglamento General de
                    Protección de Datos (RGPD) y la Ley Orgánica 3/2018 de Protección de Datos
                    Personales (LOPDGDD). Para más información, consulta nuestra{' '}
                    <Link to="/privacy">Política de Privacidad</Link>.
                </p>

                <h2>6. Exclusión de responsabilidad</h2>
                <p>TX APPS no se hace responsable de:</p>
                <ul>
                    <li>Interrupciones del servicio por causas técnicas o de fuerza mayor</li>
                    <li>Daños derivados del uso inadecuado de la plataforma por parte del usuario</li>
                    <li>Contenidos de sitios web de terceros enlazados desde esta plataforma</li>
                    <li>La exactitud de las sugerencias generadas por inteligencia artificial</li>
                </ul>

                <h2>7. Legislación aplicable y jurisdicción</h2>
                <p>
                    El presente aviso legal se rige por la legislación española. Para la resolución
                    de cualquier controversia derivada del uso de este sitio web, las partes se
                    someten a los juzgados y tribunales de Barcelona, salvo que la normativa de
                    protección al consumidor establezca otra cosa.
                </p>

                <h2>8. Contacto</h2>
                <p>
                    Para cualquier consulta legal:<br />
                    Email: <strong>legal@superlistia.com</strong>
                </p>
            </main>

            <footer className="py-8 px-4 bg-gray-900 text-gray-400 text-sm">
                <div className="max-w-3xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p>© {new Date().getFullYear()} Superlistia. Todos los derechos reservados.</p>
                    <div className="flex gap-6">
                        <Link to="/privacy" className="hover:text-white transition-colors">
                            Política de privacidad
                        </Link>
                        <Link to="/legal" className="hover:text-white transition-colors">
                            Aviso legal
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
