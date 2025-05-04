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

if ($_SESSION['admin'] === 'I') {
    header("Location: admin.php");
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

// Profil módosítása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['modosit'])) {
    $nev = !empty($_POST['name']) ? $_POST['name'] : $felhasznalo['NEV'];
    $email = !empty($_POST['email']) ? $_POST['email'] : $felhasznalo['EMAIL'];
    $jelszo = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $felhasznalo['JELSZO'];
    $szuldatum = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d', strtotime($felhasznalo['SZULDATUM']));
    $munkahely = isset($_POST['job']) && $_POST['job'] !== '' ? $_POST['job'] : $felhasznalo['MUNKAHELY'];

    // Felhasználó adatainak frissítése
    $update_query = "UPDATE FELHASZNALO SET 
                   nev = :nev, 
                   email = :email, 
                   jelszo = :jelszo, 
                   szuldatum = TO_DATE(:szuldatum, 'YYYY-MM-DD'), 
                   munkahely = :munkahely 
                   WHERE userId = :userId";
    $update_statement = oci_parse($conn, $update_query);

    oci_bind_by_name($update_statement, ":nev", $nev);
    oci_bind_by_name($update_statement, ":email", $email);
    oci_bind_by_name($update_statement, ":jelszo", $jelszo);
    oci_bind_by_name($update_statement, ":szuldatum", $szuldatum);
    oci_bind_by_name($update_statement, ":munkahely", $munkahely);
    oci_bind_by_name($update_statement, ":userId", $userId);

    $success = oci_execute($update_statement);
    oci_free_statement($update_statement);

    // Profilkép feltöltés kezelése
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] == 0) {
        // Kép adatainak beolvasása
        $fileName = $_FILES['profile_img']['name'];
        $fileType = $_FILES['profile_img']['type'];
        $fileSize = $_FILES['profile_img']['size'];
        $fileTmpName = $_FILES['profile_img']['tmp_name'];

        // Ellenőrizzük, hogy képfájl-e
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($fileType, $allowed)) {
            // Kép beolvasása bináris formában
            $imageData = file_get_contents($fileTmpName);

            // Kép mentése az adatbázisba a felhasználó profilképeként
            $update_query = "UPDATE FELHASZNALO SET profilkep = EMPTY_BLOB() WHERE userId = :userId RETURNING profilkep INTO :profilkep";
            $update_statement = oci_parse($conn, $update_query);

            $blob = oci_new_descriptor($conn, OCI_D_LOB);
            oci_bind_by_name($update_statement, ":userId", $userId);
            oci_bind_by_name($update_statement, ":profilkep", $blob, -1, OCI_B_BLOB);

            $pic_success = oci_execute($update_statement, OCI_DEFAULT);

            if ($pic_success && $blob->save($imageData)) {
                oci_commit($conn);
                $uzenet = isset($uzenet) ? $uzenet . " A profilkép sikeresen frissítve!" : "A profilkép sikeresen frissítve!";
            } else {
                oci_rollback($conn);
                $uzenet = isset($uzenet) ? $uzenet . " Hiba történt a profilkép frissítésekor!" : "Hiba történt a profilkép frissítésekor!";
            }

            $blob->free();
            oci_free_statement($update_statement);
        } else {
            $uzenet = isset($uzenet) ? $uzenet . " Csak JPG, PNG vagy GIF formátumú képeket lehet feltölteni!" : "Csak JPG, PNG vagy GIF formátumú képeket lehet feltölteni!";
        }
    }

    if ($success) {
        // Frissítjük a munkamenetben tárolt felhasználónevet is
        $_SESSION['user_name'] = $nev;
        if (!isset($uzenet)) {
            $uzenet = "Profil adatok sikeresen frissítve!";
        }

        // Frissítjük a felhasználó adatait új lekérdezéssel
        $query = "SELECT * FROM FELHASZNALO WHERE USERID = :userId";
        $statement = oci_parse($conn, $query);
        oci_bind_by_name($statement, ":userId", $userId);
        oci_execute($statement);
        $felhasznalo = oci_fetch_array($statement, OCI_ASSOC);
        oci_free_statement($statement);
    } else {
        if (!isset($uzenet)) {
            $e = oci_error($update_statement);
            $uzenet = "Hiba a profil frissítésekor: " . $e['message'];
        }
    }
}

