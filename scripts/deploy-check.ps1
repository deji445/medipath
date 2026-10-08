$ErrorActionPreference = 'Stop'

Write-Host 'Checking frontend source and types...'
npm run check
npm run types:check

Write-Host 'Checking PHP formatting, static analysis, and tests...'
composer run test

Write-Host 'Building production assets...'
npm run build

Write-Host 'Checking Laravel production configuration...'
php artisan about --only=environment
php artisan route:list --except-vendor

Write-Host 'Deployment checks passed.'
