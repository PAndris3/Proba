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

// Felhasználó adatainak lekérdezése
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
        header("Location: uzenofal.php");
        exit;
    }
}

// Új hozzászólás létrehozása
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
        header("Location: uzenofal.php");
        exit;
    }
}

// Ismerősnek jelölések kezelése
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['elfogad']) && isset($_POST['ismerosId'])) {
        $ismerosId = $_POST['ismerosId'];

        // Ismerősség elfogadása
        $update_query = "UPDATE ISMERETSEG SET allapot = 'elfogadva' 
                       WHERE ismerosUserId = :userId AND userId = :ismerosId AND allapot = 'várakozik'";
        $update_statement = oci_parse($conn, $update_query);

        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":ismerosId", $ismerosId);

        $success = oci_execute($update_statement);
        oci_free_statement($update_statement);

        if ($success) {
            header("Location: uzenofal.php");
            exit;
        }
    } elseif (isset($_POST['visszaut']) && isset($_POST['ismerosId'])) {
        $ismerosId = $_POST['ismerosId'];

        // Ismerősség visszautasítása
        $update_query = "UPDATE ISMERETSEG SET allapot = 'visszautasítva' 
                       WHERE ismerosUserId = :userId AND userId = :ismerosId AND allapot = 'várakozik'";
        $update_statement = oci_parse($conn, $update_query);

        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":ismerosId", $ismerosId);

        $success = oci_execute($update_statement);
        oci_free_statement($update_statement);

        if ($success) {
            header("Location: uzenofal.php");
            exit;
        }
    } elseif (isset($_POST['elfogad_csoport']) && isset($_POST['csoportId'])) {
        $csoportId = $_POST['csoportId'];

        // Csoport meghívás elfogadása
        $update_query = "UPDATE TAGSAG SET allapot = 'elfogadva' 
                       WHERE meghivottUserId = :userId AND csoportId = :csoportId AND allapot = 'várakozik'";
        $update_statement = oci_parse($conn, $update_query);

        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":csoportId", $csoportId);

        $success = oci_execute($update_statement);
        oci_free_statement($update_statement);

        if ($success) {
            header("Location: uzenofal.php");
            exit;
        }
    } elseif (isset($_POST['visszaut_csoport']) && isset($_POST['csoportId'])) {
        $csoportId = $_POST['csoportId'];

        // Csoport meghívás visszautasítása
        $update_query = "UPDATE TAGSAG SET allapot = 'visszautasítva' 
                       WHERE meghivottUserId = :userId AND csoportId = :csoportId AND allapot = 'várakozik'";
        $update_statement = oci_parse($conn, $update_query);

        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":csoportId", $csoportId);

        $success = oci_execute($update_statement);
        oci_free_statement($update_statement);

        if ($success) {
            header("Location: uzenofal.php");
            exit;
        }
    }
}

// Bejegyzések lekérdezése
$bejegyzesek_query = "SELECT u.bejegyzesId, u.szoveg, u.datum, f.nev, f.userId 
                    FROM UZENOFAL u 
                    JOIN FELHASZNALO f ON u.userId = f.userId 
                    WHERE u.csoportId IS NULL 
                    AND f.allapot NOT IN ('archivált', 'inaktív') 
                    ORDER BY u.datum DESC";
$bejegyzesek_statement = oci_parse($conn, $bejegyzesek_query);
oci_execute($bejegyzesek_statement);

// Ismerősnek jelölések lekérdezése
$jeloles_query = "SELECT i.userId AS ismerosId, f.nev 
                FROM ISMERETSEG i 
                JOIN FELHASZNALO f ON i.userId = f.userId 
                WHERE i.ismerosUserId = :userId AND i.allapot = 'várakozik'";
$jeloles_statement = oci_parse($conn, $jeloles_query);
oci_bind_by_name($jeloles_statement, ":userId", $userId);
oci_execute($jeloles_statement);

