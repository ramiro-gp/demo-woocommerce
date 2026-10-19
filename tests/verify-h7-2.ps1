#requires -Version 7.0
[CmdletBinding()]
param([switch]$MailOnly)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical workspace.' }
Set-Location $repoRoot
$privateRoot = Join-Path $repoRoot '.local/local/h7-2'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
$utf8 = [Text.UTF8Encoding]::new($false)
$mailpit = 'http://127.0.0.1:8025'
$mailpitUi = 'http://localhost:8025'
function Read-MailList {
  $messages = [Collections.Generic.List[object]]::new()
  $start = 0
  do {
    $page = Invoke-RestMethod -Uri "$mailpit/api/v1/messages?start=$start&limit=500"
    foreach ($message in $page.messages) { $messages.Add($message) }
    $start += @($page.messages).Count
  } while (@($page.messages).Count -gt 0 -and $start -lt $page.total)
  return $messages.ToArray()
}
function Normalize-Text([string]$Value) {
  return ([Net.WebUtility]::HtmlDecode(($Value -replace '<[^>]+>', ' ')) -replace '\s+', ' ').Trim()
}
function Header-Value($Headers, [string]$Name) {
  $property = $Headers.PSObject.Properties | Where-Object Name -ieq $Name | Select-Object -First 1
  if ($null -eq $property) { return '' }
  return (@($property.Value) -join '').Trim()
}
$before = [Collections.Generic.HashSet[string]]::new()
if ($MailOnly) {
  $report = Get-Content -LiteralPath (Join-Path $privateRoot 'functional-report.json') -Raw | ConvertFrom-Json
  if ($report.result -ne 'passed' -or $report.run -notmatch '^[a-f0-9]{32}$') { throw 'MailOnly requires an existing successful owned H7.2 report.' }
} else {
  foreach ($message in @(Read-MailList)) { $null = $before.Add($message.ID) }
  $lines = [Collections.Generic.List[string]]::new()
  $commandFailed = $false
  try {
    & "$repoRoot/scripts/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h7-2-qa.php', 'verify', '--quiet') | ForEach-Object { $lines.Add([string]$_) }
  } catch { $commandFailed = $true }
  $raw = $lines -join "`n"
  try { $report = $raw | ConvertFrom-Json } catch { throw 'H7.2 native QA failed without sanitized JSON; no raw exception details printed.' }
  [IO.File]::WriteAllText((Join-Path $privateRoot 'functional-report.json'), $raw + "`n", $utf8)
  if ($commandFailed -or $LASTEXITCODE -ne 0 -or $report.result -ne 'passed') { throw 'H7.2 native QA failed; see private functional-report.json.' }
}
$runRoot = Join-Path $privateRoot ('mail/' + $report.run)
$null = New-Item -ItemType Directory -Force -Path $runRoot
$captured = [Collections.Generic.List[object]]::new()
$checks = [ordered]@{}
$expectedSubjects = [Collections.Generic.HashSet[string]]::new()
foreach ($expected in $report.emails) { $null = $expectedSubjects.Add($expected.subject) }
function Assert-Mail([string]$Name, [bool]$Condition) { $checks[$Name] = $Condition }
foreach ($message in @(Read-MailList)) {
  if ($before.Contains($message.ID)) { continue }
  if (!$expectedSubjects.Contains($message.Subject)) { continue }
  $headers = Invoke-RestMethod -Uri "$mailpit/api/v1/message/$($message.ID)/headers"
  if ((Header-Value $headers 'X-Commerce-QA-Run') -ne $report.run) { continue }
  $detail = Invoke-RestMethod -Uri "$mailpit/api/v1/message/$($message.ID)"
  $stage = Header-Value $headers 'X-Commerce-QA-Stage'
  $emailId = Header-Value $headers 'X-Commerce-QA-Email'
  $orderId = [int](Header-Value $headers 'X-Commerce-QA-Order')
  if ($stage -notmatch '^[a-z0-9_]+$' -or $emailId -notmatch '^[a-z0-9_]+$') { throw 'Unexpected QA header syntax; do not create artifact paths.' }
  $prefix = "$stage--$emailId--$orderId"
  $matching = @($report.emails | Where-Object { $_.stage -eq $stage -and $_.email_id -eq $emailId -and $_.order_id -eq $orderId })
  Assert-Mail ($prefix + '_one_expected_event') ($matching.Count -eq 1)
  if ($matching.Count -ne 1) { continue }
  $expected = $matching[0]
  $recipients = @($detail.To) + @($detail.Cc) + @($detail.Bcc)
  $recipients = @($recipients | Where-Object { $_ })
  $to = @($recipients | ForEach-Object { $_.Address.ToLowerInvariant() })
  $expectedTo = @($expected.recipient -split ',' | ForEach-Object { $_.Trim().ToLowerInvariant() } | Sort-Object)
  Assert-Mail ($prefix + '_controlled_recipients') ($to.Count -gt 0 -and @($to | Where-Object { $_ -notmatch '^[^@]+@example\.test$' }).Count -eq 0)
  Assert-Mail ($prefix + '_native_recipient') ((@($to | Sort-Object) -join ',') -eq ($expectedTo -join ','))
  Assert-Mail ($prefix + '_subject') ($detail.Subject -eq $expected.subject)
  Assert-Mail ($prefix + '_sender') ($detail.From.Address -eq $expected.from_address -and $detail.From.Name -eq $expected.from_name)
  Assert-Mail ($prefix + '_native_reply_to') ((@($detail.ReplyTo | ForEach-Object Address) -join ',') -eq $expected.reply_to)
  Assert-Mail ($prefix + '_multipart') ((Header-Value $headers 'Content-Type') -match '^multipart/alternative' -and ![string]::IsNullOrWhiteSpace($detail.HTML) -and ![string]::IsNullOrWhiteSpace($detail.Text))
  $htmlText = Normalize-Text $detail.HTML
  $plainText = Normalize-Text $detail.Text
  Assert-Mail ($prefix + '_native_order_number') ($htmlText.Contains([string]$orderId) -and $plainText.Contains([string]$orderId))
  $total = Normalize-Text $expected.total_text
  $shipping = Normalize-Text $expected.shipping_text
  Assert-Mail ($prefix + '_total') ($htmlText.Contains($total) -and $plainText.Contains($total))
  Assert-Mail ($prefix + '_shipping') ($htmlText.Contains($shipping) -and $plainText.Contains($shipping))
  $itemsCorrect = $true
  $quantitiesCorrect = $true
  $quantityCells = [regex]::Matches($detail.HTML, '(?is)<td\b[^>]*>\s*(?:&times;|×)(?<value>.*?)</td>')
  if ($quantityCells.Count -ne @($expected.items).Count) { $quantitiesCorrect = $false }
  $itemIndex = 0
  foreach ($item in $expected.items) {
    foreach ($text in @($htmlText, $plainText)) {
      if (!$text.Contains((Normalize-Text $item.name))) { $itemsCorrect = $false }
      foreach ($variant in @($item.variant)) { if (!$text.Contains((Normalize-Text $variant))) { $itemsCorrect = $false } }
    }
    if ($itemIndex -lt $quantityCells.Count) {
      $quantityText = Normalize-Text $quantityCells[$itemIndex].Groups['value'].Value
      if ($quantityText -notmatch ('(^|\D)' + $item.quantity + '(\D|$)')) { $quantitiesCorrect = $false }
    }
    $plainQuantity = [regex]::Escape((Normalize-Text $item.name)) + '\s+×\s+' + $item.quantity + '(\D|$)'
    if ($plainText -notmatch $plainQuantity) { $quantitiesCorrect = $false }
    $itemIndex++
  }
  Assert-Mail ($prefix + '_products_variants') $itemsCorrect
  Assert-Mail ($prefix + '_quantities_html_plain') $quantitiesCorrect
  Assert-Mail ($prefix + '_no_web_dependencies') ($detail.HTML -notmatch '<script\b|<link\b|@import|fonts\.googleapis\.com|tailwind')
  $images = [regex]::Matches($detail.HTML, '<img\b[^>]*>')
  Assert-Mail ($prefix + '_image_alt') (@($images | Where-Object { $_.Value -notmatch '\balt\s*=' }).Count -eq 0)
  if ($report.demo_logo_empty) { Assert-Mail ($prefix + '_text_first_demo_no_images') ($images.Count -eq 0) }
  Assert-Mail ($prefix + '_plain_footer_line_break') ($detail.Text -match ('(?m)^[ \t]*' + [regex]::Escape($report.site_name) + '[ \t]*\r?$') -and $detail.Text -match '(?m)^[ \t]*Tienda de demostración\. No realizar transferencias reales\.[ \t]*\r?$')
  Assert-Mail ($prefix + '_no_sensitive_technical_copy') ($htmlText -notmatch 'APP_USR-|TEST-[A-Za-z0-9]{15}|access_token|client_secret|_Mercado_Pago|Contraseña\s*[:=]')
  if ($emailId -eq 'customer_on_hold_order') {
    Assert-Mail ($prefix + '_pending_transfer_copy') ($htmlText -match 'pendiente' -and $plainText -match 'pendiente' -and $htmlText -match 'No realizar transferencias reales' -and $plainText -match 'No realizar transferencias reales')
    Assert-Mail ($prefix + '_no_false_payment_confirmation') ($htmlText -notmatch 'recibimos tu pago|pago confirmado|transferencia recibida' -and $plainText -notmatch 'recibimos tu pago|pago confirmado|transferencia recibida')
  }
  if ($emailId -eq 'customer_processing_order') { Assert-Mail ($prefix + '_preparation_copy') ($htmlText -match 'prepar|proces|confirm' -and $plainText -match 'prepar|proces|confirm') }
  if ($emailId -eq 'customer_completed_order') { Assert-Mail ($prefix + '_no_invented_delivery') ($detail.Subject -notmatch 'en camino|entregado|despachado' -and $htmlText -notmatch 'fue entregado|entregado al domicilio|ya está en camino') }
  if ($emailId -in @('customer_refunded_order', 'customer_partially_refunded_order')) { Assert-Mail ($prefix + '_manual_refund_context') ($htmlText -match 'manual|registr' -and $plainText -match 'manual|registr') }
  [IO.File]::WriteAllText((Join-Path $runRoot ($prefix + '.html')), [string]$detail.HTML, $utf8)
  [IO.File]::WriteAllText((Join-Path $runRoot ($prefix + '.txt')), [string]$detail.Text, $utf8)
  $captured.Add([ordered]@{stage=$stage;email_id=$emailId;order_id=$orderId;subject=$detail.Subject;recipients=$to;mailpit_id=$message.ID;mailpit_url="$mailpitUi/view/$($message.ID)";html=($prefix+'.html');plain=($prefix+'.txt')})
}
Assert-Mail 'captured_count_matches_native_sends' ($captured.Count -eq @($report.emails).Count)
foreach ($expected in $report.emails) {
  $same = @($captured | Where-Object { $_.stage -eq $expected.stage -and $_.email_id -eq $expected.email_id -and $_.order_id -eq $expected.order_id })
  Assert-Mail ("$($expected.stage)--$($expected.email_id)--$($expected.order_id)_captured_once") ($same.Count -eq 1)
}
$mailReport = [ordered]@{result=$(if(@($checks.Values | Where-Object { !$_ }).Count){'failed'}else{'passed'});run=$report.run;checks=$checks;captured=$captured.ToArray();expected_count=@($report.emails).Count;captured_count=$captured.Count;private_artifacts=$runRoot;historical_mail_deleted=$false}
[IO.File]::WriteAllText((Join-Path $privateRoot 'mailpit-report.json'), ($mailReport | ConvertTo-Json -Depth 15) + "`n", $utf8)
$linkLines = & "$repoRoot/scripts/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h7-2-qa.php', 'links', $report.run, '--quiet')
if ($LASTEXITCODE -ne 0) { throw 'Could not generate private links for owned QA orders.' }
[IO.File]::WriteAllText((Join-Path $privateRoot 'review-links.json'), ($linkLines -join "`n") + "`n", $utf8)
if ($mailReport.result -ne 'passed') { throw 'H7.2 Mailpit QA failed; see private mailpit-report.json.' }
if ($MailOnly) {
  Write-Output "H7.2 MailOnly: native report reused; $(@($report.orders).Count) existing synthetic orders preserved."
} else {
  Write-Output "H7.2 native orders: $(@($report.checks.PSObject.Properties).Count) checks passed; $(@($report.orders).Count) new synthetic orders."
}
Write-Output "H7.2 Mailpit: $($captured.Count) captured emails; $($checks.Count) checks passed."
Write-Output 'Private evidence: .local/local/h7-2/{functional-report,mailpit-report,review-links}.json and mail/.'
