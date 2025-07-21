$titles = Import-Csv .\texts\subtitles.csv

$VolNums = @(36)
ForEach ($volNum in $VolNums) {
	
	$currentVolFiles = Get-ChildItem ".\texts\*_$($volNum).csv" -Recurse
	$outfile         = ".\docs\volumes\volume_$($volNum).md"
	
	$originalFile   = $currentVolFiles.fullname | Where-Object {$_ -like "*original*"}
	$romanizedFile  = $currentVolFiles.fullname | Where-Object {$_ -like "*romanized*"}
	$shibuyaFile    = $currentVolFiles.fullname | Where-Object {$_ -like "*shibuya*"}
	$yosanoFile     = $currentVolFiles.fullname | Where-Object {$_ -like "*yosano*"}
	$seidenFile     = $currentVolFiles.fullname | Where-Object {$_ -like "*seiden*"}
	$annotationFile = $currentVolFiles.fullname | Where-Object {$_ -like "*annotation*"}

	$originalText   = Import-Csv $originalFile -Encoding utf8
	$romanizedText  = Import-Csv $romanizedFile -Encoding utf8
	$shibuyaText    = Import-Csv $shibuyaFile -Encoding utf8
	$yosanoText     = Import-Csv $yosanoFile -Encoding utf8
	try { $seidenText     = Import-Csv $seidenFile -Encoding utf8 } catch { $seidenText = ""}
	$annotationText = Import-Csv $annotationFile -Encoding utf8

	$lineIDs = ForEach ($line in $originalText) {
		[PSCustomObject]@{
			Volume    = $line.Volume
			Chapter   = $line.Chapter
			Paragraph = $line.Paragraph
			Line      = $line.Line
			LineID    = $line.LineID
		}
	}
	$prevVolume    = ""
	$prevSection   = ""
	$prevParagraph = ""
	$tempOut       = @()
	
	ForEach ($lineID in $lineIDs) {
		$Text_Original  = $originalText   | Where-Object {$_.LineID -eq $lineID.LineID}
		$Text_Romanized = $romanizedText  | Where-Object {$_.LineID -eq $lineID.LineID}
		$Text_Shibuya   = $shibuyaText    | Where-Object {$_.LineID -eq $lineID.LineID}
		$Text_Yosano    = $yosanoText     | Where-Object {$_.LineID -eq $lineID.LineID}
		$Text_Seiden    = $seidenText     | Where-Object {$_.LineID -eq $lineID.LineID}
		$lineContainer = [PSCustomObject]@{
			Volume         = $lineID.Volume
			Chapter        = $lineID.Chapter
			Paragraph      = $lineID.Paragraph
			Line           = $lineID.Line
			LineID         = $lineID.LineID
			Text_Original  = $Text_Original.Text
			Text_Romanized = $Text_Romanized.Text
			Text_Shibuya   = $Text_Shibuya.Text
			Text_Yosano    = $Text_Yosano.Text
			Text_Seiden    = $Text_Seiden.Text
		}
		$annoContainer = $annotationText     |
			Where-Object {($_.Volume -eq $lineContainer.Volume) `
				-and ($_.Chapter -eq $lineContainer.Chapter) `
				-and ($_.Paragraph -eq $lineContainer.Paragraph) `
				-and ($_.Line -eq $lineContainer.Line)
			}
		# Insert subtitles
		if ($prevVolume -ne $lineContainer.Volume) {
			$volTitle = ($titles | Where-Object {
				[int]$_.Volume -eq [int]$lineContainer.Volume
				})[0].Volume_Subtitle
			Set-Content -Path $outfile -Value $volTitle -Encoding utf8 -Force
			Write-Host $outfile
			$prevChapter = ""
			$prevParagraph = ""
		}
		if ($prevChapter -ne $lineContainer.Chapter) {
			$chapTitle = ($titles | Where-Object {
				([int]$_.Volume -eq [int]$lineContainer.Volume) -and `
				([int]$_.Chapter -eq [int]$lineContainer.Chapter)
				})[0].Chapter_Title
			$content = "`r`n`r`n## " + $chapTitle + "`r`n"
			Add-Content -Path $outfile -Value $content -Encoding utf8
		}
		if ($prevParagraph -ne $lineContainer.Paragraph) {
			$paraTitle = ($titles | Where-Object {
				([int]$_.Volume -eq [int]$lineContainer.Volume) -and `
				([int]$_.Chapter -eq [int]$lineContainer.Chapter) -and `
				([int]$_.Paragraph -eq [int]$lineContainer.Paragraph)
				})[0].Paragraph_Title
			$content = "`r`n`r`n### " + $paraTitle + "`r`n"
			Add-Content -Path $outfile -Value $content -Encoding utf8
		}
		# Generate Annotations of line
		if ($annoContainer.Count -ne 0) {
			$Text_Annos = ForEach ($Anno in $annoContainer) {
			$annoContent = "
	<p class=`"annotation`">
		<span class=`"annotation_num`">$($Anno.Annotation_Num)</span>
		<span class=`"annotation_title`">$($Anno.Annotation_Title)</span>
		<span class=`"annotation_body`">$($Anno.Annotation_Body)</span>
	</p>"
			Write-Output $annoContent
			}
			$annodiv = "
  <div class=`"annotations`">$($Text_Annos)
  </div>"
		} else {
			$annodiv = $null
		}
		# Generate body texts and consolidate annotations
		$texts = "
<div id=`"$($lineContainer.LineID)`">
  <p class=`"original`">$($lineContainer.Text_Original)</p>
  <p class=`"romanized`">$($lineContainer.Text_Romanized)</p>
  <p class=`"shibuya`">$($lineContainer.Text_Shibuya)</p>
  <p class=`"yosano`">$($lineContainer.Text_Yosano)</p>
  <p class=`"seiden`">$($lineContainer.Text_seiden)</p>
  $($annodiv)
</div>"
		Add-Content  -Path $outfile -Value $texts -Encoding utf8
		
		$prevVolume = $lineContainer.Volume
		$prevChapter = $lineContainer.Chapter
		$prevParagraph = $lineContainer.Paragraph
		$Text_Annos = $null
	}
}