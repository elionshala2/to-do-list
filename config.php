<?php
// config.php

$server   = "localhost";
$user     = "root";
$pass     = "";
$database = "todolist";

// fshehja e qdo gabimi prej outputit (shfaqen vetem ne log qe mos me i pa perdoruesi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($server, $user, $pass, $database);
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // gabimi ruhet vetem ne log
    error_log("DB Error: " . $e->getMessage());

    // perdoruesi e sheh vetem kete mesazh
    http_response_code(500);
    exit("Diqka shkoi gabim. Ju lutem provoni perseri me vone.");
}