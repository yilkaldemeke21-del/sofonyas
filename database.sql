CREATE DATABASE IF NOT EXISTS `sofonyas_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sofonyas_db`;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `student_id` VARCHAR(100) NOT NULL,
  `course` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `payment_status` ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` DATETIME NOT NULL,
  `paid_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
