[CmdletBinding()]
param([Parameter(Mandatory=$true)][ValidatePattern('^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$')][string]$RunId)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$statePath = Join-Path $repoRoot '.local/local/h7-1/https/state.json'
while (Test-Path -LiteralPath $statePath) {
  try { $state = Get-Content -Raw -LiteralPath $statePath | ConvertFrom-Json }
  catch { Start-Sleep -Seconds 1; continue }
  if ($state.runId -cne $RunId -or $state.status -cne 'running') { exit 0 }
  if (-not $state.expiresAtUtc) { exit 1 }
  $expiry = if ($state.expiresAtUtc -is [DateTime]) { $state.expiresAtUtc.ToUniversalTime() } else { [DateTime]::Parse($state.expiresAtUtc, [Globalization.CultureInfo]::InvariantCulture, [Globalization.DateTimeStyles]::RoundtripKind).ToUniversalTime() }
  if ([DateTime]::UtcNow -ge $expiry) {
    & "$PSScriptRoot/h7-https.ps1" stop *> (Join-Path $repoRoot '.local/local/h7-1/https/watchdog-cleanup.txt')
    exit $LASTEXITCODE
  }
  Start-Sleep -Seconds 15
}
