<?php
$host = 'localhost';
$db   = 'photo_directory';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
