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

// Ellenőrizzük, hogy érkezett-e csoport azonosító és felhasználónév
if (!isset($_POST['csoportId']) || !isset($_POST['felhasznaloNev']) || empty(trim($_POST['felhasznaloNev']))) {
    header("Location: uzenet.php?uzenet=hianyzo_adatok");
    exit;
}

$csoportId = $_POST['csoportId'];
$felhasznaloNev = trim($_POST['felhasznaloNev']);

// Felhasználó keresése a FELHASZNALO táblában
$search_query = "SELECT userId FROM FELHASZNALO WHERE nev = :felhasznaloNev";
$search_statement = oci_parse($conn, $search_query);
oci_bind_by_name($search_statement, ":felhasznaloNev", $felhasznaloNev);
oci_execute($search_statement);
$meghivott = oci_fetch_array($search_statement, OCI_ASSOC);
oci_free_statement($search_statement);

if (!$meghivott) {
    // Ha nincs találat, visszairányítjuk hibaüzenettel
    header("Location: uzenet.php?uzenet=nem_letezo_felhasznalo");
    exit;
}

$meghivottUserId = $meghivott['USERID'];

// Check if an entry already exists in TAGSAG
$check_query = "SELECT * FROM TAGSAG WHERE csoportId = :csoportId AND meghivottUserId = :meghivottUserId";
$check_statement = oci_parse($conn, $check_query);
oci_bind_by_name($check_statement, ":csoportId", $csoportId);
oci_bind_by_name($check_statement, ":meghivottUserId", $meghivottUserId);
oci_execute($check_statement);
$existing_entry = oci_fetch_array($check_statement, OCI_ASSOC);
oci_free_statement($check_statement);

if ($existing_entry) {
    // Update the ALLAPOT to 'folyamatban' if the entry exists
    $update_query = "UPDATE TAGSAG SET allapot = 'folyamatban' 
                     WHERE csoportId = :csoportId AND meghivottUserId = :meghivottUserId";
    $update_statement = oci_parse($conn, $update_query);
    oci_bind_by_name($update_statement, ":csoportId", $csoportId);
    oci_bind_by_name($update_statement, ":meghivottUserId", $meghivottUserId);
    $success = oci_execute($update_statement);
    oci_free_statement($update_statement);
} else {
    // Insert a new entry if it does not exist
    $insert_query = "INSERT INTO TAGSAG (csoportId, meghivottUserId, userId, allapot) 
                     VALUES (:csoportId, :meghivottUserId, :userId, 'folyamatban')";
    $insert_statement = oci_parse($conn, $insert_query);
    oci_bind_by_name($insert_statement, ":csoportId", $csoportId);
    oci_bind_by_name($insert_statement, ":meghivottUserId", $meghivottUserId);
    oci_bind_by_name($insert_statement, ":userId", $userId);
    $success = oci_execute($insert_statement);
    oci_free_statement($insert_statement);
}

// Handle errors and redirect
if ($success) {
    header("Location: uzenet.php?uzenet=tag_hozzaadva");
} else {
    $e = oci_error(isset($insert_statement) ? $insert_statement : $update_statement);
    $error_message = $e ? $e['message'] : "Ismeretlen hiba";
    header("Location: uzenet.php?uzenet=hiba_a_hozzaadas_soran:" . urlencode($error_message));
    exit;
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>