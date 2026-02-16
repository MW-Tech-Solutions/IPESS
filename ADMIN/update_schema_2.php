<?php
require_once 'includes/db.php';

try {
    // SQL to add the 'role' column
    $sql = "
    ALTER TABLE `users`
    ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'APPLICANT' AFTER `password_hash`;
    ";

    $pdo->exec($sql);
    echo "Column 'role' added to 'users' table successfully.<br>";

    // You might want to update existing users' roles here if needed
    // For example, to make user with id 1 an ADMIN
    $sql_update = "UPDATE `users` SET `role` = 'ADMIN' WHERE `user_id` = 1;";
    $pdo->exec($sql_update);
    echo "Set user with ID 1 as ADMIN.<br>";


} catch (PDOException $e) {
    die("DB ERROR: ". $e->getMessage());
}
?>