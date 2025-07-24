<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;

checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

// Fetch members
$stmt = $pdo->query("
    SELECT m.*, u.name AS primary_name, u.email AS primary_email, u.phone AS primary_phone
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
");
$members = $stmt->fetchAll();

// Build HTML for PDF
$html = '<h2>Photo Directory</h2><table border="1" cellpadding="5" cellspacing="0" width="100%">
<tr><th>Name</th><th>Phone</th><th>Email</th><th>Spouse</th><th>Children</th></tr>';

foreach ($members as $member) {
    $child_stmt = $pdo->prepare("SELECT child_name FROM children WHERE member_id = ?");
    $child_stmt->execute([$member['id']]);
    $children = implode(', ', array_column($child_stmt->fetchAll(), 'child_name'));

    $html .= "<tr>
        <td>{$member['primary_name']}</td>
        <td>{$member['primary_phone']}</td>
        <td>{$member['primary_email']}</td>
        <td>{$member['spouse_name']}</td>
        <td>{$children}</td>
    </tr>";
}

$html .= '</table>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("photo_directory.pdf", ["Attachment" => true]);
?>
