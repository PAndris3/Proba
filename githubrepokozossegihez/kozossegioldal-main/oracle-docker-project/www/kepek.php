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

// Ellenőrizzük, hogy a kép azonosító meg van-e adva
if (!isset($_GET['imgId'])) {
   header("Location: albumok.php");
   exit;
}

$imgId = $_GET['imgId'];
$userId = $_SESSION['user_id'];

// Kép adatainak lekérdezése
$query = "SELECT f.*, fe.nev AS feltolto_nev 
         FROM FENYKEPEK f 
         JOIN FELHASZNALO fe ON f.userId = fe.userId 
         WHERE f.imgId = :imgId";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":imgId", $imgId);
oci_execute($statement);
$kep = oci_fetch_array($statement, OCI_ASSOC);
oci_free_statement($statement);

// Ellenőrizzük, hogy létezik-e a kép
if (!$kep) {
   header("Location: albumok.php");
   exit;
}

// Új hozzászólás létrehozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['valasz'])) {
   $szoveg = $_POST['valasz'];
   
   // Új hozzászólás beszúrása az adatbázisba
   $insert_query = "INSERT INTO HOZZASZOLAS (komszoveg, imgId, letrehozo, datum) 
                    VALUES (:szoveg, :imgId, :userId, CURRENT_TIMESTAMP)";
   $insert_statement = oci_parse($conn, $insert_query);
   
   oci_bind_by_name($insert_statement, ":szoveg", $szoveg);
   oci_bind_by_name($insert_statement, ":imgId", $imgId);
   oci_bind_by_name($insert_statement, ":userId", $userId);
   
   $success = oci_execute($insert_statement);
   oci_free_statement($insert_statement);
   
   // Az oldal újratöltése a hozzászólás után
   if ($success) {
       header("Location: kepek.php?imgId=" . $imgId);
       exit;
   }
}

// Hozzászólások lekérdezése a képhez
$komment_query = "SELECT h.komszoveg, h.datum, f.nev, h.kommentId, f.userId 
                FROM HOZZASZOLAS h 
                JOIN FELHASZNALO f ON h.letrehozo = f.userId 
                WHERE h.imgId = :imgId 
                ORDER BY h.datum ASC";
$komment_statement = oci_parse($conn, $komment_query);
oci_bind_by_name($komment_statement, ":imgId", $imgId);
oci_execute($komment_statement);
?>

<!DOCTYPE html>
<html lang="hu">
<head>
 <meta charset="UTF-8">
 <title>Kép megtekintése - Linksy</title>
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
<div id="box">
   <div id="image">
     <?php
     // Kép megjelenítése
     // Megjegyzés: A BLOB adatok megjelenítése további konfigurációt igényelhet
     // Ez egy egyszerűsített megoldás, valós környezetben kép letöltő scriptet érdemes használni
     echo '<img src="kep_megjelenito.php?imgId=' . $imgId . '" alt="Feltöltött kép">';
     
     // Alternatív megoldás ha van feltöltött fájlok mappája
     // echo '<img src="kepek/vac2.jpg">';
     ?>
   </div>
   <div class="bejegyzes">
     <p id="cim">Hozzászólások</p>
     <p class="nev">Feltöltő: <?php echo $kep['FELTOLTO_NEV']; ?></p>
     <p>Feltöltés ideje: <?php echo $kep['DATUM']; ?></p>
     
     <?php
     // Hozzászólások megjelenítése
     while ($komment = oci_fetch_array($komment_statement, OCI_ASSOC)) {
       echo '<div class="komment">';
       echo '<p class="nev">' . $komment['NEV'] . '</p>';
       echo '<p>' . $komment['KOMSZOVEG'] . '</p>';
       echo '<p class="datum">' . $komment['DATUM'] . '</p>';
       
       // Ha a felhasználó a saját hozzászólásánál van, akkor törlés gomb megjelenítése
       if ($komment['USERID'] == $userId) {
         echo '<form method="POST" action="torles_hozzaszolas.php">';
         echo '<input type="hidden" name="kommentId" value="' . $komment['KOMMENTID'] . '">';
         echo '<input type="hidden" name="imgId" value="' . $imgId . '">';
         echo '<button type="submit" class="torles">Törlés</button>';
         echo '</form>';
       }
       
       echo '</div>';
     }
     oci_free_statement($komment_statement);
     ?>
     
     <form method="POST">
       <textarea id="valasz" name="valasz" maxlength="1000" placeholder="Írj választ!" required></textarea>
       <button id="send" type="submit">Küldés</button>
     </form>
   </div>
</div>

<?php
// Adatbázis kapcsolat bezárása
oci_close($conn);
?>
</body>
</html>