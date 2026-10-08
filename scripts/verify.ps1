param(
    [string]$DatabaseUrl = $env:DATABASE_URL
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

function Invoke-VerificationCommand {
    param(
        [string]$Description,
        [scriptblock]$Command
    )

    Write-Host "`n==> $Description"
    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed with exit code $LASTEXITCODE."
    }
}

Push-Location $projectRoot

try {
    Invoke-VerificationCommand 'Frontend checks' { npm run check -- resources/js }
    Invoke-VerificationCommand 'Frontend type checks' { npm run types:check }
    Invoke-VerificationCommand 'PHP formatting check' { composer run lint:check }
    Invoke-VerificationCommand 'PHP static analysis' { composer run types:check }
    Invoke-VerificationCommand 'PHP test suite' { php artisan test }
    Invoke-VerificationCommand 'PostgreSQL database contract tests' {
        & (Join-Path $PSScriptRoot 'db-test.ps1') -DatabaseUrl $DatabaseUrl
    }
    Invoke-VerificationCommand 'Production frontend build' { npm run build }
}
finally {
    Pop-Location
}

Write-Host "`nMediPath verification passed."