// Ajánlott ismerősök lekérdezése (csak példa lekérdezés - közös ismerősök alapján)
$ajanlas_query = "SELECT DISTINCT f.userId, f.nev
                FROM FELHASZNALO f
                WHERE f.userId != :userId
                AND f.allapot NOT IN ('archivált', 'inaktív')
                AND f.userId NOT IN (
                    SELECT i.ismerosUserId FROM ISMERETSEG i WHERE i.userId = :userId
                    UNION
                    SELECT i.userId FROM ISMERETSEG i WHERE i.ismerosUserId = :userId
                )
                AND EXISTS (
                    SELECT 1 FROM ISMERETSEG i1, ISMERETSEG i2
                    WHERE (i1.userId = :userId AND i1.allapot = 'elfogadva' AND
                          (i2.userId = i1.ismerosUserId AND i2.ismerosUserId = f.userId AND i2.allapot = 'elfogadva'
                          OR
                          i2.ismerosUserId = i1.ismerosUserId AND i2.userId = f.userId AND i2.allapot = 'elfogadva'))
                    OR
                    (i1.ismerosUserId = :userId AND i1.allapot = 'elfogadva' AND
                          (i2.userId = i1.userId AND i2.ismerosUserId = f.userId AND i2.allapot = 'elfogadva'
                          OR
                          i2.ismerosUserId = i1.userId AND i2.userId = f.userId AND i2.allapot = 'elfogadva'))
                )
                AND ROWNUM <= 5";
$ajanlas_statement = oci_parse($conn, $ajanlas_query);
oci_bind_by_name($ajanlas_statement, ":userId", $userId);
oci_execute($ajanlas_statement);

// Csoport meghívások lekérdezése
$meghivas_query = "SELECT t.csoportId, c.csoportnev 
                 FROM TAGSAG t 
                 JOIN CSOPORT c ON t.csoportId = c.csoportId 
                 WHERE t.meghivottUserId = :userId AND t.allapot = 'várakozik'";
