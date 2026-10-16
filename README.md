# MediPath

MediPath is a Laravel 13 application using Inertia, Vue 3, TypeScript, and PostgreSQL. The patient intake flow, hospital portal, and Super Admin area are deployed as one application on a PHP-capable host.

## Local requirements

- PHP 8.3 or later with `pdo_pgsql`, `pgsql`, and `intl`
- Composer 2
- Node.js 22 or later
- PostgreSQL 18 (PostgreSQL 16 or later is suitable for deployment)

## First-time setup

1. Put the private PostgreSQL `DATABASE_URL` in `.env.local`.
2. Run `powershell -ExecutionPolicy Bypass -File scripts/configure-laravel-env.ps1`.
3. Run `composer install`.
4. Run `php artisan key:generate` if `APP_KEY` is empty.
5. Run `npm install`.
6. Run `composer run dev`.

Never commit `.env` or `.env.local`.

Location-aware routing uses browser geolocation when the patient explicitly
chooses it. When only an area or landmark is entered, the prototype can resolve
it through the configured Nominatim endpoint and then rank hospitals by
distance. The public endpoint is appropriate only for this low-volume demo;
keep the identifying user agent configured and use a dedicated provider or
self-hosted service for production traffic.

## Gemini assessment

The safe local default is the deterministic prototype engine. To enable the real Gemini assessment, create a Gemini API key in Google AI Studio and add these lines to `.env.local`:

```dotenv
MEDIPATH_ASSESSMENT_DRIVER=gemini
GEMINI_API_KEY=your-private-key
GEMINI_ASSESSMENT_MODEL=gemini-3.8-flash
```

Run `powershell -ExecutionPolicy Bypass -File scripts/configure-laravel-env.ps1`, then restart the Laravel development server. Never paste the key into source code or commit it. The Gemini free tier may use submitted content to improve Google's products, so this prototype must use dummy patient data only.

## Database

The PostgreSQL schema is defined in `database/migrations/001_initial_schema.sql` and registered with Laravel through the deployment-safe baseline migration. `php artisan migrate --force` now works for both an existing complete MediPath schema and a fresh PostgreSQL deployment. It refuses partially initialized schemas and never drops the clinical baseline during rollback.

Use `scripts/db-setup.ps1` for direct local SQL setup and `scripts/db-test.ps1` for schema contract tests.

## Patient phone verification

Development uses the local verification driver by default. For real Ghanaian
SMS delivery, set `MEDIPATH_PHONE_VERIFICATION_DRIVER=bms` and configure the
BMS endpoint, API key, and the exact approved sender ID. The current approved
sender ID is lowercase `medipath`; sender IDs are case-sensitive.

MediPath sends verification codes as BMS OTP campaigns. BMS charges these
campaigns to the account's GHS main wallet rather than its ordinary SMS bundle,
so the wallet must retain a positive balance. A funded SMS bundle alone is not
sufficient for `sms_type: otp` requests.

## Verification

After installing Composer and Node dependencies, configuring `.env.local`, and starting the local PostgreSQL instance, run the full submission check from a normal Windows PowerShell terminal:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify.ps1
```

The command runs frontend checks and TypeScript validation, PHP formatting and static analysis, the PHPUnit suite, PostgreSQL database contract tests, and the production Vite build. It stops at the first failed check. `scripts/db-test.ps1` obtains `DATABASE_URL` from the environment or `.env.local`; pass a disposable database explicitly when needed:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify.ps1 -DatabaseUrl "postgresql://user:password@localhost:5432/medipath_test"
```

Run this outside restricted execution environments so Vite can start its required Windows native processes and the database contract tests can connect to local PostgreSQL.

### Continuous integration

The **MediPath verification** GitHub Actions workflow runs for every pull
request, every push to `main`, and on manual request. It has three independent
gates:

- application formatting, lint, static analysis, tests, and production build;
- the deterministic clinical-safety benchmark;
- migrations, reference seeders, and SQL contract tests against a fresh
  PostgreSQL 17 database.

The workflow uses isolated test credentials. It does not require Gemini, SMS,
or deployment secrets. The principal checks can also be run locally with
`composer ci:check`, `composer safety:benchmark`, and `npm run build`.

## Deployment

Use [the deployment checklist](docs/deployment-checklist.md) to configure a
PHP-capable host, managed PostgreSQL, HTTPS, secrets, backups, health checks,
and rollback. Run `powershell -ExecutionPolicy Bypass -File
scripts/deploy-check.ps1` before creating a release.

For the final presentation, follow the repeatable
[demonstration script](docs/demo-script.md). It covers real OTP verification
with a presenter-controlled phone, a normal consented hospital submission, the
receiving admissions queue, and deterministic emergency interruption.

The latest [patient-journey accessibility audit](docs/accessibility-audit.md)
records the measured mobile, contrast, semantic-structure, and theming work
remaining before the final presentation.
