[CmdletBinding()]
param([switch]$IncludeGuard)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h7-1'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
& "$repoRoot/scripts/h7-1.ps1" verify | Out-Null
& "$repoRoot/scripts/h7-1.ps1" prepare | Out-Null
foreach ($mode in @('verify') + $(if ($IncludeGuard) { @('guard') } else { @() })) {
  $lines = [System.Collections.Generic.List[string]]::new()
  $commandFailed = $false
  try {
    & "$repoRoot/scripts/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h7-1-qa.php', $mode, '--quiet') | ForEach-Object { $lines.Add([string]$_) }
  } catch { $commandFailed = $true }
  $raw = $lines -join "`n"
  try { $report = $raw | ConvertFrom-Json } catch { throw "H7.1 functional $mode failed without a sanitized JSON report; exception details withheld." }
  [IO.File]::WriteAllText((Join-Path $privateRoot "functional-$mode.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
  if ($commandFailed -or $LASTEXITCODE -ne 0 -or $report.result -ne 'passed') { throw "H7.1 functional $mode failed; see private sanitized report." }
  Write-Output "H7.1 functional $mode passed."
}
& "$repoRoot/scripts/h7-1.ps1" verify | Out-Null
