#requires -Version 5.1
[CmdletBinding()]
param(
  [ValidateSet('install','sync','seed','inspect','verify','wp')][string]$Action = 'inspect',
  [ValidatePattern('^[a-z][a-z0-9-]{0,29}$')][string]$Instance = 'origen-casa',
  [ValidateRange(1024,65535)][int]$WordPressPort = 8082,
  [ValidateRange(1024,65535)][int]$MailpitPort = 8027,
  [string[]]$WpArgs = @()
)
$ErrorActionPreference = 'Stop'
if (Test-Path variable:PSNativeCommandUseErrorActionPreference) { $PSNativeCommandUseErrorActionPreference = $false }
$root = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Set-Location -LiteralPath $root
if ($Instance -in @('local','h2-public','h82-audit','h9-audit')) { throw 'H10 never provisions or seeds an existing milestone installation.' }
$project = "demo-woocommerce-$Instance"
$configFile = ".local/$Instance/.env"
function Compose([string[]]$Arguments) {
  & docker compose --project-directory $root --project-name $project --env-file $configFile -f compose.yaml -f compose.h10.yaml @Arguments
  if ($LASTEXITCODE -ne 0) { throw "H10 operation failed in $Instance. Volumes and secrets preserved. Inspect the reported phase and documented recovery before retrying." }
}
function Wp([string[]]$Arguments) { Compose (@('run','--rm','-T','--no-deps','wpcli','wp') + $Arguments) }
function Sync-Code {
  # Copies only versioned reusable code, never another installation's runtime.
  foreach ($source in @('commerce-base','commerce-lookbook')) {
    if (@(Get-ChildItem -LiteralPath $source -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }).Count) { throw 'Source links/junctions are not accepted.' }
    $destination = if ($source -eq 'commerce-base') { 'themes' } else { 'plugins' }
    & docker cp "./$source/." "${project}-wordpress-1:/var/www/html/wp-content/$destination/$source/"
    if ($LASTEXITCODE -ne 0) { throw 'Reusable source copy failed.' }
  }
  Compose @('exec','-T','--user','0','wordpress','sh','-c','chown -R 33:33 /var/www/html/wp-content/themes/commerce-base /var/www/html/wp-content/plugins/commerce-lookbook')
}
if ($Action -eq 'install') {
  if (-not (Test-Path -LiteralPath $configFile)) {
    if ($WordPressPort -eq $MailpitPort -or $WordPressPort -eq 8080 -or $MailpitPort -eq 8025) { throw 'Choose independent WordPress and Mailpit ports.' }
    foreach ($port in @($WordPressPort,$MailpitPort)) {
      $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$port)
      try { $listener.Start() } finally { $listener.Stop() }
    }
    & "$PSScriptRoot/dev.ps1" init -Instance $Instance -WordPressPort $WordPressPort -MailpitPort $MailpitPort
  }
  & "$PSScriptRoot/dev.ps1" start -Instance $Instance
  # Ownership/empty-shop guard runs before installing any commercial fixture.
  Wp @('eval-file','/opt/local-tools/h10-guard.php')
  $versions = Get-Content -Raw config/h2-versions.json | ConvertFrom-Json
  $installed = @(Wp @('plugin','list','--field=name','--quiet'))
  if ($installed -notcontains 'woocommerce') { Wp @('plugin','install','woocommerce',"--version=$($versions.woocommerce.version)",'--activate') }
  else {
    $current = (Wp @('plugin','get','woocommerce','--field=version','--quiet')) -join ''
    if ($current.Trim() -ne $versions.woocommerce.version) { throw 'WooCommerce version differs; explicit migration required.' }
    Wp @('plugin','activate','woocommerce','--quiet')
  }
  Wp @('plugin','verify-checksums','woocommerce','--strict')
  # MP is intentionally absent from clean installations; deactivate if an operator installed it.
  if ($installed -contains 'woocommerce-mercadopago') { Wp @('plugin','deactivate','woocommerce-mercadopago','--quiet') }
  Sync-Code
  Wp @('theme','activate','commerce-base','--quiet')
  Wp @('plugin','activate','commerce-core','commerce-order-integrity','commerce-communications','commerce-account-navigation','commerce-lookbook','--quiet')
  Wp @('language','plugin','install','woocommerce','es_AR')
  Wp @('eval-file','/opt/local-tools/h10-store.php','seed')
  Wp @('eval-file','/opt/local-tools/h10-store.php','verify')
  Write-Host "H10 DEMO ready. Runtime and credentials: .local/$Instance (ignored by Git)."
  return
}
if (-not (Test-Path -LiteralPath $configFile)) { throw 'Run install first.' }
switch ($Action) {
  'sync' { Sync-Code }
  'wp' { if (-not $WpArgs.Count) { throw 'Provide -WpArgs.' }; Wp $WpArgs }
  default { Wp @('eval-file','/opt/local-tools/h10-store.php',$Action) }
}
