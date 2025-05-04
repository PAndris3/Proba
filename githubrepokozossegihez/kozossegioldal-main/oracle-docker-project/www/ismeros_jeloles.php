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

// Ellenőrizzük, hogy érkezett-e jelölt azonosító
if (!isset($_POST['jelolt_id'])) {
    header("Location: kereses.php?uzenet=hianyzo_jelolt_azonosito");
    exit;
}

$jelolt_id = $_POST['jelolt_id'];

// Ellenőrizzük, hogy a jelölt létezik-e
$check_query = "SELECT userId FROM FELHASZNALO WHERE userId = :jelolt_id AND allapot != 'törölt'";
$check_statement = oci_parse($conn, $check_query);
oci_bind_by_name($check_statement, ":jelolt_id", $jelolt_id);
oci_execute($check_statement);
$jelolt_data = oci_fetch_array($check_statement, OCI_ASSOC);
oci_free_statement($check_statement);

if (!$jelolt_data) {
    header("Location: kereses.php?uzenet=nem_letezo_felhasznalo");
    exit;
}

// Ellenőrizzük, hogy nincs-e már kapcsolat a két felhasználó között
$relation_query = "SELECT allapot FROM ISMERETSEG 
                  WHERE (userId = :userId AND ismerosUserId = :jelolt_id)
                  OR (userId = :jelolt_id AND ismerosUserId = :userId)";
$relation_statement = oci_parse($conn, $relation_query);
oci_bind_by_name($relation_statement, ":userId", $userId);
oci_bind_by_name($relation_statement, ":jelolt_id", $jelolt_id);
oci_execute($relation_statement);
$relation_data = oci_fetch_array($relation_statement, OCI_ASSOC);
oci_free_statement($relation_statement);

if ($relation_data) {
    header("Location: kereses.php?uzenet=mar_letezik_kapcsolat");
    exit;
}

// Létrehozzuk az ismerősnek jelölést
$insert_query = "INSERT INTO ISMERETSEG (userId, ismerosUserId, allapot) VALUES (:userId, :jelolt_id, 'várakozik')";
$insert_statement = oci_parse($conn, $insert_query);
oci_bind_by_name($insert_statement, ":userId", $userId);
oci_bind_by_name($insert_statement, ":jelolt_id", $jelolt_id);
$success = oci_execute($insert_statement);
oci_free_statement($insert_statement);

if ($success) {
    header("Location: kereses.php?uzenet=sikeres_jeloles");
} else {
    $e = oci_error($insert_statement);
    header("Location: kereses.php?uzenet=hiba_a_jeloles_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>