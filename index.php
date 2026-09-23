<?php
// index.php — Faqja kryesore

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$username   = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="To-Do List — Organizohu, fokusohu, arri me shume.">
    <title>To-Do List - Organizohu me lehtesi</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">To-Do List</a>
    <div class="nav-links">
        <?php if ($isLoggedIn): ?>
            <span>Pershendetje, <strong><?= e($username) ?></strong></span>
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="logout.php" class="btn-outline">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-outline">Login</a>
            <a href="signup.php" class="btn">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>

<main class="landing">

    <!-- HERO -->
    <section class="hero">
        <div class="hero-badge">I shpejte. I sigurt. Falas.</div>
        <h1>Organizohu. Fokusohu.<br>Arri me shume.</h1>
        <p class="hero-sub">
            Nje aplikacion i thjeshte dhe i fuqishem per te menaxhuar detyrat e tua
            ditore. Krijo, organizo dhe perfundo ato.
        </p>
        <div class="hero-actions">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-large">Shko ne Dashboard</a>
            <?php else: ?>
                <a href="signup.php" class="btn btn-large">Krijo llogari falas</a>
                <a href="login.php" class="btn-outline btn-large">Ke llogari? Hyr</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="features-section">
        <h2 class="section-title">Pse te zgjedhesh ne?</h2>
        <p class="section-sub">Gjithçka qe te duhet per produktivitet, ne nje vend.</p>

        <div class="features-grid">

            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>I shpejte</h3>
                <p>Faqja hapet ne me pak se 2 sekonda. Gjithmone.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>I sigurt</h3>
                <p>Password te enkriptuara, mbrojtje nga SQLi dhe XSS.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Responsive</h3>
                <p>Punon ne laptop, tablet dhe telefon pa problem.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3>Prioritete</h3>
                <p>Cakto prioritetin: low, medium ose high per cdo detyre.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3>Afate kohore</h3>
                <p>Cakto daten e perfundimit dhe mbaj gjithçka ne kontroll.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔑</div>
                <h3>Login i lehte</h3>
                <p>Hyr me email dhe password - shpejt dhe i sigurt.</p>
            </div>

        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <h2>Gati per t'u organizuar?</h2>
        <p>Bashkohu me njerezit qe po e marrin kontrollin e dites se tyre.</p>
        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php" class="btn btn-large">Shko ne Dashboard</a>
        <?php else: ?>
            <a href="signup.php" class="btn btn-large">Fillo tani - eshte falas</a>
        <?php endif; ?>
    </section>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>