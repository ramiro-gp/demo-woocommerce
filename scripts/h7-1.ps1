[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('backup', 'sync', 'inspect', 'configure', 'verify', 'prepare', 'orders', 'cleanup', 'snapshot-linking', 'record-linking')][string]$Action,
  [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$')][string]$BackupId = 'h7-1-before-payments'
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h7-1'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
if ($Action -eq 'backup') { & "$PSScriptRoot/dev.ps1" backup -Instance local -BackupId $BackupId; return }
if ($Action -eq 'sync') { & "$PSScriptRoot/h5.ps1" sync; return }
if ($Action -in @('configure', 'snapshot-linking')) {
  $backupRoot = Join-Path $repoRoot ".local/local/backups/$BackupId"
  $manifest = Get-Content -LiteralPath (Join-Path $backupRoot 'manifest.json') -Raw | ConvertFrom-Json
  if ($manifest.formatVersion -ne 1 -or $manifest.project -cne 'demo-woocommerce-local' -or $manifest.backupId -cne $BackupId -or @($manifest.files).Count -ne 2) { throw 'Expected a complete preventive local backup.' }
  foreach ($name in @('database.sql', 'wordpress.tar.gz')) {
    $entry = @($manifest.files | Where-Object { $_.name -ceq $name })
    if ($entry.Count -ne 1 -or (Get-FileHash -LiteralPath (Join-Path $backupRoot $name) -Algorithm SHA256).Hash.ToLowerInvariant() -cne $entry[0].sha256) { throw 'Backup integrity failed.' }
  }
}
$tool = if ($Action -in @('prepare', 'orders', 'cleanup')) { 'h7-1-qa.php' } else { 'h7-1-payments.php' }
$lines = [System.Collections.Generic.List[string]]::new()
$commandFailed = $false
try {
  & "$PSScriptRoot/dev.ps1" wp -Instance local -WpArgs @('eval-file', "/opt/local-tools/$tool", $Action, $BackupId, '--quiet') | ForEach-Object { $lines.Add([string]$_) }
} catch {
  # Do not persist third-party exception text: it can contain private values.
  $commandFailed = $true
}
$raw = $lines -join "`n"
try { $report = $raw | ConvertFrom-Json } catch { throw "H7.1 $Action failed without a sanitized JSON report; exception details withheld." }
[IO.File]::WriteAllText((Join-Path $privateRoot "$Action.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
if ($commandFailed -or $LASTEXITCODE -ne 0 -or $report.result -notin @('ready', 'passed')) { throw "H7.1 $Action failed; see the private sanitized report." }
$raw
