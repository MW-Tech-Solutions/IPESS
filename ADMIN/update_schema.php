<?php
require_once 'includes/db.php';

try {
    // SQL to create the new table
    $sql = "
    CREATE TABLE IF NOT EXISTS `document_verifications` (
      `verification_id` INT AUTO_INCREMENT PRIMARY KEY,
      `doc_id` INT NOT NULL,
      `status` ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
      `verified_by` INT NULL,
      `verified_at` DATETIME NULL,
      `comments` TEXT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`doc_id`) REFERENCES `documents`(`doc_id`) ON DELETE CASCADE,
      FOREIGN KEY (`verified_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB;
    ";

    $pdo->exec($sql);
    echo "Table 'document_verifications' created successfully.<br>";

    // SQL to populate the new table with existing documents
    $sql_populate = "
    INSERT INTO `document_verifications` (doc_id)
    SELECT doc_id FROM `documents`
    WHERE doc_id NOT IN (SELECT doc_id FROM `document_verifications`);
    ";

    $pdo->exec($sql_populate);
    echo "Populated 'document_verifications' with existing documents.<br>";

} catch (PDOException $e) {
    die("DB ERROR: ". $e->getMessage());
}
?>