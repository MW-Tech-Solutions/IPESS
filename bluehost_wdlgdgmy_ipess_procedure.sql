-- =========================================================================
-- ULTRA-SAFE DB-WIDE PRIMARY KEY & AUTO-INCREMENT REPAIR SCRIPT
-- Uses a MySQL stored procedure to apply fixes ONLY to tables lacking a Primary Key.
-- Prevents '#1068 - Multiple primary key defined' and duplicate key errors.
-- =========================================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS FixTablePrimaryKey;

DELIMITER //

CREATE PROCEDURE FixTablePrimaryKey(
    IN tableName VARCHAR(64),
    IN pkColumnName VARCHAR(64),
    IN isNumeric BOOLEAN,
    IN isBigInt BOOLEAN
)
BEGIN
    DECLARE pkExists INT;
    
    -- Check if primary key exists for this table in the active database
    SELECT COUNT(*) INTO pkExists
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = tableName
      AND constraint_type = 'PRIMARY KEY';
      
    -- If no primary key exists, apply the repair
    IF pkExists = 0 THEN
        -- A. Update any 0 values on the key column first (only for numeric columns)
        IF isNumeric THEN
            SET @new_id := 0;
            SET @sqlUpdateZero = CONCAT('UPDATE `', tableName, '` SET `', pkColumnName, '` = (@new_id := @new_id + 1) WHERE `', pkColumnName, '` = 0');
            PREPARE stmtZero FROM @sqlUpdateZero;
            EXECUTE stmtZero;
            DEALLOCATE PREPARE stmtZero;
        END IF;
        
        -- B. Safely remove duplicate rows
        SET @sqlAddTemp = CONCAT('ALTER TABLE `', tableName, '` ADD `temp_id` INT');
        PREPARE stmtAddTemp FROM @sqlAddTemp;
        EXECUTE stmtAddTemp;
        DEALLOCATE PREPARE stmtAddTemp;
        
        SET @counter := 0;
        SET @sqlSetTemp = CONCAT('UPDATE `', tableName, '` SET `temp_id` = (@counter := @counter + 1)');
        PREPARE stmtSetTemp FROM @sqlSetTemp;
        EXECUTE stmtSetTemp;
        DEALLOCATE PREPARE stmtSetTemp;
        
        SET @sqlDelete = CONCAT('DELETE t1 FROM `', tableName, '` t1 INNER JOIN `', tableName, '` t2 WHERE t1.`', pkColumnName, '` = t2.`', pkColumnName, '` AND t1.temp_id > t2.temp_id');
        PREPARE stmtDelete FROM @sqlDelete;
        EXECUTE stmtDelete;
        DEALLOCATE PREPARE stmtDelete;
        
        SET @sqlDropTemp = CONCAT('ALTER TABLE `', tableName, '` DROP COLUMN `temp_id`');
        PREPARE stmtDropTemp FROM @sqlDropTemp;
        EXECUTE stmtDropTemp;
        DEALLOCATE PREPARE stmtDropTemp;
        
        -- C. Add primary key constraint
        SET @sqlAddPK = CONCAT('ALTER TABLE `', tableName, '` ADD PRIMARY KEY (`', pkColumnName, '`)');
        PREPARE stmtAddPK FROM @sqlAddPK;
        EXECUTE stmtAddPK;
        DEALLOCATE PREPARE stmtAddPK;
        
        -- D. Modify key to auto-increment (only for numeric columns)
        IF isNumeric THEN
            IF isBigInt THEN
                SET @sqlModify = CONCAT('ALTER TABLE `', tableName, '` MODIFY `', pkColumnName, '` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            ELSE
                SET @sqlModify = CONCAT('ALTER TABLE `', tableName, '` MODIFY `', pkColumnName, '` INT NOT NULL AUTO_INCREMENT');
            END IF;
            PREPARE stmtModify FROM @sqlModify;
            EXECUTE stmtModify;
            DEALLOCATE PREPARE stmtModify;
        END IF;
    END IF;
END //

DELIMITER ;

CALL FixTablePrimaryKey('admins', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('admin_notifications', 'notification_id', TRUE, FALSE);
CALL FixTablePrimaryKey('admin_recovery_codes', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('admin_reports', 'report_id', TRUE, FALSE);
CALL FixTablePrimaryKey('applicants', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('applicant_accounts', 'user_id', TRUE, FALSE);
CALL FixTablePrimaryKey('applicant_notifications', 'notification_id', TRUE, FALSE);
CALL FixTablePrimaryKey('applications', 'application_id', TRUE, FALSE);
CALL FixTablePrimaryKey('application_progress', 'progress_id', TRUE, FALSE);
CALL FixTablePrimaryKey('application_status', 'status_id', TRUE, FALSE);
CALL FixTablePrimaryKey('audit_logs', 'log_id', TRUE, FALSE);
CALL FixTablePrimaryKey('chapter_submissions', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('courses', 'course_id', TRUE, FALSE);
CALL FixTablePrimaryKey('degree_types', 'degree_id', TRUE, FALSE);
CALL FixTablePrimaryKey('departments', 'dept_id', TRUE, FALSE);
CALL FixTablePrimaryKey('dept_applications', 'app_code', FALSE, FALSE);
CALL FixTablePrimaryKey('documents', 'doc_id', TRUE, FALSE);
CALL FixTablePrimaryKey('document_verification', 'verification_id', TRUE, TRUE);
CALL FixTablePrimaryKey('faculties', 'faculty_id', TRUE, FALSE);
CALL FixTablePrimaryKey('higher_education', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('login_attempts', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('nysc_details', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('olevel_exams', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('olevel_results', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('olevel_sittings', 'sitting_id', TRUE, FALSE);
CALL FixTablePrimaryKey('password_resets', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('personal_details', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('programme_capacities', 'capacity_id', TRUE, FALSE);
CALL FixTablePrimaryKey('programme_choices', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('referees', 'referee_id', TRUE, FALSE);
CALL FixTablePrimaryKey('referee_requests', 'request_id', TRUE, FALSE);
CALL FixTablePrimaryKey('referee_status', 'referee_id', TRUE, TRUE);
CALL FixTablePrimaryKey('referee_uploads', 'upload_id', TRUE, FALSE);
CALL FixTablePrimaryKey('research_details', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('reviewer_assignments', 'assignment_id', TRUE, FALSE);
CALL FixTablePrimaryKey('reviewer_feedback', 'feedback_id', TRUE, FALSE);
CALL FixTablePrimaryKey('reviewer_history', 'history_id', TRUE, FALSE);
CALL FixTablePrimaryKey('roles', 'role_id', TRUE, FALSE);
CALL FixTablePrimaryKey('student_messages', 'message_id', TRUE, FALSE);
CALL FixTablePrimaryKey('student_notifications', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('student_profiles', 'student_id', FALSE, FALSE);
CALL FixTablePrimaryKey('student_tracking_updates', 'id', TRUE, FALSE);
CALL FixTablePrimaryKey('study_modes', 'mode_id', TRUE, FALSE);
CALL FixTablePrimaryKey('supervisor_messages', 'message_id', TRUE, FALSE);
CALL FixTablePrimaryKey('supervisor_milestones', 'milestone_id', TRUE, FALSE);
CALL FixTablePrimaryKey('supervisor_notifications', 'notification_id', TRUE, FALSE);
CALL FixTablePrimaryKey('supervisor_profiles', 'supervisor_id', FALSE, FALSE);
CALL FixTablePrimaryKey('supervisor_students', 'student_id', FALSE, FALSE);
CALL FixTablePrimaryKey('system_settings', 'settings_id', TRUE, FALSE);
CALL FixTablePrimaryKey('users', 'user_id', TRUE, FALSE);

DROP PROCEDURE IF EXISTS FixTablePrimaryKey;
SET FOREIGN_KEY_CHECKS = 1;