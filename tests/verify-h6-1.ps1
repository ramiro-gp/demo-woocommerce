[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h6-1'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
function Invoke-H61Qa([string]$mode) {
  $raw = @(& "$repoRoot/scripts/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h6-1-qa.php', $mode, '--quiet')) -join "`n"
  [IO.File]::WriteAllText((Join-Path $privateRoot "functional-$mode.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
  if (($raw | ConvertFrom-Json).result -ne 'passed') { throw "Functional QA $mode failed." }
  Write-Output "Functional $mode passed."
}
try {
  Invoke-H61Qa 'prepare'
  Invoke-H61Qa 'verify'
  & node "$PSScriptRoot/verify-h6-1-browser.cjs"
  if ($LASTEXITCODE -ne 0) { throw 'Browser H6.1 QA failed; see private report.' }
} finally { Invoke-H61Qa 'cleanup' }
