<?php
require 'includes/auth.php'; // ensures only logged-in admins can access
require 'includes/conn.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("❌ Invalid member ID.");
}

// Fetch the photo to delete it from the server as well (optional)
$stmt = $pdo->prepare("SELECT photo FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    die("❌ Member not found.");
}

// Optional: delete the photo (except default)
if (!empty($member['photo']) && $member['photo'] !== 'default.jpg') {
    $filePath = 'assets/images/uploads/' . $member['photo'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Delete from DB
$stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
$stmt->execute([$id]);

// Redirect back to the members page
header("Location: index.php?deleted=1");
exit;
