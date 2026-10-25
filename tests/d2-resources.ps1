param([int]$Samples=20)
$ErrorActionPreference='Stop'
$rows=@()
for($i=0;$i -lt $Samples;$i++){
  $values=& docker stats --no-stream --format '{{json .}}' commerce-d2-bulk-web-1 commerce-d2-bulk-db-1 commerce-d2-bulk-mail-1
  if($LASTEXITCODE -ne 0){throw 'Docker sample failed.'}
  $rows+=@{utc=[DateTime]::UtcNow.ToString('o');containers=@($values|ForEach-Object{$_|ConvertFrom-Json}|Select-Object Name,CPUPerc,MemUsage,BlockIO,PIDs)}
  Start-Sleep -Seconds 2
}
@{scope='Local Docker samples during D2 error probes; not Hostinger capacity or complete import peaks';samples=$rows}|ConvertTo-Json -Depth 8|Set-Content docs/evidence/d2/resources.json
