<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

// Fetch all members
$stmt = $pdo->query("
    SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
");
$members = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Photo Directory');

// Header Row
$sheet->setCellValue('A1', 'Name');
$sheet->setCellValue('B1', 'Phone');
$sheet->setCellValue('C1', 'Email');
$sheet->setCellValue('D1', 'Spouse Name');
$sheet->setCellValue('E1', 'Spouse Phone');
$sheet->setCellValue('F1', 'Spouse Email');
$sheet->setCellValue('G1', 'Children');

$row = 2;
foreach ($members as $member) {
    $child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
    $child_stmt->execute([$member['id']]);
    $children = implode(', ', array_column($child_stmt->fetchAll(), 'child_name'));

    $sheet->setCellValue('A' . $row, $member['primary_name']);
    $sheet->setCellValue('B' . $row, $member['primary_phone']);
    $sheet->setCellValue('C' . $row, $member['primary_email']);
    $sheet->setCellValue('D' . $row, $member['spouse_name']);
    $sheet->setCellValue('E' . $row, $member['spouse_phone']);
    $sheet->setCellValue('F' . $row, $member['spouse_email']);
    $sheet->setCellValue('G' . $row, $children);
    $row++;
}

// Download Excel File
$filename = 'photo_directory.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
