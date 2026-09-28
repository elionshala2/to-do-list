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

function ensure_categories_schema($conn) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_category (user_id, name),
            INDEX idx_category_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $conn->query("ALTER TABLE tasks ADD COLUMN category_id INT UNSIGNED NULL");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) {
            throw $e;
        }
    }

    try {
        $conn->query("ALTER TABLE categories ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) {
            throw $e;
        }
    }

    try {
        $conn->query("ALTER TABLE tasks ADD INDEX idx_tasks_user_category (user_id, category_id)");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1061) {
            throw $e;
        }
    }
}

function ensure_user_inbox_category($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT id
         FROM categories
         WHERE user_id = ? AND is_default = 1
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    $stmt = $conn->prepare(
        "SELECT id
         FROM categories
         WHERE user_id = ? AND name = 'Inbox'
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $inbox_id = (int)$existing['id'];
        $stmt = $conn->prepare(
            "UPDATE categories
             SET is_default = CASE WHEN id = ? THEN 1 ELSE 0 END
             WHERE user_id = ?"
        );
        $stmt->bind_param("ii", $inbox_id, $user_id);
        $stmt->execute();
        $stmt->close();
        return $inbox_id;
    }

    $name = 'Inbox';
    $is_default = 1;
    $stmt = $conn->prepare(
        "INSERT INTO categories (user_id, name, is_default)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("isi", $user_id, $name, $is_default);
    $stmt->execute();
    $inbox_id = (int)$stmt->insert_id;
    $stmt->close();

    return $inbox_id;
}

function assign_inbox_to_uncategorized_tasks($conn, $user_id, $inbox_id) {
    $stmt = $conn->prepare(
        "UPDATE tasks
         SET category_id = ?
         WHERE user_id = ? AND (category_id IS NULL OR category_id = 0)"
    );
    $stmt->bind_param("ii", $inbox_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

function get_user_categories($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT id, name, is_default, created_at
         FROM categories
         WHERE user_id = ?
         ORDER BY is_default DESC, name ASC, id ASC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
    return $categories;
}

function normalize_user_category_id($conn, $user_id, $category_id, $fallback_id) {
    $category_id = (int)$category_id;
    if ($category_id <= 0) {
        return (int)$fallback_id;
    }

    $stmt = $conn->prepare(
        "SELECT id
         FROM categories
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['id'] : (int)$fallback_id;
}