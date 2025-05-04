<?php
// Adatbázis kapcsolat
include 'db_connect.php';

// Ellenőrizzük, hogy a felhasználó azonosító meg van-e adva
if (!isset($_GET['userId'])) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$userId = $_GET['userId'];

// Profilkép adatainak lekérdezése
$query = "SELECT profilkep FROM FELHASZNALO WHERE userId = :userId";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":userId", $userId);
oci_execute($statement);
$row = oci_fetch_array($statement, OCI_ASSOC);

if ($row && isset($row['PROFILKEP']) && $row['PROFILKEP']) {
    // BLOB leíró megszerzése
    $image = $row['PROFILKEP']->load();
    
    // Kép típusának meghatározása
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image) ?: 'image/jpeg';
    
    // Kép fejlécek beállítása
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . strlen($image));
    
    // Kép kiküldése
    echo $image;
} else {
    // Ha nincs kép, küldünk egy alapértelmezett képet
    header('Location: kepek/profilkep.png');
    exit;
}

// Adatbázis kapcsolat bezárása
oci_free_statement($statement);
oci_close($conn);
?>