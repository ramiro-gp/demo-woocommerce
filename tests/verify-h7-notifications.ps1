#requires -Version 7.0
[CmdletBinding()]
param(
  [Parameter(Mandatory=$true)][ValidatePattern('^TESTUSER[0-9]+$')][string]$ExpectedTestSeller,
  [Parameter(Mandatory=$true)][int]$OrderId,
  [Parameter(Mandatory=$true)][ValidatePattern('^[0-9]+$')][string]$PaymentId
)
$ErrorActionPreference='Stop'
$repoRoot=Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot
$privateRoot=Join-Path $repoRoot '.local/local/h7-1/concurrency'
$null=New-Item -ItemType Directory -Force -Path $privateRoot
$state=Get-Content .local/local/h7-1/https/state.json -Raw|ConvertFrom-Json
if($state.status -ne 'running' -or $state.publicUrl -notmatch '^https://[a-z0-9-]+\.trycloudflare\.com$'){throw 'Verified H7 HTTPS required.'}
$compose=@('compose','--project-name','demo-woocommerce-local','--env-file','.local/local/.env','--env-file','.local/local/h7-1/https/public.env','-f','compose.yaml','-f','compose.h7-https.yaml')
function Qa([string]$Action) {
  $lines=[Collections.Generic.List[string]]::new()
  try { & "$repoRoot/scripts/dev.ps1" wp -WpArgs @('eval-file','/opt/local-tools/h7-concurrency-qa.php',$Action,'--quiet')|ForEach-Object{$lines.Add([string]$_)} } catch { }
  return (($lines -join "`n")|ConvertFrom-Json)
}
$preflightRaw=@(& docker @compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-seller-check.php verify-linked-test-seller $ExpectedTestSeller --quiet) -join "`n"
if($LASTEXITCODE -ne 0 -or !($preflightRaw|ConvertFrom-Json).safe_to_start_test_checkout){throw 'Expected fictional seller required.'}
$resourceRaw=@(& docker @compose run --rm -T --no-deps wpcli eval-file /opt/local-tools/h7-mp-session.php payment $OrderId $PaymentId --quiet) -join "`n"
if($LASTEXITCODE -ne 0){throw 'Authenticated owned payment read failed.'}
$resource=$resourceRaw|ConvertFrom-Json
$core=$resource.core_lookup.merchant_order
if(!$resource.authenticated_payment.test_seller_matches -or !$core.found -or !$core.reference_matches -or $core.resource_id -notmatch '^M-[0-9]+$'){throw 'Owned Core resource required.'}
[IO.File]::WriteAllText((Join-Path $privateRoot 'authenticated-resource.json'),$resourceRaw)
$before=Qa 'history-snapshot'
if($before.result -ne 'passed'){throw 'Snapshot failed.'}
$codes=@()
foreach($delivery in 1..2){
  if($delivery -eq 2){Start-Sleep -Milliseconds 1100}
  $response=Invoke-WebRequest -Uri ($state.publicUrl+'/?wc-api=WC_WooMercadoPago_Basic_Gateway&source_news=ipn') -Method Post -ContentType 'application/json' -Headers @{'X-H7-QA-Replay'='reconstructed'} -Body (@{notification_id=$core.resource_id}|ConvertTo-Json -Compress)
  $codes+=[int]$response.StatusCode
}
$after=Qa 'history-assert'
$financialPreserved=$after.physical_stock_preserved -and @($codes|Where-Object{$_ -ne 200}).Count -eq 0
$dateOnly=$false
if(!$after.full_snapshots_and_physical_stock_preserved){foreach($changedOrder in $after.changed_orders_and_fields.psobject.Properties){
  if([int]$changedOrder.Name -ne $OrderId -or @($changedOrder.Value.fields).Count -ne 1 -or $changedOrder.Value.fields[0] -ne 'meta_data'){$financialPreserved=$false;continue}
  if(@($changedOrder.Value.metadata.psobject.Properties).Count -eq 0){$financialPreserved=$false}
  foreach($meta in $changedOrder.Value.metadata.psobject.Properties){if(!$meta.Value.date_only){$financialPreserved=$false}else{$dateOnly=$true}}
}}
$report=@{result=$(if($financialPreserved){if($dateOnly){'passed_with_native_limitations'}else{'passed'}}else{'failed'});test_kind='Identical reconstructed HTTP requests using a real fictitious Core resource and native authenticated SDK; NOT original external replay';http_codes=$codes;order_id=$OrderId;historical_orders_checked=$after.historical_external_orders;financial_fields_and_physical_stock_preserved=$financialPreserved;full_metadata_idempotent=$after.full_snapshots_and_physical_stock_preserved;native_fallback_date_changes=$dateOnly;core_payment_date_presence=$core.payment_date_presence;diff=$after.changed_orders_and_fields}
[IO.File]::WriteAllText((Join-Path $privateRoot 'authenticated-reconstruction-report.json'),($report|ConvertTo-Json -Depth 12))
$report|ConvertTo-Json -Depth 12
if(!$financialPreserved){throw 'Unexpected financial/order change on reconstructed notification.'}
