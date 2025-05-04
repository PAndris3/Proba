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

// Ellenőrizzük, hogy érkezett-e kép azonosító
if (!isset($_POST['imgId'])) {
    header("Location: profil.php?uzenet=hianyzo_azonosito");
    exit;
}

$imgId = $_POST['imgId'];

// Ellenőrizzük, hogy a kép valóban a felhasználóhoz tartozik-e
$check_query = "SELECT userId FROM FENYKEPEK WHERE imgId = :imgId";
$check_statement = oci_parse($conn, $check_query);
oci_bind_by_name($check_statement, ":imgId", $imgId);
oci_execute($check_statement);
$img_data = oci_fetch_array($check_statement, OCI_ASSOC);
oci_free_statement($check_statement);

if (!$img_data || $img_data['USERID'] != $userId) {
    // Ha a kép nem létezik vagy nem a felhasználóhoz tartozik
    header("Location: profil.php?uzenet=nincs_jogosultsag");
    exit;
}

// Először töröljük az albumból való kapcsolatokat
$delete_album_query = "DELETE FROM ALBUMBATARTOZIK WHERE imgId = :imgId";
$delete_album_statement = oci_parse($conn, $delete_album_query);
oci_bind_by_name($delete_album_statement, ":imgId", $imgId);
oci_execute($delete_album_statement);
oci_free_statement($delete_album_statement);

// Töröljük a képhez tartozó hozzászólásokat
$delete_comments_query = "DELETE FROM HOZZASZOLAS WHERE imgId = :imgId";
$delete_comments_statement = oci_parse($conn, $delete_comments_query);
oci_bind_by_name($delete_comments_statement, ":imgId", $imgId);
oci_execute($delete_comments_statement);
oci_free_statement($delete_comments_statement);

// Végül töröljük magát a képet
$delete_query = "DELETE FROM FENYKEPEK WHERE imgId = :imgId";
$delete_statement = oci_parse($conn, $delete_query);
oci_bind_by_name($delete_statement, ":imgId", $imgId);
$success = oci_execute($delete_statement);
oci_free_statement($delete_statement);

if ($success) {
    // Siker esetén visszairányítjuk a profilra egy üzenettel
    header("Location: profil.php?uzenet=kep_torolve");
} else {
    // Hiba esetén visszairányítjuk a profilra egy hibaüzenettel
    $e = oci_error($delete_statement);
    header("Location: profil.php?uzenet=hiba_a_torles_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>