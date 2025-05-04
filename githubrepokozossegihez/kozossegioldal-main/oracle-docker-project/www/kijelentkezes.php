<?php
// Session indítása
session_start();

// Session változók törlése
$_SESSION = array();

// Session cookie törlése
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Session megsemmisítése
session_destroy();

// Átirányítás a bejelentkezési oldalra
header("Location: bejelentkezes.php");
exit;
?>