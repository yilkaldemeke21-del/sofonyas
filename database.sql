CREATE DATABASE IF NOT EXISTS `sofonyas_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sofonyas_db`;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_name` VARCHAR(255) NOT NULL,
  `course_code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `instructor` VARCHAR(255),
  `pdf_file` VARCHAR(255) DEFAULT NULL,
  `tutorial_topic` VARCHAR(255) DEFAULT NULL,
  `tutorial_text` TEXT DEFAULT NULL,
  `tutorial_image` VARCHAR(255) DEFAULT NULL,
  `tutorial_audio` VARCHAR(255) DEFAULT NULL,
  `tutorial_video` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `student_id` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(255) NOT NULL,
  `option_b` VARCHAR(255) NOT NULL,
  `option_c` VARCHAR(255) NOT NULL,
  `option_d` VARCHAR(255) NOT NULL,
  `correct_answer` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exam_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(100) NOT NULL,
  `student_name` VARCHAR(255) NOT NULL,
  `exam_type` VARCHAR(50) NOT NULL,
  `score` INT NOT NULL DEFAULT 0,
  `total_questions` INT NOT NULL DEFAULT 0,
  `answers` JSON DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `students` (`name`, `email`, `student_id`, `password_hash`) VALUES (
  'Yilkal Demeke',
  'yilkaldemeke21@gmail.com',
  '27',
  '$2y$10$7y8kWM.22Ewzaycs9o2iJOewINLr4nVEac2u7j9wJviTcWxxqMhIu'
);

INSERT IGNORE INTO `registrations` (`id`, `name`, `email`, `student_id`, `course`, `amount`, `payment_status`, `created_at`) VALUES (
  'reg_27',
  'Yilkal Demeke',
  'yilkaldemeke21@gmail.com',
  '27',
  'ነገረ ሃይማኖት',
  0.00,
  'unpaid',
  NOW()
);

INSERT IGNORE INTO `admin_users` (`username`, `password_hash`, `email`) VALUES ('sofonyas', '$2y$10$46zeuBUB4D6qkdAp9B7OG.ekP7SB3IljRJUHezvp3pfOEVWlTahxC', 'yilkaldemeke21@gmail.com');
