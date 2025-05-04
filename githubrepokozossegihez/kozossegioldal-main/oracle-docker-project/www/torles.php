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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bejegyzesId'])) {
  $bejegyzesId = $_POST['bejegyzesId'];

  // Ellenőrizzük, hogy a felhasználó a bejegyzés létrehozója-e
  $check_query = "SELECT * FROM UZENOFAL WHERE bejegyzesId = :bejegyzesId AND userId = :userId";
  $check_statement = oci_parse($conn, $check_query);
  oci_bind_by_name($check_statement, ":bejegyzesId", $bejegyzesId);
  oci_bind_by_name($check_statement, ":userId", $userId);
  oci_execute($check_statement);

  if ($row = oci_fetch_array($check_statement, OCI_ASSOC)) {
    // Bejegyzés törlése
    $delete_query = "DELETE FROM UZENOFAL WHERE bejegyzesId = :bejegyzesId";
    $delete_statement = oci_parse($conn, $delete_query);
    oci_bind_by_name($delete_statement, ":bejegyzesId", $bejegyzesId);

    if (oci_execute($delete_statement)) {
      $_SESSION['uzenet'] = "Bejegyzés sikeresen törölve!";
    } else {
      $_SESSION['uzenet'] = "Hiba történt a bejegyzés törlése során.";
    }
    oci_free_statement($delete_statement);
  } else {
    $_SESSION['uzenet'] = "Nincs jogosultságod a bejegyzés törlésére.";
  }

  oci_free_statement($check_statement);
  header("Location: chat.php");
  exit;
}

// Adatbázis kapcsolat bezárása
oci_close($conn);
?>
