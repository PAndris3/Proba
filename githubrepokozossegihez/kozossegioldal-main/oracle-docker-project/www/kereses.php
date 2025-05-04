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
$keresesi_eredmenyek = [];
$kereses_megtortent = false;

// Keresés feldolgozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kereso_szo'])) {
    $kereses_megtortent = true;
    $kereso_szo = '%' . $_POST['kereso_szo'] . '%';

    // Felhasználók keresése név vagy email alapján
    $search_query = "SELECT userId, nev, email, allapot, munkahely 
                    FROM FELHASZNALO 
                    WHERE (nev LIKE :kereso_szo OR email LIKE :kereso_szo) 
                    AND userId != :userId 
                    AND allapot NOT IN ('archivált', 'inaktív')";
    $search_statement = oci_parse($conn, $search_query);

    oci_bind_by_name($search_statement, ":kereso_szo", $kereso_szo);
    oci_bind_by_name($search_statement, ":userId", $userId);
    oci_execute($search_statement);

    while ($row = oci_fetch_array($search_statement, OCI_ASSOC)) {
        // Ellenőrizzük az ismeretségi állapotot
        $check_query = "SELECT allapot FROM ISMERETSEG 
                      WHERE (userId = :userId AND ismerosUserId = :ismerosId)
                      OR (userId = :ismerosId AND ismerosUserId = :userId)";
        $check_statement = oci_parse($conn, $check_query);

        oci_bind_by_name($check_statement, ":userId", $userId);
        oci_bind_by_name($check_statement, ":ismerosId", $row['USERID']);
        oci_execute($check_statement);

        $kapcsolat = oci_fetch_array($check_statement, OCI_ASSOC);

        if ($kapcsolat) {
            $row['ISMERETSEG_ALLAPOT'] = $kapcsolat['ALLAPOT'];
        } else {
            $row['ISMERETSEG_ALLAPOT'] = 'nincs';
        }

        oci_free_statement($check_statement);

        $keresesi_eredmenyek[] = $row;
    }

    oci_free_statement($search_statement);
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Felhasználók keresése - Linksy</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .search-container {
            width: 80%;
            max-width: 800px;
            margin: 20px auto;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #c0fafa;
        }

        .search-form {
            display: flex;
            margin-bottom: 20px;
        }

        .search-input {
            flex-grow: 1;
            padding: 10px;
            border-radius: 10px 0 0 10px;
            border: 1px solid #c0fafa;
            border-right: none;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .search-button {
            padding: 10px 20px;
            background-color: #c0fafa;
            border: 1px solid #c0fafa;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
        }

        .search-results {
            margin-top: 20px;
        }

        .user-card {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            flex-grow: 1;
        }

        .user-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #c0fafa;
        }

        .user-name a {
            color: #c0fafa;
            text-decoration: none;
        }

        .user-name a:hover {
            text-decoration: underline;
        }

        .user-details {
            margin-bottom: 10px;
            font-size: 14px;
        }

        .user-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .user-action-button {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        .add-friend {
            background-color: #4CAF50;
            color: white;
        }

        .pending {
            background-color: #FF9800;
            color: white;
            cursor: default;
        }

        .accepted {
            background-color: #2196F3;
            color: white;
        }

        .remove-friend {
            background-color: #f44336;
            color: white;
        }

        .send-message {
            background-color: #c0fafa;
            color: #333;
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
            <a href="uzenet.php">Üzenetek</a>
            <a href="kereses.php" class="active">Keresés</a>
            <a href="profil.php">Profil</a>
            <a href="kijelentkezes.php">Kijelentkezés</a>
        </div>
    </div>

    <div class="search-container">
        <h2>Felhasználók keresése</h2>
        <form method="POST" class="search-form">
            <input type="text" name="kereso_szo" class="search-input" placeholder="Név vagy email cím..." required>
            <button type="submit" class="search-button">Keresés</button>
        </form>

        <div class="search-results">
            <?php if ($kereses_megtortent): ?>
                <?php if (empty($keresesi_eredmenyek)): ?>
                    <p>Nincs találat a keresési feltételeknek megfelelően.</p>
                <?php else: ?>
                    <h3>Keresési eredmények:</h3>
                    <?php foreach ($keresesi_eredmenyek as $felhasznalo): ?>
                        <div class="user-card">
                            <div class="user-info">
                                <div class="user-name">
                                    <a href="masik_profil.php?userId=<?php echo $felhasznalo['USERID']; ?>">
                                        <?php echo $felhasznalo['NEV']; ?>
                                    </a>
                                </div>
                                <div class="user-details">
                                    <p>Email: <?php echo $felhasznalo['EMAIL']; ?></p>
                                    <?php if (!empty($felhasznalo['MUNKAHELY'])): ?>
                                        <p>Munkahely: <?php echo $felhasznalo['MUNKAHELY']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="user-actions">
                                <?php if ($felhasznalo['ISMERETSEG_ALLAPOT'] == 'nincs'): ?>
                                    <form method="POST" action="ismeros_jeloles.php">
                                        <input type="hidden" name="jelolt_id" value="<?php echo $felhasznalo['USERID']; ?>">
                                        <button type="submit" class="user-action-button add-friend">Ismerősnek jelölés</button>
                                    </form>
                                <?php elseif ($felhasznalo['ISMERETSEG_ALLAPOT'] == 'várakozik'): ?>
                                    <button class="user-action-button pending" disabled>Ismerősnek jelölve</button>
                                <?php elseif ($felhasznalo['ISMERETSEG_ALLAPOT'] == 'elfogadva'): ?>
                                    <form method="POST" action="ismeros_torlese.php">
                                        <input type="hidden" name="userId" value="<?php echo $felhasznalo['USERID']; ?>">
                                        <button type="submit" class="user-action-button remove-friend">Ismerősség megszüntetése</button>
                                    </form>
                                <?php endif; ?>

                                <a href="uzenet_kuldes.php?userId=<?php echo $felhasznalo['USERID']; ?>"
                                    class="user-action-button send-message">Üzenet küldése</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Adatbázis kapcsolat bezárása
    oci_close($conn);
    ?>
</body>

</html>