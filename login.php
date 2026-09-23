<?php
// login.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

// Nese tashme i kyqyur shkon ne dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors  = [];
$success = '';
$old_email = '';

// Flash nga signup (mesazh suksesi)
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF kundrej sulmeve
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kërkesë e pavlefshme. Provo përsëri.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $old_email = $email;

        // Kontrollon nese eshte thate
        if ($email === '' || $password === '') {
            $errors[] = 'Plotëso të gjitha fushat.';
        } else {
            try {
                // Kerko user-in ne databaze
                $stmt = $conn->prepare(
                    "SELECT id, username, password FROM regjistrimi WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user   = $result->fetch_assoc();
                $stmt->close();

                //  Verifiko passwordin
                if ($user && password_verify($password, $user['password'])) {

                    // Rigjenero session ID (mbrojtje session fixation)
                    session_regenerate_id(true);

                    //  Ruaj ne sesion
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    // Shko ne dashboard
                    redirect('dashboard.php');

                } else {
                    // Mesazh i pergjithshem
                    $errors[] = 'Email ose password i gabuar.';
                }

            } catch (mysqli_sql_exception $e) {
                error_log("Login error: " . $e->getMessage());
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
    <title>Login - To-Do List</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-box">
    <h1>Login</h1>

    <?php if ($success): ?>
        <div class="alert success"><p><?= e($success) ?></p></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Email</label>
        <input type="email" name="email"
               value="<?= e($old_email) ?>"
               autocomplete="email" required>

        <label>Password</label>
        <input type="password" name="password"
               autocomplete="current-password" required>

        <button type="submit">Login</button>
    </form>

    <p>Nuk ke llogari? <a href="signup.php">Regjistrohu</a></p>
    <p><a href="index.php">← Kthehu ne faqen kryesore</a></p>
</div>
</body>
</html>