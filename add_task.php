<?php
// add_task.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$errors  = [];
$old = [
    'title'       => '',
    'description' => '',
    'priority'    => 'medium',
    'due_date'    => '',
];

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
            $errors[] = 'Prioritet i pavlefshëm.';
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

                $stmt = $conn->prepare(
                    "INSERT INTO tasks (user_id, title, description, priority, due_date)
                     VALUES (?, ?, ?, ?, ?)"
                );

                // Bind: i=int, s=string. Kur due=null, bind gjithashtu si 's'
                $stmt->bind_param(
                    "issss",
                    $user_id,
                    $old['title'],
                    $old['description'],
                    $old['priority'],
                    $due
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Detyra u shtua me sukses!';
                redirect('dashboard.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Add task error: " . $e->getMessage());
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
    <title>Shto detyrë — ToDo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="logo">📝 ToDo</a>
    <div class="nav-links">
        <a href="dashboard.php">← Kthehu</a>
    </div>
</nav>

<main class="dashboard">
    <h1>Shto detyrë të re</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="task-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Titulli *</label>
        <input type="text" name="title" maxlength="255"
               value="<?= e($old['title']) ?>" required autofocus>

        <label>Përshkrimi (opsional)</label>
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
            <button type="submit" class="btn">Ruaj</button>
        </div>
    </form>
</main>
</body>
</html>