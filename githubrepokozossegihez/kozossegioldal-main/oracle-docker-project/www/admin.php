<?php
// Adatbázis kapcsolat és session kezelés
include 'db_connect.php';
session_start();

// Ellenőrizzük, hogy az admin be van-e jelentkezve
if (!isset($_SESSION['user_id']) || $_SESSION['admin'] !== 'I') {
    header("Location: bejelentkezes.php");
    exit;
}

// Jelentések lekérdezése
$query = "SELECT j.JELENTESID, j.USERID, j.JELENTETTUSERID, j.INDOK, 
                 u1.NEV AS FELHASZNALO, u2.NEV AS JELENTETT
          FROM JELENTES j
          JOIN FELHASZNALO u1 ON j.USERID = u1.USERID
          JOIN FELHASZNALO u2 ON j.JELENTETTUSERID = u2.USERID";
$statement = oci_parse($conn, $query);
oci_execute($statement);

// Jelentés kezelése
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['elvetes'])) {
        $jelentesId = $_POST['jelentesId'];
        $delete_query = "DELETE FROM JELENTES WHERE JELENTESID = :jelentesId";
        $delete_statement = oci_parse($conn, $delete_query);
        oci_bind_by_name($delete_statement, ":jelentesId", $jelentesId);
        oci_execute($delete_statement);
        oci_free_statement($delete_statement);
        header("Location: admin.php");
        exit;
    } elseif (isset($_POST['tiltas'])) {
        $jelentettUserId = $_POST['jelentettUserId'];

        // Felhasználó törlése
        $delete_user_query = "DELETE FROM FELHASZNALO WHERE USERID = :jelentettUserId";
        $delete_user_statement = oci_parse($conn, $delete_user_query);
        oci_bind_by_name($delete_user_statement, ":jelentettUserId", $jelentettUserId);
        oci_execute($delete_user_statement);
        oci_free_statement($delete_user_statement);

        // Jelentés törlése
        $jelentesId = $_POST['jelentesId'];
        $delete_query = "DELETE FROM JELENTES WHERE JELENTESID = :jelentesId";
        $delete_statement = oci_parse($conn, $delete_query);
        oci_bind_by_name($delete_statement, ":jelentesId", $jelentesId);
        oci_execute($delete_statement);
        oci_free_statement($delete_statement);

        header("Location: admin.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>
    
<a href="kijelentkezes.php">Kijelentkezés</a>
    <h1>Admin Panel - Jelentések kezelése</h1>
    <table>
        <thead>
            <tr>
                <th>Jelentés ID</th>
                <th>Felhasználó</th>
                <th>Jelentett</th>
                <th>Indok</th>
                <th>Műveletek</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = oci_fetch_array($statement, OCI_ASSOC)): ?>
                <tr>
                    <td><?php echo $row['JELENTESID']; ?></td>
                    <td><?php echo htmlspecialchars($row['FELHASZNALO']); ?></td>
                    <td><?php echo htmlspecialchars($row['JELENTETT']); ?></td>
                    <td><?php echo htmlspecialchars($row['INDOK']); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="jelentesId" value="<?php echo $row['JELENTESID']; ?>">
                            <button type="submit" name="elvetes">Elvetés</button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="jelentesId" value="<?php echo $row['JELENTESID']; ?>">
                            <input type="hidden" name="jelentettUserId" value="<?php echo $row['JELENTETTUSERID']; ?>">
                            <button type="submit" name="tiltas">Törlés</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php oci_free_statement($statement); ?>
        </tbody>
    </table>
    <?php oci_close($conn); ?>
</body>

</html>