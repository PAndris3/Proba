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

// Ellenőrizzük, hogy a címzett azonosító meg van-e adva
if (!isset($_GET['userId'])) {
    header("Location: chat.php?uzenet=hianyzo_cimzett");
    exit;
}

$cimzettId = $_GET['userId'];

// Ellenőrizzük, hogy a címzett létezik-e
$check_query = "SELECT userId, nev FROM FELHASZNALO WHERE userId = :cimzettId AND allapot != 'törölt'";
$check_statement = oci_parse($conn, $check_query);
oci_bind_by_name($check_statement, ":cimzettId", $cimzettId);
oci_execute($check_statement);
$cimzett = oci_fetch_array($check_statement, OCI_ASSOC);
oci_free_statement($check_statement);

if (!$cimzett) {
    header("Location: chat.php?uzenet=nem_letezo_cimzett");
    exit;
}

// Ellenőrizzük, hogy a felhasználók között nincs-e tiltott kapcsolat
$tiltas_query = "SELECT 1 FROM ISMERETSEG 
                 WHERE ((userId = :sajat_userId AND ismerosUserId = :cimzettId) 
                 OR (userId = :cimzettId AND ismerosUserId = :sajat_userId)) 
                 AND allapot = 'tiltott'";
$tiltas_statement = oci_parse($conn, $tiltas_query);
oci_bind_by_name($tiltas_statement, ":sajat_userId", $userId);
oci_bind_by_name($tiltas_statement, ":cimzettId", $cimzettId);
oci_execute($tiltas_statement);
$tiltas = oci_fetch_array($tiltas_statement, OCI_ASSOC);
oci_free_statement($tiltas_statement);

if ($tiltas) {
    // Ha tiltott kapcsolat van, átirányítjuk a felhasználót
    header("Location: chat.php?uzenet=tiltott_kapcsolat");
    exit;
}

// Korábbi üzenetek lekérdezése
$uzenet_query = "SELECT u.*, f1.nev AS kuldo_nev, f2.nev AS fogado_nev 
                FROM UZENET u 
                JOIN FELHASZNALO f1 ON u.kuldoUserId = f1.userId 
                JOIN FELHASZNALO f2 ON u.fogadoUserId = f2.userId 
                WHERE (u.kuldoUserId = :userId AND u.fogadoUserId = :cimzettId) 
                OR (u.kuldoUserId = :cimzettId AND u.fogadoUserId = :userId) 
                ORDER BY u.datum ASC";
$uzenet_statement = oci_parse($conn, $uzenet_query);
oci_bind_by_name($uzenet_statement, ":userId", $userId);
oci_bind_by_name($uzenet_statement, ":cimzettId", $cimzettId);
oci_execute($uzenet_statement);

// Üzenet küldés feldolgozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uzenet']) && !empty($_POST['uzenet'])) {
    $uzenet_szoveg = $_POST['uzenet'];

    // Üzenet beszúrása az adatbázisba
    $insert_query = "INSERT INTO UZENET (uzszoveg, fogadoUserId, kuldoUserId, datum) 
                    VALUES (:uzenet_szoveg, :cimzettId, :userId, CURRENT_TIMESTAMP)";
    $insert_statement = oci_parse($conn, $insert_query);

    oci_bind_by_name($insert_statement, ":uzenet_szoveg", $uzenet_szoveg);
    oci_bind_by_name($insert_statement, ":cimzettId", $cimzettId);
    oci_bind_by_name($insert_statement, ":userId", $userId);

    $success = oci_execute($insert_statement);
    oci_free_statement($insert_statement);

    if ($success) {
        // Újratöltjük az oldalt, hogy megjelenjen az új üzenet
        header("Location: uzenet_kuldes.php?userId=" . $cimzettId);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Üzenet küldése - <?php echo $cimzett['NEV']; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .message-container {
            width: 80%;
            max-width: 800px;
            margin: 20px auto;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #c0fafa;
        }

        .message-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .message-list {
            margin-bottom: 20px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .message {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 10px;
            max-width: 80%;
        }

        .sent {
            background-color: #c0fafa;
            color: #333;
            margin-left: auto;
            text-align: right;
        }

        .received {
            background-color: #45918f;
            color: white;
            margin-right: auto;
        }

        .message-info {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .message-form {
            display: flex;
            flex-direction: column;
        }

        .message-input {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 10px;
            border: 1px solid #c0fafa;
            background-color: rgba(255, 255, 255, 0.8);
            resize: vertical;
            min-height: 80px;
        }

        .send-button {
            padding: 10px;
            background-color: #c0fafa;
            color: #333;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }

        .send-button:hover {
            background-color: #53edd3;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">
            <a href="chat.php">Linksy</a>
        </div>
        <div class="navbar-links">
            <a href="chat.php">Főoldal</a>
            <a href="uzenet.php" class="active">Üzenetek</a>
            <a href="kereses.php">Keresés</a>
            <a href="profil.php">Profil</a>
            <a href="kijelentkezes.php">Kijelentkezés</a>
        </div>
    </div>

    <div class="message-container">
        <div class="message-header">
            <h2>Beszélgetés: <?php echo $cimzett['NEV']; ?></h2>
            <a href="masik_profil.php?userId=<?php echo $cimzett['USERID']; ?>">Profil megtekintése</a>
        </div>

        <div class="message-list">
            <?php
            $van_uzenet = false;
            while ($uzenet = oci_fetch_array($uzenet_statement, OCI_ASSOC)) {
                $van_uzenet = true;
                $sent_class = ($uzenet['KULDOUSERID'] == $userId) ? 'sent' : 'received';
                $uzenet_szoveg = $uzenet['UZSZOVEG'];
                $datum = $uzenet['DATUM'];

                echo '<div class="message ' . $sent_class . '">';
                echo '<div class="message-content">' . $uzenet_szoveg . '</div>';
                echo '<div class="message-info">' . $datum . '</div>';
                echo '</div>';
            }

            if (!$van_uzenet) {
                echo '<p style="text-align: center;">Még nincs üzenetváltás. Írj egy üzenetet!</p>';
            }
            ?>
        </div>

        <form class="message-form" method="POST">
            <textarea class="message-input" name="uzenet" placeholder="Írj üzenetet..." required></textarea>
            <button class="send-button" type="submit">Küldés</button>
        </form>
    </div>

    <?php
    // Adatbázis kapcsolat bezárása
    oci_free_statement($uzenet_statement);
    oci_close($conn);
    ?>
</body>

</html>