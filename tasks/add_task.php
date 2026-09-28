<?php
// add_task.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$errors  = [];
$categories = [];
$inbox_id = 0;
$old = [
    'title'       => '',
    'description' => '',
    'priority'    => 'medium',
    'due_date'    => '',
    'category_id' => 0,
];

try {
    ensure_categories_schema($conn);
    $inbox_id = ensure_user_inbox_category($conn, $user_id);
    assign_inbox_to_uncategorized_tasks($conn, $user_id, $inbox_id);
    $categories = get_user_categories($conn, $user_id);
    $old['category_id'] = $inbox_id;
} catch (mysqli_sql_exception $e) {
    error_log("Add task setup error: " . $e->getMessage());
    $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kërkesë e pavlefshme. Provo përsëri.';
    } else {
        $old['title']       = trim($_POST['title'] ?? '');
        $old['description'] = trim($_POST['description'] ?? '');
        $old['priority']    = $_POST['priority'] ?? 'medium';
        $old['due_date']    = trim($_POST['due_date'] ?? '');
        $old['category_id'] = normalize_user_category_id(
            $conn,
            $user_id,
            $_POST['category_id'] ?? 0,
            $inbox_id
        );

        // Validime
        if ($old['title'] === '') {
            $errors[] = 'Titulli është i detyrueshëm.';
        } elseif (mb_strlen($old['title']) > 255) {
            $errors[] = 'Titulli nuk duhet të kalojë 255 karaktere.';
        }

        if (!in_array($old['priority'], ['low', 'medium', 'high'], true)) {
            $errors[] = 'Prioritet i pavlefshëm.';
        }

        if ($old['due_date'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $old['due_date']);
            if (!$d || $d->format('Y-m-d') !== $old['due_date']) {
                $errors[] = 'Datë e pavlefshme.';
            }
        }

        if (empty($errors)) {
            try {
                $due = $old['due_date'] === '' ? null : $old['due_date'];

                $stmt = $conn->prepare(
                    "INSERT INTO tasks (user_id, category_id, title, description, priority, due_date)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iissss",
                    $user_id,
                    $old['category_id'],
                    $old['title'],
                    $old['description'],
                    $old['priority'],
                    $due
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Detyra u shtua me sukses.';
                redirect('../dashboard.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Add task error: " . $e->getMessage());
                $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
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
    <title>Shto detyre - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<!--<nav class="navbar">
    <a href="../dashboard.php" class="logo">To-Do List</a>
    <div class="nav-links">
        <a href="../dashboard.php" class="btn-outline">← Kthehu</a>
    </div>
</nav> -->
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="dashboard page-center">
    <div class="page-header">
        <h1>Shto detyre te re</h1>
    </div>

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

        <label>Kategoria</label>
        <select name="category_id" required>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= (int)$old['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?><?= (int)$category['is_default'] === 1 ? ' (Inbox)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-actions">
            <a href="../dashboard.php" class="btn-outline">Anulo</a>
            <button type="submit" class="btn">Ruaj</button>
        </div>
    </form>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>