<div align="center">

# 🛒 Superlistia

**Tu lista de la compra inteligente, potenciada con IA.**

Crea, comparte y optimiza tus listas de compra. Superlistia autocompleta productos,
genera listas completas a partir de una frase, estima precios, detecta duplicados,
te avisa de reposiciones y resume tu gasto semanal — todo con IA.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![Vite](https://img.shields.io/badge/Vite-7-646CFF?logo=vite&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Claude](https://img.shields.io/badge/AI-Claude%20Haiku-D97757?logo=anthropic&logoColor=white)
![Tests](https://img.shields.io/badge/tests-~967-success)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)

</div>

---

## ✨ ¿Qué hace?

Superlistia convierte la lista de la compra en una experiencia asistida. En lugar de
teclear producto a producto, describes lo que necesitas y la IA hace el trabajo pesado:
sugiere, categoriza, estima precios y aprende de tus hábitos.

### Funcionalidades

| Área | Funcionalidad |
|---|---|
| 🏠 **Landing & Waitlist** | Página de aterrizaje pública + lista de espera con captación de leads. |
| 🔐 **Autenticación** | Login con email/contraseña + **JWT**, **biometría (WebAuthn/passkeys)**, verificación de email y **borrado de cuenta conforme a RGPD**. |
| 📋 **Listas de compra** | CRUD completo, límite freemium (3 listas activas), archivar y eliminar. |
| 🥕 **Ítems y categorías** | Productos dentro de cada lista, auto-organización por secciones (Frutas, Lácteos, Panadería…), histórico de productos. |
| 👥 **Colaboración** | Comparte listas por **enlace firmado (HMAC)** con permisos (editar / solo ver), difusión por **WhatsApp y Email**. |
| ⚡ **Autocompletado inteligente** | Pipeline de **3 capas** (historial personal → catálogo → IA) que aprende de tus compras. |
| 🔁 **Reposición y complementos** | Avisos de reposición según tu frecuencia de compra + sugerencias de productos complementarios. |
| 🤖 **Generación con IA** | Escribe *"cena italiana para 2 personas"* y obtén una lista completa, con cantidades escaladas por comensal. |
| 💶 **Estimación de precios** | Precio estimado por producto con pipeline de 3 capas (historial + catálogo + IA) y confirmación de precios reales para mejorar futuras estimaciones. |
| 🔎 **Detección de duplicados** | Evita duplicados con normalización singular/plural en español (*pan ↔ panes*); los ítems ya comprados no bloquean nuevas adiciones. |
| 📊 **Historial y estadísticas** | Historial de compras + panel de estadísticas (gráficas de gasto y categorías). |
| 📧 **Resumen semanal** | Email programado (lunes) con el resumen de la semana, opt-in y baja mediante enlace firmado. |
| 🛠️ **Panel de administración** | Backoffice con **Backpack** (gestión de usuarios, planes, límites de IA, uso de IA) + **Telescope** para observabilidad. |

---

## 🧱 Arquitectura y stack

- **Backend:** Laravel 12 (PHP 8.4+), arquitectura **hexagonal** con principios **DDD**
  (contextos acotados, servicios de dominio, glosario por contexto).
- **Frontend:** React 19 + Vite (SPA servida por Laravel), Tailwind CSS con sistema de
  diseño propio, internacionalización con **i18next**.
- **Base de datos:** MySQL 8 (colas, caché y sesiones sobre BBDD — sin Redis obligatorio).
- **IA:** API de Claude (**Haiku**) para autocompletado, generación, precios y resúmenes,
  con **presupuesto mensual** configurable y límites de uso por operación/plan.
- **Auth:** JWT + WebAuthn (passkeys/biometría).
- **Calidad:** ~967 tests (PHPUnit + Vitest), **100 % de cobertura** exigida y
  **mutation testing** con Infection (Covered MSI ≈ 87 %).

```
app/
├── Domain / Services      # Lógica de negocio (hexagonal + DDD)
├── Http/Controllers       # API REST + catch-all SPA
├── Jobs                   # Trabajos encolados (p.ej. categorización IA)
├── Console/Commands       # Comandos programados (cron)
└── Support/Inflector      # Normalizador singular/plural (ES)
resources/js/              # SPA React 19 (páginas, componentes, libs)
routes/console.php         # Tareas programadas (scheduler)
docs/                      # Documentación de features y despliegue
```

---

## 🚀 Puesta en marcha (desarrollo local)

### Requisitos

- PHP **≥ 8.4** con extensiones: `mbstring, pdo_mysql, openssl, tokenizer, xml, ctype, json, bcmath, curl, fileinfo, intl`
- Composer 2.x · Node 20+ · MySQL 8 (o MariaDB 10.6+)
- Una **API key de Claude** (Anthropic) para las funciones de IA.

### Instalación

```bash
git clone https://github.com/Pelrock0/SuperIA.git
cd SuperIA

composer install
cp .env.example .env
php artisan key:generate

# Configura .env: DB_CONNECTION=mysql, credenciales de BBDD y CLAUDE_API_KEY
php artisan migrate --seed          # crea tablas + catálogo, prompts y superadmin

npm install
npm run dev                         # Vite en modo desarrollo
php artisan serve                   # http://localhost:8000
```

### Variables de entorno clave

| Variable | Descripción |
|---|---|
| `APP_KEY` | Clave de la app (`php artisan key:generate`). |
| `DB_*` | Conexión MySQL (`DB_CONNECTION=mysql` en real; el default `sqlite` es solo dev). |
| `CLAUDE_API_KEY` | **Imprescindible** — sin ella no funcionan autocompletado, generación, precios ni resúmenes. |
| `CLAUDE_MODEL` / `AI_GENERATION_MODEL` / `AI_WEEKLY_SUMMARY_MODEL` | Modelos de IA (por defecto `claude-haiku-4-5`). |
| `AI_BUDGET_CAP_MONTHLY_USD` | Tope de gasto mensual de IA; corta las llamadas si se supera. |
| `MAIL_*` | SMTP para verificación de email, resumen semanal y bajas. |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `SESSION_DRIVER` | `database` por defecto. |

> ⚠️ Los secretos (`CLAUDE_API_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`) nunca se versionan —
> `.env` está en `.gitignore`.

### Tests

```bash
php artisan test        # backend (PHPUnit, 100% cobertura)
npm run test            # frontend (Vitest)
composer security       # composer audit + Psalm taint analysis
```

---

## 🔧 Despliegue

El proyecto **ya está desplegado en producción**. Para el detalle completo de
infraestructura (Nginx, worker de colas, scheduler cron, primer arranque) consulta
**[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)**.

### Guía de actualización (deploy incremental)

```bash
php artisan down                                   # modo mantenimiento
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart                          # recarga el código en los workers
php artisan up
```

**Recuerda** que en producción deben seguir activos:
- **Worker de colas:** `php artisan queue:work` (bajo Supervisor/systemd) — procesa la
  categorización de ítems por IA.
- **Scheduler (cron cada minuto):** `* * * * * php artisan schedule:run` — ejecuta el
  borrado RGPD, la limpieza de colaboración/sugerencias, el reseteo de cuotas de IA y el
  resumen semanal de los lunes.

---

## 📄 Licencia

Distribuido bajo licencia **[MIT](LICENSE)** — © 2026 Alfredo Martín.

Puedes usar, modificar y distribuir el proyecto libremente, **conservando el aviso de
copyright y atribución al autor** en todas las copias o partes sustanciales del software.

<div align="center">
<sub>Hecho con Laravel · React · Claude</sub>
</div>
