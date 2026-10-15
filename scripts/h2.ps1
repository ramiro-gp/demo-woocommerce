[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('install', 'inspect', 'inventory')][string]$Action,
  [ValidatePattern('^[a-z][a-z0-9-]{0,29}$')][string]$Instance = 'local'
)
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$dev = Join-Path $repoRoot 'scripts/dev.ps1'
$versions = Get-Content -Raw -LiteralPath (Join-Path $repoRoot 'config/h2-versions.json') | ConvertFrom-Json
function Invoke-H2Wp([string[]]$Arguments) { & $dev wp -Instance $Instance -WpArgs $Arguments }
if ($Action -eq 'install') {
  # dev start checks persisted WordPress against the pinned H1 core version.
  & $dev start -Instance $Instance
  foreach ($component in @($versions.woocommerce, $versions.mercadopago)) {
    $installed = & $dev wp -Instance $Instance -WpArgs @('plugin', 'list', '--field=name')
    if ($installed -contains $component.slug) {
      $current = & $dev wp -Instance $Instance -WpArgs @('plugin', 'get', $component.slug, '--field=version')
      if (($current -join '').Trim() -ne $component.version) { throw "Installed $($component.slug) differs from pinned H2 version. Plan migration deliberately." }
      Invoke-H2Wp @('plugin', 'activate', $component.slug)
    } else {
      Invoke-H2Wp @('plugin', 'install', $component.slug, "--version=$($component.version)", '--activate')
    }
    Invoke-H2Wp @('plugin', 'verify-checksums', $component.slug, '--strict')
  }
  Invoke-H2Wp @('language', 'plugin', 'install', 'woocommerce', 'es_AR')
  Invoke-H2Wp @('language', 'plugin', 'install', 'woocommerce-mercadopago', 'es_AR')
  Invoke-H2Wp @('eval-file', '/opt/local-tools/h2-fixtures.php')
  Invoke-H2Wp @('eval-file', '/opt/local-tools/h2-verify.php', 'inspect')
} else {
  Invoke-H2Wp @('eval-file', '/opt/local-tools/h2-verify.php', $Action)
}
