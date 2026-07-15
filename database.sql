-- Database initialization for Tackl Planner
CREATE DATABASE IF NOT EXISTS `tackl_planner` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tackl_planner`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL,
  `account_type` ENUM('Child', 'Teen', 'Adult', 'Parent') NOT NULL,
  `age` TINYINT UNSIGNED NULL,
  `adult_role` ENUM('Student', 'Worker', 'Other') NULL,
  `parent_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample notes:
-- After importing this file into XAMPP MySQL, use the /register.php page to create new Child, Teen, Adult, and Parent accounts.
