<?php
// Adatbázis kapcsolat és session kezelés
include 'db_connect.php';
session_start();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header("Location: bejelentkezes.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Ellenőrizzük, hogy érkezett-e jelentett felhasználó azonosító és indok
if (!isset($_POST['jelentettId']) || !isset($_POST['indok']) || empty(trim($_POST['indok']))) {
    header("Location: chat.php?uzenet=hianyzo_adatok");
    exit;
}

$jelentettId = $_POST['jelentettId'];
$indok = trim($_POST['indok']);

// Jelentés beszúrása az adatbázisba
$insert_query = "INSERT INTO JELENTES (USERID, JELENTETTUSERID, INDOK) VALUES (:userId, :jelentettId, :indok)";
$insert_statement = oci_parse($conn, $insert_query);

oci_bind_by_name($insert_statement, ":userId", $userId);
oci_bind_by_name($insert_statement, ":jelentettId", $jelentettId);
oci_bind_by_name($insert_statement, ":indok", $indok);

$success = oci_execute($insert_statement);
oci_free_statement($insert_statement);

if ($success) {
    header("Location: masik_profil.php?userId=$jelentettId&uzenet=sikeres_jelentes");
} else {
    $e = oci_error($insert_statement);
    header("Location: masik_profil.php?userId=$jelentettId&uzenet=hiba_a_jelentes_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
exit;
?>