// Profil állapotának módosítása (inaktív, archivált, törölt)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['inaktiv'])) {
        $allapot = "inaktív";
    } elseif (isset($_POST['archiv'])) {
        $allapot = "archivált";
    } elseif (isset($_POST['delete'])) {
        $allapot = "törölt";
    }

    if (isset($allapot)) {
        $update_query = "UPDATE FELHASZNALO SET allapot = :allapot WHERE userId = :userId";
        $update_statement = oci_parse($conn, $update_query);

        oci_bind_by_name($update_statement, ":allapot", $allapot);
        oci_bind_by_name($update_statement, ":userId", $userId);

        $success = oci_execute($update_statement);
        oci_free_statement($update_statement);

        if ($success) {
            $uzenet = "Profil állapota sikeresen módosítva: " . $allapot;

            // Ha a felhasználó törölte a profilját, kijelentkeztetjük
            if ($allapot == "törölt") {
                session_destroy();
                header("Location: bejelentkezes.php?uzenet=torolt");
                exit;
            }
        } else {
            $e = oci_error($update_statement);
            $uzenet = "Hiba a profil állapotának módosításakor: " . $e['message'];
        }
    }
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
                  WHERE i.userId = :userId AND i.allapot = 'elfogadva'
                  UNION
                  SELECT f.userId, f.nev 
                  FROM ISMERETSEG i 
                  JOIN FELHASZNALO f ON (i.userId = f.userId) 
                  WHERE i.ismerosUserId = :userId AND i.allapot = 'elfogadva'";