$meghivas_statement = oci_parse($conn, $meghivas_query);
oci_bind_by_name($meghivas_statement, ":userId", $userId);
oci_execute($meghivas_statement);
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Főoldal - Linksy</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div id="container">
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
    </div>
    <div id="box">
        <div id="bal">
            <div id="iras">
                <form method="POST">
                    <textarea id="uzeno" name="uzenet" maxlength="1000" placeholder="Írj egy új bejegyzést!"
                        required></textarea>
                    <button id="postolas" type="submit">Küldés</button>
                </form>
            </div>

            <?php
            // Bejegyzések és hozzászólások megjelenítése
            while ($bejegyzes = oci_fetch_array($bejegyzesek_statement, OCI_ASSOC)) {
                echo '<div class="bejegyzes">';
                echo '<p class="nev">' . $bejegyzes['NEV'] . '</p>';
                echo '<p>' . $bejegyzes['SZOVEG'] . '</p>';
                echo '<p class="datum">' . $bejegyzes['DATUM'] . '</p>';

                // Ha a felhasználó a saját bejegyzésénél van, akkor törlés gomb megjelenítése
                if ($bejegyzes['USERID'] == $userId) {
                    echo '<form method="POST" action="torles.php">';
                    echo '<input type="hidden" name="bejegyzesId" value="' . $bejegyzes['BEJEGYZESID'] . '">';
                    echo '<button type="submit" class="torles">Törlés</button>';
                    echo '</form>';
                }

                echo '<hr class="line">';
                echo '<p>Válaszok</p>';

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
                    echo '<p class="valasz_nev">' . $komment['NEV'] . '</p>';
                    echo '<p class="valaszok">' . $komment['KOMSZOVEG'] . '</p>';
                    echo '<p class="valasz_datum">' . $komment['DATUM'] . '</p>';

                    // Ha a felhasználó a saját hozzászólásánál van, akkor törlés gomb megjelenítése
                    if ($komment['USERID'] == $userId) {
                        echo '<form method="POST" action="torles_hozzaszolas.php">';
                        echo '<input type="hidden" name="kommentId" value="' . $komment['KOMMENTID'] . '">';
                        echo '<button type="submit" class="torles">Törlés</button>';
                        echo '</form>';
                    }
                }
                oci_free_statement($komment_statement);

                // Új hozzászólás form
                echo '<form method="POST">';
                echo '<textarea id="valasz" name="valasz" maxlength="1000" placeholder="Írj választ!" required></textarea>';
                echo '<input type="hidden" name="bejegyzesId" value="' . $bejegyzes['BEJEGYZESID'] . '">';
                echo '<input id="kep" name="kep" type="file">';
                echo '<button id="send" type="submit">Küldés</button>';

                echo '</form>';

                echo '</div>'; // komment div vége
                echo '</div>'; // bejegyzes div vége
            }
            oci_free_statement($bejegyzesek_statement);
            ?>
        </div>
        <div id="jobb">
            <h2>Ismerősnek jelölések</h2>
            <div class="ismeros">
                <?php
                // Ismerősnek jelölések lekérdezése
                $jeloles_query = "SELECT i.userId AS ismerosId, f.nev 
                        FROM ISMERETSEG i 
                        JOIN FELHASZNALO f ON i.userId = f.userId 
                        WHERE i.ismerosUserId = :userId AND i.allapot = 'várakozik'";
                $jeloles_statement = oci_parse($conn, $jeloles_query);
                oci_bind_by_name($jeloles_statement, ":userId", $userId);
                oci_execute($jeloles_statement);

                $van_jeloles = false;

                while ($jeloles = oci_fetch_array($jeloles_statement, OCI_ASSOC)) {
                    $van_jeloles = true;
                    echo '<div class="ismeros-item">';
                    echo '<p>' . $jeloles['NEV'] . '</p>';
                    echo '<div class="gomb">';
                    echo '<form method="POST" action="ismeros_valasz.php" style="display: inline;">';
                    echo '<input type="hidden" name="ismerosId" value="' . $jeloles['ISMEROSID'] . '">';
                    echo '<button class="elfogad" name="elfogad" type="submit">&#10003;</button>';
                    echo '</form>';
                    echo '<form method="POST" action="ismeros_valasz.php" style="display: inline;">';
                    echo '<input type="hidden" name="ismerosId" value="' . $jeloles['ISMEROSID'] . '">';
                    echo '<button class="visszaut" name="visszaut" type="submit">&#10005;</button>';
                    echo '</form>';
                    echo '</div>';
                    echo '</div>';
                }

                if (!$van_jeloles) {
                    echo '<p>Nincsenek függőben lévő ismerősnek jelölések.</p>';
                }

                oci_free_statement($jeloles_statement);
                ?>
            </div>

            <h2>Ajánlások</h2>
            <?php
            // Ajánlott ismerősök megjelenítése
            while ($ajanlas = oci_fetch_array($ajanlas_statement, OCI_ASSOC)) {
                echo '<a class="ajanlas" href="masik_profil.php?userId=' . $ajanlas['USERID'] . '">' . $ajanlas['NEV'] . '</a><br>';
            }
            oci_free_statement($ajanlas_statement);
            ?>

            <h2>Csoport meghívások</h2>
            <?php
            // Csoport meghívások megjelenítése
            while ($meghivas = oci_fetch_array($meghivas_statement, OCI_ASSOC)) {
                echo '<div class="ismeros">';
                echo '<p>' . $meghivas['CSOPORTNEV'] . '</p>';
                echo '<div class="gomb">';
                echo '<form method="POST" style="display: inline;">';
                echo '<input type="hidden" name="csoportId" value="' . $meghivas['CSOPORTID'] . '">';
                echo '<button class="elfogad" name="elfogad_csoport" type="submit">&#10003;</button>';
                echo '</form>';
                echo '<form method="POST" style="display: inline;">';
                echo '<input type="hidden" name="csoportId" value="' . $meghivas['CSOPORTID'] . '">';
                echo '<button class="visszaut" name="visszaut_csoport" type="submit">&#10005;</button>';
                echo '</form>';
                echo '</div>';
                echo '</div>';
            }
            oci_free_statement($meghivas_statement);
            ?>
        </div>
    </div>

    <?php
    // Adatbázis kapcsolat bezárása
    oci_close($conn);
    ?>
</body>

</html>