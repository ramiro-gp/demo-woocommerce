[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('inspect', 'sync', 'backup', 'seed', 'verify')][string]$Action,
  [ValidateSet('local')][string]$Instance = 'local',
  [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$')][string]$BackupId = 'h4-before-content',
  [string]$ConfirmUrl = 'http://localhost:8080/'
)

$ErrorActionPreference = 'Stop'
if (Test-Path variable:PSNativeCommandUseErrorActionPreference) { $PSNativeCommandUseErrorActionPreference = $false }
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Operate H4 only from C:\Codigo\demo-woocommerce.' }
if ($ConfirmUrl -cne 'http://localhost:8080/') { throw 'H4 content is restricted to the existing demo at http://localhost:8080/.' }
$dev = Join-Path $repoRoot 'scripts/dev.ps1'
$h3 = Join-Path $repoRoot 'scripts/h3.ps1'
$envFile = Join-Path $repoRoot '.local/local/.env'
$privateRoot = Join-Path $repoRoot '.local/local/h4'
$utf8 = New-Object Text.UTF8Encoding($false)
if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) { throw 'Use the existing local instance; H4 never initializes or resets a store.' }
$instanceLine = @(Get-Content -LiteralPath $envFile | Where-Object { $_ -match '^LOCAL_INSTANCE=local$' })
$portLine = @(Get-Content -LiteralPath $envFile | Where-Object { $_ -match '^LOCAL_WP_PORT=8080$' })
if ($instanceLine.Count -ne 1 -or $portLine.Count -ne 1) { throw 'The local instance configuration does not identify the approved demo at 8080.' }

function Read-H4Content([string]$Mode) {
  $wpArgs = @('eval-file', '/opt/local-tools/h4-demo-content.php', $Mode)
  if ($Mode -eq 'seed') { $wpArgs += $ConfirmUrl }
  $wpArgs += '--quiet'
  $raw = @(& $dev wp -Instance local -WpArgs $wpArgs) -join [Environment]::NewLine
  try { $report = $raw | ConvertFrom-Json }
  catch { throw 'H4 did not return the expected local content report.' }
  if ($report.instance -ne 'local' -or $report.result -notin @('ready', 'passed', 'conflict')) { throw 'Unexpected H4 content report.' }
  return $report
}
function Save-H4Content([object]$Report, [string]$Name) {
  $null = New-Item -ItemType Directory -Force -Path $privateRoot
  [IO.File]::WriteAllText((Join-Path $privateRoot $Name), ($Report | ConvertTo-Json -Depth 30) + "`n", $utf8)
}

switch ($Action) {
  'inspect' {
    $report = Read-H4Content 'inspect'
    Save-H4Content $report 'inspection.json'
    $report | ConvertTo-Json -Depth 30
  }
  'sync' {
    & $h3 sync -Instance local
  }
  'backup' {
    & $dev backup -Instance local -BackupId $BackupId
  }
  'seed' {
    $before = Read-H4Content 'inspect'
    Save-H4Content $before 'before-seed.json'
    if ($before.result -eq 'conflict') { throw ('H4 preserves editor changes: ' + ($before.conflicts -join ' ')) }
    $backupManifest = Join-Path $repoRoot ".local/local/backups/$BackupId/manifest.json"
    if (-not (Test-Path -LiteralPath $backupManifest -PathType Leaf)) { throw 'H4 requires a completed preventive backup before writing content.' }
    $backup = Get-Content -Raw -LiteralPath $backupManifest | ConvertFrom-Json
    if ($backup.formatVersion -ne 1 -or $backup.project -cne 'demo-woocommerce-local' -or $backup.backupId -cne $BackupId) { throw 'The preventive backup does not identify this local instance.' }
    foreach ($name in @('database.sql', 'wordpress.tar.gz')) {
      $entries = @($backup.files | Where-Object { $_.name -ceq $name })
      $file = Join-Path (Split-Path -Parent $backupManifest) $name
      if ($entries.Count -ne 1 -or -not (Test-Path -LiteralPath $file -PathType Leaf) -or (Get-FileHash -Algorithm SHA256 -LiteralPath $file).Hash.ToLowerInvariant() -cne $entries[0].sha256) { throw 'The preventive backup failed integrity validation.' }
    }
    & $h3 sync -Instance local
    $report = Read-H4Content 'seed'
    if ($report.result -ne 'passed') { throw 'H4 content seeding did not complete.' }
    Save-H4Content $report 'content.json'
    Write-Host "H4 native pages and primary navigation are ready at http://localhost:8080/. Preventive backup: .local/local/backups/$BackupId. Content report: .local/local/h4/content.json"
  }
  'verify' {
    $report = Read-H4Content 'verify'
    if ($report.result -ne 'passed') { throw 'H4 content verification failed.' }
    Save-H4Content $report 'content.json'
    Write-Host 'H4 managed pages, primary menu and front-page selection match their stored fingerprints. WooCommerce data was not changed by this verifier.'
  }
}
