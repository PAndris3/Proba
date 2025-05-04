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
if (!isset($_POST['albumId']) || empty($_POST['albumId'])) {
    header("Location: profil.php?uzenet=hianyzo_album_azonosito");
    exit;
}

$albumId = $_POST['albumId'];

// Ellenőrizzük, hogy az album a felhasználóhoz tartozik-e
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

// Kép feltöltés kezelése
if (isset($_FILES['album_kep']) && $_FILES['album_kep']['error'] == 0) {
    // Kép adatainak beolvasása
    $fileName = $_FILES['album_kep']['name'];
    $fileType = $_FILES['album_kep']['type'];
    $fileSize = $_FILES['album_kep']['size'];
    $fileTmpName = $_FILES['album_kep']['tmp_name'];
    
    // Ellenőrizzük, hogy képfájl-e
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (in_array($fileType, $allowed)) {
        // Kép beolvasása bináris formában
        $imageData = file_get_contents($fileTmpName);
        
        // Kép mentése az adatbázisba
        $insert_query = "INSERT INTO FENYKEPEK (kep, userId, datum) 
                        VALUES (EMPTY_BLOB(), :userId, CURRENT_TIMESTAMP) 
                        RETURNING imgId, kep INTO :imgId, :kep";
        $insert_statement = oci_parse($conn, $insert_query);
        
        $imgId = 0;
        $blob = oci_new_descriptor($conn, OCI_D_LOB);
        oci_bind_by_name($insert_statement, ":userId", $userId);
        oci_bind_by_name($insert_statement, ":imgId", $imgId, -1, OCI_B_INT);
        oci_bind_by_name($insert_statement, ":kep", $blob, -1, OCI_B_BLOB);
        
        $success = oci_execute($insert_statement, OCI_DEFAULT);
        
        if ($success && $blob->save($imageData)) {
            oci_commit($conn);
            
            // Kép hozzáadása az albumhoz
            $add_to_album_query = "INSERT INTO ALBUMBATARTOZIK (albumId, imgId) VALUES (:albumId, :imgId)";
            $add_to_album_statement = oci_parse($conn, $add_to_album_query);
            
            oci_bind_by_name($add_to_album_statement, ":albumId", $albumId);
            oci_bind_by_name($add_to_album_statement, ":imgId", $imgId);
            
            $album_success = oci_execute($add_to_album_statement);
            oci_free_statement($add_to_album_statement);
            
            if ($album_success) {
                header("Location: profil.php?uzenet=kep_hozzaadva_az_albumhoz");
            } else {
                // Hiba esetén visszairányítjuk a profilra egy hibaüzenettel
                $e = oci_error($add_to_album_statement);
                header("Location: profil.php?uzenet=hiba_a_kep_albumhoz_adasa_soran:" . urlencode($e['message']));
            }
        } else {
            oci_rollback($conn);
            header("Location: profil.php?uzenet=hiba_a_kep_feltoltese_soran");
        }
        
        $blob->free();
        oci_free_statement($insert_statement);
    } else {
        header("Location: profil.php?uzenet=nem_tamogatott_fajlformatum");
    }
} else {
    header("Location: profil.php?uzenet=nincs_kivalasztott_kep");
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>