<?php
require_once __DIR__ . "/functions.php";
require_login();

function pdf_escape(string $text): string
{
    $text = preg_replace("/[^\x20-\x7E]/", "?", $text);
    return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);
}

function pdf_line(string $text, int $x, int $y, int $size = 10): string
{
    return "BT /F1 {$size} Tf {$x} {$y} Td (" . pdf_escape($text) . ") Tj ET\n";
}

function build_pdf(string $content): string
{
    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $num = $i + 1;
        $pdf .= "{$num} 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xref}\n%%EOF";
    return $pdf;
}

$filters = report_filters();
$rows = luggage_report_rows($filters);
$stored = 0;
$collected = 0;
foreach ($rows as $row) {
    $row["status"] === "Collected" ? $collected++ : $stored++;
}

$content = "";
$content .= pdf_line("Luggage Storage Report", 50, 800, 16);
$content .= pdf_line("Generated: " . date("Y-m-d H:i:s"), 50, 780, 10);
$content .= pdf_line("Rows: " . count($rows) . " | Stored: $stored | Collected: $collected", 50, 762, 10);
$content .= pdf_line("Tag        Guest                 Room   Type          Pay      Status      Check In", 50, 730, 9);
$content .= pdf_line(str_repeat("-", 92), 50, 716, 9);

$y = 700;
$shown = 0;
foreach ($rows as $row) {
    if ($shown >= 38) {
        break;
    }
    $line = sprintf(
        "%-10s %-21s %-6s %-13s %-8s %-11s %-16s",
        substr((string)$row["tag_code"], 0, 10),
        substr((string)$row["guest_name"], 0, 21),
        substr((string)$row["room_no"], 0, 6),
        substr((string)$row["luggage_type"], 0, 13),
        substr((string)$row["payment_status"], 0, 8),
        substr((string)$row["status"], 0, 11),
        substr((string)$row["checkin_date"], 0, 16)
    );
    $content .= pdf_line($line, 50, $y, 9);
    $y -= 16;
    $shown++;
}
if (count($rows) > $shown) {
    $content .= pdf_line("Showing first $shown rows. Use Excel export for the full data set.", 50, $y - 8, 9);
}

$pdf = build_pdf($content);
$filename = "luggage_report_" . date("Ymd_His") . ".pdf";
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=$filename");
header("Content-Length: " . strlen($pdf));
echo $pdf;