$ismerosok_statement = oci_parse($conn, $ismerosok_query);
oci_bind_by_name($ismerosok_statement, ":userId", $userId);
oci_execute($ismerosok_statement);
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Profil</title>
    <link rel="stylesheet" href="css/profile.css">
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

    <?php if (isset($uzenet)): ?>
        <div class="uzenet">
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
        <form method="POST" enctype="multipart/form-data">
            <label for="name">Név</label>
            <input id="name" name="name" type="text" placeholder="Név" value="<?php echo $felhasznalo['NEV']; ?>">

            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" placeholder="név123@gmail.com"
                value="<?php echo $felhasznalo['EMAIL']; ?>">

            <label for="password">Jelszó</label>
            <input id="password" name="password" type="password" placeholder="********" value="">

            <label for="date">Születési dátum</label>
            <input id="date" name="date" type="date"
                value="<?php echo date('Y-m-d', strtotime($felhasznalo['SZULDATUM'])); ?>">

            <label for="job">Munkahely</label>
            <input id="job" name="job" type="text" placeholder="Nincs megadva"
                value="<?php echo isset($felhasznalo['MUNKAHELY']) && $felhasznalo['MUNKAHELY'] !== null ? htmlspecialchars($felhasznalo['MUNKAHELY']) : ''; ?>">

            <button id="modosit" name="modosit" type="submit">Módosítás</button>
        </form>
    </div>

    <div class="login">
        <form method="POST">
            <button id="inaktiv" name="inaktiv" type="submit">Profil inaktivitása</button>
        </form>
    </div>
    <div class="login">
        <form method="POST">
            <button id="archiv" name="archiv" type="submit">Profil archiválása</button>
        </form>
    </div>
    <div class="login">
        <form method="POST">
            <button id="delete" name="delete" type="submit"
                onclick="return confirm('Biztosan törölni szeretnéd a profilodat?');">Profil törlése</button>
        </form>
    </div>

    <div class="data">
        <p class="cim">Fényképek</p>

        <!-- Profilkép módosítás form - elegánsabb stílussal -->
        <div class="profile-pic-upload">
            <h3>Profilkép módosítása</h3>
            <form method="POST" action="profil_kep_feltoltes.php" enctype="multipart/form-data">
                <input id="profile_img" name="profile_img" type="file">
                <button type="submit">Profilkép
                    frissítése</button>
            </form>
        </div>

        <!-- Új kép feltöltés form -->
        <div class="photo-upload">
            <h3>Új kép feltöltése</h3>
            <form method="POST" action="kepfeltoltes.php" enctype="multipart/form-data">
                <input id="feltoltes" name="feltoltes" type="file">
                <button id="feltolt" type="submit">Feltöltés</button>
            </form>
        </div>

        <!-- Képgaléria -->
        <div id="images">
            <?php
            // Fényképek megjelenítése
            while ($kep = oci_fetch_array($kepek_statement, OCI_ASSOC)) {
                echo '<div class="kep" style="position: relative;">'; // Add inline style for relative positioning
                echo '<a href="kepek.php?imgId=' . $kep['IMGID'] . '">';
                echo '<img class="image" src="kep_megjelenito.php?imgId=' . $kep['IMGID'] . '" alt="Feltöltött kép">';
                echo '</a>';
                echo '<form method="POST" action="kep_torles.php" style="position: absolute; top: 5px; right: 5px; z-index: 10;">';
                echo '<input type="hidden" name="imgId" value="' . $kep['IMGID'] . '">';
                echo '<button type="submit" class="delete-button">X</button>';
                echo '</form>';
                echo '</div>';
            }
            oci_free_statement($kepek_statement);
            ?>
        </div>
    </div>

    <div class="data">
        <p class="cim">Albumok</p>
        <form method="POST" action="album_letrehozas.php">
            <label for="uj">Új album</label>
            <input id="uj" name="albumNev" type="text" placeholder="Album neve">
            <button id="hozzaadas" type="submit">Létrehozás</button>
        </form>

        <?php
        // Albumok megjelenítése
        while ($album = oci_fetch_array($albumok_statement, OCI_ASSOC)) {
            echo '<div class="album">';
            echo '<p class="album_cim">' . $album['ALBUMNEV'] . '</p>';

            echo '<div class="add_img">';
            echo '<form method="POST" action="album_kep_hozzaadas.php" enctype="multipart/form-data">';
            echo '<input class="uj_img" name="album_kep" type="file">';
            echo '<input type="hidden" name="albumId" value="' . $album['ALBUMID'] . '">';
            echo '<button class="add" type="submit">Kép hozzáadása</button>';
            echo '</form>';
            echo '</div>';

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
                echo '<div class="kep">';
                echo '<a href="kepek.php?imgId=' . $albumkep['IMGID'] . '">';
                echo '<img class="image" src="kep_megjelenito.php?imgId=' . $albumkep['IMGID'] . '" alt="Album kép">';
                echo '</a>';
                echo '<form method="POST" action="album_kep_torles.php">';
                echo '<input type="hidden" name="imgId" value="' . $albumkep['IMGID'] . '">';
                echo '<input type="hidden" name="albumId" value="' . $album['ALBUMID'] . '">';
                echo '<button type="submit" style="background: red; color: white; border: none; border-radius: 4px; padding: 2px 5px; font-size: 10px; cursor: pointer;">X</button>';

                echo '</form>';
                echo '</div>';
            }
            oci_free_statement($album_kepek_statement);

            echo '<div class="album_delete">';
            echo '<form method="POST" action="album_torles.php">';
            echo '<input type="hidden" name="albumId" value="' . $album['ALBUMID'] . '">';
            echo '<button class="alb_delete" type="submit">Album törlése</button>';
            echo '</form>';
            echo '</div>';

            echo '</div>';
            echo '<hr class="line">';
        }
        oci_free_statement($albumok_statement);
        ?>
    </div>

    <div class="data">
        <p class="cim">Ismerősök</p>
        <?php
        // Ismerősök listázása
        while ($ismeros = oci_fetch_array($ismerosok_statement, OCI_ASSOC)) {
            echo '<p><a class="link" href="masik_profil.php?userId=' . $ismeros['USERID'] . '">' . $ismeros['NEV'] . '</a></p>';
        }
        oci_free_statement($ismerosok_statement);
        ?>
    </div>
    <div id="jobb">
        <h2>Ismerősnek jelölések</h2>
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
            echo '<div class="ismeros">';
            echo '<p><a href="masik_profil.php?userId=' . $jeloles['ISMEROSID'] . '">' . $jeloles['NEV'] . '</a></p>';
            echo '<div class="gomb">';
            echo '<form method="POST" action="ismeros_valasz.php">';
            echo '<input type="hidden" name="ismerosId" value="' . $jeloles['ISMEROSID'] . '">';
            echo '<button class="elfogad" name="elfogad" type="submit">&#10003;</button>';
            echo '</form>';
            echo '<form method="POST" action="ismeros_valasz.php" >';
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

        <h2>Kimenő ismerősnek jelölések</h2>
        <?php
        // Kimenő ismerősnek jelölések lekérdezése
        $kimeno_query = "SELECT i.ismerosUserId AS jeloltId, f.nev 
                   FROM ISMERETSEG i 
                   JOIN FELHASZNALO f ON i.ismerosUserId = f.userId 
                   WHERE i.userId = :userId AND i.allapot = 'várakozik'";
        $kimeno_statement = oci_parse($conn, $kimeno_query);
        oci_bind_by_name($kimeno_statement, ":userId", $userId);
        oci_execute($kimeno_statement);

        $van_kimeno = false;

        while ($kimeno = oci_fetch_array($kimeno_statement, OCI_ASSOC)) {
            $van_kimeno = true;
            echo '<div class="ismeros">';
            echo '<p><a href="masik_profil.php?userId=' . $kimeno['JELOLTID'] . '">' . $kimeno['NEV'] . '</a></p>';
            echo '<span class="status">Várakozás...</span>';
            echo '</div>';
        }

        if (!$van_kimeno) {
            echo '<p>Nincsenek kimenő ismerősnek jelölések.</p>';
        }

        oci_free_statement($kimeno_statement);
        ?>


    </div>

    <?php
    // Adatbázis kapcsolat bezárása
    oci_close($conn);
    ?>
</body>

</html>