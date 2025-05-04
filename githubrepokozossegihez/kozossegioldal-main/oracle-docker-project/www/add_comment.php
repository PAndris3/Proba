<?php
// Adatbázis kapcsolat és session kezelés
include 'db_connect.php';
session_start();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header("Location: bejelentkezes.php");
    exit;
}

// Ellenőrizzük, hogy a szükséges adatok meg vannak-e adva
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['komszoveg'], $_POST['bejegyzesId'])) {
    $komszoveg = trim($_POST['komszoveg']);
    $bejegyzesId = $_POST['bejegyzesId'];
    $userId = $_SESSION['user_id'];

    // Hozzászólás beszúrása az adatbázisba
    $insert_query = "INSERT INTO Hozzaszolas (komszoveg, bejegyzesId, letrehozo, datum) 
                     VALUES (:komszoveg, :bejegyzesId, :userId, CURRENT_TIMESTAMP)";
    $insert_statement = oci_parse($conn, $insert_query);

    oci_bind_by_name($insert_statement, ":komszoveg", $komszoveg);
    oci_bind_by_name($insert_statement, ":bejegyzesId", $bejegyzesId);
    oci_bind_by_name($insert_statement, ":userId", $userId);

    $success = oci_execute($insert_statement);
    oci_free_statement($insert_statement);

    // Visszairányítás az üzenőfalra
    if ($success) {
        header("Location: chat.php");
        exit;
    } else {
        echo "Hiba történt a hozzászólás mentésekor.";
    }
} else {
    header("Location: chat.php");
    exit;
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>