<?php
// Detect environment
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    // Local Config
    define('SITE_NAME', 'Photo Directory (Local)');
    define('BASE_URL', 'http://localhost/photo-directory/');
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'photo_directory');
} else {
    // Live Config
    define('SITE_NAME', 'Photo Directory');
    define('BASE_URL', 'http://svkzone.com/photo_directory/');
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u522900848_directory');  
    define('DB_PASS', 'St.Church@321');
    define('DB_NAME', 'u522900848_directory');
}
?>
