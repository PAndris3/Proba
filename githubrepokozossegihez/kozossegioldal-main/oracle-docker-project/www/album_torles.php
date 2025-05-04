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

// Ellenőrizzük, hogy érkezett-e album azonosító
if (!isset($_POST['albumId'])) {
    header("Location: profil.php?uzenet=hianyzo_album_azonosito");
    exit;
}

$albumId = $_POST['albumId'];

// Ellenőrizzük, hogy az album valóban a felhasználóhoz tartozik-e
$check_query = "SELECT userId FROM ALBUM WHERE albumId = :albumId";
$check_statement = oci_parse($conn, $check_query);
oci_bind_by_name($check_statement, ":albumId", $albumId);
oci_execute($check_statement);
$album_data = oci_fetch_array($check_statement, OCI_ASSOC);
oci_free_statement($check_statement);

if (!$album_data || $album_data['USERID'] != $userId) {
    // Ha az album nem létezik vagy nem a felhasználóhoz tartozik
    header("Location: profil.php?uzenet=nincs_jogosultsag_az_albumhoz");
    exit;
}

// Először töröljük az albumhoz tartozó kapcsolatokat
$delete_relations_query = "DELETE FROM ALBUMBATARTOZIK WHERE albumId = :albumId";
$delete_relations_statement = oci_parse($conn, $delete_relations_query);
oci_bind_by_name($delete_relations_statement, ":albumId", $albumId);
oci_execute($delete_relations_statement);
oci_free_statement($delete_relations_statement);

// Végül töröljük magát az albumot
$delete_query = "DELETE FROM ALBUM WHERE albumId = :albumId";
$delete_statement = oci_parse($conn, $delete_query);
oci_bind_by_name($delete_statement, ":albumId", $albumId);
$success = oci_execute($delete_statement);
oci_free_statement($delete_statement);

if ($success) {
    // Siker esetén visszairányítjuk a profilra egy üzenettel
    header("Location: profil.php?uzenet=album_torolve");
} else {
    // Hiba esetén visszairányítjuk a profilra egy hibaüzenettel
    $e = oci_error($delete_statement);
    header("Location: profil.php?uzenet=hiba_az_album_torlese_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>