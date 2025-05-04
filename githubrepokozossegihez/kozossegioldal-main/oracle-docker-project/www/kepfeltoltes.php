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
$uzenet = "";

// Kép feltöltés kezelése
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['feltoltes']) && $_FILES['feltoltes']['error'] == 0) {
    // Kép adatainak beolvasása
    $fileName = $_FILES['feltoltes']['name'];
    $fileType = $_FILES['feltoltes']['type'];
    $fileSize = $_FILES['feltoltes']['size'];
    $fileTmpName = $_FILES['feltoltes']['tmp_name'];
    
    // Ellenőrizzük, hogy képfájl-e
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (in_array($fileType, $allowed)) {
        // Kép beolvasása bináris formában
        $imageData = file_get_contents($fileTmpName);
        
        // Kép mentése az adatbázisba
        $insert_query = "INSERT INTO FENYKEPEK (kep, userId, datum) VALUES (EMPTY_BLOB(), :userId, CURRENT_TIMESTAMP) RETURNING kep INTO :kep";
        $statement = oci_parse($conn, $insert_query);
        
        $blob = oci_new_descriptor($conn, OCI_D_LOB);
        oci_bind_by_name($statement, ":userId", $userId);
        oci_bind_by_name($statement, ":kep", $blob, -1, OCI_B_BLOB);
        
        $success = oci_execute($statement, OCI_DEFAULT);
        
        if ($success && $blob->save($imageData)) {
            oci_commit($conn);
            $uzenet = "A kép sikeresen feltöltve!";
        } else {
            oci_rollback($conn);
            $uzenet = "Hiba történt a kép feltöltésekor!";
        }
        
        $blob->free();
        oci_free_statement($statement);
    } else {
        $uzenet = "Csak JPG, PNG vagy GIF formátumú képeket lehet feltölteni!";
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Kép feltöltése - Linksy</title>
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
<div id="container">
    <div id="title">Linksy</div>
    <div class="reg_button">
        <div class="button">
            <a class="link" href="profil.php">Vissza a profilhoz</a>
        </div>
    </div>
</div>

<div id="box" style="text-align: center; margin: 20px auto; padding: 20px; max-width: 600px;">
    <h2>Kép feltöltése</h2>
    
    <?php if (!empty($uzenet)): ?>
        <div class="uzenet" style="margin: 15px; padding: 10px; background-color: #f0f0f0;">
            <?php echo $uzenet; ?>
        </div>
    <?php endif; ?>
    
    <p>A kép feltöltése <?php echo !empty($uzenet) ? 'befejeződött' : 'folyamatban van'; ?>.</p>
    <p><a href="profil.php">Vissza a profilodhoz</a></p>
</div>

<?php
// Adatbázis kapcsolat bezárása
oci_close($conn);
?>
</body>
</html>