<?php
// toggle_task.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/footer.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];

// Vetem POST lejohet
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../dashboard.php');
}

// CSRF
if (!verify_csrf($_POST['csrf'] ?? '')) {
    $_SESSION['flash_success'] = 'Kerkese e pavlefshme. Provo perseri.';
    redirect('../dashboard.php');
}

$task_id = (int)($_POST['id'] ?? 0);

if ($task_id <= 0) {
    redirect('../dashboard.php');
}

try {
    // Toggle: nese 0 behet 1, nese 1 behet 0
    // WHERE id = ? AND user_id = ? -> vetem task i userit aktual
    $stmt = $conn->prepare(
        "UPDATE tasks
         SET is_completed = 1 - is_completed
         WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Toggle task error: " . $e->getMessage());
}

redirect('../dashboard.php');