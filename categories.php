<?php
// categories.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Merr te gjitha kategorite e userit
$sql = "SELECT id, name, color, created_at
        FROM categories
        WHERE user_id = ?
        ORDER BY created_at DESC";

$categories = [];

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Categories error: " . $e->getMessage());
    $categories = [];
}

// Merr detyrat e userit qe kane kategori (nje query per te gjitha, grupohen sipas category_id)
$tasks_by_category = [];

if (!empty($categories)) {
    try {
        $stmt = $conn->prepare(
            "SELECT *
             FROM tasks
             WHERE user_id = ? AND category_id IS NOT NULL
             ORDER BY id DESC"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $tasks_by_category[(int)$row['category_id']][] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Category tasks error: " . $e->getMessage());
        $tasks_by_category = [];
    }
}

// Ndihmes: a eshte detyra e kryer?
function task_is_done(array $t): bool {
    if (!empty($t['is_completed'])) return true;
    if (!empty($t['completed'])) return true;
    if (isset($t['status']) && in_array(strtolower((string)$t['status']), ['completed', 'done'], true)) return true;
    return false;
}

$priority_labels = [
    'low'    => 'Ulët',
    'medium' => 'Mesatar',
    'high'   => 'Lartë',
];

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
    <title>Kategoritë - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .cat-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* ---------- Category card ---------- */
        .cat-card {
            position: relative;
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: background 0.25s, border-color 0.25s, box-shadow 0.25s;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) backwards;
        }
        .cat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 16px;
            bottom: 16px;
            width: 3px;
            border-radius: 0 4px 4px 0;
            background: var(--cat);
        }
        .cat-card:hover {
            background: var(--surface-2);
            border-color: var(--border-hi);
        }
        .cat-card.open {
            background: var(--surface-2);
            border-color: color-mix(in srgb, var(--cat) 45%, transparent);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
        }

        .cat-head {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 20px 18px 24px;
            cursor: pointer;
            user-select: none;
        }
        .cat-head:focus-visible {
            outline: 2px solid var(--accent-2);
            outline-offset: -2px;
            border-radius: var(--radius);
        }

        .cat-chev {
            width: 8px;
            height: 8px;
            border-right: 2px solid var(--text-dim);
            border-bottom: 2px solid var(--text-dim);
            transform: rotate(-45deg);
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s;
            flex-shrink: 0;
            margin-right: 2px;
        }
        .cat-card:hover .cat-chev { border-color: var(--text-soft); }
        .cat-card.open .cat-chev {
            transform: rotate(45deg);
            border-color: var(--cat);
        }

        .cat-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .category-badge {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 6px 14px;
            border-radius: 16px;
            background-color: var(--cat);
            color: #fff;
            font-size: 0.9em;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .category-badge .dot {
            width: 8px;
            height: 8px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            flex-shrink: 0;
        }
        .category-badge .name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cat-meta {
            font-size: 12px;
            color: var(--text-dim);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .cat-count {
            flex-shrink: 0;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-soft);
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border);
            white-space: nowrap;
        }
        .cat-card.open .cat-count {
            color: var(--text);
            border-color: color-mix(in srgb, var(--cat) 50%, transparent);
        }

        .cat-actions {
            display: flex;
            gap: 2px;
            align-items: center;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .cat-card:hover .cat-actions,
        .cat-card:focus-within .cat-actions { opacity: 1; }

        /* ---------- Expandable panel ---------- */
        .cat-panel {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .cat-card.open .cat-panel { grid-template-rows: 1fr; }
        .cat-panel-inner {
            min-height: 0;
            overflow: hidden;
        }
        .cat-tasks {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0 20px 20px 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .cat-task {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .cat-task-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border-hi);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: transparent;
            flex-shrink: 0;
        }
        .cat-task.done .cat-task-check {
            background: var(--cat);
            border-color: var(--cat);
            color: #fff;
        }
        .cat-task-body {
            flex: 1;
            min-width: 0;
        }
        .cat-task-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            word-wrap: break-word;
        }
        .cat-task.done .cat-task-title {
            text-decoration: line-through;
            color: var(--text-dim);
        }
        .cat-task.done { opacity: 0.6; }
        .cat-task-desc {
            font-size: 12.5px;
            color: var(--text-soft);
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cat-task-side {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            font-size: 12px;
            color: var(--text-dim);
        }
        .cat-task-side .overdue {
            color: var(--red);
            font-weight: 600;
        }

        .cat-empty {
            margin: 0 20px 20px 24px;
            padding: 18px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 13px;
            color: var(--text-dim);
        }
        .cat-empty span {
            display: block;
            padding-top: 14px;
        }

        @media (max-width: 600px) {
            .cat-head { padding: 14px 14px 14px 18px; gap: 10px; flex-wrap: wrap; }
            .cat-actions { opacity: 1; margin-left: auto; }
            .cat-count { order: 3; }
            .cat-tasks, .cat-empty { margin: 0 14px 16px 18px; }
            .cat-task { flex-wrap: wrap; }
            .cat-task-side { width: 100%; padding-left: 32px; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard">

    <div class="dashboard-header">
        <h1>Kategoritë e mia</h1>
        <a href="tasks/add_category.php" class="btn">+ Shto kategori</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="empty-state">
            <p>📭 Nuk ka kategori ende.</p>
            <p><a href="tasks/add_category.php">Shto kategorine e pare</a></p>
        </div>
    <?php else: ?>
        <ul class="cat-list">
            <?php foreach ($categories as $i => $category):
                $cid    = (int)$category['id'];
                $color  = $category['color'] ?? '#3b82f6';
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) { $color = '#3b82f6'; }

                $cat_tasks = $tasks_by_category[$cid] ?? [];
                // te pakryerat fillimisht
                usort($cat_tasks, fn($a, $b) => (int)task_is_done($a) <=> (int)task_is_done($b));
                $count = count($cat_tasks);
            ?>
                <li class="cat-card" style="--cat: <?= e($color) ?>; animation-delay: <?= min($i, 5) * 0.04 ?>s;">

                    <div class="cat-head" role="button" tabindex="0"
                         aria-expanded="false" aria-controls="cat-panel-<?= $cid ?>">
                        <span class="cat-chev"></span>

                        <div class="cat-main">
                            <span class="category-badge">
                                <span class="dot"></span>
                                <span class="name"><?= e($category['name']) ?></span>
                            </span>
                            <div class="cat-meta">
                                <span>🕒 <?= e($category['created_at']) ?></span>
                            </div>
                        </div>

                        <span class="cat-count"><?= $count ?> <?= $count === 1 ? 'detyre' : 'detyra' ?></span>

                        <div class="cat-actions">
                            <a href="tasks/edit_category.php?id=<?= $cid ?>" class="icon-btn" title="Edito">✏️</a>

                            <form action="tasks/delete_category.php" method="POST" class="inline-form"
                                  onsubmit="return confirm('Fshij kete kategori?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id"   value="<?= $cid ?>">
                                <button type="submit" class="icon-btn" title="Fshij">🗑️</button>
                            </form>
                        </div>
                    </div>

                    <div class="cat-panel" id="cat-panel-<?= $cid ?>">
                        <div class="cat-panel-inner">
                            <?php if ($count === 0): ?>
                                <div class="cat-empty"><span>Nuk ka detyra ne kete kategori.</span></div>
                            <?php else: ?>
                                <ul class="cat-tasks">
                                    <?php foreach ($cat_tasks as $t):
                                        $done     = task_is_done($t);
                                        $title    = $t['title'] ?? $t['name'] ?? '(pa titull)';
                                        $desc     = trim((string)($t['description'] ?? ''));
                                        $priority = strtolower((string)($t['priority'] ?? ''));
                                        $due      = $t['due_date'] ?? null;
                                        $overdue  = false;
                                        $due_txt  = '';
                                        if (!empty($due) && strtotime($due) !== false) {
                                            $due_txt = date('d.m.Y', strtotime($due));
                                            $overdue = !$done && strtotime($due) < strtotime('today');
                                        }
                                    ?>
                                        <li class="cat-task<?= $done ? ' done' : '' ?>">
                                            <span class="cat-task-check">✓</span>
                                            <div class="cat-task-body">
                                                <div class="cat-task-title"><?= e($title) ?></div>
                                                <?php if ($desc !== ''): ?>
                                                    <div class="cat-task-desc"><?= e($desc) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="cat-task-side">
                                                <?php if ($due_txt !== ''): ?>
                                                    <span class="<?= $overdue ? 'overdue' : '' ?>">📅 <?= e($due_txt) ?></span>
                                                <?php endif; ?>
                                                <?php if (isset($priority_labels[$priority])): ?>
                                                    <span class="priority priority-<?= e($priority) ?>"><?= e($priority_labels[$priority]) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.cat-card').forEach(function (card) {
            const head = card.querySelector('.cat-head');

            function toggle() {
                const open = card.classList.toggle('open');
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            head.addEventListener('click', function (e) {
                if (e.target.closest('.cat-actions')) return;
                toggle();
            });

            head.addEventListener('keydown', function (e) {
                if (e.target !== head) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
        });
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>