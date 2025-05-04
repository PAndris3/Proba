<?php
// Adatbázis kapcsolat és session kezelés
include 'db_connect.php';
session_start();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header("Location: bejelentkezes.php");
    exit;
}

// Ellenőrizzük, hogy érkezett-e csoport azonosító és tag azonosító
if (!isset($_POST['csoportId']) || !isset($_POST['tagUserId'])) {
    header("Location: uzenet.php?uzenet=hianyzo_adatok");
    exit;
}

$csoportId = $_POST['csoportId'];
$tagUserId = $_POST['tagUserId'];

// Törlés a TAGSAG táblából
$delete_query = "DELETE FROM TAGSAG WHERE csoportId = :csoportId AND meghivottUserId = :tagUserId";
$delete_statement = oci_parse($conn, $delete_query);
oci_bind_by_name($delete_statement, ":csoportId", $csoportId);
oci_bind_by_name($delete_statement, ":tagUserId", $tagUserId);

$success = oci_execute($delete_statement);
oci_free_statement($delete_statement);

// Átirányítás az eredmény alapján
if ($success) {
    header("Location: uzenet.php?uzenet=tag_torolve");
} else {
    $e = oci_error($delete_statement);
    header("Location: uzenet.php?uzenet=hiba_a_torles_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>