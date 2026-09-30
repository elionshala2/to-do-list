<?php
// add_task.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$errors  = [];
$old = [
    'title'       => '',
    'description' => '',
    'priority'    => 'medium',
    'due_date'    => '',
    'category_id' => '',
];

// Load user's categories
$categories = [];
try {
    $stmt = $conn->prepare(
        "SELECT id, name, color FROM categories WHERE user_id = ? ORDER BY name ASC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Load categories error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $old['title']       = trim($_POST['title'] ?? '');
        $old['description'] = trim($_POST['description'] ?? '');
        $old['priority']    = $_POST['priority'] ?? 'medium';
        $old['due_date']    = trim($_POST['due_date'] ?? '');
        $old['category_id'] = $_POST['category_id'] ?? '';

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
                $category_id = !empty($old['category_id']) ? (int)$old['category_id'] : null;

                $stmt = $conn->prepare(
                    "INSERT INTO tasks (user_id, title, description, priority, due_date, category_id)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "issssi",
                    $user_id,
                    $old['title'],
                    $old['description'],
                    $old['priority'],
                    $due,
                    $category_id
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Detyra u shtua me sukses!';
                redirect('../dashboard.php');

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
        <select name="category_id">
            <option value="">-- Pa kategori --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $old['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
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