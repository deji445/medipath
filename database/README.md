# MediPath Database

The project uses standard PostgreSQL. The existing schema baseline is maintained as plain SQL and the Laravel application uses Eloquent for application data access. It does not depend on Supabase.

## Local database

Expected development database:

- Database: `medipath_dev`
- Application role: `medipath_app`
- Host: `127.0.0.1`
- Port: `5432`

## Configure the connection

Create `.env.local` with a `DATABASE_URL` entry and replace `YOUR_PASSWORD`. Do not commit `.env.local`. The database scripts load this file automatically.

For the current PowerShell session, set the connection without placing the password in command history by using a secure prompt:

```powershell
$credential = Get-Credential -UserName medipath_app -Message 'Enter the local PostgreSQL password'
$password = $credential.GetNetworkCredential().Password
$encodedPassword = [System.Uri]::EscapeDataString($password)
$env:DATABASE_URL = "postgresql://medipath_app:$encodedPassword@127.0.0.1:5432/medipath_dev"
```

## Apply the schema and seed data

From the project root:

```powershell
.\scripts\db-setup.ps1
```

To apply migrations without demo seed data:

```powershell
.\scripts\db-setup.ps1 -SkipSeed
```

The seed is repeatable and creates three fictional hospitals matching the PRD configurations.

## Run database contract tests

After applying the schema:

```powershell
.\scripts\db-test.ps1
```

The tests use a transaction that is rolled back. They verify the high-severity routing restriction and the visit-submission state transition without retaining test patient data.

## Design rules

- Patients are platform-level records and do not have a `hospital_id`.
- Hospitals obtain access to patient information through `hospital_submissions`.
- A submission contains an immutable JSON snapshot so later patient-profile changes do not rewrite historical hospital records.
- Tenant-owned operational records always include `hospital_id`.
- Cross-tenant authorization must be enforced in the application service layer before hospital features are exposed.
- Only dummy data is permitted during the prototype phase.

See `docs/mvp-contracts.md` for the shared application contracts.
