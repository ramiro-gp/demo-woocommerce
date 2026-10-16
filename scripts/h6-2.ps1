[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('backup', 'sync', 'inspect', 'seed', 'verify')][string]$Action,
  [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$')][string]$BackupId = 'h6-2-before-shipping-logistics'
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h6-2'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
if ($Action -eq 'backup') { & "$PSScriptRoot/dev.ps1" backup -Instance local -BackupId $BackupId; return }
if ($Action -eq 'sync') { & "$PSScriptRoot/h5.ps1" sync; return }
if ($Action -eq 'seed') {
  $backupRoot = Join-Path $repoRoot ".local/local/backups/$BackupId"
  $manifest = Get-Content -LiteralPath (Join-Path $backupRoot 'manifest.json') -Raw | ConvertFrom-Json
  if ($manifest.project -cne 'demo-woocommerce-local' -or $manifest.backupId -cne $BackupId) { throw 'Expected preventive local backup.' }
  foreach ($name in @('database.sql', 'wordpress.tar.gz')) {
    $entry = @($manifest.files | Where-Object { $_.name -ceq $name })
    if ($entry.Count -ne 1 -or (Get-FileHash -LiteralPath (Join-Path $backupRoot $name) -Algorithm SHA256).Hash.ToLowerInvariant() -cne $entry[0].sha256) { throw 'Backup integrity failed.' }
  }
}
$lines = [System.Collections.Generic.List[string]]::new()
try {
  & "$PSScriptRoot/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h6-2-shipping.php', $Action, $BackupId, '--quiet') | ForEach-Object { $lines.Add([string]$_) }
} catch {
  $failure = ($lines -join "`n") + "`n" + $_.Exception.Message
  [IO.File]::WriteAllText((Join-Path $privateRoot "$Action-error.txt"), $failure + "`n", (New-Object Text.UTF8Encoding($false)))
  throw
}
$raw = $lines -join "`n"
[IO.File]::WriteAllText((Join-Path $privateRoot "$Action.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
if ($LASTEXITCODE -ne 0) { throw $raw }
$report = $raw | ConvertFrom-Json
if ($report.result -notin @('ready', 'passed')) { throw 'H6.2 validation failed.' }
$raw
