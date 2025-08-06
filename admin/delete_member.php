<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();

if (!isAdmin()) {
    die("Access Denied");
}

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$member_id = intval($_GET['id']);

// Fetch member details for cleanup
$stmt = $pdo->prepare("SELECT family_photo, user_id, spouse_user_id FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch();

if (!$member) {
    die("Member not found");
}

try {
    $pdo->beginTransaction();

    // Delete children
    $pdo->prepare("DELETE FROM children WHERE member_id = ?")->execute([$member_id]);

    // Delete from members
    $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$member_id]);

    // Delete primary user account
    if (!empty($member['user_id'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$member['user_id']]);
    }

    // Delete spouse user account
    if (!empty($member['spouse_user_id'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$member['spouse_user_id']]);
    }

    // Remove family photo from uploads
    $photo_path = __DIR__ . '/assets/images/uploads/' . $member['family_photo'];
    if (!empty($member['family_photo']) && file_exists($photo_path)) {
        unlink($photo_path);
    }

    $pdo->commit();

    header("Location: members.php?msg=deleted");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error deleting member: " . $e->getMessage());
}
