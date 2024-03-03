$json = Get-Content .\consolidated_genji.json -Encoding utf8 | ConvertFrom-Json

$chapters = $json.Chapter | Get-Unique 

ForEach ($chapter in $chapters) {
	$output = @()
	$outName = ".\json\Chapter_$($chapter | % {"{0:d2}" -f [int]$_}).json"
	$targets = $json | Where-Object {$_.Chapter -eq [string]$chapter} 
	Foreach ($target in $targets) { $output += ($target | ConvertTo-Json) }
	"[" | Set-Content $outName -Encoding utf8
	$output -join ",`r`n" | Add-Content $outName -Encoding utf8
	"]" | Add-Content $outName -Encoding utf8
	Write-Host $outName
}
