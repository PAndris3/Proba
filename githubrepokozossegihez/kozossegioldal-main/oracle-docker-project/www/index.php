<?php
// Adatbázis kapcsolat
include 'db_connect.php';
session_start();

// Ha a felhasználó már be van jelentkezve, átirányítjuk a főoldalra
if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

// Üzenet változó inicializálása
$uzenet = "";
$siker = false;

// Regisztrációs form feldolgozása
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nev = $_POST['name'];
    $email = $_POST['email'];
    $szuldatum = $_POST['date'];
    $jelszo = $_POST['password'];
    $jelszo2 = $_POST['password2'];
    $isAdmin = isset($_POST['admin']) ? 'I' : 'N'; // Determine if the user is an admin

    // Ellenőrzések
    if ($jelszo != $jelszo2) {
        $uzenet = "A két jelszó nem egyezik!";
    } else {
        // Ellenőrizzük, hogy az email cím nem foglalt-e
        $query = "SELECT * FROM FELHASZNALO WHERE EMAIL = :email";
        $statement = oci_parse($conn, $query);
        oci_bind_by_name($statement, ":email", $email);
        oci_execute($statement);
        $emailExists = oci_fetch_array($statement, OCI_ASSOC);
        oci_free_statement($statement);

        if ($emailExists) {
            $uzenet = "Ez az email cím már használatban van!";
        } else {
            // Jelszó hashelése
            $hashed_password = password_hash($jelszo, PASSWORD_DEFAULT);
            // A hashelt jelszót tároljuk az adatbázisban
            $insert_query = "INSERT INTO FELHASZNALO (nev, email, jelszo, admin, szuldatum, allapot) 
                    VALUES (:nev, :email, :jelszo, :admin, TO_DATE(:szuldatum, 'YYYY-MM-DD'), 'aktív')";
            $insert_statement = oci_parse($conn, $insert_query);

            oci_bind_by_name($insert_statement, ":nev", $nev);
            oci_bind_by_name($insert_statement, ":email", $email);
            oci_bind_by_name($insert_statement, ":jelszo", $hashed_password); // <-- Itt már a hashelt jelszó megy be!
            oci_bind_by_name($insert_statement, ":admin", $isAdmin); // Bind the admin value
            oci_bind_by_name($insert_statement, ":szuldatum", $szuldatum);

            $success = oci_execute($insert_statement);
            oci_free_statement($insert_statement);

            if ($success) {
                $siker = true;
                $uzenet = "Sikeres regisztráció! Most már bejelentkezhet.";

                //automatikus bejelentkeztetés
                /*
                // Az új felhasználó ID-jának lekérdezése
                $query = "SELECT USERID FROM FELHASZNALO WHERE EMAIL = :email";
                $statement = oci_parse($conn, $query);
                oci_bind_by_name($statement, ":email", $email);
                oci_execute($statement);
                $user = oci_fetch_array($statement, OCI_ASSOC);
                oci_free_statement($statement);
                
                // Bejelentkeztetés
                $_SESSION['user_id'] = $user['USERID'];
                $_SESSION['user_name'] = $nev;
                
                // Átirányítás a főoldalra
                header("Location: chat.php");
                exit;
                */
            } else {
                $e = oci_error($insert_statement);
                $uzenet = "Hiba történt a regisztráció során: " . $e['message'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Regisztráció</title>
    <link rel="stylesheet" href="css/index.css">
</head>

<body>
    <div id="container">
        <div id="title">Linksy</div>
        <div id="reg">Regisztráció</div>
        <div id="login_button">
            <div class="button">
                <a class="link" href="bejelentkezes.php">Bejelentkezés</a>
            </div>
        </div>
    </div>
    <div id="box">
        <?php if (!empty($uzenet)): ?>
            <div class="uzenet <?php echo $siker ? 'siker' : 'hiba'; ?>"
                style="margin-bottom: 15px; color: <?php echo $siker ? 'green' : 'red'; ?>;">
                <?php echo $uzenet; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="name">Név</label>
            <input id="name" name="name" type="text" required>

            <label for="email">E-mail cím</label>
            <input id="email" name="email" type="email" required>

            <label for="date">Születési dátum</label>
            <input id="date" name="date" type="date" required>

            <label for="password">Jelszó</label>
            <input id="password" name="password" type="password" required>

            <label for="password2">Jelszó megerősítése</label>
            <input id="password2" name="password2" type="password" required>

            <label for="admin">Admin</label>
            <input id="admin" name="admin" type="checkbox">

            <button id="mentes" type="submit">Regisztráció</button>
        </form>
    </div>
    <div id="login">
        Van már fiókja? <a href="bejelentkezes.php">Bejelentkezés</a>
    </div>

    <?php
    // Adatbázis kapcsolat bezárása
    oci_close($conn);
    ?>
</body>

</html>