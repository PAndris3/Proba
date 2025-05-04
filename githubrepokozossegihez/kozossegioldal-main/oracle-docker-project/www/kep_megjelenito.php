<?php
// Adatbázis kapcsolat
include 'db_connect.php';

// Ellenőrizzük, hogy a kép azonosító meg van-e adva
if (!isset($_GET['imgId'])) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$imgId = $_GET['imgId'];

// Kép adatainak lekérdezése
$query = "SELECT kep FROM FENYKEPEK WHERE imgId = :imgId";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":imgId", $imgId);
oci_execute($statement);
$row = oci_fetch_array($statement, OCI_ASSOC);

if ($row && isset($row['KEP'])) {
    // BLOB leíró megszerzése
    $image = $row['KEP']->load();
    
    // Kép típusának meghatározása (egyszerűsített)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image) ?: 'image/jpeg';
    
    // Kép fejlécek beállítása
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . strlen($image));
    
    // Kép kiküldése
    echo $image;
} else {
    // Ha nincs kép, küldünk egy alapértelmezett képet vagy hibát
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Adatbázis kapcsolat bezárása
oci_free_statement($statement);
oci_close($conn);
?>