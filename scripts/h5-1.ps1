[CmdletBinding()]
param(
  [Parameter(Position = 0, Mandatory = $true)]
  [ValidateSet('sync', 'seed', 'inspect', 'verify')][string]$Action
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if ($repoRoot.TrimEnd('\') -ine 'C:\Codigo\demo-woocommerce') { throw 'Use the canonical local workspace.' }
$privateRoot = Join-Path $repoRoot '.local/local/h5-1'
$null = New-Item -ItemType Directory -Force -Path $privateRoot
if ($Action -eq 'sync') {
  & "$PSScriptRoot/h5.ps1" sync
  $pluginRoot = Join-Path $repoRoot 'commerce-lookbook'
  if (@(Get-ChildItem -LiteralPath $pluginRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }).Count) { throw 'Plugin source must not contain links or junctions.' }
  $archive = Join-Path $privateRoot 'commerce-lookbook.tar.gz'
  & tar.exe -czf $archive -C $repoRoot commerce-lookbook
  if ($LASTEXITCODE -ne 0) { throw 'Plugin archive failed.' }
  $entries = @(& tar.exe -tzf $archive)
  if ($LASTEXITCODE -ne 0 -or @($entries | Where-Object { $_ -notmatch '^commerce-lookbook(?:/|$)' -or $_ -match '(^|/)\.\.(/|$)' }).Count) { throw 'Invalid plugin archive paths.' }
  & docker cp $archive 'demo-woocommerce-local-wordpress-1:/tmp/commerce-lookbook-h5-1.tar.gz'
  if ($LASTEXITCODE -ne 0) { throw 'Plugin copy failed.' }
  & docker exec demo-woocommerce-local-wordpress-1 tar -xzf /tmp/commerce-lookbook-h5-1.tar.gz -C /var/www/html/wp-content/plugins --no-same-owner --no-same-permissions
  if ($LASTEXITCODE -ne 0) { throw 'Plugin extraction failed.' }
  & docker exec demo-woocommerce-local-wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/plugins/commerce-lookbook
  if ($LASTEXITCODE -ne 0) { throw 'Plugin ownership failed.' }
  & "$PSScriptRoot/dev.ps1" wp -Instance local -WpArgs @('plugin', 'activate', 'commerce-lookbook', '--quiet')
} else {
  if ($Action -eq 'seed') {
    $backupRoot = Join-Path $repoRoot '.local/local/backups/h5-1-before-lookbook'
    $manifest = Get-Content -LiteralPath (Join-Path $backupRoot 'manifest.json') -Raw | ConvertFrom-Json
    if ($manifest.project -cne 'demo-woocommerce-local' -or $manifest.backupId -cne 'h5-1-before-lookbook') { throw 'Expected preventive local backup.' }
    foreach ($name in @('database.sql', 'wordpress.tar.gz')) {
      $entry = @($manifest.files | Where-Object { $_.name -ceq $name })
      if ($entry.Count -ne 1 -or (Get-FileHash -LiteralPath (Join-Path $backupRoot $name) -Algorithm SHA256).Hash.ToLowerInvariant() -cne $entry[0].sha256) { throw 'Backup integrity failed.' }
    }
  }
  $raw = @(& "$PSScriptRoot/dev.ps1" wp -Instance local -WpArgs @('eval-file', '/opt/local-tools/h5-1-lookbook.php', $Action, '--quiet')) -join "`n"
  [IO.File]::WriteAllText((Join-Path $privateRoot "$Action.json"), $raw + "`n", (New-Object Text.UTF8Encoding($false)))
  $report = $raw | ConvertFrom-Json
  if ($report.result -ne 'passed') { throw 'H5.1 verification failed.' }
  $raw
}
