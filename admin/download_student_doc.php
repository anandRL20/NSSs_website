<?php
/**
 * download_student_doc.php
 * 
 * Generates a .docx in PURE PHP — no Node.js, no shell_exec, no external libs.
 * All helpers are static methods on a class so PHP scoping never breaks.
 */
ob_start();
require_once '../config.php';

// ── Auth guard ──────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin'] || !$_SESSION['is_whitelisted']) {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// ── Validate ────────────────────────────────────────────────────
if (!isset($_POST['student_id']) || !is_numeric($_POST['student_id'])) {
    ob_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid student ID']);
    exit();
}

$student_user_id = intval($_POST['student_id']);
$conn = getDBConnection();

// ── Fetch student ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT u.*, s.* FROM users u 
                        LEFT JOIN student_info s ON u.id = s.user_id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $student_user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    ob_clean();
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Student not found']);
    $conn->close();
    exit();
}

// ── Fetch marks ─────────────────────────────────────────────────
$marks_stmt = $conn->prepare("SELECT * FROM marks WHERE student_id = ? ORDER BY semester, subject_name");
$marks_stmt->bind_param("i", $student['id']);
$marks_stmt->execute();
$marks = $marks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// ══════════════════════════════════════════════════════════════════
// DocBuilder class — all helpers are static, no global variables
// ══════════════════════════════════════════════════════════════════
class DocBuilder {

    // Border used on every cell — class constant, accessible anywhere
    const BORDER =
        '<w:top w:val="single" w:sz="4" w:space="0" w:color="B0C4D8"/>'
      . '<w:left w:val="single" w:sz="4" w:space="0" w:color="B0C4D8"/>'
      . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="B0C4D8"/>'
      . '<w:right w:val="single" w:sz="4" w:space="0" w:color="B0C4D8"/>';

