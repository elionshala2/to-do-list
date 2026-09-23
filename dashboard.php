<?php
// dashboard.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// filtri (all / active / completed)
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'active', 'completed'], true)) {
    $filter = 'all';
}

// nderto query sipas filtrit
$sql = "SELECT id, title, description, priority, due_date, is_completed, created_at
        FROM tasks
        WHERE user_id = ?";

if ($filter === 'active') {
    $sql .= " AND is_completed = 0";
} elseif ($filter === 'completed') {
    $sql .= " AND is_completed = 1";
}

$sql .= " ORDER BY is_completed ASC,
                   FIELD(priority, 'high', 'medium', 'low'),
                   due_date IS NULL, due_date ASC,
                   created_at DESC";

$tasks = [];

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $tasks = [];
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
<nav class="navbar">
    <a href="dashboard.php" class="logo">📝 ToDo</a>
    <div class="nav-links">
        <span>Pershendetje, <strong><?= e($username) ?></strong></span>
        <a href="logout.php" class="btn-outline">Logout</a>
    </div>
</nav>

<main class="dashboard">

    <div class="dashboard-header">
        <h1>Detyrat e mia</h1>
        <a href="add_task.php" class="btn">+ Shto detyrë</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <!-- Filtra -->
    <div class="filters">
        <a href="?filter=all"       class="<?= $filter === 'all'       ? 'active' : '' ?>">Te gjitha</a>
        <a href="?filter=active"    class="<?= $filter === 'active'    ? 'active' : '' ?>">Aktive</a>
        <a href="?filter=completed" class="<?= $filter === 'completed' ? 'active' : '' ?>">Te kryera</a>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <p>📭 Nuk ka detyra per kete filter.</p>
            <?php if ($filter === 'all'): ?>
                <p><a href="add_task.php">Shto detyren e pare</a></p>
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
                    <form action="toggle_task.php" method="POST" class="task-toggle">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                        <button type="submit" class="check-btn" title="Ndrysho statusin">
                            <?= $isDone ? '✓' : '' ?>
                        </button>
                    </form>

                    <!-- Përmbajtja -->
                    <div class="task-body">
                        <div class="task-title">
                            <?= e($task['title']) ?>
                            <span class="priority priority-<?= e($task['priority']) ?>">
                                <?= e($task['priority']) ?>
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

                    <!-- Veprime -->
                    <div class="task-actions">
                        <a href="edit_task.php?id=<?= (int)$task['id'] ?>" class="icon-btn" title="Edito">✏️</a>

                        <form action="delete_task.php" method="POST" class="inline-form"
                              onsubmit="return confirm('Fshij këtë detyrë?');">
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
</body>
</html>