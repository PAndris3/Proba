<?php
// Adatbázis kapcsolat és session kezelés
include 'db_connect.php';
session_start();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    // Ha nincs bejelentkezve, átirányítjuk a bejelentkezési oldalra
    header("Location: bejelentkezes.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Ellenőrizzük, hogy érkezett-e album név
if (!isset($_POST['albumNev']) || empty($_POST['albumNev'])) {
    header("Location: profil.php?uzenet=hianyzo_album_nev");
    exit;
}

$albumNev = $_POST['albumNev'];

// Album létrehozása az adatbázisban
$insert_query = "INSERT INTO ALBUM (albumNev, userId) VALUES (:albumNev, :userId)";
$insert_statement = oci_parse($conn, $insert_query);

oci_bind_by_name($insert_statement, ":albumNev", $albumNev);
oci_bind_by_name($insert_statement, ":userId", $userId);

$success = oci_execute($insert_statement);
oci_free_statement($insert_statement);

if ($success) {
    // Siker esetén visszairányítjuk a profilra egy üzenettel
    header("Location: profil.php?uzenet=album_letrehozva");
} else {
    // Hiba esetén visszairányítjuk a profilra egy hibaüzenettel
    $e = oci_error($insert_statement);
    header("Location: profil.php?uzenet=hiba_az_album_letrehozasa_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>