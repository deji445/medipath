param(
    [string] $SourcePath = '.env.local',
    [string] $TargetPath = '.env'
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$source = Join-Path $projectRoot $SourcePath
$target = Join-Path $projectRoot $TargetPath
$example = Join-Path $projectRoot '.env.example'

if (-not (Test-Path -LiteralPath $source)) {
    throw "Missing local secret file: $SourcePath"
}

$databaseUrlLine = Get-Content -LiteralPath $source |
    Where-Object { $_ -match '^DATABASE_URL=' } |
    Select-Object -First 1

if (-not $databaseUrlLine) {
    throw "$SourcePath must contain a DATABASE_URL entry."
}

$databaseUrl = $databaseUrlLine -replace '^DATABASE_URL=', ''
$template = Get-Content -LiteralPath $example -Raw
$configured = $template -replace '(?m)^DB_URL=.*$', "DB_URL=$databaseUrl"

$optionalLocalSettings = @(
    'MEDIPATH_ASSESSMENT_DRIVER',
    'GEMINI_API_KEY',
    'GEMINI_ASSESSMENT_MODEL'
)

foreach ($setting in $optionalLocalSettings) {
    $settingLine = Get-Content -LiteralPath $source |
        Where-Object { $_ -match "^$setting=" } |
        Select-Object -First 1

    if ($settingLine) {
        $configured = $configured -replace "(?m)^$setting=.*$", $settingLine
    }
}

if (Test-Path -LiteralPath $target) {
    $existingAppKey = Get-Content -LiteralPath $target |
        Where-Object { $_ -match '^APP_KEY=.+$' } |
        Select-Object -First 1

    if ($existingAppKey) {
        $configured = $configured -replace '(?m)^APP_KEY=.*$', $existingAppKey
    }
}

Set-Content -LiteralPath $target -Value $configured -Encoding utf8NoBOM
Write-Host "Created $TargetPath from the application template and local settings in $SourcePath."
