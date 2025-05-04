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

// Ellenőrizzük, hogy érkezett-e ismerős azonosító
if (!isset($_POST['ismerosId'])) {
    header("Location: chat.php?uzenet=hianyzo_ismeros_azonosito");
    exit;
}

$ismerosId = $_POST['ismerosId'];

// Megnézzük, hogy elfogadás vagy visszautasítás történt
if (isset($_POST['elfogad'])) {
    $allapot = 'elfogadva';
} elseif (isset($_POST['visszaut'])) {
    $allapot = 'visszautasítva';
} else {
    header("Location: chat.php?uzenet=hianyzo_valasz_tipus");
    exit;
}

// Frissítjük az ismeretségi állapotot
$update_query = "UPDATE ISMERETSEG SET allapot = :allapot 
                WHERE userId = :ismerosId AND ismerosUserId = :userId AND allapot = 'várakozik'";
$update_statement = oci_parse($conn, $update_query);
oci_bind_by_name($update_statement, ":allapot", $allapot);
oci_bind_by_name($update_statement, ":ismerosId", $ismerosId);
oci_bind_by_name($update_statement, ":userId", $userId);
$success = oci_execute($update_statement);
oci_free_statement($update_statement);

if ($success) {
    header("Location: chat.php?uzenet=sikeres_" . ($allapot == 'elfogadva' ? 'elfogadas' : 'visszautasitas'));
} else {
    $e = oci_error($update_statement);
    header("Location: chat.php?uzenet=hiba_a_valasz_soran:" . urlencode($e['message']));
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>