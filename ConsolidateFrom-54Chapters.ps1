$jsonFiles = Get-ChildItem .\json\*

$oneJson = @()
ForEach ($file in $jsonFiles) {
	$data = Get-Content $file -Encoding utf8 | ConvertFrom-Json
	$oneJson += $data	
}

$outName = ".\consolidated_genji.json"
$output = @()
ForEach ($item in $oneJson) { $output += ($item | ConvertTo-Json) }
"[" | Set-Content $outName -Encoding utf8
$output -join ",`r`n" | Add-Content $outName -Encoding utf8
"]" | Add-Content -Path $outName -Encoding utf8