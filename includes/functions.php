<?php
// includes/functions.php

// Escape Output -- mbrojtja nga sulmet XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF token mbrojtja nga sulmet CSRF
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? '');
}

// Redirect 
function redirect($url) {
    header("Location: $url");
    exit;
}

// Kontrollon nese useri eshte i kyqur ne llogari
function require_login() {
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}