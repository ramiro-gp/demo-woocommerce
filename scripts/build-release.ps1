#requires -Version 5.1
[CmdletBinding()]
param([ValidatePattern('^[0-9]+\.[0-9]+\.[0-9]+$')][string]$ReleaseVersion = '1.0.0')
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Set-Location -LiteralPath $root
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$output = Join-Path $root ".local/h11-release/$ReleaseVersion"
New-Item -ItemType Directory -Path $output -Force | Out-Null
$packages = @()
foreach ($slug in @('commerce-base','commerce-core','commerce-communications','commerce-order-integrity','commerce-account-navigation','commerce-lookbook')) {
    $header = if ($slug -eq 'commerce-base') { "$slug/style.css" } else { "$slug/$slug.php" }
    $headerText = (git show "HEAD:$header") -join "`n"
    if ($LASTEXITCODE -ne 0) { throw 'Cannot read committed package header.' }
    if (@(git diff HEAD -- $slug).Count) { throw "Commit package changes before building: $slug" }
    $match = [regex]::Match($headerText,'(?m)^\s*(?:\*\s*)?Version:\s*([0-9.]+)')
    if (-not $match.Success) { throw "Missing package version: $slug" }
    $version = $match.Groups[1].Value
    $files = @(git ls-files -- $slug | Where-Object {
        $_ -ne 'commerce-base/styles/origen-casa.json' -and
        $_ -notmatch '^commerce-base/(?:inc/showcase\.php|assets/images/editorial-.*\.webp|patterns/editorial-(?:hero|story)\.php)$'
    } | Sort-Object)
    if ($LASTEXITCODE -ne 0 -or -not $files.Count) { throw 'Cannot enumerate tracked sources.' }
    $target = Join-Path $output "$slug-$version.zip"
    $stream = [IO.File]::Open($target,[IO.FileMode]::Create)
    $zip = [IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create)
    $inventory = @()
    try {
        foreach ($file in $files) {
            if ($file -notmatch '^[a-z0-9-]+/[A-Za-z0-9_./-]+\.(php|css|js|json|html|txt|md|svg|woff2)$' -or $file -match '(^|/)(\.\.?|uploads|backups|logs)(/|$)') { throw "Unsupported release path: $file" }
            if (Get-Item -LiteralPath $file -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) { throw 'Source links are forbidden.' }
            # git show reads canonical Git bytes: no CRLF/platform-dependent packaging.
            $gitProcess = [Diagnostics.ProcessStartInfo]::new('git', "show HEAD:$file")
            $gitProcess.UseShellExecute = $false
            $gitProcess.RedirectStandardOutput = $true
            $gitProcess.RedirectStandardError = $true
            $process = [Diagnostics.Process]::Start($gitProcess)
            $buffer = [IO.MemoryStream]::new()
            $process.StandardOutput.BaseStream.CopyTo($buffer)
            $process.WaitForExit()
            if ($process.ExitCode -ne 0) { throw "Cannot read committed source: $file" }
            $bytes = $buffer.ToArray()
            $entry = $zip.CreateEntry($file,[IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = [DateTimeOffset]::new(2026,1,1,0,0,0,[TimeSpan]::Zero)
            $entryStream = $entry.Open()
            try { $entryStream.Write($bytes,0,$bytes.Length) } finally { $entryStream.Dispose() }
            $sha = [Security.Cryptography.SHA256]::Create()
            try { $hash=([BitConverter]::ToString($sha.ComputeHash($bytes))).Replace('-','').ToLowerInvariant() } finally { $sha.Dispose() }
            $inventory += @{ path=$file; sha256=$hash }
        }
    } finally { $zip.Dispose(); $stream.Dispose() }
    $packages += @{ slug=$slug; version=$version; zip=(Split-Path -Leaf $target); sha256=(Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant(); files=$inventory }
}
$manifest = [ordered]@{ distribution='commerce-clean'; version=$ReleaseVersion; sourceCommit=(git rev-parse HEAD).Trim(); demoContent=$false; credentials=$false; packages=$packages }
$manifest | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $output 'manifest.json') -Encoding UTF8
Write-Output "Release ${ReleaseVersion}: six installable ZIPs + SHA256 manifest in .local/h11-release/$ReleaseVersion"
