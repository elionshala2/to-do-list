<?php
// includes/functions.php

// Escape Output -- mbrojtja nga sulmet XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF tokeni -- mbrojtja nga sulmet CSRF
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? '');
}

// Redirecti
function redirect($url) {
    header("Location: $url");
    exit;
}

// Kontrollon nese useri eshte i kyqur ne llogari
function require_login() {
    if (empty($_SESSION['user_id'])) {
        $prefix = str_contains($_SERVER['SCRIPT_NAME'], '/tasks/') ? '../' : '';
        redirect($prefix . 'login.php');
    }
}

// Siguron qe useri te kaje kategorine default "Inbox"
function ensure_default_category($conn, $user_id) {
    // Kontrollo nse ekziston ndonje kategori
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Nese nuk ka kategori, krijo Inbox default
    if ($row['count'] == 0) {
        $stmt = $conn->prepare(
            "INSERT INTO categories (user_id, name, color, is_default) VALUES (?, 'Inbox', '#3b82f6', 1)"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}