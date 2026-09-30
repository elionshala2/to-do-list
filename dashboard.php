<?php
// dashboard.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
require_login();

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$today = date('Y-m-d');

// -------------------------------------------------
// Ndihmese
// -------------------------------------------------
function dash_safe_color($c): string {
    return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? $c : '#3b82f6';
}

function dash_url(string $filter, string $cat): string {
    $q = [];
    if ($filter !== 'all') $q['filter'] = $filter;
    if ($cat !== 'all')    $q['cat']    = $cat;
    return 'dashboard.php' . ($q ? '?' . http_build_query($q) : '');
}

$priority_labels = [
    'low'    => 'Ulët',
    'medium' => 'Mesatar',
    'high'   => 'Lartë',
];

// -------------------------------------------------
// Kategorite e userit
// -------------------------------------------------
$categories = [];   // id => [id, name, color]

try {
    $stmt = $conn->prepare(
        "SELECT id, name, color
         FROM categories
         WHERE user_id = ?
         ORDER BY name ASC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['color'] = dash_safe_color($row['color'] ?? null);
        $categories[(int)$row['id']] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Dashboard categories error: " . $e->getMessage());
    $categories = [];
}

// -------------------------------------------------
// Filtrat (status + kategori)
// -------------------------------------------------
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'active', 'completed'], true)) {
    $filter = 'all';
}

$cat_filter = 'all';   // 'all' | 'none' | '<id>'
$cat_raw = (string)($_GET['cat'] ?? 'all');
if ($cat_raw === 'none') {
    $cat_filter = 'none';
} elseif (ctype_digit($cat_raw) && isset($categories[(int)$cat_raw])) {
    $cat_filter = (string)(int)$cat_raw;
}

// -------------------------------------------------
// Statistika (te gjitha detyrat e userit, pa filtra)
// -------------------------------------------------
$stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0];
$count_by_cat = [];
$count_none   = 0;

