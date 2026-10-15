[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('sync', 'activate', 'snapshot', 'verify')][string]$Action,
  [ValidatePattern('^[a-z][a-z0-9-]{0,29}$')][string]$Instance = 'local'
)

$ErrorActionPreference = 'Stop'
if (Test-Path variable:PSNativeCommandUseErrorActionPreference) { $PSNativeCommandUseErrorActionPreference = $false }
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Operate H3 only from C:\Codigo\demo-woocommerce.' }
$dev = Join-Path $repoRoot 'scripts/dev.ps1'
$envFile = Join-Path $repoRoot ".local/$Instance/.env"
$privateRoot = Join-Path $repoRoot ".local/$Instance/h3"
$themeRoot = Join-Path $repoRoot 'commerce-base'
$utf8 = New-Object Text.UTF8Encoding($false)
if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) { throw 'Use an existing initialized instance; H3 does not initialize or reset a store.' }
$null = New-Item -ItemType Directory -Force -Path $privateRoot

function Invoke-H3Compose {
  param([string[]]$Arguments, [switch]$Capture)
  $dockerArgs = @('compose', '--project-directory', $repoRoot, '--project-name', "demo-woocommerce-$Instance", '--env-file', $envFile, '-f', (Join-Path $repoRoot 'compose.yaml')) + $Arguments
  if ($Capture) {
    $output = & docker @dockerArgs 2>&1
    if ($LASTEXITCODE -ne 0) { throw "H3 Docker command failed ($LASTEXITCODE): $($output -join [Environment]::NewLine)" }
    return ($output | ForEach-Object { "$_" })
  }
  & docker @dockerArgs
  if ($LASTEXITCODE -ne 0) { throw "H3 Docker command failed with exit code $LASTEXITCODE." }
}

function Read-H3Report([string]$Mode) {
  $raw = @(& $dev wp -Instance $Instance -WpArgs @('eval-file', '/opt/local-tools/h3-verify.php', $Mode, '--quiet')) -join [Environment]::NewLine
  try { $report = $raw | ConvertFrom-Json }
  catch { throw 'H3 verification did not return the expected redacted JSON report.' }
  if ($report.result -ne 'passed') { throw 'H3 verification failed; no commerce settings were changed by the verifier.' }
  return $report
}

function Save-H3Report([object]$Report, [string]$Name) {
  [IO.File]::WriteAllText((Join-Path $privateRoot $Name), ($Report | ConvertTo-Json -Depth 30) + "`n", $utf8)
}

switch ($Action) {
  'sync' {
    foreach ($file in @('style.css', 'theme.json', 'templates/index.html')) {
      if (-not (Test-Path -LiteralPath (Join-Path $themeRoot $file) -PathType Leaf)) { throw "Missing required theme source: commerce-base/$file" }
    }
    $sourceEntries = @(Get-ChildItem -LiteralPath $themeRoot -Force -Recurse)
    if (@($sourceEntries | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }).Count -gt 0) { throw 'Theme source must not contain junctions or symbolic links.' }
    $archive = Join-Path $privateRoot 'commerce-base.tar.gz'
    & tar.exe -czf $archive -C $repoRoot commerce-base
    if ($LASTEXITCODE -ne 0) { throw 'Could not build the local theme archive.' }
    $archiveEntries = @(& tar.exe -tzf $archive)
    if ($LASTEXITCODE -ne 0 -or @($archiveEntries | Where-Object { $_ -notmatch '^commerce-base(?:/|$)' -or $_ -match '(^|/)\.\.(/|$)' }).Count -gt 0) { throw 'Theme archive paths did not pass validation.' }
    Invoke-H3Compose -Arguments @('cp', $archive, 'wordpress:/tmp/commerce-base-h3.tar.gz')
    Invoke-H3Compose -Arguments @('exec', '-T', 'wordpress', 'tar', '-xzf', '/tmp/commerce-base-h3.tar.gz', '-C', '/var/www/html/wp-content/themes', '--no-same-owner', '--no-same-permissions')
    $sourceFiles = @($sourceEntries | Where-Object { -not $_.PSIsContainer } | ForEach-Object { $_.FullName.Substring($themeRoot.Length + 1).Replace('\', '/') })
    $runtimeFiles = @(Invoke-H3Compose -Arguments @('exec', '-T', 'wordpress', 'find', '/var/www/html/wp-content/themes/commerce-base', '-type', 'f') -Capture)
    $stale = @($runtimeFiles | ForEach-Object { $_.Replace('/var/www/html/wp-content/themes/commerce-base/', '') } | Where-Object { $sourceFiles -cnotcontains $_ })
    if ($stale.Count -gt 0) { Write-Warning ('Runtime contains files absent from source; sync preserves them. Inspect explicitly before removing: ' + ($stale -join ', ')) }
    Write-Host "Commerce Base synchronized to demo-woocommerce-$Instance. Store data and other themes preserved; no host bind mount was added."
  }
  'activate' {
    & $dev wp -Instance $Instance -WpArgs @('theme', 'activate', 'commerce-base')
  }
  'snapshot' {
    $report = Read-H3Report 'snapshot'
    Save-H3Report $report 'baseline.json'
    Write-Host "Commerce baseline saved privately: .local/$Instance/h3/baseline.json"
  }
  'verify' {
    $baselinePath = Join-Path $privateRoot 'baseline.json'
    if (-not (Test-Path -LiteralPath $baselinePath -PathType Leaf)) { throw 'Capture h3 snapshot before activating the new theme.' }
    $baseline = Get-Content -Raw -LiteralPath $baselinePath | ConvertFrom-Json
    $report = Read-H3Report 'theme'
    Save-H3Report $report 'after.json'
    if ($baseline.commerce_state_hash -cne $report.commerce_state_hash) { throw "Preserved commerce state differs from baseline. Inspect the redacted .local/$Instance/h3/baseline.json and after.json; do not overwrite the baseline to hide changes." }
    Write-Host "Commerce Base passed static validation; plugins, HPOS, settings, stock, orders/refunds and cart/checkout content match the baseline. Private report: .local/$Instance/h3/after.json"
  }
}
