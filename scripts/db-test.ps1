param(
    [string]$DatabaseUrl = $env:DATABASE_URL
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

$testDirectory = Join-Path $projectRoot 'database\tests'
$testFiles = Get-ChildItem -LiteralPath $testDirectory -Filter '*.sql' | Sort-Object Name

foreach ($testFile in $testFiles) {
    Write-Host "Running database test $($testFile.Name)"
    & $psql $DatabaseUrl -X -v ON_ERROR_STOP=1 -f $testFile.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "Database test $($testFile.Name) failed."
    }
}

Write-Host 'Database contract tests passed.'
