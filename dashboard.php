<?php
// dashboard.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$errors = [];
$categories = [];
$inbox_id = 0;

try {
    ensure_categories_schema($conn);
    $inbox_id = ensure_user_inbox_category($conn, $user_id);
    assign_inbox_to_uncategorized_tasks($conn, $user_id, $inbox_id);
    $categories = get_user_categories($conn, $user_id);
} catch (mysqli_sql_exception $e) {
    error_log("Dashboard setup error: " . $e->getMessage());
    $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
}

// filtri (all / active / completed)
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'active', 'completed'], true)) {
    $filter = 'all';
}

$category_filter = (int)($_GET['category_id'] ?? 0);
$date_filter_raw = trim($_GET['due_date'] ?? '');
$date_filter = '';
if ($date_filter_raw !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $date_filter_raw);
    if ($d && $d->format('Y-m-d') === $date_filter_raw) {
        $date_filter = $date_filter_raw;
    }
}

// nderto query sipas filtrit
$sql = "SELECT t.id, t.title, t.description, t.priority, t.due_date, t.is_completed, t.created_at,
               t.category_id, c.name AS category_name
        FROM tasks
        LEFT JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
        WHERE t.user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter === 'active') {
    $sql .= " AND t.is_completed = 0";
} elseif ($filter === 'completed') {
    $sql .= " AND t.is_completed = 1";
}

if ($category_filter > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($date_filter !== '') {
    $sql .= " AND t.due_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$sql .= " ORDER BY t.is_completed ASC,
                   FIELD(t.priority, 'high', 'medium', 'low'),
                   t.due_date IS NULL, t.due_date ASC,
                   t.created_at DESC";

$tasks = [];

if (empty($errors)) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if (empty($row['category_name'])) {
                $row['category_name'] = 'Inbox';
            }
            $tasks[] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
        $errors[] = 'Diçka shkoi gabim. Provo përsëri.';
    }
}

$selected_category = 0;
if ($category_filter > 0) {
    foreach ($categories as $category) {
        if ((int)$category['id'] === $category_filter) {
            $selected_category = $category_filter;
            break;
        }
    }
}

// flash nga veprimet (add/edit/delete)
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
    <title>Dashboard - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<!--<nav class="navbar">
    <a href="dashboard.php" class="logo">📝 To-Do List</a>
    <div class="nav-links">
        <span>Pershendetje, <strong><?= e($username) ?></strong></span>
        <a href="logout.php" class="btn-outline">Logout</a>
    </div>
</nav> -->
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard">

    <div class="dashboard-header">
        <h1>Detyrat e mia</h1>
        <a href="tasks/add_task.php" class="btn">+ Shto detyrë</a>
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

    <!-- Filtra -->
    <div class="filters">
        <a href="?filter=all&category_id=<?= (int)$selected_category ?>&due_date=<?= e($date_filter) ?>" class="<?= $filter === 'all' ? 'active' : '' ?>">Te gjitha</a>
        <a href="?filter=active&category_id=<?= (int)$selected_category ?>&due_date=<?= e($date_filter) ?>" class="<?= $filter === 'active' ? 'active' : '' ?>">Aktive</a>
        <a href="?filter=completed&category_id=<?= (int)$selected_category ?>&due_date=<?= e($date_filter) ?>" class="<?= $filter === 'completed' ? 'active' : '' ?>">Te kryera</a>
    </div>

    <form method="GET" class="filter-form">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <div class="filter-field">
            <label for="category_id">Kategoria</label>
            <select name="category_id" id="category_id">
                <option value="0">Te gjitha kategorite</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>" <?= $selected_category === (int)$category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="due_date">Data e detyres</label>
            <input type="date" name="due_date" id="due_date" value="<?= e($date_filter) ?>">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Filtro</button>
            <a href="dashboard.php?filter=<?= e($filter) ?>" class="btn-outline">Pastro</a>
        </div>
    </form>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <p>📭 Nuk ka detyra per kete filter.</p>
            <?php if ($filter === 'all'): ?>
                <p><a href="tasks/add_task.php">Shto detyren e pare</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="task-list">
            <?php foreach ($tasks as $task): ?>
                <?php
                    $isDone = (int)$task['is_completed'] === 1;
                    $isOverdue = !$isDone
                        && !empty($task['due_date'])
                        && $task['due_date'] < date('Y-m-d');
                ?>
                <li class="task-item <?= $isDone ? 'completed' : '' ?>">

                    <!-- Toggle complete -->
                    <form action="tasks/toggle_task.php" method="POST" class="task-toggle">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                        <button type="submit" class="check-btn" title="Ndrysho statusin">
                            <?= $isDone ? '✓' : '' ?>
                        </button>
                    </form>

                    <!-- Permbajtja -->
                    <div class="task-body">
                        <div class="task-title">
                            <?= e($task['title']) ?>
                            <span class="priority priority-<?= e($task['priority']) ?>">
                                <?= e($task['priority']) ?>
                            </span>
                            <span class="priority priority-medium">
                                <?= e($task['category_name']) ?>
                            </span>
                        </div>

                        <?php if (!empty($task['description'])): ?>
                            <p class="task-desc"><?= e($task['description']) ?></p>
                        <?php endif; ?>

                        <div class="task-meta">
                            <?php if (!empty($task['due_date'])): ?>
                                <span class="<?= $isOverdue ? 'overdue' : '' ?>">
                                    📅 <?= e($task['due_date']) ?>
                                    <?= $isOverdue ? ' (vonuar)' : '' ?>
                                </span>
                            <?php endif; ?>
                            <span>🕒 <?= e($task['created_at']) ?></span>
                        </div>
                    </div>

                    <!-- Veprimet -->
                    <div class="task-actions">
                        <a href="tasks/edit_task.php?id=<?= (int)$task['id'] ?>" class="icon-btn" title="Edito">✏️</a>

                        <form action="tasks/delete_task.php" method="POST" class="inline-form"
                              onsubmit="return confirm('Fshij kete detyre?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                            <button type="submit" class="icon-btn" title="Fshij">🗑️</button>
                        </form>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>