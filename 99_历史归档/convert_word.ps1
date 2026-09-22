
$ErrorActionPreference = "SilentlyContinue"
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0

try {
    $doc = $word.Documents.Open("D:\研究生文件\研三\2026年9月\毕业论文重构版\99_历史归档\毕业论文-何天元.doc")
    $doc.Activate()
    $doc.SaveAs("D:\研究生文件\研三\2026年9月\毕业论文重构版\99_历史归档\毕业论文-何天元.docx", 16)
    $doc.Close()
    Write-Host "SUCCESS"
} catch {
    Write-Host "ERROR: $_"
} finally {
    $word.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
}
