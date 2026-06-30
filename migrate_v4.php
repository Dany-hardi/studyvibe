<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

try {
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting migration...\n";

    // Helper function to check if column exists using INFORMATION_SCHEMA
    $columnExists = function($table, $column) use ($pdo) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = :db 
              AND TABLE_NAME = :tbl 
              AND COLUMN_NAME = :col
        ");
        $stmt->execute([
            'db' => DB_NAME,
            'tbl' => $table,
            'col' => $column
        ]);
        return (int)$stmt->fetchColumn() > 0;
    };

    // 1. Add explanation to lesson_questions
    if (!$columnExists('lesson_questions', 'explanation')) {
        $pdo->exec("ALTER TABLE `lesson_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
        echo "Added 'explanation' column to lesson_questions.\n";
    } else {
        echo "'explanation' column already exists in lesson_questions.\n";
    }

    // 2. Add explanation to course_questions
    if (!$columnExists('course_questions', 'explanation')) {
        $pdo->exec("ALTER TABLE `course_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
        echo "Added 'explanation' column to course_questions.\n";
    } else {
        echo "'explanation' column already exists in course_questions.\n";
    }

    // 3. Add explanation to live_eval_questions
    if (!$columnExists('live_eval_questions', 'explanation')) {
        $pdo->exec("ALTER TABLE `live_eval_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
        echo "Added 'explanation' column to live_eval_questions.\n";
    } else {
        echo "'explanation' column already exists in live_eval_questions.\n";
    }

    // 4. Add student_id to live_eval_registrations
    if (!$columnExists('live_eval_registrations', 'student_id')) {
        $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `student_id` INT DEFAULT NULL");
        
        // Add foreign key constraint
        $pdo->exec("
            ALTER TABLE `live_eval_registrations` 
            ADD CONSTRAINT `fk_live_eval_regs_student` 
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) 
            ON DELETE SET NULL
        ");
        echo "Added 'student_id' column and constraint to live_eval_registrations.\n";
    } else {
        echo "'student_id' column already exists in live_eval_registrations.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
