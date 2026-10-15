[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical repository.' }
$privateRoot = Join-Path $repoRoot '.local/h2-public'
if (-not (Test-Path -LiteralPath (Join-Path $privateRoot '.env'))) { throw 'Prepare instance h2-public first.' }
$composeBase = @('compose', '--project-directory', $repoRoot, '--project-name', 'demo-woocommerce-h2-public', '--env-file', (Join-Path $privateRoot '.env'), '-f', (Join-Path $repoRoot 'compose.yaml'))
$validationEnv = Join-Path $privateRoot 'https-origin-validation.env'
$utf8 = New-Object Text.UTF8Encoding($false)
$composeOverride = @('compose', '--project-directory', $repoRoot, '--project-name', 'demo-woocommerce-h2-public', '--env-file', (Join-Path $privateRoot '.env'), '--env-file', $validationEnv, '-f', (Join-Path $repoRoot 'compose.yaml'), '-f', (Join-Path $repoRoot 'compose.h2-https.yaml'))
function Invoke-CheckedDocker([string[]]$Arguments) {
  & docker @Arguments
  if ($LASTEXITCODE -ne 0) { throw 'Docker origin verification failed.' }
}
function Read-Origin([string]$Path) {
  # Connect only to loopback; .invalid is a reserved hostname, never resolved.
  $request = [Net.HttpWebRequest]::Create('http://127.0.0.1:18081' + $Path)
  $request.Host = 'h2-origin-check.invalid'
  $request.Headers.Add('X-Forwarded-Proto', 'https')
  $request.AllowAutoRedirect = $false
  $request.Proxy = $null
  $request.Timeout = 30000
  $response = $request.GetResponse()
  try {
    $reader = New-Object IO.StreamReader($response.GetResponseStream())
    try { $body = $reader.ReadToEnd() } finally { $reader.Dispose() }
    return [pscustomobject]@{Status=[int]$response.StatusCode; Body=$body; SecureCookie=([string]$response.Headers['Set-Cookie'] -match '(?i);\s*secure')}
  } finally { $response.Dispose() }
}
$mutex = New-Object Threading.Mutex($false, 'Local\demo-woocommerce-h2-public-https')
$locked = $false
try {
  try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $locked = $true }
  if (-not $locked) { throw 'Another h2-public HTTPS operation is running; wait for it to finish.' }
  if (Get-Process -Name cloudflared -ErrorAction SilentlyContinue) { throw 'Stop public tunnels before testing the local origin override.' }
  if ([Environment]::GetEnvironmentVariable('H2_PUBLIC_SITE_URL', 'Process')) { throw 'Use a clean shell without H2_PUBLIC_SITE_URL.' }
  foreach ($line in [IO.File]::ReadAllLines((Join-Path $privateRoot '.env'))) {
    if ($line -match '^([A-Z_]+)=([a-zA-Z0-9@._-]+)$') {
      $inherited = [Environment]::GetEnvironmentVariable($Matches[1], 'Process')
      if ($inherited -and $inherited -cne $Matches[2]) { throw 'An inherited variable would override the dedicated instance.' }
    }
  }
  [IO.File]::WriteAllText($validationEnv, "H2_PUBLIC_SITE_URL=https://h2-origin-check.invalid`n", $utf8)
  $report = [ordered]@{verifiedAtUtc=[DateTime]::UtcNow.ToString('o');scope='Loopback reverse-proxy simulation only; no public tunnel';passed=$false}
  try {
    Invoke-CheckedDocker ($composeOverride + @('up', '-d', '--wait', '--wait-timeout', '180', 'wordpress'))
    $shop = Read-Origin '/?post_type=product'
    if ($shop.Status -ne 200 -or $shop.Body -notmatch 'https://h2-origin-check\.invalid/\?product=' -or $shop.Body -match '(?:http:|https:)//localhost:18081') { throw 'Canonical HTTPS URLs did not apply to the storefront.' }
    $login = Read-Origin '/wp-login.php'
    if ($login.Status -ne 200 -or -not $login.SecureCookie -or $login.Body -notmatch 'https://h2-origin-check\.invalid/wp-login\.php') { throw 'HTTPS login recognition or secure test cookie failed.' }
    $cart = Read-Origin '/index.php?rest_route=/wc/store/v1/cart'
    $cartData = $cart.Body | ConvertFrom-Json
    if ($cart.Status -ne 200 -or $cartData.totals.currency_code -ne 'ARS') { throw 'Store API did not respond through the simulated HTTPS origin.' }
    $report.passed=$true
    $report.canonicalHttpsUrls=$true
    $report.loginHttp=200
    $report.secureLoginCookie=$true
    $report.storeApiHttp=200
    $report.currency='ARS'
  } finally {
    # No search/replace or persistent site-URL mutation is needed: restore base env.
    Invoke-CheckedDocker ($composeBase + @('up', '-d', '--wait', '--wait-timeout', '180', 'wordpress'))
    $restored=Invoke-WebRequest -UseBasicParsing 'http://localhost:18081/'
    if ($restored.StatusCode -ne 200 -or $restored.Content -match 'h2-origin-check\.invalid') { throw 'Local origin was not restored.' }
    $report.localOriginRestored=$true
    [IO.File]::WriteAllText((Join-Path $privateRoot 'h2-https-origin.json'), ($report | ConvertTo-Json -Depth 5) + "`n", $utf8)
  }
  $report | ConvertTo-Json -Depth 5
} finally {
  if ($locked) { $mutex.ReleaseMutex() }
  $mutex.Dispose()
}
