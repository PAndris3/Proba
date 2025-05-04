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

// A saját felhasználói azonosító
$sajat_userId = $_SESSION['user_id'];

// Ellenőrizzük, hogy a megtekintendő profil azonosítója meg van-e adva
if (!isset($_GET['userId'])) {
    header("Location: chat.php");
    exit;
}

$userId = $_GET['userId'];

// Ellenőrizzük, hogy a felhasználó saját profilját nézi-e
if ($userId == $sajat_userId) {
    header("Location: profil.php");
    exit;
}

// Felhasználó adatainak lekérdezése
$query = "SELECT * FROM FELHASZNALO WHERE USERID = :userId";
$statement = oci_parse($conn, $query);
oci_bind_by_name($statement, ":userId", $userId);
oci_execute($statement);
$felhasznalo = oci_fetch_array($statement, OCI_ASSOC);
oci_free_statement($statement);

// Ellenőrizzük, hogy létezik-e ilyen felhasználó
if (!$felhasznalo) {
    header("Location: chat.php");
    exit;
}

// Ismeretségi állapot ellenőrzése
$ismeretseg_query = "SELECT allapot FROM ISMERETSEG 
                    WHERE (userId = :sajat_userId AND ismerosUserId = :userId) 
                    OR (userId = :userId AND ismerosUserId = :sajat_userId)";
$ismeretseg_statement = oci_parse($conn, $ismeretseg_query);
oci_bind_by_name($ismeretseg_statement, ":sajat_userId", $sajat_userId);
oci_bind_by_name($ismeretseg_statement, ":userId", $userId);
oci_execute($ismeretseg_statement);
$ismeretseg = oci_fetch_array($ismeretseg_statement, OCI_ASSOC);
oci_free_statement($ismeretseg_statement);

// Ellenőrizzük, hogy a felhasználók között nincs-e tiltott kapcsolat
$tiltas_query = "SELECT 1 FROM ISMERETSEG 
                 WHERE ((userId = :sajat_userId AND ismerosUserId = :userId) 
                 OR (userId = :userId AND ismerosUserId = :sajat_userId)) 
                 AND allapot = 'tiltott'";
$tiltas_statement = oci_parse($conn, $tiltas_query);
oci_bind_by_name($tiltas_statement, ":sajat_userId", $sajat_userId);
oci_bind_by_name($tiltas_statement, ":userId", $userId);
oci_execute($tiltas_statement);
$tiltas = oci_fetch_array($tiltas_statement, OCI_ASSOC);
oci_free_statement($tiltas_statement);

if ($tiltas) {
    // Ha tiltott kapcsolat van, átirányítjuk a felhasználót
    header("Location: chat.php?uzenet=tiltott_kapcsolat");
    exit;
}

// Felhasználó képeinek lekérdezése
$kepek_query = "SELECT * FROM FENYKEPEK WHERE USERID = :userId";
$kepek_statement = oci_parse($conn, $kepek_query);
oci_bind_by_name($kepek_statement, ":userId", $userId);
oci_execute($kepek_statement);

// Felhasználó albumainak lekérdezése
$albumok_query = "SELECT * FROM ALBUM WHERE USERID = :userId";
$albumok_statement = oci_parse($conn, $albumok_query);
oci_bind_by_name($albumok_statement, ":userId", $userId);
oci_execute($albumok_statement);

// Ismerősök lekérdezése
$ismerosok_query = "SELECT f.userId, f.nev 
                   FROM ISMERETSEG i 
                   JOIN FELHASZNALO f ON (i.ismerosUserId = f.userId) 
                   WHERE i.userId = :userId 
                   AND i.allapot = 'elfogadva' 
                   AND f.allapot NOT IN ('archivált', 'inaktív')
                   UNION
                   SELECT f.userId, f.nev 
                   FROM ISMERETSEG i 
                   JOIN FELHASZNALO f ON (i.userId = f.userId) 
                   WHERE i.ismerosUserId = :userId 
                   AND i.allapot = 'elfogadva' 
                   AND f.allapot NOT IN ('archivált', 'inaktív')";
