<?php
$host = 'localhost';
$dbname = 'sasadiir_coupGmeDb';
$username = 'sasadiir_coupGmeDbUs';
$password = '8bItsXVc8H0V3mr8';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>