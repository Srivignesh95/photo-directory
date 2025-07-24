<?php
require_once __DIR__ . '/includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: directory.php");
} else {
    header("Location: auth/login.php");
}
exit;
?>
