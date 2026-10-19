[CmdletBinding()]
param(
  [Parameter(Position=0,Mandatory=$true)]
  [ValidateSet('configure','inspect','verify')][string]$Action,
  [ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$')][string]$BackupId='h7-2-before-communications-20261019'
)
$ErrorActionPreference='Stop'
$repoRoot=[IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
$privateRoot=Join-Path $repoRoot '.local/local/h7-2'
$null=New-Item -ItemType Directory -Force -Path $privateRoot
$raw=@(& "$PSScriptRoot/dev.ps1" wp -WpArgs @('eval-file','/opt/local-tools/h7-2-communications.php',$Action,$BackupId,'--quiet')) -join "`n"
if ($LASTEXITCODE -ne 0) { throw 'H7.2 command failed. Review local configuration; no credentials are printed.' }
$report=$raw | ConvertFrom-Json
[IO.File]::WriteAllText((Join-Path $privateRoot "$Action.json"),$raw+"`n",(New-Object Text.UTF8Encoding($false)))
if ($report.result -notin @('passed','ready')) { throw 'H7.2 verification failed; see the private report.' }
$raw
