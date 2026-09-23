<?php
// delete_task.php

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
    $_SESSION['flash_success'] = 'Kerkes e pavlefshme. Provo perseri.';
    redirect('../dashboard.php');
}

$task_id = (int)($_POST['id'] ?? 0);

if ($task_id <= 0) {
    redirect('../dashboard.php');
}

try {
    // WHERE id = ? AND user_id = ? -> vetem task i userit aktual
    $stmt = $conn->prepare(
        "DELETE FROM tasks WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['flash_success'] = 'Detyra u fshi me sukses!';
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Delete task error: " . $e->getMessage());
}

redirect('../dashboard.php');