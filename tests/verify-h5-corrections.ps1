[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Run in the canonical workspace.' }
$testExit = 1
try {
  & node "$PSScriptRoot/verify-h5-corrections.cjs"
  $testExit = $LASTEXITCODE
} finally {
  & "$repoRoot/scripts/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h5-corrections-cleanup.php', '--quiet')
}
if ($testExit -ne 0) { throw "Post-H5 browser QA failed ($testExit)." }