try {
    $stmt = $conn->prepare("SELECT category_id, is_completed, due_date FROM tasks WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $done = (int)$row['is_completed'] === 1;
        $stats['total']++;
        if ($done) {
            $stats['completed']++;
        } else {
            $stats['active']++;
            if (!empty($row['due_date']) && $row['due_date'] < $today) {
                $stats['overdue']++;
            }
        }
        if ($row['category_id'] === null) {
            $count_none++;
        } else {
            $cid = (int)$row['category_id'];
            $count_by_cat[$cid] = ($count_by_cat[$cid] ?? 0) + 1;
        }
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

$percent_done = $stats['total'] > 0 ? (int)round($stats['completed'] / $stats['total'] * 100) : 0;

// -------------------------------------------------
// Detyrat (me filtra)
// -------------------------------------------------
$sql = "SELECT t.id, t.title, t.description, t.priority, t.due_date, t.is_completed,
               t.created_at, t.category_id
        FROM tasks t
        WHERE t.user_id = ?";
$types  = "i";
$params = [$user_id];

if ($filter === 'active') {
    $sql .= " AND t.is_completed = 0";
} elseif ($filter === 'completed') {
    $sql .= " AND t.is_completed = 1";
}

if ($cat_filter === 'none') {
    $sql .= " AND t.category_id IS NULL";
} elseif ($cat_filter !== 'all') {
    $sql .= " AND t.category_id = ?";
    $types  .= "i";
    $params[] = (int)$cat_filter;
}

$sql .= " ORDER BY t.is_completed ASC,
                   FIELD(t.priority, 'high', 'medium', 'low'),
                   t.due_date IS NULL, t.due_date ASC,
                   t.created_at DESC";

$tasks = [];

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
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

$filters_active = ($filter !== 'all' || $cat_filter !== 'all');

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
    <style>
        /* ---------- Stats ---------- */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 14px;
        }
        .stat {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 18px;
        }
        .stat-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.1;
            color: var(--text);
        }
        .stat-label {
            margin-top: 4px;
            font-size: 12px;
            letter-spacing: 0.04em;
            color: var(--text-dim);
        }
        .stat.is-danger .stat-num { color: var(--red); }
        .stat.is-good .stat-num   { color: var(--green); }

        .progress-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
            font-size: 12px;
            color: var(--text-dim);
        }
        .progress {
            flex: 1;
            height: 6px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.07);
            overflow: hidden;
        }
        .progress > span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .progress-wrap strong { color: var(--text-soft); font-weight: 600; }

        /* ---------- Toolbar / filters ---------- */
        .dash-toolbar {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 24px;
        }
        .toolbar-row {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .toolbar-label {
            min-width: 74px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-dim);
        }
        .dash-toolbar .filters { margin-bottom: 0; }
        .filters a .n {
            margin-left: 6px;
            font-size: 11px;
            font-weight: 600;
            opacity: 0.6;
        }

        .cat-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        a.cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-soft);
            background: var(--surface);
            border: 1px solid var(--border);
            transition: all 0.2s;
            white-space: nowrap;
        }
        a.cat-pill .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--cat, var(--text-dim));
            flex-shrink: 0;
        }
        a.cat-pill .n {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-dim);
        }
        a.cat-pill:hover {
            background: var(--surface-2);
            border-color: var(--border-hi);
            color: var(--text);
        }
        a.cat-pill.active {
            color: var(--text);
            background: color-mix(in srgb, var(--cat, var(--accent)) 20%, transparent);
            border-color: color-mix(in srgb, var(--cat, var(--accent)) 60%, transparent);
        }
        a.cat-pill.active .dot { box-shadow: 0 0 10px var(--cat, var(--accent)); }

        .clear-filters {
            font-size: 12px;
            color: var(--text-dim);
            margin-left: auto;
        }
        .clear-filters:hover { color: var(--accent-2); }

        /* ---------- Task extras ---------- */
        a.task-cat {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 10px 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text);
            background: color-mix(in srgb, var(--cat) 18%, transparent);
            border: 1px solid color-mix(in srgb, var(--cat) 50%, transparent);
            transition: background 0.2s;
        }
        a.task-cat:hover {
            background: color-mix(in srgb, var(--cat) 30%, transparent);
            color: var(--text);
        }
        a.task-cat .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--cat);
        }
        .task-meta .today {
            color: var(--yellow);
            font-weight: 600;
        }

        @media (max-width: 700px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .toolbar-label { min-width: 100%; }
            .clear-filters { margin-left: 0; }
            .dash-toolbar .filters { max-width: 100%; overflow-x: auto; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard">

    <div class="dashboard-header">
        <h1>Detyrat e mia</h1>
        <a href="tasks/add_task.php" class="btn">+ Shto detyrë</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if ($stats['total'] > 0): ?>
        <!-- Statistika -->
        <div class="stats">
            <div class="stat">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-label">Gjithsej</div>
            </div>
            <div class="stat">
                <div class="stat-num"><?= $stats['active'] ?></div>
                <div class="stat-label">Aktive</div>
            </div>
            <div class="stat <?= $stats['completed'] > 0 ? 'is-good' : '' ?>">
                <div class="stat-num"><?= $stats['completed'] ?></div>
                <div class="stat-label">Të kryera</div>
            </div>
            <div class="stat <?= $stats['overdue'] > 0 ? 'is-danger' : '' ?>">
                <div class="stat-num"><?= $stats['overdue'] ?></div>
                <div class="stat-label">Të vonuara</div>
            </div>
        </div>
        <div class="progress-wrap">
            <div class="progress"><span style="width: <?= $percent_done ?>%"></span></div>
            <span><strong><?= $percent_done ?>%</strong> e kryer</span>
        </div>
    <?php endif; ?>

    <!-- Filtra -->
    <div class="dash-toolbar">
        <div class="toolbar-row">
            <span class="toolbar-label">Statusi</span>
            <div class="filters">
                <a href="<?= e(dash_url('all', $cat_filter)) ?>"       class="<?= $filter === 'all'       ? 'active' : '' ?>">Te gjitha<span class="n"><?= $stats['total'] ?></span></a>
                <a href="<?= e(dash_url('active', $cat_filter)) ?>"    class="<?= $filter === 'active'    ? 'active' : '' ?>">Aktive<span class="n"><?= $stats['active'] ?></span></a>
                <a href="<?= e(dash_url('completed', $cat_filter)) ?>" class="<?= $filter === 'completed' ? 'active' : '' ?>">Te kryera<span class="n"><?= $stats['completed'] ?></span></a>
            </div>

            <?php if ($filters_active): ?>
                <a href="dashboard.php" class="clear-filters">✕ Pastro filtrat</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="toolbar-row">
                <span class="toolbar-label">Kategoria</span>
                <div class="cat-filters">
                    <a href="<?= e(dash_url($filter, 'all')) ?>"
                       class="cat-pill <?= $cat_filter === 'all' ? 'active' : '' ?>">Te gjitha</a>

                    <?php foreach ($categories as $cid => $cat): ?>
                        <a href="<?= e(dash_url($filter, (string)$cid)) ?>"
                           class="cat-pill <?= $cat_filter === (string)$cid ? 'active' : '' ?>"
                           style="--cat: <?= e($cat['color']) ?>;">
                            <span class="dot"></span>
                            <?= e($cat['name']) ?>
                            <span class="n"><?= (int)($count_by_cat[$cid] ?? 0) ?></span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($count_none > 0 || $cat_filter === 'none'): ?>
                        <a href="<?= e(dash_url($filter, 'none')) ?>"
                           class="cat-pill <?= $cat_filter === 'none' ? 'active' : '' ?>">
                            <span class="dot"></span>
                            Pa kategori
                            <span class="n"><?= $count_none ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <p>📭 Nuk ka detyra per kete filter.</p>
            <?php if ($filters_active): ?>
                <p><a href="dashboard.php">Pastro filtrat</a></p>
            <?php else: ?>
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
                        && $task['due_date'] < $today;
                    $isToday = !$isDone
                        && !empty($task['due_date'])
                        && $task['due_date'] === $today;

                    $taskCat = null;
                    if ($task['category_id'] !== null && isset($categories[(int)$task['category_id']])) {
                        $taskCat = $categories[(int)$task['category_id']];
                    }

                    $prio = (string)$task['priority'];
                    $prioLabel = $priority_labels[$prio] ?? $prio;
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
                            <span class="priority priority-<?= e($prio) ?>">
                                <?= e($prioLabel) ?>
                            </span>
                            <?php if ($taskCat): ?>
                                <a href="<?= e(dash_url($filter, (string)$taskCat['id'])) ?>"
                                   class="task-cat"
                                   style="--cat: <?= e($taskCat['color']) ?>;"
                                   title="Shfaq vetem kete kategori">
                                    <span class="dot"></span><?= e($taskCat['name']) ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($task['description'])): ?>
                            <p class="task-desc"><?= e($task['description']) ?></p>
                        <?php endif; ?>

                        <div class="task-meta">
                            <?php if (!empty($task['due_date'])): ?>
                                <span class="<?= $isOverdue ? 'overdue' : ($isToday ? 'today' : '') ?>">
                                    📅 <?= e(date('d.m.Y', strtotime($task['due_date']))) ?>
                                    <?= $isOverdue ? ' (vonuar)' : ($isToday ? ' (sot)' : '') ?>
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