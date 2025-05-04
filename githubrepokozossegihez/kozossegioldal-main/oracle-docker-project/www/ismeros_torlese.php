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

// Ellenőrizzük, hogy érkezett-e a törlendő ismerős azonosítója
if (!isset($_POST['userId'])) {
    header("Location: profil.php?uzenet=hianyzo_ismeros_azonosito");
    exit;
}

$ismerosId = $_POST['userId'];

// Ellenőrizzük, hogy létezik-e ismeretségi kapcsolat
$check_query = "SELECT allapot FROM ISMERETSEG 
                WHERE ((userId = :userId AND ismerosUserId = :ismerosId) 
                OR (userId = :ismerosId AND ismerosUserId = :userId)) 
                AND allapot = 'elfogadva'";
$check_statement = oci_parse($conn, $check_query);
oci_bind_by_name($check_statement, ":userId", $userId);
oci_bind_by_name($check_statement, ":ismerosId", $ismerosId);
oci_execute($check_statement);
$ismeretseg = oci_fetch_array($check_statement, OCI_ASSOC);
oci_free_statement($check_statement);

if (!$ismeretseg) {
    header("Location: profil.php?uzenet=nincs_ilyen_ismeroseg");
    exit;
}

// Töröljük az ismeretségi kapcsolatot
$delete_query = "DELETE FROM ISMERETSEG 
                WHERE (userId = :userId AND ismerosUserId = :ismerosId) 
                OR (userId = :ismerosId AND ismerosUserId = :userId)";
$delete_statement = oci_parse($conn, $delete_query);
oci_bind_by_name($delete_statement, ":userId", $userId);
oci_bind_by_name($delete_statement, ":ismerosId", $ismerosId);
$success = oci_execute($delete_statement);
oci_free_statement($delete_statement);

if ($success) {
    // Visszatérés a megtekintett profilra, ha az URL-ben szerepel a back=profile paraméter
    if (isset($_POST['back']) && $_POST['back'] == 'profile') {
        header("Location: masik_profil.php?userId=" . $ismerosId . "&uzenet=ismeros_torolve");
    } else {
        header("Location: profil.php?uzenet=ismeros_torolve");
    }
} else {
    $e = oci_error($delete_statement);
    header("Location: profil.php?uzenet=hiba_az_ismeros_torlese_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>