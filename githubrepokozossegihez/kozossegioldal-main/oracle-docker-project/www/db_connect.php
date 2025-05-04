<?php
$username = 'system';  // vagy a saját felhasználóneved
$password = 'password'; // az adatbázis jelszavad
$charset = 'AL32UTF8';

// Oracle kapcsolat létrehozása a Docker szolgáltatásnévvel
$conn = oci_connect($username, $password, 'oracle_db:1521/XE', $charset);

if (!$conn) {
    $e = oci_error();
    echo "Kapcsolódási hiba: " . $e['message'];
    exit;
}
?>