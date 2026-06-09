<?php
// session_bootstrap.php

// Set a consistent session name and path BEFORE starting the session
$lifetime = 8 * 60 * 60; // 8 hours
$cookie_path = '/';      // share cookie across all folders
$session_name = 'EYSISESSION';

if (session_status() === PHP_SESSION_NONE) {
    $path = $_SERVER['DOCUMENT_ROOT'] . "/sessions";
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
    chmod($path, 0777);

    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);

    // Ensure the session name is set BEFORE session_start()
    session_name($session_name);
    session_save_path($path);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $cookie_path,
        'domain' => '', // keep blank for current host
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

date_default_timezone_set('Asia/Manila');
?>
