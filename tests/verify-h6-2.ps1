[CmdletBinding()]
param([switch]$IncludeGuard)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h6-2'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
function Invoke-H62Qa([string]$mode) {
  $lines = [System.Collections.Generic.List[string]]::new()
  try {
    & "$repoRoot/scripts/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h6-2-qa.php', $mode, '--quiet') | ForEach-Object { $lines.Add([string]$_) }
  } catch {
    [IO.File]::WriteAllText((Join-Path $privateRoot "functional-$mode-error.txt"), ($lines -join "`n") + "`n" + $_.Exception.Message + "`n", (New-Object Text.UTF8Encoding($false)))
    throw
  }
  $raw = $lines -join "`n"
  [IO.File]::WriteAllText((Join-Path $privateRoot "functional-$mode.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
  if ($LASTEXITCODE -ne 0 -or ($raw | ConvertFrom-Json).result -ne 'passed') { throw "Functional QA $mode failed; see private report." }
  Write-Output "Functional $mode passed."
}
try {
  Invoke-H62Qa 'prepare'
  & "$repoRoot/scripts/h6-2.ps1" verify | Out-Null
  Invoke-H62Qa 'verify'
  if ($IncludeGuard) { Invoke-H62Qa 'guard' }
  & "$repoRoot/scripts/h6-2.ps1" verify | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'Final fixture verification failed.' }
} finally { Invoke-H62Qa 'cleanup' }
