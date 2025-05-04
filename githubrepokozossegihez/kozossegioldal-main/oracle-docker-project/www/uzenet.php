<?php
ob_start(); // Start output buffering
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

// Új csoport létrehozása
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['csoportLetrehozas']) && isset($_POST['csoportNev'])) {
  $csoportNev = $_POST['csoportNev'];

  // Új csoport beszúrása az adatbázisba
  $insert_query = "INSERT INTO CSOPORT (csoportnev, letrehozo) VALUES (:csoportnev, :userId)";
  $insert_statement = oci_parse($conn, $insert_query);

  oci_bind_by_name($insert_statement, ":csoportnev", $csoportNev);
  oci_bind_by_name($insert_statement, ":userId", $userId);

  $success = oci_execute($insert_statement);
  oci_free_statement($insert_statement);

  if ($success) {
    $uzenet = "Csoport sikeresen létrehozva!";
  }
}

// Beszélgetések lekérdezése (üzenetek)
$uzenet_query = "SELECT DISTINCT 
                 CASE 
                   WHEN u.kuldoUserId = :userId THEN f.nev
                   ELSE f2.nev
                 END AS masik_nev,
                 CASE 
                   WHEN u.kuldoUserId = :userId THEN u.fogadoUserId
                   ELSE u.kuldoUserId
                 END AS masik_id
               FROM UZENET u
               JOIN FELHASZNALO f ON u.fogadoUserId = f.userId
               JOIN FELHASZNALO f2 ON u.kuldoUserId = f2.userId
               WHERE u.kuldoUserId = :userId OR u.fogadoUserId = :userId
               ORDER BY masik_nev";
$uzenet_statement = oci_parse($conn, $uzenet_query);
oci_bind_by_name($uzenet_statement, ":userId", $userId);
oci_execute($uzenet_statement);

// Felhasználó csoportjainak lekérdezése (ahol ő a létrehozó)
$csoportok_query = "SELECT * FROM CSOPORT WHERE letrehozo = :userId";
$csoportok_statement = oci_parse($conn, $csoportok_query);
oci_bind_by_name($csoportok_statement, ":userId", $userId);
oci_execute($csoportok_statement);

// Csoport meghívások lekérdezése
$meghivas_query = "SELECT t.csoportId, c.csoportnev, f.nev AS meghivo_nev 
                   FROM TAGSAG t 
                   JOIN CSOPORT c ON t.csoportId = c.csoportId 
                   JOIN FELHASZNALO f ON c.letrehozo = f.userId 
                   WHERE t.meghivottUserId = :userId AND t.allapot = 'folyamatban'";
$meghivas_statement = oci_parse($conn, $meghivas_query);
oci_bind_by_name($meghivas_statement, ":userId", $userId);
oci_execute($meghivas_statement);
?>

<!DOCTYPE html>
<html lang="hu">

