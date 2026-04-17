import React from 'react';
import { Link } from 'react-router-dom';

export default function PrivacyPage() {
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
                <h1>Política de Privacidad</h1>
                <p className="text-gray-500">Última actualización: abril 2026</p>

                <h2>1. Datos que recogemos</h2>
                <p>
                    Cuando te registras en la lista de espera, recogemos tu <strong>nombre</strong> y
                    tu <strong>dirección de email</strong>. Opcionalmente, puedes indicar con quién
                    sueles compartir la compra.
                </p>
                <p>
                    Cuando usas la aplicación, almacenamos tus listas de compra, ítems y el historial
                    de productos comprados para ofrecerte sugerencias personalizadas.
                </p>

                <h2>2. Finalidad del tratamiento</h2>
                <ul>
                    <li>Gestionar tu acceso a la plataforma</li>
                    <li>Ofrecerte sugerencias personalizadas basadas en tus hábitos</li>
                    <li>Enviar comunicaciones relacionadas con tu cuenta (invitaciones, alertas)</li>
                </ul>

                <h2>3. Base legal</h2>
                <p>
                    El tratamiento se basa en tu <strong>consentimiento</strong> (al registrarte) y en la
                    <strong> ejecución del contrato</strong> de uso del servicio.
                </p>

                <h2>4. Plazo de conservación</h2>
                <p>
                    Tus datos se conservan mientras tu cuenta esté activa. Si solicitas la eliminación,
                    se eliminan de forma permanente en un plazo máximo de 30 días conforme al RGPD.
                </p>

                <h2>5. Compartición con terceros</h2>
                <p>
                    <strong>No vendemos ni compartimos</strong> tus datos con terceros para fines
                    publicitarios. Solo compartimos datos con proveedores estrictamente necesarios
                    para el funcionamiento del servicio (hosting, email transaccional).
                </p>

                <h2>6. Cookies y tracking</h2>
                <p>
                    Superlistia <strong>no utiliza cookies de tracking, analytics de terceros ni píxeles
                    de seguimiento</strong>. Solo usamos cookies técnicas estrictamente necesarias
                    para el funcionamiento de la sesión.
                </p>

                <h2>7. Tus derechos (RGPD)</h2>
                <p>Tienes derecho a:</p>
                <ul>
                    <li><strong>Acceso:</strong> solicitar una copia de tus datos personales</li>
                    <li><strong>Rectificación:</strong> corregir datos inexactos</li>
                    <li><strong>Supresión:</strong> eliminar tu cuenta y todos tus datos</li>
                    <li><strong>Portabilidad:</strong> recibir tus datos en formato estructurado</li>
                    <li><strong>Oposición:</strong> oponerte al tratamiento de tus datos</li>
                    <li><strong>Limitación:</strong> limitar el uso de tus datos</li>
                </ul>
                <p>
                    Para ejercer cualquiera de estos derechos, contacta con nosotros en
                    <strong> privacidad@superlistia.com</strong>.
                </p>

                <h2>8. Contacto</h2>
                <p>
                    Responsable del tratamiento: TX APPS<br />
                    Email: privacidad@superlistia.com
                </p>
            </main>

            <footer className="py-8 px-4 bg-gray-900 text-gray-400 text-sm">
                <div className="max-w-3xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p>© {new Date().getFullYear()} Superlistia. Todos los derechos reservados.</p>
                    <div className="flex gap-6">
                        <Link to="/privacy" className="hover:text-white transition-colors">
                            Política de privacidad
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
