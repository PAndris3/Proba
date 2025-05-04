<?php
// Adatbázis kapcsolat és session kezelés
include 'db_connect.php';
session_start();

function deleteAlbumImage($conn, $albumId, $imgId)
{
    $delete_query = "DELETE FROM ALBUMBATARTOZIK WHERE albumId = :albumId AND imgId = :imgId";
    $delete_statement = oci_parse($conn, $delete_query);
    oci_bind_by_name($delete_statement, ":albumId", $albumId);
    oci_bind_by_name($delete_statement, ":imgId", $imgId);

    $success = oci_execute($delete_statement);
    oci_free_statement($delete_statement);

    return $success;
}

// Example usage
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['albumId'], $_POST['imgId'])) {
    $albumId = $_POST['albumId'];
    $imgId = $_POST['imgId'];

    if (deleteAlbumImage($conn, $albumId, $imgId)) {
        header("Location: profil.php?uzenet=kep_torolve");
    } else {
        $e = oci_error();
        header("Location: profil.php?uzenet=hiba_a_kep_torlese_soran:" . urlencode($e['message']));
    }
    exit;
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>