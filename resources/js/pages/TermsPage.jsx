import React from 'react';
import { Link } from 'react-router-dom';

export default function TermsPage() {
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
                <h1>Términos de Servicio</h1>
                <p className="text-gray-500">Última actualización: abril 2026</p>

                <h2>1. Objeto</h2>
                <p>
                    Los presentes Términos de Servicio regulan el acceso y uso de la plataforma
                    <strong> Superlistia</strong>, un asistente digital de listas de compra con inteligencia
                    artificial, operado por <strong>TX APPS</strong>.
                </p>

                <h2>2. Aceptación</h2>
                <p>
                    Al registrarte o utilizar Superlistia, aceptas estos términos en su totalidad.
                    Si no estás de acuerdo con alguna de las condiciones, no debes utilizar el servicio.
                </p>

                <h2>3. Descripción del servicio</h2>
                <p>Superlistia ofrece las siguientes funcionalidades:</p>
                <ul>
                    <li>Creación y gestión de listas de compra</li>
                    <li>Sugerencias personalizadas mediante inteligencia artificial</li>
                    <li>Listas compartidas en tiempo real con otros usuarios</li>
                    <li>Historial de compras y estadísticas de consumo</li>
                    <li>Generación automática de listas basadas en descripción libre</li>
                </ul>

                <h2>4. Registro y cuenta</h2>
                <p>
                    Para usar Superlistia necesitas crear una cuenta con un email válido y una contraseña
                    segura (mínimo 8 caracteres, una mayúscula y un número). Eres responsable de
                    mantener la confidencialidad de tus credenciales.
                </p>

                <h2>5. Uso aceptable</h2>
                <p>Te comprometes a:</p>
                <ul>
                    <li>No utilizar el servicio para fines ilegales o no autorizados</li>
                    <li>No intentar acceder a cuentas de otros usuarios</li>
                    <li>No interferir con el funcionamiento normal de la plataforma</li>
                    <li>No realizar ingeniería inversa del servicio</li>
                </ul>

                <h2>6. Propiedad intelectual</h2>
                <p>
                    El diseño, código, marca y contenido de Superlistia son propiedad de TX APPS.
                    Tus datos y listas de compra son de tu propiedad y puedes exportarlos o
                    eliminarlos en cualquier momento.
                </p>

                <h2>7. Inteligencia artificial</h2>
                <p>
                    Las sugerencias generadas por IA son orientativas y no constituyen consejo
                    nutricional, médico o profesional. Superlistia no se hace responsable de las
                    decisiones de compra basadas en las sugerencias del sistema.
                </p>

                <h2>8. Disponibilidad</h2>
                <p>
                    Nos esforzamos por mantener el servicio disponible 24/7, pero no garantizamos
                    una disponibilidad ininterrumpida. Podemos realizar mantenimientos programados
                    con aviso previo.
                </p>

                <h2>9. Planes y precios</h2>
                <p>
                    Superlistia ofrece un plan gratuito con funcionalidades básicas y un plan premium
                    con mayor capacidad de operaciones de IA. Los precios pueden actualizarse con
                    un aviso mínimo de 30 días.
                </p>

                <h2>10. Cancelación</h2>
                <p>
                    Puedes cancelar tu cuenta en cualquier momento desde tu perfil. Al cancelar,
                    tus datos se eliminarán de forma permanente en un plazo máximo de 30 días,
                    conforme a nuestra <Link to="/privacy">Política de Privacidad</Link>.
                </p>

                <h2>11. Limitación de responsabilidad</h2>
                <p>
                    Superlistia se ofrece «tal cual». No nos responsabilizamos de daños indirectos,
                    pérdida de datos por causas ajenas a nuestro control, ni de la exactitud de
                    las sugerencias de IA.
                </p>

                <h2>12. Modificaciones</h2>
                <p>
                    Podemos actualizar estos términos. Te notificaremos por email cualquier cambio
                    sustancial con al menos 15 días de antelación. El uso continuado del servicio
                    implica la aceptación de los nuevos términos.
                </p>

                <h2>13. Legislación aplicable</h2>
                <p>
                    Estos términos se rigen por la legislación española. Para cualquier controversia,
                    las partes se someten a los juzgados y tribunales de Barcelona.
                </p>

                <h2>14. Contacto</h2>
                <p>
                    Para cualquier consulta sobre estos términos:<br />
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
                        <Link to="/terms" className="hover:text-white transition-colors">
                            Términos de servicio
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
