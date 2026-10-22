#requires -Version 5.1
[CmdletBinding()]
param([ValidateSet('inventory','clone','sync','wp')][string]$Action='inventory', [ValidateSet('local','h9-audit')][string]$Instance='local', [string[]]$WpArgs=@())
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Set-Location -LiteralPath $root
function Invoke-H9Compose([string]$Target,[string[]]$Arguments) {
    & docker compose --project-name "demo-woocommerce-$Target" --env-file ".local/$Target/.env" -f compose.yaml @Arguments
    if($LASTEXITCODE -ne 0){throw "H9 Compose operation failed in $Target"}
}
if($Action -eq 'inventory') {Invoke-H9Compose $Instance @('run','--rm','-T','--no-deps','wpcli','eval-file','/opt/local-tools/h9-inventory.php','--quiet');exit}
if($Action -eq 'wp') {Invoke-H9Compose $Instance (@('run','--rm','-T','--no-deps','wpcli','wp')+$WpArgs);exit}
if($Action -eq 'sync') {
    & docker cp ./commerce-base/. "demo-woocommerce-$Instance-wordpress-1:/var/www/html/wp-content/themes/commerce-base/"
    if($LASTEXITCODE -ne 0){throw 'Theme copy failed'}
    & docker cp ./commerce-lookbook/. "demo-woocommerce-$Instance-wordpress-1:/var/www/html/wp-content/plugins/commerce-lookbook/"
    if($LASTEXITCODE -ne 0){throw 'Lookbook copy failed'}
    exit
}
if($Instance -ne 'h9-audit'){throw 'Clone requires explicit -Instance h9-audit'}
if(Test-Path -LiteralPath '.local/h9-audit/.env'){throw 'Existing audit instance preserved; clone is one-shot'}
# Backup helper stops only local WordPress briefly and restarts it in finally.
& "$root/scripts/dev.ps1" backup -Instance local -BackupId h9-baseline
if($LASTEXITCODE -ne 0){throw 'Baseline backup failed'}
& "$root/scripts/dev.ps1" init -Instance h9-audit -WordPressPort 8199 -MailpitPort 8299
if($LASTEXITCODE -ne 0){throw 'Audit initialization failed'}
Copy-Item -LiteralPath '.local/local/backups/h9-baseline' -Destination '.local/h9-audit/backups/h9-baseline' -Recurse
Invoke-H9Compose 'h9-audit' @('up','-d','--wait','db','mailpit','wordpress')
# This only replaces the newly created audit volume. The main volume is never mounted here.
Invoke-H9Compose 'h9-audit' @('run','--rm','-T','--no-deps','--user','0:0','--entrypoint','sh','wpcli','-c','set -e; tar -xzf /backups/h9-baseline/wordpress.tar.gz --exclude=./wp-content/plugins/commerce-core --exclude=./wp-content/plugins/commerce-order-integrity --exclude=./wp-content/plugins/commerce-communications --exclude=./wp-content/plugins/commerce-account-navigation -C /var/www/html; chown -R 33:33 /var/www/html/wp-content/themes /var/www/html/wp-content/uploads; chown 33:33 /var/www/html/wp-content/mu-plugins')
& docker cp ./docker/tools/h9-offline.php 'demo-woocommerce-h9-audit-wordpress-1:/var/www/html/wp-content/mu-plugins/h9-offline.php'
if($LASTEXITCODE -ne 0){throw 'Clone offline guard copy failed'}
Invoke-H9Compose 'h9-audit' @('run','--rm','-T','--no-deps','wpcli','wp','db','import','/backups/h9-baseline/database.sql')
Invoke-H9Compose 'h9-audit' @('run','--rm','-T','--no-deps','wpcli','wp','search-replace','http://localhost:8080','http://localhost:8199','--all-tables-with-prefix','--precise','--skip-columns=guid','--quiet')
Write-Output 'Isolated clone ready at http://localhost:8199; Mailpit http://localhost:8299. Credentials and database remain private in .local.'
