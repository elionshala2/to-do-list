<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$errors = [];

try {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_category (user_id, name),
            INDEX idx_category_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} catch (mysqli_sql_exception $e) {
    error_log("Create categories table error: " . $e->getMessage());
    $errors[] = 'Diqka shkoi gabim. Provo perseri.';
}

$form = [
    'name' => '',
    'mode' => 'create',
    'id' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $action = $_POST['action'] ?? '';
        $category_id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if (!in_array($action, ['create', 'update', 'delete'], true)) {
            $errors[] = 'Veprim i pavlefshem.';
        }

        if (in_array($action, ['create', 'update'], true)) {
            $form['name'] = $name;
            $form['mode'] = $action === 'update' ? 'edit' : 'create';
            $form['id'] = $category_id;

            if ($name === '') {
                $errors[] = 'Emri i kategorise eshte i detyrueshem.';
            } elseif (mb_strlen($name) > 100) {
                $errors[] = 'Emri i kategorise nuk duhet te kaloje 100 karaktere.';
            }
        }

        if (in_array($action, ['update', 'delete'], true) && $category_id <= 0) {
            $errors[] = 'Kategori e pavlefshme.';
        }

        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $stmt = $conn->prepare(
                        "INSERT INTO categories (user_id, name) VALUES (?, ?)"
                    );
                    $stmt->bind_param("is", $user_id, $name);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['flash_success'] = 'Kategoria u shtua me sukses!';
                    redirect('categories.php');
                }

                if ($action === 'update') {
                    $stmt = $conn->prepare(
                        "UPDATE categories
                         SET name = ?
                         WHERE id = ? AND user_id = ?"
                    );
                    $stmt->bind_param("sii", $name, $category_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['flash_success'] = 'Kategoria u perditesua me sukses!';
                    redirect('categories.php');
                }

                if ($action === 'delete') {
                    $stmt = $conn->prepare(
                        "DELETE FROM categories
                         WHERE id = ? AND user_id = ?"
                    );
                    $stmt->bind_param("ii", $category_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['flash_success'] = 'Kategoria u fshi me sukses!';
                    redirect('categories.php');
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Category action error: " . $e->getMessage());
                if ((int)$e->getCode() === 1062) {
                    $errors[] = 'Kjo kategori ekziston tashme.';
                } else {
                    $errors[] = 'Diqka shkoi gabim. Provo perseri.';
                }
            }
        }
    }
}

if (empty($errors) && isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        try {
            $stmt = $conn->prepare(
                "SELECT id, name
                 FROM categories
                 WHERE id = ? AND user_id = ?
                 LIMIT 1"
            );
            $stmt->bind_param("ii", $edit_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $category = $result->fetch_assoc();
            $stmt->close();

            if ($category) {
                $form['mode'] = 'edit';
                $form['id'] = (int)$category['id'];
                $form['name'] = $category['name'];
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Load category error: " . $e->getMessage());
            $errors[] = 'Diqka shkoi gabim. Provo perseri.';
        }
    }
}

$categories = [];
if (empty($errors)) {
    try {
        $stmt = $conn->prepare(
            "SELECT id, name, created_at
             FROM categories
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("List categories error: " . $e->getMessage());
        $errors[] = 'Diqka shkoi gabim. Provo perseri.';
    }
}

$flash = '';
if (!empty($_SESSION['flash_success'])) {
    $flash = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorite - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard">
    <div class="dashboard-header">
        <h1>Kategorite e mia</h1>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="categories-grid">
        <section class="task-form category-form-panel">
            <h2><?= $form['mode'] === 'edit' ? 'Edito kategorine' : 'Shto kategori' ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $form['mode'] === 'edit' ? 'update' : 'create' ?>">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <label>Emri i kategorise *</label>
                <input type="text" name="name" maxlength="100" value="<?= e($form['name']) ?>" required>

                <div class="form-actions category-actions">
                    <?php if ($form['mode'] === 'edit'): ?>
                        <a href="categories.php" class="btn-outline">Anulo</a>
                    <?php endif; ?>
                    <button type="submit" class="btn"><?= $form['mode'] === 'edit' ? 'Ruaj ndryshimet' : 'Shto kategorine' ?></button>
                </div>
            </form>
        </section>

        <section class="category-list-panel">
            <h2>Lista e kategorive</h2>
            <?php if (empty($categories)): ?>
                <div class="empty-state category-empty">
                    <p>📂 Nuk ka kategori ende.</p>
                </div>
            <?php else: ?>
                <ul class="task-list">
                    <?php foreach ($categories as $category): ?>
                        <li class="task-item category-item">
                            <div class="task-body">
                                <div class="task-title">
                                    <?= e($category['name']) ?>
                                </div>
                                <div class="task-meta">
                                    <span>🕒 <?= e($category['created_at']) ?></span>
                                </div>
                            </div>

                            <div class="task-actions category-item-actions">
                                <a href="categories.php?edit=<?= (int)$category['id'] ?>" class="icon-btn" title="Edito">✏️</a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Fshij kete kategori?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                    <button type="submit" class="icon-btn" title="Fshij">🗑️</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
