[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical repository.' }
$privateRoot = Join-Path $repoRoot '.local/local'
if (-not (Test-Path -LiteralPath (Join-Path $privateRoot '.env'))) { throw 'Prepare instance local first.' }
$composeBase = @('compose', '--project-directory', $repoRoot, '--project-name', 'demo-woocommerce-local', '--env-file', (Join-Path $privateRoot '.env'), '-f', (Join-Path $repoRoot 'compose.yaml'))
$validationEnv = Join-Path $privateRoot 'https-origin-validation.env'
$utf8 = New-Object Text.UTF8Encoding($false)
Add-Type -AssemblyName System.Net.Http
$composeOverride = @('compose', '--project-directory', $repoRoot, '--project-name', 'demo-woocommerce-local', '--env-file', (Join-Path $privateRoot '.env'), '--env-file', $validationEnv, '-f', (Join-Path $repoRoot 'compose.yaml'), '-f', (Join-Path $repoRoot 'compose.h7-https.yaml'))
function Invoke-CheckedDocker([string[]]$Arguments) {
  & docker @Arguments
  if ($LASTEXITCODE -ne 0) { throw 'Docker origin verification failed.' }
}
function Read-Origin([string]$Path) {
  # Follow native canonical redirects through loopback only. The reserved
  # .invalid origin is never resolved and must remain HTTPS and same-origin.
  $target = [Uri]('https://h7-origin-check.invalid' + $Path)
  for ($redirect = 0; $redirect -le 5; $redirect++) {
    if ($target.Scheme -cne 'https' -or $target.Authority -cne 'h7-origin-check.invalid' -or $target.UserInfo) { throw 'Unsafe simulated-origin redirect.' }
    # HttpWebRequest.Host silently appends :80 for this HTTP transport,
    # manufacturing a canonical loop. HttpRequestMessage preserves the exact
    # public Host header that the real reverse proxy supplies.
    $handler = New-Object Net.Http.HttpClientHandler
    $handler.AllowAutoRedirect = $false
    $handler.UseProxy = $false
    $client = New-Object Net.Http.HttpClient($handler)
    $client.Timeout = [TimeSpan]::FromSeconds(30)
    $request = New-Object Net.Http.HttpRequestMessage
    $request.Method = [Net.Http.HttpMethod]::Get
    $request.RequestUri = [Uri]('http://127.0.0.1:8080' + $target.PathAndQuery)
    $request.Headers.Host = 'h7-origin-check.invalid'
    $request.Headers.Add('X-Forwarded-Proto', 'https')
    $response = $null
    try {
      $response = $client.SendAsync($request).GetAwaiter().GetResult()
      if ([int]$response.StatusCode -in @(301, 302, 303, 307, 308)) {
        $nextTarget = New-Object Uri($target, [string]$response.Headers.Location)
        $report.redirectDiagnostics += @([ordered]@{path=$target.PathAndQuery; destinationPath=$nextTarget.PathAndQuery; sourceScheme=$target.Scheme; destinationScheme=$nextTarget.Scheme; sameHost=($target.Authority -ceq $nextTarget.Authority)})
        $target = $nextTarget
        continue
      }
      $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
      return [pscustomobject]@{Status=[int]$response.StatusCode; Body=$body; Redirects=$redirect; NoIndex=($response.Headers.Contains('X-Robots-Tag') -and (($response.Headers.GetValues('X-Robots-Tag') -join ',') -match 'noindex'))}
    } finally { if ($response) { $response.Dispose() }; $request.Dispose(); $client.Dispose(); $handler.Dispose() }
  }
  throw 'Too many simulated-origin redirects.'
}
$mutex = New-Object Threading.Mutex($false, 'Local\demo-woocommerce-local-h7-1-https')
$locked = $false
try {
  try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $locked = $true }
  if (-not $locked) { throw 'Another local HTTPS operation is running; wait for it to finish.' }
  if (Get-Process -Name cloudflared -ErrorAction SilentlyContinue) { throw 'Stop public tunnels before testing the local origin override.' }
  if ([Environment]::GetEnvironmentVariable('H7_PUBLIC_SITE_URL', 'Process')) { throw 'Use a clean shell without H7_PUBLIC_SITE_URL.' }
  foreach ($line in [IO.File]::ReadAllLines((Join-Path $privateRoot '.env'))) {
    if ($line -match '^([A-Z_]+)=([a-zA-Z0-9@._-]+)$') {
      $inherited = [Environment]::GetEnvironmentVariable($Matches[1], 'Process')
      if ($inherited -and $inherited -cne $Matches[2]) { throw 'An inherited variable would override the dedicated instance.' }
    }
  }
  [IO.File]::WriteAllText($validationEnv, "H7_PUBLIC_SITE_URL=https://h7-origin-check.invalid`n", $utf8)
  $report = [ordered]@{verifiedAtUtc=[DateTime]::UtcNow.ToString('o');scope='Loopback reverse-proxy simulation only; no public tunnel';passed=$false;redirectDiagnostics=@()}
  try {
    Invoke-CheckedDocker ($composeOverride + @('up', '-d', '--wait', '--wait-timeout', '180', 'wordpress'))
    $login = Read-Origin '/wp-login.php'
    $report.loginHttp = $login.Status
    $shop = Read-Origin '/?post_type=product'
    $report.storefrontHttp = $shop.Status
    $report.storefrontRedirects = $shop.Redirects
    $report.hasCanonicalProductLinks = $shop.Body -match 'https://h7-origin-check\.invalid/\?product='
    $report.containsOldLocalOrigin = $shop.Body -match '(?:http:|https:)//localhost:8080'
    if ($report.containsOldLocalOrigin) {
      # Diagnostics stay private and contain no response headers or cookies.
      $diagnosticRoot = Join-Path $privateRoot 'h7-1'
      $null = New-Item -ItemType Directory -Force -Path $diagnosticRoot
      [IO.File]::WriteAllText((Join-Path $diagnosticRoot 'https-origin-storefront.html'), $shop.Body, $utf8)
    }
    if ($shop.Status -ne 200 -or $shop.Body -notmatch 'https://h7-origin-check\.invalid/\?product=' -or $shop.Body -match '(?:http:|https:)//localhost:8080') { throw 'Canonical HTTPS URLs did not apply to the storefront.' }
    if ($login.Status -ne 403 -or -not $login.NoIndex -or -not $shop.NoIndex) { throw 'Public login block or noindex header failed.' }
    foreach ($blockedPath in @('/wp-admin/', '/xmlrpc.php')) {
      if ((Read-Origin $blockedPath).Status -ne 403) { throw 'Sensitive public route was not blocked.' }
    }
    $cart = Read-Origin '/index.php?rest_route=/wc/store/v1/cart'
    $cartData = $cart.Body | ConvertFrom-Json
    if ($cart.Status -ne 200 -or $cartData.totals.currency_code -ne 'ARS') { throw 'Store API did not respond through the simulated HTTPS origin.' }
    $report.passed=$true
    $report.canonicalHttpsUrls=$true
    $report.loginHttp=403
    $report.sensitiveRoutesBlocked=$true
    $report.noIndexHeader=$true
    $report.storeApiHttp=200
    $report.currency='ARS'
  } finally {
    # No search/replace or persistent site-URL mutation is needed: restore base env.
    Invoke-CheckedDocker ($composeBase + @('up', '-d', '--wait', '--wait-timeout', '180', 'wordpress'))
    $restored=Invoke-WebRequest -UseBasicParsing 'http://localhost:8080/'
    if ($restored.StatusCode -ne 200 -or $restored.Content -match 'h7-origin-check\.invalid') { throw 'Local origin was not restored.' }
    $report.localOriginRestored=$true
    $reportRoot = Join-Path $privateRoot 'h7-1'
    $null = New-Item -ItemType Directory -Force -Path $reportRoot
    [IO.File]::WriteAllText((Join-Path $reportRoot 'https-origin.json'), ($report | ConvertTo-Json -Depth 5) + "`n", $utf8)
  }
  $report | ConvertTo-Json -Depth 5
} finally {
  if ($locked) { $mutex.ReleaseMutex() }
  $mutex.Dispose()
}
