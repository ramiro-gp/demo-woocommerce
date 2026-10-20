#requires -Version 7.0
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$output = Join-Path $repoRoot '.local/local/h8-1'
$null = New-Item -ItemType Directory -Force -Path $output
foreach ($mode in @('source', 'verify')) {
    $raw = @(& "$repoRoot/scripts/dev.ps1" wp -WpArgs @('eval-file', '/opt/local-tools/h8-1-qa.php', $mode, '--quiet')) -join "`n"
    if ($LASTEXITCODE -ne 0) { throw "H8.1 $mode failed; inspect local state." }
    $report = $raw | ConvertFrom-Json
    [IO.File]::WriteAllText((Join-Path $output "$mode-report.json"), $raw + "`n", [Text.UTF8Encoding]::new($false))
    if ($report.result -ne 'passed') { throw "H8.1 $mode failed." }
    Write-Output "H8.1 $mode`: $(@($report.checks.PSObject.Properties).Count) checks passed."
}
$raw = @(& "$repoRoot/scripts/dev.ps1" wp -WpArgs @('--require=/opt/local-tools/h8-1-isolated.php', 'eval-file', '/opt/local-tools/h8-1-qa.php', 'theme', '--quiet')) -join "`n"
if ($LASTEXITCODE -ne 0) { throw 'Isolated theme QA failed.' }
$report = $raw | ConvertFrom-Json
if ($report.result -ne 'passed') { throw 'Isolated theme QA failed.' }
[IO.File]::WriteAllText((Join-Path $output 'theme-report.json'), $raw + "`n", [Text.UTF8Encoding]::new($false))
Write-Output "H8.1 isolated theme: $(@($report.checks.PSObject.Properties).Count) checks passed."
