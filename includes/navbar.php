<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = !empty($_SESSION['user_id']);
$username   = $_SESSION['username'] ?? '';
$current    = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="nav-left">
        <a href="index.php" class="logo">To-Do List</a>

        <?php if ($isLoggedIn): ?>
            <div class="nav-main">
                <a href="index.php"
                   class="btn-outline <?= $current === 'index.php' ? 'active' : '' ?>">Home</a>

                <a href="dashboard.php"
                   class="btn-outline <?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>

                <div class="nav-dropdown">
                    <a href="categories.php"
                       class="btn-outline <?= in_array($current, ['categories.php','add_category.php','edit_category.php']) ? 'active' : '' ?>">
                        Kategoritë <span class="caret"></span>
                    </a>
                    <div class="nav-dropdown-menu">
                        <a href="categories.php">Shiko te gjitha</a>
                        <a href="add_category.php">+ Shto kategori</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="nav-links">
        <?php if ($isLoggedIn): ?>
            <span class="nav-user">Pershendetje, <strong><?= e($username) ?></strong></span>
            <a href="logout.php" class="btn-outline">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-outline">Login</a>
            <a href="signup.php" class="btn">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>