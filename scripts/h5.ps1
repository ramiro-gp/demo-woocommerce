[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('inspect', 'sync', 'backup', 'seed', 'verify')][string]$Action,
  [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$')][string]$BackupId = 'h5-before-catalog'
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'H5 must run in the canonical workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h5'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
switch ($Action) {
  'backup' { & "$PSScriptRoot/dev.ps1" backup -Instance local -BackupId $BackupId }
  'sync' {
    & "$PSScriptRoot/h3.ps1" sync -Instance local
    & "$PSScriptRoot/dev.ps1" wp -Instance local -WpArgs @('eval', 'wp_clean_themes_cache(true);', '--quiet')
  }
  default {
    if ($Action -eq 'seed') {
      $manifestPath = Join-Path $repoRoot ".local/local/backups/$BackupId/manifest.json"
      if (-not (Test-Path -LiteralPath $manifestPath)) { throw 'Create a preventive backup before importing H5.' }
      $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
      if ($manifest.project -cne 'demo-woocommerce-local' -or $manifest.backupId -cne $BackupId) { throw 'Backup belongs to another instance.' }
      foreach ($file in @('database.sql', 'wordpress.tar.gz')) {
        $entry = @($manifest.files | Where-Object { $_.name -ceq $file })
        $filePath = Join-Path (Split-Path -Parent $manifestPath) $file
        if ($entry.Count -ne 1 -or (Get-FileHash -LiteralPath $filePath -Algorithm SHA256).Hash.ToLowerInvariant() -cne $entry[0].sha256) { throw 'Backup integrity failed.' }
      }
    }
    $wpArgs = @('eval-file', '/opt/local-tools/h5-catalog.php', $Action, $BackupId, '--quiet')
    $raw = @(& "$PSScriptRoot/dev.ps1" wp -Instance local -WpArgs $wpArgs) -join "`n"
    $report = $raw | ConvertFrom-Json
    [IO.File]::WriteAllText((Join-Path $privateRoot "$Action.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
    if ($report.result -notin @('ready', 'passed')) { throw ('H5 preserved conflicting content: ' + ($report.conflicts -join ' ')) }
    $raw
  }
}
