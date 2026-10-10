[CmdletBinding()]
param(
  [ValidatePattern('^h1-[a-z0-9-]{1,20}$')][string]$Instance = 'h1-check',
  [int]$WordPressPort = 18080,
  [int]$MailpitPort = 18025
)
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$dev = Join-Path $repoRoot 'scripts/dev.ps1'
$privateRoot = Join-Path $repoRoot ".local/$Instance"
$project = "demo-woocommerce-$Instance"
if (Test-Path -LiteralPath $privateRoot) { throw 'Use a new h1-* instance for this destructive integration verification.' }
$checks = New-Object 'System.Collections.Generic.List[string]'
function Pass([string]$Name) { $checks.Add($Name); Write-Host "PASS: $Name" }
function Fixture([string]$Mode) { & $dev wp -Instance $Instance -WpArgs @('eval-file', '/opt/local-tools/verify-fixture.php', $Mode) }
function Expect-Refusal([scriptblock]$Call, [string]$Message) {
  $refused = $false
  try { & $Call } catch { $refused = $true; Write-Host "Expected refusal: $($_.Exception.Message)" }
  if (-not $refused) { throw "Required refusal failed: $Message" }
  Pass $Message
}

& $dev init -Instance $Instance -WordPressPort $WordPressPort -MailpitPort $MailpitPort
& $dev start -Instance $Instance
Fixture 'fresh'
$siteResponse = Invoke-WebRequest -UseBasicParsing -Uri "http://localhost:$WordPressPort/"
$admin = Invoke-WebRequest -UseBasicParsing -Uri "http://localhost:$WordPressPort/wp-admin/"
if ($siteResponse.StatusCode -ne 200 -or $admin.StatusCode -ne 200) { throw 'Local HTTP access failed.' }
Pass 'Fresh install, locale, private login password and HTTP access'

$originalSecretHash = (Get-FileHash -Algorithm SHA256 -LiteralPath (Join-Path $privateRoot 'secrets/wp-admin-password.txt')).Hash
Fixture 'seed'
& $dev init -Instance $Instance
& $dev stop -Instance $Instance
& $dev start -Instance $Instance
Fixture 'original'
if ((Get-FileHash -Algorithm SHA256 -LiteralPath (Join-Path $privateRoot 'secrets/wp-admin-password.txt')).Hash -ne $originalSecretHash) { throw 'init rotated the private administrator password.' }
Pass 'Repeated init and stop/start preserve database, uploads, options and secrets'

& $dev mail-test -Instance $Instance
$messages = Invoke-RestMethod -Uri "http://localhost:$MailpitPort/api/v1/messages"
if ($messages.total -lt 1 -or -not ($messages.messages | Where-Object { $_.Subject -eq 'H1 local mail verification' })) { throw 'Mailpit did not capture wp_mail.' }
& $dev stop -Instance $Instance
& $dev start -Instance $Instance
$messages = Invoke-RestMethod -Uri "http://localhost:$MailpitPort/api/v1/messages"
if ($messages.total -lt 1) { throw 'Mailpit messages did not persist.' }
Pass 'wp_mail capture and Mailpit persistence'

& $dev backup -Instance $Instance -BackupId 'baseline'
Fixture 'changed'
Expect-Refusal { & $dev restore -Instance $Instance -BackupId 'baseline' -ConfirmName 'wrong-project' } 'Wrong project confirmation refused'
$sql = Join-Path $privateRoot 'backups/baseline/database.sql'
[IO.File]::AppendAllText($sql, "`n-- checksum tamper probe`n")
Expect-Refusal { & $dev restore -Instance $Instance -BackupId 'baseline' -ConfirmName $project } 'Tampered backup refused before replacement'
# Remove exactly the appended probe, leaving the original byte sequence unchanged.
$sqlBytes = [IO.File]::ReadAllBytes($sql)
$probeBytes = [Text.Encoding]::UTF8.GetBytes("`n-- checksum tamper probe`n")
$originalBytes = New-Object byte[] ($sqlBytes.Length - $probeBytes.Length)
[Array]::Copy($sqlBytes, $originalBytes, $originalBytes.Length)
[IO.File]::WriteAllBytes($sql, $originalBytes)
& $dev restore -Instance $Instance -BackupId 'baseline' -ConfirmName $project
Fixture 'original'
Pass 'Backup and full file/database restore; later files removed'

& $dev reset-db -Instance $Instance -ConfirmName $project
Fixture 'reset-db'
Fixture 'fresh'
Pass 'Deliberate database reset preserves uploads and reinstalls WordPress'
& $dev reset-all -Instance $Instance -ConfirmName $project
Fixture 'reset-all'
Fixture 'fresh'
$messages = Invoke-RestMethod -Uri "http://localhost:$MailpitPort/api/v1/messages"
if ($messages.total -ne 0) { throw 'Full reset retained captured mail.' }
Pass 'Deliberate full reset removes only this instance data and keeps private backups'
if (-not (Test-Path -LiteralPath (Join-Path $privateRoot 'backups/baseline/manifest.json'))) { throw 'Full reset deleted private backups.' }

& $dev stop -Instance $Instance
$report = [ordered]@{ verifiedAtUtc = [DateTime]::UtcNow.ToString('o'); instance = $Instance; checks = @($checks); result = 'passed' }
[IO.File]::WriteAllText((Join-Path $privateRoot 'verification.json'), ($report | ConvertTo-Json -Depth 4) + "`n", (New-Object Text.UTF8Encoding($false)))
Write-Host "H1 checks passed. Test instance stopped; private report: .local/$Instance/verification.json"