    public static function xval($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function fdate($d) {
        return $d ? date('M d, Y', strtotime($d)) : '—';
    }

    // Single table cell
    public static function cell($width, $text, $bold = false, $shade = null, $align = 'left', $color = '1A1A2E') {
        $shd     = $shade ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $shade . '"/>' : '';
        $jc      = ($align !== 'left') ? '<w:jc w:val="' . $align . '"/>' : '';
        $boldTag = $bold ? '<w:b/><w:bCs/>' : '';

        return '<w:tc>'
             . '<w:tcPr>'
             . '<w:tcW w:w="' . $width . '" w:type="dxa"/>'
             . '<w:tcBorders>' . self::BORDER . '</w:tcBorders>'
             . $shd
             . '</w:tcPr>'
             . '<w:p><w:pPr>' . $jc . '</w:pPr>'
             . '<w:r><w:rPr>'
             . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
             . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
             . '<w:color w:val="' . $color . '"/>'
             . $boldTag
             . '</w:rPr>'
             . '<w:t xml:space="preserve">' . self::xval($text) . '</w:t>'
             . '</w:r></w:p></w:tc>';
    }

    // Two-column info row: label (gray) | value (white)
    public static function infoRow($label, $value) {
        return '<w:tr>'
             . self::cell(3120, $label,  true,  'F4F6F8', 'left', '0F4C75')
             . self::cell(6240, $value,  false, 'FFFFFF', 'left', '1A1A2E')
             . '</w:tr>';
    }

    // Section heading paragraph
    public static function sectionHeading($text) {
        return '<w:p>'
             . '<w:pPr><w:spacing w:before="360" w:after="140"/></w:pPr>'
             . '<w:r><w:rPr>'
             . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
             . '<w:b/><w:bCs/><w:sz w:val="26"/><w:szCs w:val="26"/>'
             . '<w:color w:val="0F4C75"/>'
             . '</w:rPr>'
             . '<w:t>' . self::xval($text) . '</w:t>'
             . '</w:r></w:p>';
    }

    // Opening <w:tbl> tag with grid columns
    public static function tableStart($cols) {
        $sum  = array_sum($cols);
        $grid = '';
        foreach ($cols as $w) {
            $grid .= '<w:gridCol w:w="' . $w . '"/>';
        }
        return '<w:tbl>'
             . '<w:tblPr><w:tblW w:w="' . $sum . '" w:type="dxa"/><w:tblLook w:val="04A0"/></w:tblPr>'
             . '<w:tblGrid>' . $grid . '</w:tblGrid>';
    }

    // Build the entire document body XML
    public static function buildBody($student, $marks) {
        $body = '';

        // ── Title ───────────────────────────────────────────────
        $body .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="60"/></w:pPr>'
               . '<w:r><w:rPr>'
               . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
               . '<w:b/><w:bCs/><w:sz w:val="36"/><w:szCs w:val="36"/>'
               . '<w:color w:val="0F4C75"/>'
               . '</w:rPr><w:t>Student Information Report</w:t></w:r></w:p>';

        // ── Subtitle ────────────────────────────────────────────
        $body .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="400"/></w:pPr>'
               . '<w:r><w:rPr>'
               . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
               . '<w:i/><w:iCs/><w:sz w:val="20"/><w:szCs w:val="20"/>'
               . '<w:color w:val="666666"/>'
               . '</w:rPr><w:t>Generated on ' . date('F d, Y') . '</w:t></w:r></w:p>';

        // ── Personal Details ────────────────────────────────────
        $body .= self::sectionHeading('Personal Details');
        $body .= self::tableStart([3120, 6240]);
        $body .= self::infoRow('Full Name',  $student['full_name']  ?? '—');
        $body .= self::infoRow('Email',      $student['email']      ?? '—');
        $body .= self::infoRow('Username',   $student['username']   ?? '—');
        $body .= self::infoRow('Joined',     self::fdate($student['created_at']));
        $body .= '</w:tbl>';

        // ── Academic Details ────────────────────────────────────
        if (!empty($student['roll_number'])) {
            $body .= self::sectionHeading('Academic Details');
            $body .= self::tableStart([3120, 6240]);
            $body .= self::infoRow('Roll Number',   $student['roll_number']   ?? '—');
            $body .= self::infoRow('Course',        $student['course']        ?? '—');
            $body .= self::infoRow('Year',          $student['year']          ?? '—');
            $body .= self::infoRow('Department',    $student['department']    ?? '—');
            $body .= self::infoRow('Phone',         $student['phone']         ?? '—');
            $body .= self::infoRow('Date of Birth', self::fdate($student['date_of_birth']));
            $body .= self::infoRow('Address',       $student['address']       ?? '—');
            $body .= '</w:tbl>';
        }

        // ── Marks Table ─────────────────────────────────────────
        $body .= self::sectionHeading('Academic Performance');

        if (count($marks) > 0) {
            $COL     = [2340, 1870, 1870, 1560, 1720];
            $headers = ['Subject', 'Semester', 'Marks Obtained', 'Max Marks', 'Percentage'];

            $body .= self::tableStart($COL);

            // Header row
            $body .= '<w:tr>';
            foreach ($headers as $i => $h) {
                $body .= self::cell($COL[$i], $h, true, '0F4C75', 'center', 'FFFFFF');
            }
            $body .= '</w:tr>';

            // Data rows
            foreach ($marks as $idx => $m) {
                $pct   = $m['max_marks'] > 0
                       ? number_format(($m['marks_obtained'] / $m['max_marks']) * 100, 2) . '%'
                       : '—';
                $rowBg = ($idx % 2 === 0) ? 'FFFFFF' : 'F4F6F8';

                $body .= '<w:tr>';
                $body .= self::cell($COL[0], $m['subject_name'],           false, $rowBg, 'left',   '1A1A2E');
                $body .= self::cell($COL[1], $m['semester'],               false, $rowBg, 'center', '1A1A2E');
                $body .= self::cell($COL[2], (string)$m['marks_obtained'], false, $rowBg, 'center', '1A1A2E');
                $body .= self::cell($COL[3], (string)$m['max_marks'],      false, $rowBg, 'center', '1A1A2E');
                $body .= self::cell($COL[4], $pct,                         true,  $rowBg, 'center', '0F4C75');
                $body .= '</w:tr>';
            }
            $body .= '</w:tbl>';

        } else {
            $body .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="200"/></w:pPr>'
                   . '<w:r><w:rPr>'
                   . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
                   . '<w:i/><w:iCs/><w:sz w:val="22"/><w:szCs w:val="22"/>'
                   . '<w:color w:val="999999"/>'
                   . '</w:rPr><w:t>No marks have been added yet.</w:t></w:r></w:p>';
        }

        return $body;
    }
}

// ══════════════════════════════════════════════════════════════════
// Generate the body, assemble the ZIP, stream to browser
// ══════════════════════════════════════════════════════════════════

$body = DocBuilder::buildBody($student, $marks);

// ── The 5 XML files that make a .docx ───────────────────────────
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
. '<Default Extension="xml" ContentType="application/xml"/>'
. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
. '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
. '</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
. '</Relationships>';

$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
. '</Relationships>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
. '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
. '<w:docDefaults><w:rPrDefault><w:rPr>'
. '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
. '<w:sz w:val="22"/><w:szCs w:val="22"/>'
. '</w:rPr></w:rPrDefault></w:docDefaults>'
. '</w:styles>';

$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
. '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
. '<w:body>'
. $body
. '<w:sectPr>'
. '<w:pgSz w:w="11906" w:h="16838"/>'
. '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>'
. '</w:sectPr>'
. '</w:body>'
. '</w:document>';

// ── Pack into ZIP ───────────────────────────────────────────────
$tmpDocx = sys_get_temp_dir() . '/student_' . $student_user_id . '_' . time() . '.docx';

$zip = new ZipArchive();
if ($zip->open($tmpDocx, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ZipArchive failed. Check /tmp write permissions.']);
    exit();
}

$zip->addFromString('[Content_Types].xml',          $contentTypes);
$zip->addFromString('_rels/.rels',                  $rels);
$zip->addFromString('word/document.xml',            $document);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->addFromString('word/styles.xml',              $styles);
$zip->close();

// ── Stream to browser ───────────────────────────────────────────
ob_clean();

$safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $student['full_name'] ?? 'Student');
$filename = $safeName . '_Report.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpDocx));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmpDocx);
unlink($tmpDocx);
exit;
?>