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

// Ellenőrizzük, hogy a csoport azonosító meg van-e adva
if (!isset($_GET['csoportId'])) {
    header("Location: csoportok.php");
    exit;
}

$csoportId = $_GET['csoportId'];
$userId = $_SESSION['user_id'];

// Csoport adatainak lekérdezése
$query = "SELECT * FROM CSOPORT WHERE CSOPORTID = :csoportId";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":csoportId", $csoportId);
oci_execute($statement);
$csoport = oci_fetch_array($statement, OCI_ASSOC);
oci_free_statement($statement);

// Ellenőrizzük, hogy létezik-e a csoport
if (!$csoport) {
    header("Location: csoportok.php");
    exit;
}

// Ellenőrizzük, hogy a felhasználó tagja-e a csoportnak
$query = "SELECT * FROM TAGSAG WHERE CSOPORTID = :csoportId AND MEGHIVOTTUSERID = :userId AND ALLAPOT = 'elfogadva'";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":csoportId", $csoportId);
oci_bind_by_name($statement, ":userId", $userId);
oci_execute($statement);
$tagsag = oci_fetch_array($statement, OCI_ASSOC);
oci_free_statement($statement);

if (!$tagsag && $csoport['LETREHOZO'] != $userId) {
    header("Location: csoportok.php?hiba=nincs_jogosultsag");
    exit;
}

// Új bejegyzés létrehozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uzenet'])) {
    $szoveg = $_POST['uzenet'];
    
    // Új bejegyzés beszúrása az adatbázisba
    $insert_query = "INSERT INTO UZENOFAL (szoveg, userId, csoportId, datum) VALUES (:szoveg, :userId, :csoportId, CURRENT_TIMESTAMP)";
    $insert_statement = oci_parse($conn, $insert_query);
    
    oci_bind_by_name($insert_statement, ":szoveg", $szoveg);
    oci_bind_by_name($insert_statement, ":userId", $userId);
    oci_bind_by_name($insert_statement, ":csoportId", $csoportId);
    
    $success = oci_execute($insert_statement);
    oci_free_statement($insert_statement);
    
    // Az oldal újratöltése a bejegyzés után
    if ($success) {
        header("Location: groupchat.php?csoportId=" . $csoportId);
        exit;
    }
}

// Hozzászólás létrehozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['valasz']) && isset($_POST['bejegyzesId'])) {
    $szoveg = $_POST['valasz'];
    $bejegyzesId = $_POST['bejegyzesId'];
    
    // Új hozzászólás beszúrása az adatbázisba
    $insert_query = "INSERT INTO HOZZASZOLAS (komszoveg, bejegyzesId, letrehozo, datum) VALUES (:szoveg, :bejegyzesId, :userId, CURRENT_TIMESTAMP)";
    $insert_statement = oci_parse($conn, $insert_query);
    
    oci_bind_by_name($insert_statement, ":szoveg", $szoveg);
    oci_bind_by_name($insert_statement, ":bejegyzesId", $bejegyzesId);
    oci_bind_by_name($insert_statement, ":userId", $userId);
    
    $success = oci_execute($insert_statement);
    oci_free_statement($insert_statement);
    
    // Az oldal újratöltése a hozzászólás után
    if ($success) {
        header("Location: groupchat.php?csoportId=" . $csoportId);
        exit;
    }
}

// Bejegyzések lekérdezése a csoportból
$query = "SELECT u.bejegyzesId, u.szoveg, u.datum, f.nev, f.userId 
          FROM UZENOFAL u 
          JOIN FELHASZNALO f ON u.userId = f.userId 
          WHERE u.csoportId = :csoportId 
          ORDER BY u.datum DESC";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":csoportId", $csoportId);
