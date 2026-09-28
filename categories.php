<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$errors = [];

try {
    ensure_categories_schema($conn);
    $inbox_id = ensure_user_inbox_category($conn, $user_id);
    assign_inbox_to_uncategorized_tasks($conn, $user_id, $inbox_id);
} catch (mysqli_sql_exception $e) {
    error_log("Categories setup error: " . $e->getMessage());
    $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
}

$form = [
    'name' => '',
    'mode' => 'create',
    'id' => 0,
    'is_default' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kërkesë e pavlefshme. Provo përsëri.';
    } else {
        $action = $_POST['action'] ?? '';
        $category_id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if (!in_array($action, ['create', 'update', 'delete'], true)) {
            $errors[] = 'Veprim i pavlefshëm.';
        }

        if (in_array($action, ['create', 'update'], true)) {
            $form['name'] = $name;
            $form['mode'] = $action === 'update' ? 'edit' : 'create';
            $form['id'] = $category_id;

            if ($name === '') {
                $errors[] = 'Emri i kategorisë është i detyrueshëm.';
            } elseif (mb_strlen($name) > 100) {
                $errors[] = 'Emri i kategorisë nuk duhet të kalojë 100 karaktere.';
            }
        }

        if (in_array($action, ['update', 'delete'], true) && $category_id <= 0) {
            $errors[] = 'Kategori e pavlefshme.';
        }

        if (empty($errors)) {
            $transaction_started = false;
            try {
                $target_default = 0;
                if (in_array($action, ['update', 'delete'], true)) {
                    $stmt = $conn->prepare(
                        "SELECT is_default
                         FROM categories
                         WHERE id = ? AND user_id = ?
                         LIMIT 1"
                    );
                    $stmt->bind_param("ii", $category_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $found = $result->fetch_assoc();
                    $stmt->close();

                    if (!$found) {
                        $errors[] = 'Kategoria nuk u gjet.';
                    } else {
                        $target_default = (int)$found['is_default'];
                    }
                }

                if (!empty($errors)) {
                    throw new RuntimeException('Category validation failed.');
                }

                if ($action === 'create') {
                    $stmt = $conn->prepare(
                        "INSERT INTO categories (user_id, name, is_default) VALUES (?, ?, 0)"
                    );
                    $stmt->bind_param("is", $user_id, $name);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['flash_success'] = 'Kategoria u shtua me sukses.';
                    redirect('categories.php');
                }

                if ($action === 'update') {
                    if ($target_default === 1) {
                        $errors[] = 'Kategoria Inbox nuk mund të riemërtohet.';
                        throw new RuntimeException('Cannot rename inbox.');
                    }

                    $stmt = $conn->prepare(
                        "UPDATE categories
                         SET name = ?
                         WHERE id = ? AND user_id = ?"
                    );
                    $stmt->bind_param("sii", $name, $category_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['flash_success'] = 'Kategoria u përditësua me sukses.';
                    redirect('categories.php');
                }

                if ($action === 'delete') {
                    if ($target_default === 1) {
                        $errors[] = 'Kategoria Inbox nuk mund të fshihet.';
                        throw new RuntimeException('Cannot delete inbox.');
                    }

                    $conn->begin_transaction();
                    $transaction_started = true;

                    $stmt = $conn->prepare(
                        "UPDATE tasks
                         SET category_id = ?
                         WHERE user_id = ? AND category_id = ?"
                    );
                    $stmt->bind_param("iii", $inbox_id, $user_id, $category_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare(
                        "DELETE FROM categories
                         WHERE id = ? AND user_id = ?"
                    );
                    $stmt->bind_param("ii", $category_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    $transaction_started = false;

                    $_SESSION['flash_success'] = 'Kategoria u fshi me sukses.';
                    redirect('categories.php');
                }
            } catch (RuntimeException $e) {
                if ($transaction_started) {
                    $conn->rollback();
                }
            } catch (mysqli_sql_exception $e) {
                if ($transaction_started) {
                    $conn->rollback();
                }
                error_log("Category action error: " . $e->getMessage());
                if ((int)$e->getCode() === 1062) {
                    $errors[] = 'Kjo kategori ekziston tashmë.';
                } else {
                    $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
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
                "SELECT id, name, is_default
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
                $form['is_default'] = (int)$category['is_default'];
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Load category error: " . $e->getMessage());
            $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
        }
    }
}

$categories = [];
if (empty($errors)) {
    try {
        $categories = get_user_categories($conn, $user_id);
    } catch (mysqli_sql_exception $e) {
        error_log("List categories error: " . $e->getMessage());
        $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
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
                <input type="text" name="name" maxlength="100" value="<?= e($form['name']) ?>" required <?= $form['is_default'] === 1 ? 'disabled' : '' ?>>

                <div class="form-actions category-actions">
                    <?php if ($form['mode'] === 'edit'): ?>
                        <a href="categories.php" class="btn-outline">Anulo</a>
                    <?php endif; ?>
                    <?php if ($form['mode'] === 'edit' && $form['is_default'] === 1): ?>
                        <button type="button" class="btn" disabled>Inbox është e mbrojtur</button>
                    <?php else: ?>
                        <button type="submit" class="btn"><?= $form['mode'] === 'edit' ? 'Ruaj ndryshimet' : 'Shto kategorinë' ?></button>
                    <?php endif; ?>
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
                                    <?php if ((int)$category['is_default'] === 1): ?>
                                        <span class="priority priority-medium">DEFAULT</span>
                                    <?php endif; ?>
                                </div>
                                <div class="task-meta">
                                    <span>🕒 <?= e($category['created_at']) ?></span>
                                </div>
                            </div>

                            <div class="task-actions category-item-actions">
                                <?php if ((int)$category['is_default'] === 1): ?>
                                    <span class="category-locked">E mbrojtur</span>
                                <?php else: ?>
                                    <a href="categories.php?edit=<?= (int)$category['id'] ?>" class="icon-btn" title="Edito">✏️</a>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Fshij kete kategori? Detyrat kalojne te Inbox.');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                        <button type="submit" class="icon-btn" title="Fshij">🗑️</button>
                                    </form>
                                <?php endif; ?>
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
