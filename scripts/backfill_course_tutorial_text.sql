-- One-time backfill for missing tutorial_text content
-- Run this after the DB backup is created.

USE sofonyas_db;

SELECT COUNT(*) AS courses_to_update
FROM courses
WHERE tutorial_text IS NULL
   OR TRIM(tutorial_text) = ''
   OR tutorial_text = 'NULL';

START TRANSACTION;

UPDATE courses
SET tutorial_text = description
WHERE (tutorial_text IS NULL OR TRIM(tutorial_text) = '')
  AND description IS NOT NULL
  AND TRIM(description) <> '';

COMMIT;

SELECT COUNT(*) AS courses_still_missing_tutorial_text
FROM courses
WHERE tutorial_text IS NULL
   OR TRIM(tutorial_text) = ''
   OR tutorial_text = 'NULL';
