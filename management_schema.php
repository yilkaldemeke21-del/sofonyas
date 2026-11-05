<?php

function ensureManagementTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS church_families (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        family_number VARCHAR(30) DEFAULT NULL,
        family_name VARCHAR(180) NOT NULL,
        address VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_church_family_number (family_number),
        INDEX idx_church_family_name (family_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_branches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        branch_code VARCHAR(30) NOT NULL UNIQUE,
        branch_name VARCHAR(180) NOT NULL,
        parent_id INT UNSIGNED DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(40) DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_church_branches_parent (parent_id),
        INDEX idx_church_branches_status (status),
        CONSTRAINT fk_church_branch_parent FOREIGN KEY (parent_id) REFERENCES church_branches (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_number VARCHAR(30) DEFAULT NULL,
        verification_token CHAR(64) DEFAULT NULL,
        full_name VARCHAR(180) NOT NULL,
        phone VARCHAR(40) DEFAULT NULL,
        email VARCHAR(190) DEFAULT NULL,
        gender VARCHAR(30) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        birth_date DATE DEFAULT NULL,
        photo_path VARCHAR(255) DEFAULT NULL,
        family_id INT UNSIGNED DEFAULT NULL,
        branch_id INT UNSIGNED DEFAULT NULL,
        class_group VARCHAR(100) DEFAULT NULL,
        service_group VARCHAR(100) DEFAULT NULL,
        joined_on DATE NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_church_member_number (member_number),
        INDEX idx_church_members_status (status),
        INDEX idx_church_members_name (full_name),
        INDEX idx_church_members_family (family_id),
        INDEX idx_church_members_branch (branch_id),
        INDEX idx_church_members_class_group (class_group),
        INDEX idx_church_members_service_group (service_group)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $memberColumnDefinitions = [
        'member_number' => 'ALTER TABLE church_members ADD COLUMN member_number VARCHAR(30) DEFAULT NULL AFTER id',
        'verification_token' => 'ALTER TABLE church_members ADD COLUMN verification_token CHAR(64) DEFAULT NULL AFTER member_number',
        'birth_date' => 'ALTER TABLE church_members ADD COLUMN birth_date DATE DEFAULT NULL AFTER address',
        'photo_path' => 'ALTER TABLE church_members ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER birth_date',
        'family_id' => 'ALTER TABLE church_members ADD COLUMN family_id INT UNSIGNED DEFAULT NULL AFTER photo_path',
        'branch_id' => 'ALTER TABLE church_members ADD COLUMN branch_id INT UNSIGNED DEFAULT NULL AFTER family_id',
        'class_group' => 'ALTER TABLE church_members ADD COLUMN class_group VARCHAR(100) DEFAULT NULL AFTER family_id',
        'service_group' => 'ALTER TABLE church_members ADD COLUMN service_group VARCHAR(100) DEFAULT NULL AFTER class_group',
    ];
    $memberColumnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    foreach ($memberColumnDefinitions as $column => $definition) {
        $memberColumnCheck->execute([':table' => 'church_members', ':column' => $column]);
        if ((int)$memberColumnCheck->fetchColumn() === 0) {
            $pdo->exec($definition);
        }
    }
    $pdo->exec("UPDATE church_members SET member_number = CONCAT('MEM-', LPAD(id, 6, '0')) WHERE member_number IS NULL OR member_number = ''");
    $memberNumberColumn = $pdo->query("SHOW COLUMNS FROM church_members LIKE 'member_number'")->fetch(PDO::FETCH_ASSOC);
    if ($memberNumberColumn && strtoupper((string)$memberNumberColumn['Null']) === 'YES') {
        $pdo->exec('ALTER TABLE church_members MODIFY member_number VARCHAR(30) NOT NULL');
    }
    $memberNumberIndex = $pdo->query("SHOW INDEX FROM church_members WHERE Key_name = 'uq_church_member_number'")->fetch(PDO::FETCH_ASSOC);
    if (!$memberNumberIndex) {
        $pdo->exec('ALTER TABLE church_members ADD UNIQUE KEY uq_church_member_number (member_number)');
    }
    $verificationTokenIndex = $pdo->query("SHOW INDEX FROM church_members WHERE Key_name = 'uq_church_member_verification_token'")->fetch(PDO::FETCH_ASSOC);
    if (!$verificationTokenIndex) {
        $pdo->exec('ALTER TABLE church_members ADD UNIQUE KEY uq_church_member_verification_token (verification_token)');
    }
    $membersWithoutVerificationToken = $pdo->query("SELECT id FROM church_members WHERE verification_token IS NULL OR verification_token = ''")->fetchAll(PDO::FETCH_COLUMN);
    $saveVerificationToken = $pdo->prepare('UPDATE church_members SET verification_token = :token WHERE id = :id AND (verification_token IS NULL OR verification_token = "")');
    foreach ($membersWithoutVerificationToken as $memberId) {
        $saveVerificationToken->execute([':token' => bin2hex(random_bytes(32)), ':id' => (int)$memberId]);
    }

    $familyNumberCheck = $pdo->query("SHOW INDEX FROM church_families WHERE Key_name = 'uq_church_family_number'")->fetch(PDO::FETCH_ASSOC);
    if (!$familyNumberCheck) {
        $pdo->exec('ALTER TABLE church_families ADD UNIQUE KEY uq_church_family_number (family_number)');
    }
    $pdo->exec("UPDATE church_families SET family_number = CONCAT('FAM-', LPAD(id, 6, '0')) WHERE family_number IS NULL OR family_number = ''");
    $familyIndex = $pdo->query("SHOW INDEX FROM church_members WHERE Key_name = 'idx_church_members_family'")->fetch(PDO::FETCH_ASSOC);
    if (!$familyIndex) {
        $pdo->exec('ALTER TABLE church_members ADD INDEX idx_church_members_family (family_id)');
    }
    $familyConstraint = $pdo->query("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'church_members' AND CONSTRAINT_NAME = 'fk_church_member_family'")->fetchColumn();
    if ((int)$familyConstraint === 0) {
        $pdo->exec('ALTER TABLE church_members ADD CONSTRAINT fk_church_member_family FOREIGN KEY (family_id) REFERENCES church_families (id) ON DELETE SET NULL');
    }
    $branchIndex = $pdo->query("SHOW INDEX FROM church_members WHERE Key_name = 'idx_church_members_branch'")->fetch(PDO::FETCH_ASSOC);
    if (!$branchIndex) {
        $pdo->exec('ALTER TABLE church_members ADD INDEX idx_church_members_branch (branch_id)');
    }
    $branchConstraint = $pdo->query("SELECT COUNT(*) FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'church_members' AND CONSTRAINT_NAME = 'fk_church_member_branch'")->fetchColumn();
    if ((int)$branchConstraint === 0) {
        $pdo->exec('ALTER TABLE church_members ADD CONSTRAINT fk_church_member_branch FOREIGN KEY (branch_id) REFERENCES church_branches (id) ON DELETE SET NULL');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_member_change_requests (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id INT UNSIGNED NOT NULL,
        requested_by_student_id VARCHAR(100) NOT NULL,
        requested_changes JSON NOT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        review_note VARCHAR(1000) DEFAULT NULL,
        notification_status ENUM('not_sent', 'sent', 'failed') NOT NULL DEFAULT 'not_sent',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_member_change_requests_status_date (status, created_at),
        INDEX idx_member_change_requests_member (member_id),
        CONSTRAINT fk_member_change_request_member FOREIGN KEY (member_id) REFERENCES church_members (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_meetings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        branch_id INT UNSIGNED DEFAULT NULL,
        title VARCHAR(180) NOT NULL,
        meeting_at DATETIME NOT NULL,
        location VARCHAR(180) DEFAULT NULL,
        attendees MEDIUMTEXT DEFAULT NULL,
        minutes MEDIUMTEXT DEFAULT NULL,
        decisions MEDIUMTEXT DEFAULT NULL,
        status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_church_meetings_date (meeting_at),
        INDEX idx_church_meetings_branch (branch_id),
        INDEX idx_church_meetings_status (status),
        CONSTRAINT fk_church_meeting_branch FOREIGN KEY (branch_id) REFERENCES church_branches (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_attendance (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id INT UNSIGNED NOT NULL,
        service_name VARCHAR(120) NOT NULL,
        service_date DATE NOT NULL,
        checkin_time TIME DEFAULT NULL,
        event_type VARCHAR(80) DEFAULT NULL,
        attendance_status ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
        attendance_method ENUM('manual', 'qr') NOT NULL DEFAULT 'manual',
        notes VARCHAR(500) DEFAULT NULL,
        recorded_by VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_church_attendance_service (member_id, service_date, service_name),
        INDEX idx_church_attendance_date (service_date),
        CONSTRAINT fk_church_attendance_member FOREIGN KEY (member_id) REFERENCES church_members (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $attendanceColumns = [
        'checkin_time' => 'ALTER TABLE church_attendance ADD COLUMN checkin_time TIME DEFAULT NULL AFTER service_date',
        'event_type' => 'ALTER TABLE church_attendance ADD COLUMN event_type VARCHAR(80) DEFAULT NULL AFTER service_name',
        'attendance_method' => 'ALTER TABLE church_attendance ADD COLUMN attendance_method ENUM(\'manual\', \'qr\') NOT NULL DEFAULT \'manual\' AFTER attendance_status',
    ];
    $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    foreach ($attendanceColumns as $column => $definition) {
        $columnCheck->execute([':table' => 'church_attendance', ':column' => $column]);
        if ((int)$columnCheck->fetchColumn() === 0) {
            $pdo->exec($definition);
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_type ENUM('income', 'expense') NOT NULL,
        category VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        transaction_date DATE NOT NULL,
        payment_method VARCHAR(60) DEFAULT NULL,
        reference VARCHAR(120) DEFAULT NULL,
        recorded_by VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_finance_type_date (transaction_type, transaction_date),
        INDEX idx_finance_date (transaction_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_ministry_staff (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(180) NOT NULL,
        role ENUM('teacher', 'singer', 'member') NOT NULL,
        phone VARCHAR(40) DEFAULT NULL,
        email VARCHAR(190) DEFAULT NULL,
        specialization VARCHAR(150) DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        notes VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ministry_staff_role_status (role, status),
        INDEX idx_ministry_staff_name (full_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $staffRoleColumn = $pdo->query("SHOW COLUMNS FROM church_ministry_staff LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    if (strpos(strtolower((string)($staffRoleColumn['Type'] ?? '')), "'member'") === false) {
        $pdo->exec("ALTER TABLE church_ministry_staff MODIFY role ENUM('teacher', 'singer', 'member') NOT NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS church_education_programs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        program_type ENUM('sunday_school', 'spiritual', 'education', 'choir', 'youth_service', 'children_service', 'education_service', 'charity_service', 'prayer_hymn') NOT NULL,
        program_name VARCHAR(180) NOT NULL,
        level_or_group VARCHAR(100) DEFAULT NULL,
        schedule VARCHAR(150) DEFAULT NULL,
        leader_id INT UNSIGNED DEFAULT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_education_program_type_status (program_type, status),
        INDEX idx_education_program_leader (leader_id),
        CONSTRAINT fk_education_program_leader FOREIGN KEY (leader_id) REFERENCES church_ministry_staff (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $programTypeColumn = $pdo->query("SHOW COLUMNS FROM church_education_programs LIKE 'program_type'")->fetch(PDO::FETCH_ASSOC);
    $programTypeDefinition = (string)($programTypeColumn['Type'] ?? '');
    $additionalProgramTypes = ['youth_service', 'children_service', 'education_service', 'charity_service', 'prayer_hymn'];
    $requiresProgramTypeMigration = false;
    foreach ($additionalProgramTypes as $programType) {
        if (strpos($programTypeDefinition, "'{$programType}'") === false) {
            $requiresProgramTypeMigration = true;
            break;
        }
    }
    if ($requiresProgramTypeMigration) {
        $pdo->exec("ALTER TABLE church_education_programs MODIFY program_type ENUM('sunday_school', 'spiritual', 'education', 'choir', 'youth_service', 'children_service', 'education_service', 'charity_service', 'prayer_hymn') NOT NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS school_academic_years (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        year_name VARCHAR(60) NOT NULL UNIQUE,
        start_date DATE DEFAULT NULL,
        end_date DATE DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_academic_year_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS school_semesters (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        academic_year_id INT UNSIGNED NOT NULL,
        semester_name VARCHAR(80) NOT NULL,
        start_date DATE DEFAULT NULL,
        end_date DATE DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_school_semester (academic_year_id, semester_name),
        INDEX idx_school_semester_year (academic_year_id),
        INDEX idx_school_semester_status (status),
        CONSTRAINT fk_school_semester_year FOREIGN KEY (academic_year_id) REFERENCES school_academic_years (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS school_subjects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subject_name VARCHAR(120) NOT NULL UNIQUE,
        subject_code VARCHAR(30) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school_subject_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_weekly_schedule (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_id INT DEFAULT NULL,
        course_name VARCHAR(180) NOT NULL,
        teacher_id INT DEFAULT NULL,
        weekday TINYINT UNSIGNED NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        location VARCHAR(180) DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_weekly_schedule_day_time (weekday, start_time),
        INDEX idx_weekly_schedule_course (course_id),
        INDEX idx_weekly_schedule_teacher (teacher_id),
        INDEX idx_weekly_schedule_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS spiritual_events (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_type ENUM('spiritual_class', 'liturgy', 'prayer', 'service', 'holiday', 'special') NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT DEFAULT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME DEFAULT NULL,
        location VARCHAR(180) DEFAULT NULL,
        status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_spiritual_event_start (starts_at),
        INDEX idx_spiritual_event_type (event_type),
        INDEX idx_spiritual_event_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS spiritual_appointments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        appointment_type ENUM('confession', 'counseling', 'private') NOT NULL,
        member_id INT UNSIGNED DEFAULT NULL,
        person_name VARCHAR(180) DEFAULT NULL,
        starts_at DATETIME NOT NULL,
        assigned_to INT DEFAULT NULL,
        private_notes TEXT DEFAULT NULL,
        status ENUM('requested', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'requested',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_spiritual_appointment_start (starts_at),
        INDEX idx_spiritual_appointment_status (status),
        INDEX idx_spiritual_appointment_assignee (assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS spiritual_access (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT NOT NULL UNIQUE,
        permission ENUM('view', 'manage') NOT NULL,
        can_manage_confidential TINYINT(1) NOT NULL DEFAULT 0,
        granted_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_spiritual_access_permission (permission),
        INDEX idx_spiritual_access_confidential (can_manage_confidential),
        CONSTRAINT fk_spiritual_access_user FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
        CONSTRAINT fk_spiritual_access_granted_by FOREIGN KEY (granted_by) REFERENCES admin_users (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_classes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(160) NOT NULL,
        education_level VARCHAR(100) DEFAULT NULL,
        subject_id INT UNSIGNED DEFAULT NULL,
        academic_year_id INT UNSIGNED DEFAULT NULL,
        semester_id INT UNSIGNED DEFAULT NULL,
        age_min TINYINT UNSIGNED DEFAULT NULL,
        age_max TINYINT UNSIGNED DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        weekday TINYINT UNSIGNED DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        location VARCHAR(180) DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sunday_classes_teacher (teacher_id),
        INDEX idx_sunday_classes_age (age_min, age_max),
        INDEX idx_sunday_classes_status (status),
        INDEX idx_sunday_classes_subject (subject_id),
        INDEX idx_sunday_classes_year (academic_year_id),
        INDEX idx_sunday_classes_semester (semester_id),
        CONSTRAINT fk_sunday_class_subject FOREIGN KEY (subject_id) REFERENCES school_subjects (id) ON DELETE SET NULL,
        CONSTRAINT fk_sunday_class_year FOREIGN KEY (academic_year_id) REFERENCES school_academic_years (id) ON DELETE SET NULL,
        CONSTRAINT fk_sunday_class_semester FOREIGN KEY (semester_id) REFERENCES school_semesters (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $classColumns = ['subject_id', 'academic_year_id', 'semester_id'];
    foreach ($classColumns as $columnName) {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM sunday_school_classes LIKE '{$columnName}'")->fetch(PDO::FETCH_ASSOC);
        if (!$columnCheck) {
            $pdo->exec("ALTER TABLE sunday_school_classes ADD COLUMN {$columnName} INT UNSIGNED DEFAULT NULL AFTER education_level");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_enrollments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        class_id INT UNSIGNED NOT NULL,
        member_id INT UNSIGNED NOT NULL,
        student_id VARCHAR(100) DEFAULT NULL,
        guardian_name VARCHAR(180) DEFAULT NULL,
        guardian_phone VARCHAR(40) DEFAULT NULL,
        guardian_email VARCHAR(190) DEFAULT NULL,
        enrolled_on DATE NOT NULL,
        status ENUM('active', 'inactive', 'completed') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sunday_class_member (class_id, member_id),
        INDEX idx_sunday_enrollment_student (student_id),
        INDEX idx_sunday_enrollment_status (status),
        CONSTRAINT fk_sunday_enrollment_class FOREIGN KEY (class_id) REFERENCES sunday_school_classes (id) ON DELETE RESTRICT,
        CONSTRAINT fk_sunday_enrollment_member FOREIGN KEY (member_id) REFERENCES church_members (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_attendance (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT UNSIGNED NOT NULL,
        attendance_date DATE NOT NULL,
        attendance_status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
        notes VARCHAR(500) DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sunday_attendance_day (enrollment_id, attendance_date),
        INDEX idx_sunday_attendance_date (attendance_date),
        CONSTRAINT fk_sunday_attendance_enrollment FOREIGN KEY (enrollment_id) REFERENCES sunday_school_enrollments (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_assessments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        score DECIMAL(7,2) NOT NULL,
        max_score DECIMAL(7,2) NOT NULL,
        assessed_on DATE NOT NULL,
        teacher_notes VARCHAR(1000) DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sunday_assessment_enrollment (enrollment_id),
        INDEX idx_sunday_assessment_date (assessed_on),
        CONSTRAINT fk_sunday_assessment_enrollment FOREIGN KEY (enrollment_id) REFERENCES sunday_school_enrollments (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_lessons (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        class_id INT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        lesson_notes MEDIUMTEXT DEFAULT NULL,
        resource_url VARCHAR(500) DEFAULT NULL,
        resource_type ENUM('none', 'video', 'audio', 'pdf', 'link') NOT NULL DEFAULT 'none',
        week_of DATE DEFAULT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sunday_lessons_class_publish (class_id, is_published),
        CONSTRAINT fk_sunday_lesson_class FOREIGN KEY (class_id) REFERENCES sunday_school_classes (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT UNSIGNED NOT NULL,
        recipient_email VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        notification_type VARCHAR(40) NOT NULL,
        delivery_status ENUM('sent', 'failed') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sunday_notifications_enrollment (enrollment_id),
        INDEX idx_sunday_notifications_date (created_at),
        CONSTRAINT fk_sunday_notification_enrollment FOREIGN KEY (enrollment_id) REFERENCES sunday_school_enrollments (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sunday_school_certificates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT UNSIGNED NOT NULL,
        certificate_code VARCHAR(50) NOT NULL UNIQUE,
        issued_by INT DEFAULT NULL,
        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sunday_certificate_enrollment (enrollment_id),
        CONSTRAINT fk_sunday_certificate_enrollment FOREIGN KEY (enrollment_id) REFERENCES sunday_school_enrollments (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_projects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(180) NOT NULL UNIQUE,
        description TEXT DEFAULT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_finance_projects_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $financeColumnDefinitions = [
        'giving_type' => "ALTER TABLE finance_transactions ADD COLUMN giving_type ENUM('general', 'tithe', 'donation') NOT NULL DEFAULT 'general' AFTER transaction_type",
        'donor_member_id' => 'ALTER TABLE finance_transactions ADD COLUMN donor_member_id INT UNSIGNED DEFAULT NULL AFTER giving_type',
        'donor_name' => 'ALTER TABLE finance_transactions ADD COLUMN donor_name VARCHAR(180) DEFAULT NULL AFTER donor_member_id',
        'is_anonymous' => 'ALTER TABLE finance_transactions ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER donor_name',
        'project_id' => 'ALTER TABLE finance_transactions ADD COLUMN project_id INT UNSIGNED DEFAULT NULL AFTER is_anonymous',
        'receipt_number' => 'ALTER TABLE finance_transactions ADD COLUMN receipt_number VARCHAR(50) DEFAULT NULL AFTER reference',
    ];
    $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    foreach ($financeColumnDefinitions as $column => $definition) {
        $columnCheck->execute([':table' => 'finance_transactions', ':column' => $column]);
        if ((int)$columnCheck->fetchColumn() === 0) {
            $pdo->exec($definition);
        }
    }

    $receiptIndex = $pdo->query("SHOW INDEX FROM finance_transactions WHERE Key_name = 'uq_finance_receipt_number'")->fetch(PDO::FETCH_ASSOC);
    if (!$receiptIndex) {
        $pdo->exec('ALTER TABLE finance_transactions ADD UNIQUE KEY uq_finance_receipt_number (receipt_number)');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_access (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT NOT NULL UNIQUE,
        permission ENUM('view', 'manage') NOT NULL,
        granted_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_finance_access_permission (permission),
        CONSTRAINT fk_finance_access_user FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE CASCADE,
        CONSTRAINT fk_finance_access_granted_by FOREIGN KEY (granted_by) REFERENCES admin_users (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_id INT DEFAULT NULL,
        actor_username VARCHAR(100) NOT NULL,
        action VARCHAR(40) NOT NULL,
        entity_type VARCHAR(30) NOT NULL,
        entity_id BIGINT UNSIGNED DEFAULT NULL,
        old_data JSON DEFAULT NULL,
        new_data JSON DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_finance_audit_entity (entity_type, entity_id),
        INDEX idx_finance_audit_actor_date (actor_id, created_at),
        INDEX idx_finance_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$requestedScript = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
$schemaScript = realpath(__FILE__);
if ($requestedScript !== false && $schemaScript !== false && strcasecmp($requestedScript, $schemaScript) === 0) {
    session_start();
    require_once __DIR__ . '/db.php';
    requireRole(['admin'], $pdo);
    header('Content-Type: text/html; charset=utf-8');

    if (!($pdo instanceof PDO)) {
        http_response_code(503);
        exit('Database connection is unavailable.');
    }

    try {
        ensureManagementTables($pdo);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Management schema status</title></head><body style="font-family:Arial,sans-serif;max-width:720px;margin:48px auto;padding:24px;color:#202b27"><h1>Church management schema is ready</h1><p>The management tables and required columns were checked successfully.</p><p><a href="admin_management_dashboard.php">Return to management dashboard</a></p></body></html>';
    } catch (Throwable $exception) {
        error_log('Direct management schema check failed: ' . $exception->getMessage());
        http_response_code(500);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Schema check failed</title></head><body><h1>Management schema check failed</h1><p>Check the application error log for details.</p></body></html>';
    }
    exit;
}