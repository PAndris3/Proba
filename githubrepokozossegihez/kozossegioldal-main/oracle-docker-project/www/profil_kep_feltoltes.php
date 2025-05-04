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
$uzenet = "";

// Profilkép feltöltés kezelése
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] == 0) {
    // Kép adatainak beolvasása
    $fileName = $_FILES['profile_img']['name'];
    $fileType = $_FILES['profile_img']['type'];
    $fileSize = $_FILES['profile_img']['size'];
    $fileTmpName = $_FILES['profile_img']['tmp_name'];
    
    // Ellenőrizzük, hogy képfájl-e
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (in_array($fileType, $allowed)) {
        // Kép beolvasása bináris formában
        $imageData = file_get_contents($fileTmpName);
        
        // Kép mentése az adatbázisba a felhasználó profilképeként
        $update_query = "UPDATE FELHASZNALO SET profilkep = EMPTY_BLOB() WHERE userId = :userId RETURNING profilkep INTO :profilkep";
        $statement = oci_parse($conn, $update_query);
        
        $blob = oci_new_descriptor($conn, OCI_D_LOB);
        oci_bind_by_name($statement, ":userId", $userId);
        oci_bind_by_name($statement, ":profilkep", $blob, -1, OCI_B_BLOB);
        
        $success = oci_execute($statement, OCI_DEFAULT);
        
        if ($success && $blob->save($imageData)) {
            oci_commit($conn);
            $uzenet = "A profilkép sikeresen frissítve!";
        } else {
            oci_rollback($conn);
            $uzenet = "Hiba történt a profilkép frissítésekor!";
        }
        
        $blob->free();
        oci_free_statement($statement);
    } else {
        $uzenet = "Csak JPG, PNG vagy GIF formátumú képeket lehet feltölteni!";
    }
}

// Átirányítás vissza a profilra
header("Location: profil.php?uzenet=" . urlencode($uzenet));
exit;
?>