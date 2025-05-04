<?php
// Adatbázis kapcsolat és session inicializálás
include 'db_connect.php';
session_start();

// Üzenet változó inicializálása
$uzenet = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['name']) && isset($_POST['password'])) {
        $nev = trim($_POST['name']);
        $jelszo = trim($_POST['password']);

        // Adatbázis lekérdezés csak a név alapján
        $query = "SELECT * FROM FELHASZNALO WHERE NEV = :nev";
        $statement = oci_parse($conn, $query);
        oci_bind_by_name($statement, ":nev", $nev);
        oci_execute($statement);

        $felhasznalo = oci_fetch_array($statement, OCI_ASSOC);

        if ($felhasznalo) {
            // Jelszó ellenőrzése hash-sel
            if (password_verify($jelszo, $felhasznalo['JELSZO'])) {
                if ($felhasznalo['ALLAPOT'] == 'aktív') {
                    $_SESSION['user_id'] = $felhasznalo['USERID'];
                    $_SESSION['user_name'] = $felhasznalo['NEV'];
                    $_SESSION['admin'] = $felhasznalo['ADMIN'];

                    header("Location: profil.php");
                    exit;
                } else {
                    $uzenet = "A felhasználói fiók nem aktív. Állapot: " . $felhasznalo['ALLAPOT'];
                }
            } else {
                $uzenet = "Hibás felhasználónév vagy jelszó!";
            }
        } else {
            $uzenet = "Hibás felhasználónév vagy jelszó!";
        }

        oci_free_statement($statement);
    } else {
        $uzenet = "Kérjük, töltse ki az összes mezőt!";
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Bejelentkezés</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
<div id="container">
    <div id="title">Linksy</div>
    <div id="reg">Bejelentkezés</div>
    <div id="reg_button">
        <div class="button">
            <a class="link" href="index.php">Regisztráció</a>
        </div>
    </div>
</div>
<div id="box">
    <?php if (!empty($uzenet)): ?>
        <div class="hibauzenet" style="color: red; margin-bottom: 15px;"><?php echo $uzenet; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <label for="name">Név</label>
        <input id="name" name="name" type="text" required>
        <label for="password">Jelszó</label>
        <input id="password" name="password" type="password" required>
        <button id="mentes" type="submit">Bejelentkezés</button>
    </form>
</div>
<div id="login">
    Nincs még fiókja? <a href="index.php">Regisztráció</a>
</div>

<?php
oci_close($conn);
?>
</body>
</html>