$ismerosok_statement = oci_parse($conn, $ismerosok_query);
oci_bind_by_name($ismerosok_statement, ":userId", $userId);
oci_execute($ismerosok_statement);
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title><?php echo $felhasznalo['NEV']; ?> profilja - Linksy</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
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
                class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">Saját
                profil</a>
            <a href="kijelentkezes.php">Kijelentkezés</a>
        </div>
    </div>

    <?php if (isset($uzenet)): ?>
        <div class="uzenet" style="text-align: center; margin: 10px; padding: 10px; background-color: #f0f0f0;">
            <?php echo $uzenet; ?>
        </div>
    <?php endif; ?>

    <div id="box">
        <div id="profile">
            <?php
            // Profilkép megjelenítése
            if (isset($felhasznalo['PROFILKEP']) && $felhasznalo['PROFILKEP'] !== null) {
                echo '<img id="img" src="profilkep.php?userId=' . $userId . '&t=' . time() . '" alt="Profilkép">';
            } else {
                echo '<img id="img" src="kepek/profilkep.png" alt="Alapértelmezett profilkép">';
            }
            ?>
        </div>
        <div id="adatok">
            <p>
            <h2>Név</h2>
            </p>
            <p id="name"><?php echo $felhasznalo['NEV']; ?></p>
            <p>
            <h2>E-mail</h2>
            </p>
            <p id="email"><?php echo $felhasznalo['EMAIL']; ?></p>
            <p>
            <h2>Születési dátum</h2>
            </p>
            <p id="date"><?php echo date('Y.m.d', strtotime($felhasznalo['SZULDATUM'])); ?></p>
            <p>
            <h2>Munkahely</h2>
            </p>
            <p id="job"><?php echo !empty($felhasznalo['MUNKAHELY']) ? $felhasznalo['MUNKAHELY'] : 'Nincs megadva'; ?>
            </p>
        </div>
    </div>

    <div class="button">
        <a class="link" href="uzenet_kuldes.php?userId=<?php echo $userId; ?>">Üzenet küldés</a>
    </div>

    <?php if (!$ismeretseg): ?>
        <div class="login">
            <form method="POST" action="ismeros_jeloles.php">
                <input type="hidden" name="jelolt_id" value="<?php echo $userId; ?>">
                <button id="jeloles" name="jeloles" type="submit">Ismerősnek jelölés</button>
            </form>
        </div>
    <?php elseif ($ismeretseg['ALLAPOT'] == 'várakozik'): ?>
        <div class="login">
            <button id="jeloles" type="button" disabled>Ismerősnek jelölve (várakozás)</button>
        </div>
    <?php elseif ($ismeretseg['ALLAPOT'] == 'elfogadva'): ?>
        <div class="login">
            <form method="POST" action="ismeros_torlese.php">
                <input type="hidden" name="userId" value="<?php echo $userId; ?>">
                <button id="jeloles" name="ismerosTorlese" type="submit">Ismerősség megszüntetése</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="login">
        <form method="POST" action="tiltas_kezeles.php">
            <input type="hidden" name="blockedId" value="<?php echo $userId; ?>">
            <?php if ($ismeretseg && $ismeretseg['ALLAPOT'] == 'tiltott'): ?>
                <button id="tiltas" name="unblock" type="submit">Tiltás visszavonása</button>
            <?php else: ?>
                <button id="tiltas" name="block" type="submit">Tiltás</button>
            <?php endif; ?>
        </form>
    </div>

    <div id="jel_div">
        <form method="POST" action="felhasznalo_jelentese.php">
            <label for="indok">Indok</label>
            <input type="hidden" name="jelentettId" value="<?php echo $userId; ?>">
            <input id="indok" name="indok" type="text" required>
            <button id="jelentes" name="jelentes" type="submit">Felhasználó jelentése</button>
        </form>
    </div>

    <div class="data">
        <p class="cim">Fényképek</p>
        <div id="images">
            <?php
            // Számláló a képekhez
            $kepek_szama = 0;

            // Fényképek megjelenítése
            while ($kep = oci_fetch_array($kepek_statement, OCI_ASSOC)) {
                $kepek_szama++;
                echo '<a href="kepek.php?imgId=' . $kep['IMGID'] . '">';
                echo '<img class="image" src="kep_megjelenito.php?imgId=' . $kep['IMGID'] . '" alt="Feltöltött kép">';
                echo '</a>';
            }
            oci_free_statement($kepek_statement);

            // A számláló alapján ellenőrizzük, hogy vannak-e képek
            if ($kepek_szama == 0) {
                echo '<p>A felhasználónak nincsenek képei.</p>';
            }
            ?>
        </div>
    </div>

    <div class="data">
        <p class="cim">Albumok</p>
        <?php
        // Albumok számláló
        $albumok_szama = 0;

        // Albumok megjelenítése
        while ($album = oci_fetch_array($albumok_statement, OCI_ASSOC)) {
            $albumok_szama++;
            echo '<div class="album">';
            echo '<p class="album_cim">' . $album['ALBUMNEV'] . '</p>';

            // Album képeinek lekérdezése
            $album_kepek_query = "SELECT f.* 
                           FROM FENYKEPEK f 
                           JOIN ALBUMBATARTOZIK a ON f.imgId = a.imgId 
                           WHERE a.albumId = :albumId";
            $album_kepek_statement = oci_parse($conn, $album_kepek_query);
            oci_bind_by_name($album_kepek_statement, ":albumId", $album['ALBUMID']);
            oci_execute($album_kepek_statement);

            // Album képeinek megjelenítése
            while ($albumkep = oci_fetch_array($album_kepek_statement, OCI_ASSOC)) {
                echo '<a href="kepek.php?imgId=' . $albumkep['IMGID'] . '">';
                echo '<img class="image" src="kep_megjelenito.php?imgId=' . $albumkep['IMGID'] . '" alt="Album kép">';
                echo '</a>';
            }
            oci_free_statement($album_kepek_statement);

            echo '</div>';
            echo '<hr class="line">';
        }
        oci_free_statement($albumok_statement);

        // A számláló alapján ellenőrizzük, hogy vannak-e albumok
        if ($albumok_szama == 0) {
            echo '<p>A felhasználónak nincsenek albumai.</p>';
        }
        ?>
    </div>

    <?php
    // Adatbázis kapcsolat bezárása
    oci_close($conn);
    ?>
</body>

</html>