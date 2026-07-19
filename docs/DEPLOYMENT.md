# Guía de despliegue — Superlistia

Stack: **Laravel 12 · PHP 8.4+ (probado en 8.5) · React 19 + Vite · MySQL 8**.
App tipo SPA servida por Laravel (catch-all React) + panel `/admin` (Backpack).

> El framework Sofia4Builders (`.cursor/`, `cli/`, `.claude/`, `dashboard/`) está
> en `.gitignore` y **no** forma parte del despliegue de la app.

---

## 1. Requisitos del servidor

| Componente | Versión / nota |
|---|---|
| PHP | **≥ 8.4** (CLI + FPM). Extensiones: `mbstring, pdo_mysql, openssl, tokenizer, xml, ctype, json, bcmath, curl, fileinfo, intl` |
| Composer | 2.x |
| Node | 20+ (solo para build; `package.json` es ESM `type: module`) |
| MySQL | 8.0+ (o MariaDB 10.6+) |
| Web server | Nginx + PHP-FPM (recomendado) o Apache |
| HTTPS | **Obligatorio** — WebAuthn/biometría exige TLS y un RP ID = dominio real |

> Nota: `.env.example` trae `DB_CONNECTION=sqlite` por defecto (dev). En producción
> **usa MySQL**. No hay Dockerfile (se retiró); despliegue clásico VPS/PaaS.

---

## 2. Variables de entorno (`.env` de producción)

Genera desde `.env.example` y ajusta como mínimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
APP_KEY=            # php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=superlistia
DB_USERNAME=superlistia
DB_PASSWORD=********

# Colas/caché/sesión van a BBDD (no requieren Redis)
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# IA (Claude) — imprescindible para autocomplete, generación, precios, resumen
AI_PROVIDER=claude
CLAUDE_API_KEY=sk-ant-...          # SECRETO
CLAUDE_MODEL=claude-haiku-4-5-20251001
AI_GENERATION_MODEL=claude-haiku-4-5-20251001
AI_WEEKLY_SUMMARY_MODEL=claude-haiku-4-5-20251001
AI_TIMEZONE=Europe/Madrid
AI_BUDGET_CAP_MONTHLY_USD=50       # corta la IA si se excede
AI_ADMIN_ALERT_EMAIL=ops@tu-dominio.com

# Email (verificación, resumen semanal, unsubscribe firmado)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@tu-dominio.com
```

⚠️ `CLAUDE_API_KEY`, `DB_PASSWORD` y `MAIL_PASSWORD` son secretos — nunca en git
(`.env*` ya está en `.gitignore`).

---

## 3. Pasos de despliegue

```bash
# 1. Código
git clone git@github.com:Pelrock0/SuperIA.git && cd SuperIA
git checkout main

# 2. Dependencias PHP (sin dev)
composer install --no-dev --optimize-autoloader

# 3. App key (solo la primera vez) y enlace de storage
php artisan key:generate        # si APP_KEY vacío
php artisan storage:link

# 4. Migraciones (crea tablas incl. sessions/cache/jobs)
php artisan migrate --force

# 5. Seeders base (catálogo de productos + prompts IA + superadmin)
php artisan db:seed --force     # o seeders concretos: ProductCatalog, AiPrompt, Superadmin

# 6. Frontend (build de Vite → public/build)
npm ci
npm run build

# 7. Cachés de producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> El seed del catálogo puede tardar (llama a IA). Si da timeout, sube `AI_TIMEOUT`
> a 60–90 o siembra en lotes (`prices:seed-catalog`).

---

## 4. Procesos permanentes (¡no olvidar!)

La app **no funciona bien solo con FPM** — necesita cola y scheduler.

### 4.1 Worker de colas (`QUEUE_CONNECTION=database`)
Job encolado: `InferItemCategoryJob` (categorización IA de items).
```bash
php artisan queue:work --tries=3 --timeout=120
```
Gestiónalo con **Supervisor** (o systemd) para que reinicie:
```ini
[program:superlistia-worker]
command=php /var/www/superlistia/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/superlistia-worker.log
```

### 4.2 Scheduler (cron cada minuto)
```cron
* * * * * cd /var/www/superlistia && php artisan schedule:run >> /dev/null 2>&1
```
Tareas registradas (`routes/console.php`):

| Comando | Frecuencia | Qué hace |
|---|---|---|
| `accounts:delete-expired` | diaria 03:00 | Hard-delete RGPD de cuentas expiradas |
| `app:cleanup-collaborator-data` | horaria | Limpieza de datos de colaboración |
| `ai:reset-daily-usage` | diaria 00:00 (Madrid) | Resetea cuotas IA diarias |
| `ai:cleanup-dismissed-suggestions` | diaria 03:30 (Madrid) | Purga sugerencias descartadas (TTL) |
| `ai:dispatch-weekly-summary` | lunes 08:00 (Madrid) | Envía email resumen semanal |

---

## 5. Nginx (ejemplo)

```nginx
server {
    listen 443 ssl http2;
    server_name tu-dominio.com;
    root /var/www/superlistia/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.com/privkey.pem;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
    client_max_body_size 20M;
}
```
El SPA de React se sirve por el catch-all de Laravel; **`/admin`** (Backpack) y
**`/telescope`** (gated a superadmin) quedan fuera de ese catch-all por diseño.

---

## 6. Post-deploy — smoke test

1. `https://tu-dominio.com` → landing carga.
2. Login → dashboard (`/app`).
3. Crear lista, añadir item (autocomplete responde → IA + BBDD OK).
4. Generar lista con IA (verifica `CLAUDE_API_KEY`).
5. Compartir lista → enlace `/shared/...` abre para invitado (HMAC OK).
6. `/admin` con el superadmin sembrado.
7. `php artisan schedule:list` y logs del worker/scheduler.

---

## 7. Actualizaciones (deploy incremental)

```bash
php artisan down                       # modo mantenimiento
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart              # que los workers recarguen el código
php artisan up
```

---

## 8. Notas / deuda conocida

- **Verificación de email**: histórico issue (Epic 1) con el clic del enlace —
  probar en el entorno real tras configurar `APP_URL` + `MAIL_*`.
- **FEAT-TEST-INFRA-FIX** (pendiente): los tests comparten BBDD con dev; en CI usa
  una base separada. No afecta a producción, solo al pipeline de tests.
- **FEAT-COMPLETE-SHOPPING-PARTIAL**: solo diseño S1, **no** implementada — no
  esperes el botón "Lista completada" en producción todavía.
- **Budget IA**: `AI_BUDGET_CAP_MONTHLY_USD` corta las llamadas si se supera;
  vigila `AI_ADMIN_ALERT_EMAIL` para no quedarte sin IA a mitad de mes.