<head>
  <meta charset="UTF-8">
  <title>Üzenetek és Csoportok</title>
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
    <div class="uzenet-info" style="text-align: center; margin: 10px; padding: 10px; background-color: #f0f0f0;">
      <?php echo $uzenet; ?>
    </div>
  <?php endif; ?>

  <div id="box">
    <div id="bal">
      <h2>Üzenetek</h2>
      <?php
      // Beszélgetések listázása
      while ($beszelgetes = oci_fetch_array($uzenet_statement, OCI_ASSOC)) {
        echo '<div class="uzenet">';
        echo '<a class="nev" href="uzenet_kuldes.php?userId=' . $beszelgetes['MASIK_ID'] . '">' . $beszelgetes['MASIK_NEV'] . '</a>';
        echo '<div class="gomb">';
        echo '<form method="POST" action="beszelgetes_torles.php">';
        echo '<input type="hidden" name="masikId" value="' . $beszelgetes['MASIK_ID'] . '">';
        echo '<button class="delete" type="submit">Beszélgetés törlése</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
      }
      oci_free_statement($uzenet_statement);
      ?>
    </div>
    <div id="jobb">
      <h2>Saját csoportjaim</h2>
      <form method="POST">
        <input id="tag" name="csoportNev" type="text" placeholder="Csoport neve" required>
        <button class="add" name="csoportLetrehozas" type="submit">Csoport létrehozása</button>
      </form>

      <?php
      // Csoportok listázása
      while ($csoport = oci_fetch_array($csoportok_statement, OCI_ASSOC)) {
        echo '<div class="group">';
        echo '<div class="header">';
        echo '<a class="nev" href="groupchat.php?csoportId=' . $csoport['CSOPORTID'] . '">' . $csoport['CSOPORTNEV'] . '</a>';
        echo '<form method="POST" action="csoport_torles.php">';
        echo '<input type="hidden" name="csoportId" value="' . $csoport['CSOPORTID'] . '">';
        echo '<button class="torles" type="submit">Csoport törlése</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="gomb">';

        // Tag hozzáadása form
        echo '<form method="POST" action="tag_hozzaadas.php">';
        echo '<input class="tag" name="felhasznaloNev" type="text" placeholder="felhasználó neve" required>';
        echo '<input type="hidden" name="csoportId" value="' . $csoport['CSOPORTID'] . '">';
        echo '<button class="add" type="submit">Tag hozzáadása</button>';
        echo '</form>';

        // Tag eltávolítása form
        echo '<form method="POST" action="tag_eltavolitas.php">';
        echo '<input class="tag" name="felhasznaloNev" type="text" placeholder="felhasználó neve" required>';
        echo '<input type="hidden" name="csoportId" value="' . $csoport['CSOPORTID'] . '">';
        echo '<button class="add" type="submit">Tag eltávolítása</button>';
        echo '</form>';

        // Csoport átnevezése form
        echo '<form method="POST" action="csoport_atnevezes.php">';
        echo '<input class="tag" name="ujNev" type="text" placeholder="új név" required>';
        echo '<input type="hidden" name="csoportId" value="' . $csoport['CSOPORTID'] . '">';
        echo '<button class="add" type="submit">Átnevezés</button>';
        echo '</form>';

        // Tagok listázása és eltávolítása
        $tagok_query = "SELECT t.meghivottUserId AS tagUserId, f.nev 
                        FROM TAGSAG t 
                        JOIN FELHASZNALO f ON t.meghivottUserId = f.userId 
                        WHERE t.csoportId = :csoportId AND t.allapot = 'elfogadva'";
        $tagok_statement = oci_parse($conn, $tagok_query);
        oci_bind_by_name($tagok_statement, ":csoportId", $csoport['CSOPORTID']);
        oci_execute($tagok_statement);

        while ($tag = oci_fetch_array($tagok_statement, OCI_ASSOC)) {
          echo '<div class="tag-item">';
          echo '<span>' . htmlspecialchars($tag['NEV']) . '</span>';
          echo '<form method="POST" action="tag_torles.php" style="display: inline;">';
          echo '<input type="hidden" name="tagUserId" value="' . $tag['TAGUSERID'] . '">';
          echo '<input type="hidden" name="csoportId" value="' . $csoport['CSOPORTID'] . '">';
          echo '<button class="delete" type="submit">Törlés</button>';
          echo '</form>';
          echo '</div>';
        }
        oci_free_statement($tagok_statement);

        echo '</div>'; // gomb div vége
        echo '</div>'; // group div vége
        echo '<hr class="line">';
      }
      oci_free_statement($csoportok_statement);
      ?>

      <h2>Csoportjaim</h2>
      <div>
        <?php
        $user_groups_query = "SELECT c.csoportId, c.csoportnev 
                              FROM TAGSAG t 
                              JOIN CSOPORT c ON t.csoportId = c.csoportId 
                              JOIN FELHASZNALO f ON c.letrehozo = f.userId 
                              WHERE t.meghivottUserId = :userId 
                              AND t.allapot = 'elfogadva' 
                              AND f.allapot NOT IN ('archivált', 'inaktív')";
        $user_groups_statement = oci_parse($conn, $user_groups_query);
        oci_bind_by_name($user_groups_statement, ":userId", $userId);
        oci_execute($user_groups_statement);

        while ($group = oci_fetch_array($user_groups_statement, OCI_ASSOC)) {
          echo '<p><a href="groupchat.php?csoportId=' . $group['CSOPORTID'] . '">' . htmlspecialchars($group['CSOPORTNEV']) . '</a></p>';
        }
        oci_free_statement($user_groups_statement);
        ?>
      </div>

      <h2>Csoport meghívások</h2>
      <?php
      while ($meghivas = oci_fetch_array($meghivas_statement, OCI_ASSOC)) {
        echo '<div class="group-invite">';
        echo '<p><strong>' . htmlspecialchars($meghivas['CSOPORTNEV']) . '</strong> - Meghívó: ' . htmlspecialchars($meghivas['MEGHIVO_NEV']) . '</p>';
        echo '<form method="POST" style="display: inline;">';
        echo '<input type="hidden" name="csoportId" value="' . $meghivas['CSOPORTID'] . '">';
        echo '<button class="accept" name="elfogad_meghivas" type="submit">Elfogadás</button>';
        echo '</form>';
        echo '<form method="POST" style="display: inline;">';
        echo '<input type="hidden" name="csoportId" value="' . $meghivas['CSOPORTID'] . '">';
        echo '<button class="reject" name="visszaut_meghivas" type="submit">Visszautasítás</button>';
        echo '</form>';
        echo '</div>';
      }
      oci_free_statement($meghivas_statement);
      ?>
    </div>
  </div>

  <?php
  // Meghívás elfogadása vagy visszautasítása
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['elfogad_meghivas']) && isset($_POST['csoportId'])) {
      $csoportId = $_POST['csoportId'];
      $check_query = "SELECT * FROM TAGSAG 
                      WHERE meghivottUserId = :userId AND csoportId = :csoportId";
      $check_statement = oci_parse($conn, $check_query);
      oci_bind_by_name($check_statement, ":userId", $userId);
      oci_bind_by_name($check_statement, ":csoportId", $csoportId);
      oci_execute($check_statement);
      $existing_entry = oci_fetch_array($check_statement, OCI_ASSOC);
      oci_free_statement($check_statement);

      if ($existing_entry) {
        $update_query = "UPDATE TAGSAG SET allapot = 'elfogadva' 
                         WHERE meghivottUserId = :userId AND csoportId = :csoportId AND allapot = 'folyamatban'";
        $update_statement = oci_parse($conn, $update_query);
        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":csoportId", $csoportId);
        oci_execute($update_statement);
        oci_free_statement($update_statement);
      } else {
        $update_query = "UPDATE TAGSAG SET allapot = 'elfogadva' 
                         WHERE meghivottUserId = :userId AND csoportId = :csoportId AND allapot = 'folyamatban'";
        $update_statement = oci_parse($conn, $update_query);
        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":csoportId", $csoportId);
        oci_execute($update_statement);
        oci_free_statement($update_statement);
      }
      header("Location: uzenet.php");
      exit;
    } elseif (isset($_POST['visszaut_meghivas']) && isset($_POST['csoportId'])) {
      $csoportId = $_POST['csoportId'];
      $update_query = "UPDATE TAGSAG SET allapot = 'visszautasítva' 
                       WHERE meghivottUserId = :userId AND csoportId = :csoportId AND allapot = 'folyamatban'";
      $update_statement = oci_parse($conn, $update_query);
      oci_bind_by_name($update_statement, ":userId", $userId);
      oci_bind_by_name($update_statement, ":csoportId", $csoportId);
      oci_execute($update_statement);
      oci_free_statement($update_statement);
      header("Location: uzenet.php");
      exit;
    }
  }

  // Adatbázis kapcsolat bezárása
  oci_close($conn);
  ob_end_flush(); // Flush the output buffer
  ?>
</body>

</html>