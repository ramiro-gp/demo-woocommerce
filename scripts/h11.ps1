#requires -Version 5.1
[CmdletBinding()]
param(
  [ValidateSet('install','wp','backup','restore','update-code','stop','inspect')][string]$Action='inspect',
  [ValidateSet('qa','restore')][string]$Instance='qa',
  [string[]]$WpArgs=@()
)
$ErrorActionPreference='Stop'
if (Test-Path variable:PSNativeCommandUseErrorActionPreference) { $PSNativeCommandUseErrorActionPreference=$false }
$root=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Set-Location -LiteralPath $root
$runtime=Join-Path $root ".local/h11/$Instance"
$envFile=Join-Path $runtime '.env'
$project="commerce-h11-$Instance"
function Compose([string[]]$Arguments) {
  & docker compose --project-directory $root --project-name $project --env-file $envFile -f compose.h11.yaml @Arguments
  if ($LASTEXITCODE -ne 0) { throw "H11 $Action failed; private files and volumes preserved." }
}
function Wp([string[]]$Arguments) { Compose (@('run','--rm','-T','--no-deps','h11-cli','wp')+$Arguments) }
function Install-Code {
  $manifest=Get-Content '.local/h11-release/1.0.0/manifest.json' -Raw | ConvertFrom-Json
  foreach ($package in $manifest.packages) {
    $zip=Join-Path $root ".local/h11-release/1.0.0/$($package.zip)"
    if ((Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant() -ne $package.sha256) { throw 'Release checksum mismatch.' }
    $type=if ($package.slug -eq 'commerce-base') {'theme'} else {'plugin'}
    Wp @($type,'install',"/release/1.0.0/$($package.zip)",'--force')
  }
}
if ($Action -eq 'install') {
  $newInstance = -not (Test-Path -LiteralPath $envFile)
  if ($newInstance) {
  foreach ($dir in @("$runtime/secrets","$runtime/backups",(Join-Path $root '.local/h11/private'))) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  $rng=[Security.Cryptography.RandomNumberGenerator]::Create()
  try { foreach ($name in @('db','root','admin')) { $bytes=New-Object byte[] 36; $rng.GetBytes($bytes); [IO.File]::WriteAllText("$runtime/secrets/$name.txt",[Convert]::ToBase64String($bytes)) } } finally {$rng.Dispose()}
  $port=if($Instance -eq 'qa'){8184}else{8185}
  $mailPort=if($Instance -eq 'qa'){8186}else{8187}
  foreach ($p in @($port,$mailPort)) { $listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$p); try {$listener.Start()} finally {$listener.Stop()} }
  [IO.File]::WriteAllText($envFile,"H11_INSTANCE=$Instance`nH11_PORT=$port`nH11_MAIL_PORT=$mailPort`n")
  }
  Compose @('up','-d','--wait','--wait-timeout','150','h11-db','h11-web','h11-mail')
  Compose @('exec','-T','h11-web','sh','-c','test -f wp-load.php && test -f wp-config.php')
  # Prompt value travels through the private secret, never an argument or stdout.
  Compose @('run','--rm','-T','--no-deps','h11-cli','sh','-c','if ! wp core is-installed >/dev/null 2>&1; then wp core install --url="$H11_URL" --title="H11 QA - NO PUBLICAR" --admin_user=h11_admin --admin_email=admin@h11.test --skip-email --prompt=admin_password < /run/secrets/admin > /tmp/h11-install.txt 2>&1 || { echo "WordPress install failed; inspect private container log"; exit 1; }; fi')
  Wp @('eval','if (get_option("commerce_distribution_initialized")) { WP_CLI::error("Already initialized; use inspect/update-code instead of install."); }')
  $versions=Get-Content config/h2-versions.json -Raw | ConvertFrom-Json
  Wp @('plugin','install','woocommerce',"--version=$($versions.woocommerce.version)",'--activate')
  Wp @('plugin','verify-checksums','woocommerce','--strict')
  Install-Code
  Wp @('theme','activate','commerce-base')
  Wp @('plugin','activate','commerce-core','commerce-communications','commerce-order-integrity','commerce-account-navigation','commerce-lookbook')
  Wp @('option','update','commerce_h11_installation','h11-production-simulation')
  Wp @('eval-file','/opt/h11-deployment/initialize.php')
  Wp @('rewrite','structure','/%postname%/','--hard')
  Wp @('eval-file','/opt/h11-deployment/preflight.php')
  return
}
if (-not(Test-Path -LiteralPath $envFile)) {throw 'Run install with a fresh instance first.'}
switch($Action) {
  'wp' { if(-not $WpArgs.Count){throw 'Provide -WpArgs'}; Wp $WpArgs }
  'inspect' {Compose @('ps'); Wp @('eval-file','/opt/h11-deployment/preflight.php')}
  'stop' {Compose @('stop')}
  'update-code' {Install-Code; Wp @('cache','flush'); Wp @('rewrite','flush','--hard')}
  'backup' {
    if ($Instance -ne 'qa') {throw 'Backup source must be disposable qa.'}
    # Only this laboratory is quiesced. It is never the production backup script.
    Compose @('stop','h11-web')
    try {
      Wp @('db','export','/h11-backups/database.sql','--single-transaction','--add-drop-table')
      Compose @('run','--rm','-T','--no-deps','h11-cli','tar','-czf','/h11-backups/site.tar.gz','--exclude=wp-content/mu-plugins/h11-lab-mail.php','-C','/var/www/html','.')
      Get-FileHash "$runtime/backups/database.sql","$runtime/backups/site.tar.gz" -Algorithm SHA256 | Select-Object @{n='file';e={Split-Path -Leaf $_.Path}},Hash | ConvertTo-Json | Set-Content "$runtime/backups/checksums.json" -Encoding UTF8
    } finally {Compose @('start','h11-web')}
  }
  'restore' {
    if ($Instance -ne 'restore') {throw 'Restore only accepts the separate disposable restore target.'}
    $source=Join-Path $root '.local/h11/qa/backups'
    foreach($row in (Get-Content "$source/checksums.json" -Raw | ConvertFrom-Json)) {
      if ($row.file -notin @('database.sql','site.tar.gz')) {throw 'Invalid backup manifest.'}
      if ((Get-FileHash (Join-Path $source $row.file) -Algorithm SHA256).Hash -ne $row.Hash) {throw 'Backup integrity failed.'}
      Copy-Item -LiteralPath (Join-Path $source $row.file) -Destination "$runtime/backups/$($row.file)"
    }
    Wp @('eval','if (get_option("commerce_h11_installation") !== "h11-production-simulation" || wc_get_orders(["limit"=>1]) || get_posts(["post_type"=>"product","posts_per_page"=>1])) { WP_CLI::error("Restore target must be an empty H11 installation."); }')
    Compose @('stop','h11-web')
    # Keep the TARGET config and salts: credentials/URLs are environment specific.
    # On failure web remains stopped; preserve all source and target artifacts.
    Compose @('run','--rm','-T','--no-deps','h11-cli','tar','-xzf','/h11-backups/site.tar.gz','--exclude=./wp-config.php','-C','/var/www/html')
    Wp @('db','import','/h11-backups/database.sql')
    Wp @('search-replace','http://localhost:8184','http://localhost:8185','--all-tables-with-prefix','--precise','--skip-columns=guid')
    Wp @('cache','flush')
    Wp @('rewrite','flush','--hard')
    Compose @('start','h11-web')
  }
}
