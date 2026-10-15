[CmdletBinding()]
param(
  [ValidatePattern('^[a-z][a-z0-9-]{0,29}$')][string]$Instance = 'local',
  [ValidatePattern('^/[^\r\n]*$')][string]$ShowcasePath = '/?commerce-base-showcase=1'
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Run H3 verification only from the canonical repository.' }
& (Join-Path $repoRoot 'scripts/h3.ps1') verify -Instance $Instance
$privateRoot = Join-Path $repoRoot ".local/$Instance/h3"
$state = Get-Content -Raw -LiteralPath (Join-Path $privateRoot 'after.json') | ConvertFrom-Json
$baseUrl = ([Uri]$state.commerce_state.home).GetLeftPart([UriPartial]::Authority)
if (([Uri]$baseUrl).Host -ne 'localhost' -or ([Uri]$baseUrl).Scheme -ne 'http') { throw 'H3 smoke tests require the existing loopback HTTP instance; do not reopen a tunnel.' }
$urls = [ordered]@{
  frontend = $state.commerce_state.home
  admin_login = $baseUrl + '/wp-login.php'
  showcase = $baseUrl + $ShowcasePath
}
foreach ($name in @('shop', 'cart', 'checkout', 'myaccount')) { $urls[$name] = $state.commerce_state.pages.$name.url }
$checks = New-Object 'System.Collections.Generic.List[object]'
foreach ($name in $urls.Keys) {
  $url = [string]$urls[$name]
  if (-not $url -or ([Uri]$url).GetLeftPart([UriPartial]::Authority) -cne $baseUrl) { throw "Unexpected local URL for $name." }
  $response = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 45 -MaximumRedirection 5
  if ([int]$response.StatusCode -ne 200) { throw "$name did not return HTTP 200." }
  if ($response.Content -match '(?i)(Fatal error:|Parse error:|There has been a critical error|Ha habido un error cr.tico)') { throw "$name contains a visible PHP error." }
  if ($name -eq 'showcase' -and $response.Content -notmatch 'commerce-base') { throw 'Showcase did not load Commerce Base markup/assets.' }
  if ($name -eq 'showcase' -and ([string]$response.Headers['X-Robots-Tag']) -notmatch 'noindex') { throw 'Showcase must send an explicit noindex response header.' }
  $checks.Add([ordered]@{ name = $name; httpStatus = [int]$response.StatusCode; url = $url })
  Write-Host "PASS: $name HTTP 200"
}
$report = [ordered]@{
  verifiedAtUtc = [DateTime]::UtcNow.ToString('o')
  instance = $Instance
  result = 'passed'
  scope = 'Static Block Theme validation, preserved commerce baseline, and local HTTP smoke. Browser interaction QA is additional.'
  checks = @($checks.ToArray())
}
[IO.File]::WriteAllText((Join-Path $privateRoot 'http.json'), ($report | ConvertTo-Json -Depth 8) + "`n", (New-Object Text.UTF8Encoding($false)))
Write-Host "H3 local HTTP checks passed. Private report: .local/$Instance/h3/http.json"
