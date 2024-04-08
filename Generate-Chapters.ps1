$docs = Get-Content .\consolidated_genji.json -Encoding utf8
$docs = $docs | ConvertFrom-Json 

$checkboxes = Get-Content .\checkboxes.txt -Encoding utf8
$prevChapter = ""
$prevSection = ""
$prevParagraph = ""
ForEach ($doc in $docs) {
	$outfile = ".\docs\chapters\chapter$($doc.Chapter).md"
	if ($prevChapter -ne $doc.Chapter) {
		Set-Content -Path $outfile -Value $doc.Chapter_Subtitle -Encoding utf8 -Force
		Write-Host $outfile
		Add-Content -Path $outfile -Value $checkboxes -Encoding utf8
		$prevSection = ""
		$prevParagraph = ""
	}
	if ($prevSection -ne $doc.Section) {
		$content = "`r`n## " + $doc.Section_Title + "`r`n"
		Add-Content -Path $outfile -Value $content -Encoding utf8
		#Write-Host $doc.Section_Title
	}
	if ($prevParagraph -ne $doc.Paragraph) {
		$content = "`r`n### " + $doc.Paragraph_Title + "`r`n"
		Add-Content -Path $outfile -Value $content -Encoding utf8
		#Write-Host $doc.Paragraph_Title
	}
	if ($doc.Annotations.count -ne 0) {
		$Text_Annos = ForEach ($Anno in $doc.Annotations) {
		$annocontainer = "
	<p class=`"annotation`">
		<span class=`"annotation_num`">$($Anno.Annotation_Num)</span>
		<span class=`"annotation_title`">$($Anno.Annotation_Title)</span>
		<span class=`"annotation_body`">$($Anno.Annotation_Body)</span>
	</p>"
		Write-Output $annocontainer
		}
		$annodiv = "
  <div class=`"annotations`">$($Text_Annos)
  </div>"
	} else {
		$annodiv = $null
	}
	$texts = "
<div>
  <p class=`"original`">$($doc.Text_Original)</p>
  <p class=`"romanized`">$($doc.Text_Original_Romanized)</p>
  <p class=`"shibuya`">$($doc.Text_Shibuya)</p>
  <p class=`"yosano`">$($doc.Text_Yosano)</p>
  $($annodiv)
</div>"
	Add-Content  -Path $outfile -Value $texts -Encoding utf8
	$prevChapter = $doc.Chapter
	$prevSection = $doc.Section
	$prevParagraph = $doc.Paragraph
	$Text_Annos = $null
}