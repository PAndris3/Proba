<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: bejelentkezes.php");
    exit;
}

$userId = $_SESSION['user_id'];
$blockedId = $_POST['blockedId'];

if (isset($_POST['block'])) {
    // Check if a relationship already exists
    $query = "SELECT * FROM ISMERETSEG WHERE (userId = :userId AND ismerosUserId = :blockedId)";
    $statement = oci_parse($conn, $query);
    oci_bind_by_name($statement, ":userId", $userId);
    oci_bind_by_name($statement, ":blockedId", $blockedId);
    oci_execute($statement);
    $relation = oci_fetch_array($statement, OCI_ASSOC);
    oci_free_statement($statement);

    if ($relation) {
        // Update the existing relationship to "tiltott"
        $update_query = "UPDATE ISMERETSEG SET allapot = 'tiltott' WHERE userId = :userId AND ismerosUserId = :blockedId";
        $update_statement = oci_parse($conn, $update_query);
        oci_bind_by_name($update_statement, ":userId", $userId);
        oci_bind_by_name($update_statement, ":blockedId", $blockedId);
        oci_execute($update_statement);
        oci_free_statement($update_statement);
    } else {
        // Insert a new relationship with "tiltott"
        $insert_query = "INSERT INTO ISMERETSEG (userId, ismerosUserId, allapot) VALUES (:userId, :blockedId, 'tiltott')";
        $insert_statement = oci_parse($conn, $insert_query);
        oci_bind_by_name($insert_statement, ":userId", $userId);
        oci_bind_by_name($insert_statement, ":blockedId", $blockedId);
        oci_execute($insert_statement);
        oci_free_statement($insert_statement);
    }
} elseif (isset($_POST['unblock'])) {
    // Update the relationship back to "elfogadva"
    $update_query = "UPDATE ISMERETSEG SET allapot = 'elfogadva' WHERE userId = :userId AND ismerosUserId = :blockedId";
    $update_statement = oci_parse($conn, $update_query);
    oci_bind_by_name($update_statement, ":userId", $userId);
    oci_bind_by_name($update_statement, ":blockedId", $blockedId);
    oci_execute($update_statement);
    oci_free_statement($update_statement);
}

oci_close($conn);
header("Location: masik_profil.php?userId=" . $blockedId);
exit;
?>