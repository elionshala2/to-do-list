<?php
// logout.php

session_start();

// Fshi te gjitha variablat e sesionit
$_SESSION = [];

// Fshi cookien e sesionit nga browseri
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000, 
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;