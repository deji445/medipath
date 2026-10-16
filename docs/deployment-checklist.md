# MediPath deployment checklist

MediPath should be deployed as one Laravel application on a PHP-capable host
with a managed PostgreSQL database. The current build is a dummy-data academic
prototype, not a production clinical service.

## 1. Hosting requirements

- PHP 8.3 or later with `pdo_pgsql`, `pgsql`, `intl`, `mbstring`, `openssl`,
  `curl`, and `fileinfo`.
- PostgreSQL 16 or later.
- Node.js 22 or later during the build stage.
- HTTPS with automatic certificate renewal.
- A persistent writable `storage` directory and `bootstrap/cache` directory.
- A scheduler entry that runs `php artisan schedule:run` every minute if
  scheduled work is enabled later.

## 2. Production environment

Create production secrets in the hosting provider's environment manager. Do
not upload `.env` or `.env.local`.

Required values:

```dotenv
APP_NAME=MediPath
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=base64:generated-secret
APP_TIMEZONE=Africa/Accra

DB_CONNECTION=pgsql
DB_URL=postgresql://USER:PASSWORD@HOST:5432/DATABASE?sslmode=require

SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr

MEDIPATH_PHONE_VERIFICATION_DRIVER=bms
BMS_SMS_ENDPOINT=https://api.mnotify.com/api/sms/quick
BMS_API_KEY=private-secret
BMS_SENDER_ID=medipath
BMS_CONNECT_TIMEOUT=5
BMS_TIMEOUT=10

MEDIPATH_ASSESSMENT_DRIVER=gemini
MEDIPATH_ASSESSMENT_MAX_QUESTIONS=10
GEMINI_API_KEY=private-secret
GEMINI_ASSESSMENT_MODEL=gemini-3.5-flash-lite
GEMINI_CIRCUIT_FAILURE_THRESHOLD=3
GEMINI_CIRCUIT_FAILURE_WINDOW_SECONDS=120
GEMINI_CIRCUIT_COOLDOWN_SECONDS=60
MEDIPATH_INITIAL_AI_SAFETY_SCREEN=false

MEDIPATH_EMERGENCY_CONTACT_LABEL="Ghana National Ambulance Service"
MEDIPATH_EMERGENCY_PHONE=112
MEDIPATH_GEOCODING_ENABLED=true
MEDIPATH_GEOCODING_USER_AGENT="MediPath-AI-Prototype/1.0 contact@example.com"
```

Patient phone verification uses the `local` demo driver during development and
BMS/mNotify when `MEDIPATH_PHONE_VERIFICATION_DRIVER=bms`. The local driver
deliberately refuses to run in production. Keep the BMS key in the host's
secret manager and confirm that `BMS_SENDER_ID` is approved for the account
before accepting real patient traffic. Sender IDs are case-sensitive; the
approved production value is lowercase `medipath`. Verification requests use
`sms_type: otp`, which BMS charges against the GHS main wallet rather than an
ordinary SMS bundle. Keep that wallet funded and verify a real OTP delivery
before each release.

Database-backed sessions and a file cache are acceptable for a single
demonstration server. Use a shared Redis cache before running more than one web
instance. The shared cache is also required for a circuit breaker that works
consistently across every web instance.

The AI consultant uses server-sent events at `/consultant/chat/stream`.
Configure the web server or reverse proxy not to buffer this route, and keep its
request timeout above `GEMINI_CONSULTANT_TIMEOUT` so response text reaches the
browser as it is generated.

The public Nominatim endpoint is suitable only for this low-volume demo and
requires an identifying user agent. Use a contracted or self-hosted geocoder
before public production traffic.

## 3. Build and release

Run these commands from a clean release directory:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run types:check
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first platform administrator through the host's secure console:

```powershell
php artisan medipath:create-super-admin
```

Never place the administrator password in a deploy script, shell history,
repository file, or build log.

## 4. Release checks

- `GET /up` returns HTTP 200 through HTTPS.
- The home page and staff login load without browser console errors.
- A dummy emergency concern opens emergency guidance immediately.
- A low-risk AI consultant reply appears progressively without a page reload.
- A dummy low-risk intake can select a hospital, give consent, and appear in
  only that hospital's admissions queue.
- A hospital administrator can open settings and scan the walk-in QR code.
- A Super Admin can manage hospitals and consultant callback requests.
- Gemini failure preserves answers and never creates a default low-severity
  result.

## 5. Backups and rollback

- Enable daily encrypted PostgreSQL backups with at least seven retained
  restore points.
- Take a database snapshot immediately before each migration.
- Keep the previous application release available for rollback.
- Roll application code back first. Roll the database back only from a tested
  snapshot when a migration is not backward compatible.
- Test a restore into a separate database before the demonstration date.

## 6. Prototype safety boundary

Use only dummy records. Patient lookup and consultant callback requests now
require an expiring, attempt-limited SMS OTP, but this prototype has not
completed a formal security or privacy assessment. The safety rules are not
clinically validated, and the application has no regulatory certification or
live hospital integration. Those limitations must remain visible in the final
report and demonstration.
