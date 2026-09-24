<?php
require_once __DIR__ . '/config.php'; // __DIR__ eshte folderi prind i ketij file
require_once __DIR__ . '/includes/functions.php';

session_start();

// Nese je i kyqur shko ne dashboard direkt
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = []; // per grumbullim te erroreve
$old = ['username' => '', 'email' => '']; // ne menyre qe nese ka errore, vlerat e formes nuk fshihen

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $cpassword = $_POST['cpassword'] ?? '';

        $old['username'] = $username;
        $old['email']    = $email;

        // Validime
        if (strlen($username) < 3 || strlen($username) > 20) {
            $errors[] = 'Username duhet 3-20 karaktere.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username mund te permbaje vetem shkronja, numra dhe _.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email i pavlefshëm.';
        }

        if (strlen($password) < 8
            || !preg_match('/[A-Za-z]/', $password)
            || !preg_match('/\d/', $password)) {
            $errors[] = 'Password: min 8 karaktere, te pakten 1 shkronje dhe 1 numer.';
        }

        if ($password !== $cpassword) {
            $errors[] = 'Password-et nuk perputhen.';
        }

        // Kontroll dublikat + insert
        if (empty($errors)) {
            try {
                // Kontroll email ose username ekzistues
                $stmt = $conn->prepare(
                    "SELECT id FROM regjistrimi WHERE email = ? OR username = ? LIMIT 1"
                );
                $stmt->bind_param("ss", $email, $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $errors[] = 'Ky email ose username është regjistruar më parë.';
                } else {
                    // Hash password
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare(
                        "INSERT INTO regjistrimi (username, email, password) VALUES (?, ?, ?)"
                    );
                    $stmt->bind_param("sss", $username, $email, $hash);
                    $stmt->execute();

                    $_SESSION['flash_success'] = 'Llogaria u krijua me sukses! Tani mund te hyni.';
                    redirect('login.php');
                }
                $stmt->close();

            } catch (mysqli_sql_exception $e) {
                error_log("Signup error: " . $e->getMessage());

                // Nese eshte dublikat trajtoje
                if ($e->getCode() === 1062) {
                    $errors[] = 'Ky email ose username ekziston tashme.';
                } else {
                    $errors[] = 'Diqka shkoi gabim. Provo perseri.';
                }
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
    <title>Sign Up - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
   <!-- <nav class="navbar">
    <a href="index.php" class="logo">To-Do List</a>
    <div class="nav-links">
        <a href="index.php" class="btn-outline">← Kthehu</a>
        <a href="login.php" class="btn">Login</a>
    </div>
</nav> -->
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<div class="auth-box">
    <h1>Signup</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="signupForm" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Username</label>
        <input type="text" name="username" maxlength="20"
               value="<?= e($old['username']) ?>" required>

        <label>Email</label>
        <input type="email" name="email"
               value="<?= e($old['email']) ?>" required>

        <label>Password</label>
        <input type="password" name="password" id="password" required>

        <label>Confirm Password</label>
        <input type="password" name="cpassword" id="cpassword" required>

        <button type="submit">Regjistrohu</button>
    </form>

    <p>Ke llogari? <a href="login.php">Login</a></p>
    <p><a href="index.php">← Kthehu ne faqen kryesore</a></p>
</div>
<script src="assets/script.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>