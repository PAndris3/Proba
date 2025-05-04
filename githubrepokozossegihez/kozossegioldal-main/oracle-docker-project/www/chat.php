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

// A felhasználó adatainak lekérdezése
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM FELHASZNALO WHERE USERID = :userId";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":userId", $userId);
oci_execute($statement);
$felhasznalo = oci_fetch_array($statement, OCI_ASSOC);
oci_free_statement($statement);

// Új bejegyzés létrehozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uzenet'])) {
    $szoveg = $_POST['uzenet'];

    // Új bejegyzés beszúrása az adatbázisba
    $insert_query = "INSERT INTO UZENOFAL (szoveg, userId, datum) VALUES (:szoveg, :userId, CURRENT_TIMESTAMP)";
    $insert_statement = oci_parse($conn, $insert_query);

    oci_bind_by_name($insert_statement, ":szoveg", $szoveg);
    oci_bind_by_name($insert_statement, ":userId", $userId);

    $success = oci_execute($insert_statement);
    oci_free_statement($insert_statement);

    // Az oldal újratöltése a bejegyzés után
    if ($success) {
        header("Location: chat.php");
        exit;
    }
}

// Bejegyzések lekérdezése az üzenőfalról
$query = "SELECT u.bejegyzesId, u.szoveg, u.datum, f.nev, f.userId 
          FROM UZENOFAL u 
          JOIN FELHASZNALO f ON u.userId = f.userId 
          WHERE u.csoportId IS NULL 
          AND f.allapot NOT IN ('archivált', 'inaktív') 
          ORDER BY u.datum DESC";
$statement = oci_parse($conn, $query);
oci_execute($statement);

// Query to fetch comments for each post
$comments_query = "SELECT h.komszoveg, h.datum, f.nev 
                   FROM Hozzaszolas h 
                   JOIN Felhasznalo f ON h.letrehozo = f.userId 
                   WHERE h.bejegyzesId = :bejegyzesId 
                   ORDER BY h.datum ASC";
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Főoldal</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">
            <a href="chat.php">Linksy</a>
        </div>
        <div class="navbar-links">
            <a href="chat.php"
                class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">Főoldal</a>
            <a href="uzenet.php"
                class="<?php echo basename($_SERVER['PHP_SELF']) == 'uzenet.php' ? 'active' : ''; ?>">Üzenetek</a>
            <a href="kereses.php"
                class="<?php echo basename($_SERVER['PHP_SELF']) == 'kereses.php' ? 'active' : ''; ?>">Keresés</a>
            <a href="profil.php"
                class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">Profil</a>
            <a href="kijelentkezes.php">Kijelentkezés</a>
        </div>
    </div>
    <div id="bal">
        <p id="cim2">Üdvözöljük, <?php echo $felhasznalo['NEV']; ?>!</p>

        <div id="iras">
            <form method="POST">
                <textarea id="uzeno" name="uzenet" maxlength="1000" placeholder="Üzenet írása" required></textarea>
                <button id="postolas" type="submit">Küldés</button>
            </form>
        </div>

        <?php
        // Bejegyzések megjelenítése
        while ($bejegyzes = oci_fetch_array($statement, OCI_ASSOC)) {
            echo '<div class="bejegyzes">';
            echo '<p class="nev">' . $bejegyzes['NEV'] . ' - ' . $bejegyzes['DATUM'] . '</p>';
            echo '<p>' . $bejegyzes['SZOVEG'] . '</p>';

            // Fetch and display comments for the current post
            $comments_statement = oci_parse($conn, $comments_query);
            oci_bind_by_name($comments_statement, ":bejegyzesId", $bejegyzes['BEJEGYZESID']);
            oci_execute($comments_statement);

            echo '<div class="komment">';
            while ($komment = oci_fetch_array($comments_statement, OCI_ASSOC)) {
                echo '<div class="valasz">';
                echo '<p class="valasz_nev">' . $komment['NEV'] . ' válasza - ' . $komment['DATUM'] . ':</p>';
                echo '<p class="valaszok">' . $komment['KOMSZOVEG'] . '</p>';
                echo '</div>';
            }
            oci_free_statement($comments_statement);
            echo '</div>';

            echo '<form method="POST" action="add_comment.php">';
            echo '<textarea id="valasz" name="komszoveg" maxlength="1000" placeholder="Írj egy hozzászólást!" required></textarea>';
            echo '<input type="hidden" name="bejegyzesId" value="' . $bejegyzes['BEJEGYZESID'] . '">';
            echo '<button id="send" type="submit">Küldés</button>';
            echo '</form>';

            if ($bejegyzes['USERID'] == $userId) {
                echo '<form method="POST" action="torles.php">';
                echo '<input type="hidden" name="bejegyzesId" value="' . $bejegyzes['BEJEGYZESID'] . '">';
                echo '<button type="submit" class="torles">Törlés</button>';
                echo '</form>';
            }

            echo '</div>';
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