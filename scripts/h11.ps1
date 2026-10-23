#requires -Version 5.1
[CmdletBinding()]
param(
  [ValidateSet('install','wp','backup','restore','update-code','stop','inspect')][string]$Action='inspect',
  [ValidateSet('qa','restore','repeat')][string]$Instance='qa',
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
function Code-Permissions([bool]$Writable) {
  $owner=if($Writable){'33:33'}else{'0:33'}
  # Fixed H11 container paths only; no host permission change.
  Compose @('exec','-T','--user','0','h11-web','sh','-c',"chown $owner wp-content/themes wp-content/plugins; for p in themes/commerce-base plugins/commerce-core plugins/commerce-communications plugins/commerce-order-integrity plugins/commerce-account-navigation plugins/commerce-lookbook; do chown -R $owner wp-content/`$p; find wp-content/`$p -type d -exec chmod 755 {} +; find wp-content/`$p -type f -exec chmod 644 {} +; done; chown 0:33 wp-config.php; chmod 440 wp-config.php")
  if (-not $Writable) { Compose @('exec','-T','--user','0','h11-web','sh','-c','chown -R 0:33 wp-content/plugins/woocommerce; find wp-content/plugins/woocommerce -type d -exec chmod 755 {} +; find wp-content/plugins/woocommerce -type f -exec chmod 644 {} +') }
}
function Install-Code {
  $manifest=Get-Content '.local/h11-release/1.0.0/manifest.json' -Raw | ConvertFrom-Json
  $expected=@('commerce-base','commerce-core','commerce-communications','commerce-order-integrity','commerce-account-navigation','commerce-lookbook')
  if ($manifest.distribution -ne 'commerce-clean' -or $manifest.version -ne '1.0.0' -or $manifest.demoContent -ne $false -or $manifest.credentials -ne $false -or @($manifest.packages).Count -ne 6 -or @(Compare-Object ($manifest.packages.slug | Sort-Object -Unique) ($expected | Sort-Object)).Count) {throw 'Invalid/incomplete clean release manifest.'}
  foreach ($package in $manifest.packages) {
    if ($package.version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+$' -or $package.zip -ne "$($package.slug)-$($package.version).zip" -or $package.sha256 -notmatch '^[a-f0-9]{64}$') {throw 'Invalid package metadata.'}
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
  $port=switch($Instance){'qa'{8184}'restore'{8185}'repeat'{8188}}
  $mailPort=switch($Instance){'qa'{8186}'restore'{8187}'repeat'{8189}}
  foreach ($p in @($port,$mailPort)) { $listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$p); try {$listener.Start()} finally {$listener.Stop()} }
  [IO.File]::WriteAllText($envFile,"H11_INSTANCE=$Instance`nH11_PORT=$port`nH11_MAIL_PORT=$mailPort`n")
  }
  Compose @('up','-d','--wait','--wait-timeout','150','h11-db','h11-web','h11-mail')
  Compose @('exec','-T','h11-web','sh','-c','test -f wp-load.php && test -f wp-config.php')
  # Prompt value travels through the private secret, never an argument or stdout.
  Compose @('run','--rm','-T','--no-deps','h11-cli','sh','-c','if ! wp core is-installed >/dev/null 2>&1; then wp core install --url="$H11_URL" --title="H11 QA - NO PUBLICAR" --admin_user=h11_admin --admin_email=admin@h11.test --skip-email --prompt=admin_password < /run/secrets/admin > /tmp/h11-install.txt 2>&1 || { echo "WordPress install failed; inspect private container log"; exit 1; }; fi')
  Wp @('eval','if (get_option("commerce_distribution_initialized")) { WP_CLI::error("Already initialized; use inspect/update-code instead of install."); }')
  Wp @('eval','global $wpdb; if (count(get_users())!==1 || (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (\"product\",\"product_variation\",\"shop_order\",\"shop_order_refund\",\"attachment\",\"wp_navigation\")")>0) { WP_CLI::error("H11 install requires a fresh disposable WordPress."); }')
  Wp @('eval','global $wpdb; foreach (["wc_orders","commerce_legal_requests","commerce_legal_events"] as $suffix) { $t=$wpdb->prefix.$suffix; if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$wpdb->esc_like($t)))===$t && (int)$wpdb->get_var("SELECT COUNT(*) FROM `".$t."`")>0) { WP_CLI::error("Existing history; H11 install refused before package changes."); } }')
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
  Wp @('eval-file','/opt/h11-deployment/lab-pristine.php','snapshot')
  Code-Permissions $false
  return
}
if (-not(Test-Path -LiteralPath $envFile)) {throw 'Run install with a fresh instance first.'}
switch($Action) {
  'wp' { if(-not $WpArgs.Count){throw 'Provide -WpArgs'}; Wp $WpArgs }
  'inspect' {Compose @('ps'); Wp @('eval-file','/opt/h11-deployment/preflight.php')}
  'stop' {Compose @('stop')}
  'update-code' {
    Code-Permissions $true
    try { Install-Code; Wp @('cache','flush'); Wp @('rewrite','flush','--hard') }
    finally { Code-Permissions $false }
  }
  'backup' {
    if ($Instance -ne 'qa') {throw 'Backup source must be disposable qa.'}
    # Only this laboratory is quiesced. It is never the production backup script.
    Compose @('stop','h11-web')
    try {
      Wp @('db','export','/h11-backups/database.sql','--single-transaction','--add-drop-table','--skip-ssl')
      Compose @('run','--rm','-T','--no-deps','h11-cli','tar','-czf','/h11-backups/site.tar.gz','--exclude=wp-content/mu-plugins/h11-lab-mail.php','-C','/var/www/html','.')
      Get-FileHash "$runtime/backups/database.sql","$runtime/backups/site.tar.gz" -Algorithm SHA256 | Select-Object @{n='file';e={Split-Path -Leaf $_.Path}},Hash | ConvertTo-Json | Set-Content "$runtime/backups/checksums.json" -Encoding UTF8
    } finally {Compose @('start','h11-web')}
  }
  'restore' {
    if ($Instance -ne 'restore') {throw 'Restore only accepts the separate disposable restore target.'}
    $source=Join-Path $root '.local/h11/qa/backups'
    $backupRows=@(Get-Content "$source/checksums.json" -Raw | ConvertFrom-Json)
    if ($backupRows.Count -ne 2 -or @($backupRows.file | Select-Object -Unique).Count -ne 2) {throw 'Incomplete backup manifest.'}
    foreach($row in $backupRows) {
      if ($row.file -notin @('database.sql','site.tar.gz')) {throw 'Invalid backup manifest.'}
      if ((Get-FileHash (Join-Path $source $row.file) -Algorithm SHA256).Hash -ne $row.Hash) {throw 'Backup integrity failed.'}
      Copy-Item -LiteralPath (Join-Path $source $row.file) -Destination "$runtime/backups/$($row.file)"
    }
    Wp @('eval','if (get_option("commerce_h11_installation") !== "h11-production-simulation" || wc_get_orders(["limit"=>1]) || get_posts(["post_type"=>"product","posts_per_page"=>1])) { WP_CLI::error("Restore target must be an empty H11 installation."); }')
    Wp @('eval-file','/opt/h11-deployment/lab-pristine.php','verify')
    Compose @('run','--rm','-T','--no-deps','h11-cli','sh','/opt/h11-deployment/lab-validate-backup.sh')
    Compose @('stop','h11-web')
    # Keep the TARGET config and salts: credentials/URLs are environment specific.
    # On failure web remains stopped; preserve all source and target artifacts.
    Compose @('run','--rm','-T','--no-deps','--user','0','h11-cli','tar','-xzf','/h11-backups/site.tar.gz','--exclude=./wp-config.php','-C','/var/www/html')
    # LAB ONLY: WP-CLI 2.12 db import probes SQL modes without forwarding --skip-ssl.
    # Use the installed native client on this isolated network; keep the secret out of argv/output.
    # Production must use the selected host's supported TLS policy and credentials.
    Compose @('run','--rm','-T','--no-deps','h11-cli','sh','-c','MYSQL_PWD="$(cat /run/secrets/db)" mariadb --skip-ssl --host=h11-db --user=h11 h11 < /h11-backups/database.sql')
    Wp @('search-replace','http://localhost:8184','http://localhost:8185','--all-tables-with-prefix','--precise','--skip-columns=guid')
    Wp @('cache','flush')
    Wp @('rewrite','flush','--hard')
    Compose @('start','h11-web')
    Code-Permissions $false
  }
}
