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

// Ellenőrizzük, hogy érkezett-e másik felhasználó azonosító
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['masikId'])) {
    $masikId = $_POST['masikId'];

    // Üzenetek törlése a két felhasználó között
    $delete_query = "DELETE FROM UZENET 
                     WHERE (kuldoUserId = :userId AND fogadoUserId = :masikId) 
                        OR (kuldoUserId = :masikId AND fogadoUserId = :userId)";
    $delete_statement = oci_parse($conn, $delete_query);
    oci_bind_by_name($delete_statement, ":userId", $userId);
    oci_bind_by_name($delete_statement, ":masikId", $masikId);

    $success = oci_execute($delete_statement);
    oci_free_statement($delete_statement);

    // Átirányítás az eredmény alapján
    if ($success) {
        header("Location: uzenet.php?uzenet=beszelgetes_torolva");
    } else {
        $e = oci_error($delete_statement);
        header("Location: uzenet.php?uzenet=hiba_a_torles_soran:" . urlencode($e['message']));
    }
    exit;
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
header("Location: uzenet.php?uzenet=ervenytelen_keres");
exit;
?>