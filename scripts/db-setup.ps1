param(
    [string]$DatabaseUrl = $env:DATABASE_URL,
    [switch]$SkipSeed
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($DatabaseUrl)) {
    $localEnv = Join-Path $projectRoot '.env.local'
    if (Test-Path -LiteralPath $localEnv) {
        $databaseUrlEntry = Get-Content -LiteralPath $localEnv |
            Where-Object { $_ -match '^DATABASE_URL=' } |
            Select-Object -First 1

        if ($databaseUrlEntry) {
            $DatabaseUrl = $databaseUrlEntry -replace '^DATABASE_URL=', ''
        }
    }
}

if ([string]::IsNullOrWhiteSpace($DatabaseUrl)) {
    throw 'DATABASE_URL is not set and was not found in .env.local.'
}

$psqlCommand = Get-Command 'psql' -ErrorAction SilentlyContinue
if ($psqlCommand) {
    $psql = $psqlCommand.Source
}
else {
    $knownPsql = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
    if (-not (Test-Path -LiteralPath $knownPsql)) {
        throw 'psql was not found. Install PostgreSQL client tools or add psql to PATH.'
    }
    $psql = $knownPsql
}

$migrationDirectory = Join-Path $projectRoot 'database\migrations'
$seedDirectory = Join-Path $projectRoot 'database\seeds'

$migrationFiles = Get-ChildItem -LiteralPath $migrationDirectory -Filter '*.sql' | Sort-Object Name
foreach ($migrationFile in $migrationFiles) {
    $version = [System.IO.Path]::GetFileNameWithoutExtension($migrationFile.Name)
    $migrationTableExists = & $psql $DatabaseUrl -X -A -t -v ON_ERROR_STOP=1 -c "select to_regclass('public.schema_migrations') is not null;"

    if ($LASTEXITCODE -ne 0) {
        throw 'Could not inspect the migration table.'
    }

    $migrationApplied = $false
    if ($migrationTableExists.Trim() -eq 't') {
        $appliedResult = & $psql $DatabaseUrl -X -A -t -v ON_ERROR_STOP=1 -c "select exists (select 1 from schema_migrations where version = '$version');"
        if ($LASTEXITCODE -ne 0) {
            throw "Could not check migration $version."
        }
        $migrationApplied = $appliedResult.Trim() -eq 't'
    }

    if ($migrationApplied) {
        Write-Host "Skipping applied migration $version"
        continue
    }

    Write-Host "Applying migration $version"
    & $psql $DatabaseUrl -X -v ON_ERROR_STOP=1 -f $migrationFile.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "Migration $version failed."
    }
}

if (-not $SkipSeed) {
    $seedFiles = Get-ChildItem -LiteralPath $seedDirectory -Filter '*.sql' | Sort-Object Name
    foreach ($seedFile in $seedFiles) {
        Write-Host "Applying seed $($seedFile.Name)"
        & $psql $DatabaseUrl -X -v ON_ERROR_STOP=1 -f $seedFile.FullName
        if ($LASTEXITCODE -ne 0) {
            throw "Seed $($seedFile.Name) failed."
        }
    }
}

Write-Host 'Database setup completed successfully.'
