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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['csoportId'])) {
    $csoportId = $_POST['csoportId'];

    // Ellenőrizzük, hogy a felhasználó a csoport létrehozója-e
    $check_query = "SELECT * FROM CSOPORT WHERE csoportId = :csoportId AND letrehozo = :userId";
    $check_statement = oci_parse($conn, $check_query);
    oci_bind_by_name($check_statement, ":csoportId", $csoportId);
    oci_bind_by_name($check_statement, ":userId", $userId);
    oci_execute($check_statement);

    if ($row = oci_fetch_array($check_statement, OCI_ASSOC)) {
        // Csoport törlése
        $delete_query = "DELETE FROM CSOPORT WHERE csoportId = :csoportId";
        $delete_statement = oci_parse($conn, $delete_query);
        oci_bind_by_name($delete_statement, ":csoportId", $csoportId);

        if (oci_execute($delete_statement)) {
            $_SESSION['uzenet'] = "Csoport sikeresen törölve!";
        } else {
            $_SESSION['uzenet'] = "Hiba történt a csoport törlése során.";
        }
        oci_free_statement($delete_statement);
    } else {
        $_SESSION['uzenet'] = "Nincs jogosultságod a csoport törlésére.";
    }

    oci_free_statement($check_statement);
    header("Location: uzenet.php");
    exit;
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>