oci_execute($statement);
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?php echo $csoport['CSOPORTNEV']; ?> - Linksy</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div id="container">
<div class="navbar">
  <div class="navbar-brand">
    <a href="chat.php">Linksy</a>
  </div>
  <div class="navbar-links">
    <a href="chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">Főoldal</a>
    <a href="uzenet.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'uzenet.php' ? 'active' : ''; ?>">Üzenetek</a>
    <a href="kereses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kereses.php' ? 'active' : ''; ?>">Keresés</a>
    <a href="profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">Profil</a>
    <a href="kijelentkezes.php">Kijelentkezés</a>
  </div>
</div>
</div>
<div id="bal">
    <p id="cim2"><?php echo $csoport['CSOPORTNEV']; ?></p>
    
    <div id="iras">
        <form method="POST">
            <textarea id="uzeno" name="uzenet" maxlength="1000" placeholder="Üzenet írása" required></textarea>
            <button id="postolas" type="submit">Küldés</button>
        </form>
    </div>
    
    <?php
    // Bejegyzések és hozzászólások megjelenítése
    while ($bejegyzes = oci_fetch_array($statement, OCI_ASSOC)) {
        echo '<div class="bejegyzes">';
        echo '<p class="nev">' . $bejegyzes['NEV'] . ' - ' . $bejegyzes['DATUM'] . '</p>';
        echo '<p>' . $bejegyzes['SZOVEG'] . '</p>';
        
        // Ha a felhasználó a saját bejegyzésénél van, akkor törlés gomb megjelenítése
        if ($bejegyzes['USERID'] == $userId) {
            echo '<form method="POST" action="torles.php">';
            echo '<input type="hidden" name="bejegyzesId" value="' . $bejegyzes['BEJEGYZESID'] . '">';
            echo '<input type="hidden" name="csoportId" value="' . $csoportId . '">';
            echo '<button type="submit" class="torles">Törlés</button>';
            echo '</form>';
        }
        
        // Hozzászólások lekérdezése a bejegyzéshez
        $komment_query = "SELECT h.komszoveg, h.datum, f.nev, h.kommentId, f.userId 
                         FROM HOZZASZOLAS h 
                         JOIN FELHASZNALO f ON h.letrehozo = f.userId 
                         WHERE h.bejegyzesId = :bejegyzesId 
                         ORDER BY h.datum ASC";
        $komment_statement = oci_parse($conn, $komment_query);
        oci_bind_by_name($komment_statement, ":bejegyzesId", $bejegyzes['BEJEGYZESID']);
        oci_execute($komment_statement);
        
        echo '<div class="komment">';
        
        // Hozzászólások megjelenítése
        while ($komment = oci_fetch_array($komment_statement, OCI_ASSOC)) {
            echo '<div class="valasz">';
            echo '<p class="valasz_nev">' . $komment['NEV'] . ' - ' . $komment['DATUM'] . '</p>';
            echo '<p class="valaszok">' . $komment['KOMSZOVEG'] . '</p>';
            
            // Ha a felhasználó a saját hozzászólásánál van, akkor törlés gomb megjelenítése
            if ($komment['USERID'] == $userId) {
                echo '<form method="POST" action="torles_hozzaszolas.php">';
                echo '<input type="hidden" name="kommentId" value="' . $komment['KOMMENTID'] . '">';
                echo '<input type="hidden" name="csoportId" value="' . $csoportId . '">';
                echo '<button type="submit" class="torles">Törlés</button>';
                echo '</form>';
            }
            
            echo '</div>';
        }
        oci_free_statement($komment_statement);
        
        // Új hozzászólás form
        echo '<form method="POST">';
        echo '<textarea id="valasz" name="valasz" maxlength="1000" placeholder="Válasz" required></textarea>';
        echo '<input type="hidden" name="bejegyzesId" value="' . $bejegyzes['BEJEGYZESID'] . '">';
        echo '<button id="send" type="submit">Küldés</button>';
        echo '</form>';
        
        echo '</div>'; // komment div vége
        echo '</div>'; // bejegyzes div vége
    }
    oci_free_statement($statement);
    ?>
</div>

<?php
// Adatbázis kapcsolat bezárása
oci_close($conn);
?>
</body>
</html>