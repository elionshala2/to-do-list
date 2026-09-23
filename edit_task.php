<?php
// edit_task.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$task_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors  = [];

// Nese ID e pavlefshme
if ($task_id <= 0) {
    $_SESSION['flash_success'] = 'Detyra nuk u gjet.';
    redirect('dashboard.php');
}

// -------------------------------------------------
// 1. Ngarko taskun — VETEM nese i perket userit
// -------------------------------------------------
function load_task($conn, $task_id, $user_id) {
    $stmt = $conn->prepare(
        "SELECT id, title, description, priority, due_date, is_completed
         FROM tasks
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();
    return $task;
}

$task = load_task($conn, $task_id, $user_id);

if (!$task) {
    // ose nuk ekziston, ose si perket kti useri
    $_SESSION['flash_success'] = 'Detyra nuk u gjet.';
    redirect('dashboard.php');
}

// Vlerat fillestare te formes
$old = [
    'title'       => $task['title'],
    'description' => $task['description'] ?? '',
    'priority'    => $task['priority'],
    'due_date'    => $task['due_date'] ?? '',
];

// -------------------------------------------------
// Dergimi i formes
// -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $old['title']       = trim($_POST['title'] ?? '');
        $old['description'] = trim($_POST['description'] ?? '');
        $old['priority']    = $_POST['priority'] ?? 'medium';
        $old['due_date']    = trim($_POST['due_date'] ?? '');

        // Validime
        if ($old['title'] === '') {
            $errors[] = 'Titulli eshte i detyrueshem.';
        } elseif (mb_strlen($old['title']) > 255) {
            $errors[] = 'Titulli nuk duhet te kaloje 255 karaktere.';
        }

        if (!in_array($old['priority'], ['low', 'medium', 'high'], true)) {
            $errors[] = 'Prioritet i pavlefshem.';
        }

        if ($old['due_date'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $old['due_date']);
            if (!$d || $d->format('Y-m-d') !== $old['due_date']) {
                $errors[] = 'Date e pavlefshme.';
            }
        }

        if (empty($errors)) {
            try {
                $due = $old['due_date'] === '' ? null : $old['due_date'];

                // UPDATE — VETËM nëse task i përket userit (dyfish siguri)
                $stmt = $conn->prepare(
                    "UPDATE tasks
                     SET title = ?, description = ?, priority = ?, due_date = ?
                     WHERE id = ? AND user_id = ?"
                );

                $stmt->bind_param(
                    "ssssii",
                    $old['title'],
                    $old['description'],
                    $old['priority'],
                    $due,
                    $task_id,
                    $user_id
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Detyra u përditësua me sukses!';
                redirect('dashboard.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Edit task error: " . $e->getMessage());
                $errors[] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edito detyren - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="logo">📝 To-Do List</a>
    <div class="nav-links">
        <a href="dashboard.php">← Kthehu</a>
    </div>
</nav>

<main class="dashboard">
    <h1>Edito detyren</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="task-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id"   value="<?= (int)$task_id ?>">

        <label>Titulli *</label>
        <input type="text" name="title" maxlength="255"
               value="<?= e($old['title']) ?>" required autofocus>

        <label>Pershkrimi (opsional)</label>
        <textarea name="description" rows="4"><?= e($old['description']) ?></textarea>

        <label>Prioriteti</label>
        <select name="priority">
            <option value="low"    <?= $old['priority'] === 'low'    ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= $old['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="high"   <?= $old['priority'] === 'high'   ? 'selected' : '' ?>>High</option>
        </select>

        <label>Data (opsionale)</label>
        <input type="date" name="due_date" value="<?= e($old['due_date']) ?>">

        <div class="form-actions">
            <a href="dashboard.php" class="btn-outline">Anulo</a>
            <button type="submit" class="btn">Ruaj ndryshimet</button>
        </div>
    </form>
</main>
</body>
</html>