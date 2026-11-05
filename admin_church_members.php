<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';
require_once __DIR__ . '/mail_config.php';
requireRole(['admin'], $pdo);
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit(adminManagementText('db_unavailable'));
}

ensureManagementTables($pdo);
$csrf = csrfToken();
$errorKey = '';
$savedKey = [
    'member' => 'member_registered',
    'updated' => 'member_saved_update',
    'family' => 'member_family_created',
    'status' => 'member_status_updated',
    'review_sent' => 'member_portal_notification_sent',
    'review_failed' => 'member_portal_notification_failed',
    'review_saved' => 'member_portal_review_saved',
][(string)($_GET['saved'] ?? '')] ?? '';
$privateUploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'church_members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $errorKey = 'csrf_error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'review_change_request') {
                $requestId = (int)($_POST['request_id'] ?? 0);
                $decision = (string)($_POST['decision'] ?? '');
                $reviewNote = trim((string)($_POST['review_note'] ?? ''));
                if ($requestId < 1 || !in_array($decision, ['approved', 'rejected'], true) || strlen($reviewNote) > 1000) {
                    $errorKey = 'member_portal_validation';
                } else {
                    $pdo->beginTransaction();
                    $requestStmt = $pdo->prepare("SELECT id, member_id, requested_by_student_id, requested_changes FROM church_member_change_requests WHERE id = :id AND status = 'pending' FOR UPDATE");
                    $requestStmt->execute([':id' => $requestId]);
                    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$request) {
                        $pdo->rollBack();
                        $errorKey = 'member_portal_validation';
                    } else {
                        $changes = json_decode((string)$request['requested_changes'], true);
                        $allowedChanges = [];
                        $allowedFields = ['full_name' => 180, 'phone' => 40, 'address' => 255];
                        if ($decision === 'approved' && is_array($changes)) {
                            foreach ($allowedFields as $field => $maxLength) {
                                if (array_key_exists($field, $changes) && is_string($changes[$field]) && strlen($changes[$field]) <= $maxLength && ($field !== 'full_name' || trim($changes[$field]) !== '')) {
                                    $allowedChanges[$field] = $changes[$field];
                                }
                            }
                        }

                        if ($decision === 'approved' && !$allowedChanges) {
                            $pdo->rollBack();
                            $errorKey = 'member_portal_validation';
                        } else {
                            if ($decision === 'approved') {
                                $assignments = [];
                                $values = [':member_id' => (int)$request['member_id']];
                                foreach ($allowedChanges as $field => $value) {
                                    $assignments[] = $field . ' = :' . $field;
                                    $values[':' . $field] = $value !== '' ? $value : null;
                                }
                                $updateMember = $pdo->prepare('UPDATE church_members SET ' . implode(', ', $assignments) . ' WHERE id = :member_id');
                                $updateMember->execute($values);
                            }
                            $reviewStmt = $pdo->prepare('UPDATE church_member_change_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW(), review_note = :review_note WHERE id = :id AND status = \'pending\'');
                            $reviewStmt->execute([
                                ':status' => $decision,
                                ':reviewed_by' => (int)$_SESSION['admin_id'],
                                ':review_note' => $reviewNote !== '' ? $reviewNote : null,
                                ':id' => $requestId,
                            ]);
                            $memberEmailStmt = $pdo->prepare('SELECT s.email, s.email_verified, m.full_name FROM students s JOIN church_members m ON m.id = :member_id WHERE s.student_id = :student_id LIMIT 1');
                            $memberEmailStmt->execute([':member_id' => (int)$request['member_id'], ':student_id' => (string)$request['requested_by_student_id']]);
                            $emailTarget = $memberEmailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                            $pdo->commit();
                            $notificationStatus = 'not_sent';
                            if ($emailTarget && (int)$emailTarget['email_verified'] === 1 && filter_var($emailTarget['email'], FILTER_VALIDATE_EMAIL)) {
                                $mailBody = '<p>' . htmlspecialchars(adminManagementText($decision === 'approved' ? 'member_portal_approved' : 'member_portal_rejected'), ENT_QUOTES, 'UTF-8') . '</p>'
                                    . '<p>' . htmlspecialchars((string)$emailTarget['full_name'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars((string)$reviewNote, ENT_QUOTES, 'UTF-8') . '</p>';
                                $notificationStatus = sendAppEmail((string)$emailTarget['email'], adminManagementText('member_portal_request_status'), $mailBody) ? 'sent' : 'failed';
                            }
                            $noticeStmt = $pdo->prepare('UPDATE church_member_change_requests SET notification_status = :notification_status WHERE id = :id');
                            $noticeStmt->execute([':notification_status' => $notificationStatus, ':id' => $requestId]);
                            $savedValue = $notificationStatus === 'sent' ? 'review_sent' : ($notificationStatus === 'failed' ? 'review_failed' : 'review_saved');
                            header('Location: admin_church_members.php?lang=' . urlencode($adminManagementLanguage) . '&saved=' . $savedValue);
                            exit;
                        }
                    }
                }
            } elseif ($action === 'add_family') {
                $familyName = trim((string)($_POST['family_name'] ?? ''));
                $familyAddress = trim((string)($_POST['family_address'] ?? ''));
                if ($familyName === '' || strlen($familyName) > 180) {
                    $errorKey = 'member_family_required';
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('INSERT INTO church_families (family_number, family_name, address) VALUES (:family_number, :family_name, :address)');
                    $stmt->execute([
                        ':family_number' => 'TMP-' . bin2hex(random_bytes(8)),
                        ':family_name' => $familyName,
                        ':address' => $familyAddress !== '' ? $familyAddress : null,
                    ]);
                    $familyId = (int)$pdo->lastInsertId();
                    $familyNumber = 'FAM-' . str_pad((string)$familyId, 6, '0', STR_PAD_LEFT);
                    $update = $pdo->prepare('UPDATE church_families SET family_number = :number WHERE id = :id');
                    $update->execute([':number' => $familyNumber, ':id' => $familyId]);
                    $pdo->commit();
                    header('Location: admin_church_members.php?lang=' . urlencode($adminManagementLanguage) . '&saved=family');
                    exit;
                }
            } elseif ($action === 'save_member') {
                $memberId = (int)($_POST['id'] ?? 0);
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $gender = (string)($_POST['gender'] ?? '');
                $address = trim((string)($_POST['address'] ?? ''));
                $birthDateInput = (string)($_POST['birth_date'] ?? '');
                $joinedOn = (string)($_POST['joined_on'] ?? '');
                $familyId = (int)($_POST['family_id'] ?? 0);
                $branchId = (int)($_POST['branch_id'] ?? 0);
                $classGroup = trim((string)($_POST['class_group'] ?? ''));
                $serviceGroup = trim((string)($_POST['service_group'] ?? ''));
                $status = (string)($_POST['status'] ?? 'active');
                $birthDate = $birthDateInput !== '' ? DateTime::createFromFormat('!Y-m-d', $birthDateInput) : null;
                $joinDate = DateTime::createFromFormat('!Y-m-d', $joinedOn);

                if ($fullName === '' || strlen($fullName) > 180 || !$joinDate || $joinDate->format('Y-m-d') !== $joinedOn || !in_array($status, ['active', 'inactive'], true)) {
                    $errorKey = 'member_validation_error';
                } elseif (($birthDateInput !== '' && (!$birthDate || $birthDate->format('Y-m-d') !== $birthDateInput || $birthDate > new DateTime('today'))) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                    $errorKey = 'member_validation_error';
                } elseif (!in_array($gender, ['', 'Female', 'Male', 'Other'], true)) {
                    $errorKey = 'member_validation_error';
                } else {
                    if ($familyId > 0) {
                        $familyCheck = $pdo->prepare('SELECT id FROM church_families WHERE id = :id LIMIT 1');
                        $familyCheck->execute([':id' => $familyId]);
                        if (!$familyCheck->fetchColumn()) {
                            $familyId = 0;
                        }
                    }
                    if ($branchId > 0) {
                        $branchCheck = $pdo->prepare('SELECT id FROM church_branches WHERE id = :id LIMIT 1');
                        $branchCheck->execute([':id' => $branchId]);
                        if (!$branchCheck->fetchColumn()) {
                            $errorKey = 'member_validation_error';
                        }
                    }

                    $existing = null;
                    if ($memberId > 0) {
                        $existingStmt = $pdo->prepare('SELECT * FROM church_members WHERE id = :id LIMIT 1');
                        $existingStmt->execute([':id' => $memberId]);
                        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                        if (!$existing) {
                            $errorKey = 'member_save_error';
                        }
                    }

                    $newPhotoName = null;
                    $photo = $_FILES['photo'] ?? null;
                    if ($errorKey === '' && $photo && (int)($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        if ((int)$photo['error'] !== UPLOAD_ERR_OK || (int)$photo['size'] > 5 * 1024 * 1024 || !is_uploaded_file($photo['tmp_name'])) {
                            $errorKey = 'member_upload_error';
                        } else {
                            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
                            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                            $imageInfo = @getimagesize($photo['tmp_name']);
                            if (!isset($extensions[$mimeType]) || !$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                                $errorKey = 'member_upload_error';
                            } else {
                                if (!is_dir($privateUploadDirectory) && !mkdir($privateUploadDirectory, 0750, true) && !is_dir($privateUploadDirectory)) {
                                    throw new RuntimeException('Cannot create private member photo directory.');
                                }
                                $newPhotoName = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
                                $targetPath = $privateUploadDirectory . DIRECTORY_SEPARATOR . $newPhotoName;
                                if (!move_uploaded_file($photo['tmp_name'], $targetPath)) {
                                    $errorKey = 'member_upload_error';
                                    $newPhotoName = null;
                                }
                            }
                        }
                    }

                    if ($errorKey === '') {
                        $photoPath = $newPhotoName ?? ($existing['photo_path'] ?? null);
                        if (isset($_POST['remove_photo']) && $existing && $newPhotoName === null) {
                            $photoPath = null;
                        }
                        $pdo->beginTransaction();
                        if ($memberId > 0) {
                            $stmt = $pdo->prepare('UPDATE church_members SET full_name = :full_name, phone = :phone, email = :email, gender = :gender, address = :address, birth_date = :birth_date, photo_path = :photo_path, family_id = :family_id, branch_id = :branch_id, class_group = :class_group, service_group = :service_group, joined_on = :joined_on, status = :status WHERE id = :id');
                            $stmt->execute([
                                ':full_name' => $fullName,
                                ':phone' => $phone !== '' ? $phone : null,
                                ':email' => $email !== '' ? $email : null,
                                ':gender' => $gender !== '' ? $gender : null,
                                ':address' => $address !== '' ? $address : null,
                                ':birth_date' => $birthDate ? $birthDate->format('Y-m-d') : null,
                                ':photo_path' => $photoPath,
                                ':family_id' => $familyId > 0 ? $familyId : null,
                                ':branch_id' => $branchId > 0 ? $branchId : null,
                                ':class_group' => $classGroup !== '' ? $classGroup : null,
                                ':service_group' => $serviceGroup !== '' ? $serviceGroup : null,
                                ':joined_on' => $joinedOn,
                                ':status' => $status,
                                ':id' => $memberId,
                            ]);
                            $successValue = 'updated';
                        } else {
                            $newMemberToken = bin2hex(random_bytes(32));
                            $stmt = $pdo->prepare('INSERT INTO church_members (member_number, verification_token, full_name, phone, email, gender, address, birth_date, photo_path, family_id, branch_id, class_group, service_group, joined_on, status) VALUES (:member_number, :verification_token, :full_name, :phone, :email, :gender, :address, :birth_date, :photo_path, :family_id, :branch_id, :class_group, :service_group, :joined_on, :status)');
                            $stmt->execute([
                                ':member_number' => 'TMP-' . bin2hex(random_bytes(8)),
                                ':verification_token' => $newMemberToken,
                                ':full_name' => $fullName,
                                ':phone' => $phone !== '' ? $phone : null,
                                ':email' => $email !== '' ? $email : null,
                                ':gender' => $gender !== '' ? $gender : null,
                                ':address' => $address !== '' ? $address : null,
                                ':birth_date' => $birthDate ? $birthDate->format('Y-m-d') : null,
                                ':photo_path' => $photoPath,
                                ':family_id' => $familyId > 0 ? $familyId : null,
                                ':branch_id' => $branchId > 0 ? $branchId : null,
                                ':class_group' => $classGroup !== '' ? $classGroup : null,
                                ':service_group' => $serviceGroup !== '' ? $serviceGroup : null,
                                ':joined_on' => $joinedOn,
                                ':status' => $status,
                            ]);
                            $memberId = (int)$pdo->lastInsertId();
                            $memberNumber = 'MEM-' . str_pad((string)$memberId, 6, '0', STR_PAD_LEFT);
                            $numberStmt = $pdo->prepare('UPDATE church_members SET member_number = :number WHERE id = :id');
                            $numberStmt->execute([':number' => $memberNumber, ':id' => $memberId]);
                            $successValue = 'member';
                        }
                        $pdo->commit();
                        if ($successValue === 'member' && $email !== '') {
                            try {
                                $verifiedAccount = $pdo->prepare('SELECT email_verified FROM students WHERE email = :email LIMIT 1');
                                $verifiedAccount->execute([':email' => $email]);
                                if ((int)$verifiedAccount->fetchColumn() === 1) {
                                    $verificationUrl = buildAppUrl('verify_church_member.php?token=' . urlencode($newMemberToken));
                                    $mailBody = '<p>' . htmlspecialchars(adminManagementText('member_registered'), ENT_QUOTES, 'UTF-8') . '</p>'
                                        . '<p>' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($memberNumber, ENT_QUOTES, 'UTF-8') . '</p>'
                                        . '<p><a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(adminManagementText('member_portal_card'), ENT_QUOTES, 'UTF-8') . '</a></p>';
                                    if (!sendAppEmail($email, adminManagementText('member_portal_card'), $mailBody)) {
                                        error_log('Church member verification email could not be sent for member ' . $memberId);
                                    }
                                }
                            } catch (Throwable $mailException) {
                                error_log('Church member registration notification failed: ' . $mailException->getMessage());
                            }
                        }
                        if ($existing && !empty($existing['photo_path']) && $photoPath !== $existing['photo_path']) {
                            $oldPath = $privateUploadDirectory . DIRECTORY_SEPARATOR . basename($existing['photo_path']);
                            if (is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $redirectUrl = 'admin_church_members.php?lang=' . urlencode($adminManagementLanguage) . '&saved=' . $successValue;
                        if ($successValue === 'member') {
                            $redirectUrl .= '&member_id=' . $memberId;
                        }
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                }
            } elseif ($action === 'set_status') {
                $memberId = (int)($_POST['id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                if ($memberId > 0 && in_array($status, ['active', 'inactive'], true)) {
                    $stmt = $pdo->prepare('UPDATE church_members SET status = :status WHERE id = :id');
                    $stmt->execute([':status' => $status, ':id' => $memberId]);
                    header('Location: admin_church_members.php?lang=' . urlencode($adminManagementLanguage) . '&saved=status');
                    exit;
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!empty($newPhotoName)) {
                $newPhotoPath = $privateUploadDirectory . DIRECTORY_SEPARATOR . basename($newPhotoName);
                if (is_file($newPhotoPath)) {
                    unlink($newPhotoPath);
                }
            }
            error_log('Church member save failed: ' . $exception->getMessage());
            $errorKey = 'member_save_error';
        }
    }
}

$savedKeys = [
    'member' => 'member_registered',
    'updated' => 'member_saved_update',
    'family' => 'member_family_created',
    'status' => 'member_status_updated',
    'review_sent' => 'member_portal_notification_sent',
    'review_failed' => 'member_portal_notification_failed',
    'review_saved' => 'member_portal_review_saved',
];
$savedKey = $savedKeys[(string)($_GET['saved'] ?? '')] ?? '';
$savedMemberId = (string)($_GET['saved'] ?? '') === 'member' ? (int)($_GET['member_id'] ?? 0) : 0;
$editingId = (int)($_GET['edit'] ?? 0);
$editingMember = null;
if ($editingId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM church_members WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editingId]);
    $editingMember = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$search = trim((string)($_GET['q'] ?? ''));
$classFilter = trim((string)($_GET['class_group'] ?? ''));
$serviceFilter = trim((string)($_GET['service_group'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'active');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'active';
}
$ageFilter = (string)($_GET['age_group'] ?? '');
if (!in_array($ageFilter, ['', 'child', 'youth', 'adult', 'senior'], true)) {
    $ageFilter = '';
}

$where = [];
$params = [];
if ($statusFilter !== 'all') {
    $where[] = 'm.status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(m.full_name LIKE :search_name OR m.member_number LIKE :search_number OR m.phone LIKE :search_phone OR m.email LIKE :search_email)';
    $searchValue = '%' . $search . '%';
    $params[':search_name'] = $searchValue;
    $params[':search_number'] = $searchValue;
    $params[':search_phone'] = $searchValue;
    $params[':search_email'] = $searchValue;
}
if ($classFilter !== '') {
    $where[] = 'm.class_group = :class_group';
    $params[':class_group'] = $classFilter;
}
if ($serviceFilter !== '') {
    $where[] = 'm.service_group = :service_group';
    $params[':service_group'] = $serviceFilter;
}
$ageConditions = [
    'child' => 'TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) BETWEEN 0 AND 12',
    'youth' => 'TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) BETWEEN 13 AND 17',
    'adult' => 'TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) BETWEEN 18 AND 59',
    'senior' => 'TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) >= 60',
];
if ($ageFilter !== '') {
    $where[] = '(m.birth_date IS NOT NULL AND ' . $ageConditions[$ageFilter] . ')';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$query = 'SELECT m.*, f.family_name, f.family_number, b.branch_name, TIMESTAMPDIFF(YEAR, m.birth_date, CURDATE()) AS age FROM church_members m LEFT JOIN church_families f ON f.id = m.family_id LEFT JOIN church_branches b ON b.id = m.branch_id' . $whereSql . ' ORDER BY m.full_name ASC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM church_members m' . $whereSql);
$countStmt->execute($params);
$matchingCount = (int)$countStmt->fetchColumn();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvStmt = $pdo->prepare($query);
    $csvStmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="church-members-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array_map('adminManagementText', ['member_number', 'full_name', 'member_age', 'phone', 'email', 'address', 'member_family', 'member_class_group', 'member_service_group', 'joined_on', 'status']));
    foreach ($csvStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $values = [$row['member_number'], $row['full_name'], $row['age'] ?? '', $row['phone'], $row['email'], $row['address'], $row['family_name'], $row['class_group'], $row['service_group'], $row['joined_on'], adminManagementText($row['status'] === 'active' ? 'member_active' : 'member_inactive')];
        foreach ($values as &$value) {
            $value = (string)$value;
            if (preg_match('/^[=+\-@\t\r]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values);
    }
    fclose($output);
    exit;
}

$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
$families = $pdo->query('SELECT id, family_number, family_name FROM church_families ORDER BY family_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query('SELECT id, branch_code, branch_name, status FROM church_branches ORDER BY status ASC, branch_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$classGroups = $pdo->query("SELECT DISTINCT class_group FROM church_members WHERE class_group IS NOT NULL AND class_group <> '' ORDER BY class_group")->fetchAll(PDO::FETCH_COLUMN);
$serviceGroups = $pdo->query("SELECT DISTINCT service_group FROM church_members WHERE service_group IS NOT NULL AND service_group <> '' ORDER BY service_group")->fetchAll(PDO::FETCH_COLUMN);
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM church_members WHERE status = 'active'")->fetchColumn();
$familyCount = (int)$pdo->query('SELECT COUNT(*) FROM church_families')->fetchColumn();
$pendingChangeRequests = $pdo->query("SELECT r.id, r.member_id, r.requested_by_student_id, r.requested_changes, r.created_at, m.full_name, m.member_number, b.branch_name FROM church_member_change_requests r JOIN church_members m ON m.id = r.member_id LEFT JOIN church_branches b ON b.id = m.branch_id WHERE r.status = 'pending' ORDER BY r.created_at ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('church_members_page')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#202b2b;background:#f4f7f5;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{background:#143b36;color:#fff;padding:16px max(20px,calc((100% - 1200px)/2));display:flex;justify-content:space-between;align-items:center;gap:14px}.topbar h1{font-size:20px;margin:0}.topbar p{margin:4px 0 0;color:#c4d5ce;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{color:#fff;text-decoration:none;border:1px solid #739188;border-radius:4px;padding:9px 12px}.wrap{max-width:1200px;margin:24px auto;padding:0 18px}.intro{display:flex;justify-content:space-between;align-items:end;gap:14px;margin-bottom:16px}.intro h2{font-size:22px;margin:0 0 5px}.muted{color:#65736e}.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:18px}.stat{background:#fff;padding:13px 16px;border-top:3px solid #236c5e}.stat span{font-size:13px;color:#65736e}.stat strong{display:block;font-size:24px;margin-top:4px}.columns{display:grid;grid-template-columns:1.5fr 1fr;gap:16px}.section{background:#fff;border-top:2px solid #d7e2dc;padding:18px;margin-bottom:18px;min-width:0}.section h3{font-size:17px;margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:600}input,select{width:100%;padding:9px;border:1px solid #cbd5d1;border-radius:4px;background:#fff;color:#202b2b;font:inherit}.check{display:flex;gap:8px;align-items:center}.check input{width:auto}.submit,.button{padding:9px 13px;border:0;border-radius:4px;background:#236c5e;color:#fff;text-decoration:none;font:inherit;cursor:pointer}.secondary{background:#fff;color:#236c5e;border:1px solid #236c5e}.notice{padding:10px 12px;background:#e8f4ef;color:#176244;margin-bottom:13px}.error{background:#fff0eb;color:#9b351f}.filters{display:grid;grid-template-columns:2fr repeat(4,minmax(130px,1fr)) auto;gap:8px;align-items:end}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:middle;padding:9px 8px;border-bottom:1px solid #e5ebe7;white-space:nowrap}th{background:#f3f7f4}.person{display:flex;gap:10px;align-items:center}.photo{width:42px;height:42px;object-fit:cover;border-radius:50%;background:#e7eeea}.avatar{width:42px;height:42px;border-radius:50%;background:#dcebe4;display:grid;place-items:center;color:#236c5e;font-weight:700}.actions{display:flex;gap:8px;align-items:center}.actions a,.actions button{background:none;border:0;padding:0;color:#236c5e;text-decoration:underline;cursor:pointer;font:inherit}.status{font-weight:600}.empty{color:#65736e;padding:12px 0}@media(max-width:900px){.columns{grid-template-columns:1fr}.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:580px){.topbar{align-items:flex-start;flex-direction:column}.intro{align-items:flex-start;flex-direction:column}.filters,.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}@media print{body{background:#fff}.topbar,.form-section,.filters,.actions,.no-print{display:none!important}.wrap{margin:0;max-width:none}.section{border:0;padding:0}}
    </style>
</head>
<body>
<header class="topbar"><div><h1><?php echo safe(adminManagementText('church_members_page')); ?></h1><p><?php echo safe(adminManagementText('church_members_intro')); ?></p></div><nav class="nav"><a href="<?php echo safe($adminManagementSwitchUrl); ?>"><?php echo safe(adminManagementText('language_switch')); ?></a><a href="admin_church_management.php"><?php echo safe(adminManagementText('attendance_report')); ?></a><a href="admin_management_dashboard.php">← <?php echo safe(adminManagementText('back_dashboard')); ?></a></nav></header>
<main class="wrap">
    <?php if ($savedKey !== ''): ?><div class="notice"><?php echo safe(adminManagementText($savedKey)); ?><?php if ($savedMemberId > 0): ?> <a href="church_member_id_card.php?id=<?php echo $savedMemberId; ?>&lang=<?php echo safe($adminManagementLanguage); ?>"><?php echo safe(adminManagementText('member_portal_card')); ?></a><?php endif; ?></div><?php endif; ?>
    <?php if ($errorKey !== ''): ?><div class="notice error"><?php echo safe(adminManagementText($errorKey)); ?></div><?php endif; ?>
    <section class="stats"><div class="stat"><span><?php echo safe(adminManagementText('active_members')); ?></span><strong><?php echo number_format($activeCount); ?></strong></div><div class="stat"><span><?php echo safe(adminManagementText('member_family')); ?></span><strong><?php echo number_format($familyCount); ?></strong></div><div class="stat"><span><?php echo safe(adminManagementText('member_records_count')); ?></span><strong><?php echo number_format($matchingCount); ?></strong></div></section>
    <section class="section" id="member-change-requests">
        <h3><?php echo safe(adminManagementText('member_portal_admin_requests')); ?> · <?php echo count($pendingChangeRequests); ?></h3>
        <?php if (!$pendingChangeRequests): ?><p class="muted"><?php echo safe(adminManagementText('member_portal_no_requests')); ?></p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('date')); ?></th><th><?php echo safe(adminManagementText('member')); ?></th><th><?php echo safe(adminManagementText('member_portal_request')); ?></th><th><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
        <?php foreach ($pendingChangeRequests as $request): $requestedChanges = json_decode((string)$request['requested_changes'], true) ?: []; $requestFieldLabels = ['full_name' => 'member_portal_field_name', 'phone' => 'member_portal_field_phone', 'address' => 'member_portal_field_address']; ?>
            <tr><td><?php echo safe($request['created_at']); ?></td><td><?php echo safe($request['full_name'] . ' · ' . $request['member_number']); ?><?php if (!empty($request['branch_name'])): ?><br><small class="muted"><?php echo safe($request['branch_name']); ?></small><?php endif; ?></td><td><?php foreach ($requestedChanges as $field => $value): if (!isset($requestFieldLabels[$field])) continue; ?><div><strong><?php echo safe(adminManagementText($requestFieldLabels[$field])); ?>:</strong> <?php echo safe((string)$value); ?></div><?php endforeach; ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="review_change_request"><input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>"><input name="review_note" maxlength="1000" placeholder="<?php echo safe(adminManagementText('member_portal_review_note')); ?>"><div class="actions"><button class="submit" type="submit" name="decision" value="approved"><?php echo safe(adminManagementText('member_portal_approve')); ?></button><button class="submit" type="submit" name="decision" value="rejected"><?php echo safe(adminManagementText('member_portal_reject')); ?></button></div></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
    <div class="columns">
        <section class="section form-section">
            <h3><?php echo safe(adminManagementText($editingMember ? 'member_update' : 'member_new')); ?></h3>
            <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="save_member"><input type="hidden" name="id" value="<?php echo (int)($editingMember['id'] ?? 0); ?>">
                <div class="form-grid">
                    <?php if ($editingMember): ?><div class="field full"><label><?php echo safe(adminManagementText('member_number')); ?></label><input value="<?php echo safe($editingMember['member_number']); ?>" readonly></div><?php endif; ?>
                    <div class="field full"><label for="full_name"><?php echo safe(adminManagementText('full_name')); ?> *</label><input id="full_name" name="full_name" maxlength="180" value="<?php echo safe($editingMember['full_name'] ?? ''); ?>" required></div>
                    <div class="field"><label for="birth_date"><?php echo safe(adminManagementText('member_birth_date')); ?></label><input id="birth_date" name="birth_date" type="date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo safe($editingMember['birth_date'] ?? ''); ?>"></div>
                    <div class="field"><label for="joined_on"><?php echo safe(adminManagementText('joined_on')); ?> *</label><input id="joined_on" name="joined_on" type="date" value="<?php echo safe($editingMember['joined_on'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="field"><label for="gender"><?php echo safe(adminManagementText('gender')); ?></label><select id="gender" name="gender"><option value=""><?php echo safe(adminManagementText('not_specified')); ?></option><?php foreach (['Female' => 'female', 'Male' => 'male', 'Other' => 'other'] as $value => $label): ?><option value="<?php echo safe($value); ?>" <?php echo ($editingMember['gender'] ?? '') === $value ? 'selected' : ''; ?>><?php echo safe(adminManagementText($label)); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="phone"><?php echo safe(adminManagementText('phone')); ?></label><input id="phone" name="phone" maxlength="40" value="<?php echo safe($editingMember['phone'] ?? ''); ?>"></div>
                    <div class="field"><label for="email"><?php echo safe(adminManagementText('email')); ?></label><input id="email" name="email" type="email" maxlength="190" value="<?php echo safe($editingMember['email'] ?? ''); ?>"></div>
                    <div class="field full"><label for="address"><?php echo safe(adminManagementText('address')); ?></label><input id="address" name="address" maxlength="255" value="<?php echo safe($editingMember['address'] ?? ''); ?>"></div>
                    <div class="field"><label for="family_id"><?php echo safe(adminManagementText('member_family')); ?></label><select id="family_id" name="family_id"><option value="0"><?php echo safe(adminManagementText('member_no_family')); ?></option><?php foreach ($families as $family): ?><option value="<?php echo (int)$family['id']; ?>" <?php echo (int)($editingMember['family_id'] ?? 0) === (int)$family['id'] ? 'selected' : ''; ?>><?php echo safe($family['family_number'] . ' · ' . $family['family_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="branch_id"><?php echo $adminManagementLanguage === 'en' ? 'Church branch' : 'የቤተክርስቲያን ቅርንጫፍ'; ?></label><select id="branch_id" name="branch_id"><option value="0"><?php echo $adminManagementLanguage === 'en' ? 'No branch' : 'ቅርንጫፍ አልተመረጠም'; ?></option><?php foreach ($branches as $branch): ?><option value="<?php echo (int)$branch['id']; ?>" <?php echo (int)($editingMember['branch_id'] ?? 0) === (int)$branch['id'] ? 'selected' : ''; ?>><?php echo safe($branch['branch_name']); ?><?php echo $branch['status'] === 'active' ? '' : ' · ' . ($adminManagementLanguage === 'en' ? 'Inactive' : 'ንቁ ያልሆነ'); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="status"><?php echo safe(adminManagementText('status')); ?></label><select id="status" name="status"><option value="active" <?php echo ($editingMember['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_active')); ?></option><option value="inactive" <?php echo ($editingMember['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_inactive')); ?></option></select></div>
                    <div class="field"><label for="class_group"><?php echo safe(adminManagementText('member_class_group')); ?></label><input id="class_group" name="class_group" maxlength="100" list="class-groups" value="<?php echo safe($editingMember['class_group'] ?? ''); ?>"><datalist id="class-groups"><?php foreach ($classGroups as $group): ?><option value="<?php echo safe($group); ?>"><?php endforeach; ?></datalist></div>
                    <div class="field"><label for="service_group"><?php echo safe(adminManagementText('member_service_group')); ?></label><input id="service_group" name="service_group" maxlength="100" list="service-groups" value="<?php echo safe($editingMember['service_group'] ?? ''); ?>"><datalist id="service-groups"><?php foreach ($serviceGroups as $group): ?><option value="<?php echo safe($group); ?>"><?php endforeach; ?></datalist></div>
                    <div class="field full"><label for="photo"><?php echo safe(adminManagementText('member_photo')); ?></label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small class="muted"><?php echo safe(adminManagementText('member_photo_hint')); ?></small><?php if ($editingMember && !empty($editingMember['photo_path'])): ?><div class="person"><img class="photo" src="church_member_photo.php?id=<?php echo (int)$editingMember['id']; ?>" alt="<?php echo safe(adminManagementText('member_photo')); ?>"><label class="check"><input type="checkbox" name="remove_photo" value="1"> <?php echo safe(adminManagementText('delete')); ?></label></div><?php endif; ?></div>
                </div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText($editingMember ? 'update_record' : 'register')); ?></button><?php if ($editingMember): ?> <a class="button secondary" href="admin_church_members.php"><?php echo safe(adminManagementText('cancel_edit')); ?></a><?php endif; ?>
            </form>
        </section>
        <section class="section form-section">
            <h3><?php echo safe(adminManagementText('member_create_family')); ?></h3>
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="add_family">
                <div class="form-grid"><div class="field full"><label for="family_name"><?php echo safe(adminManagementText('member_family_name')); ?> *</label><input id="family_name" name="family_name" maxlength="180" required></div><div class="field full"><label for="family_address"><?php echo safe(adminManagementText('address')); ?></label><input id="family_address" name="family_address" maxlength="255"></div></div>
                <button class="submit" type="submit"><?php echo safe(adminManagementText('member_add_family')); ?></button>
            </form>
        </section>
    </div>
    <section class="section" id="member-report">
        <div class="intro"><div><h3><?php echo safe(adminManagementText('member_list_report')); ?></h3><div class="muted"><?php echo number_format($matchingCount); ?> <?php echo safe(adminManagementText('member_records_count')); ?></div></div><a class="button secondary no-print" href="<?php echo safe('admin_church_members.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>"><?php echo safe(adminManagementText('member_export_csv')); ?></a></div>
        <form method="get" class="filters no-print"><input type="hidden" name="lang" value="<?php echo safe($adminManagementLanguage); ?>"><input name="q" value="<?php echo safe($search); ?>" placeholder="<?php echo safe(adminManagementText('member_search')); ?>"><select name="class_group"><option value=""><?php echo safe(adminManagementText('member_class_group')); ?></option><?php foreach ($classGroups as $group): ?><option value="<?php echo safe($group); ?>" <?php echo $classFilter === $group ? 'selected' : ''; ?>><?php echo safe($group); ?></option><?php endforeach; ?></select><select name="service_group"><option value=""><?php echo safe(adminManagementText('member_service_group')); ?></option><?php foreach ($serviceGroups as $group): ?><option value="<?php echo safe($group); ?>" <?php echo $serviceFilter === $group ? 'selected' : ''; ?>><?php echo safe($group); ?></option><?php endforeach; ?></select><select name="age_group"><option value=""><?php echo safe(adminManagementText('member_all_ages')); ?></option><?php foreach (['child' => 'member_child', 'youth' => 'member_youth', 'adult' => 'member_adult', 'senior' => 'member_senior'] as $key => $label): ?><option value="<?php echo safe($key); ?>" <?php echo $ageFilter === $key ? 'selected' : ''; ?>><?php echo safe(adminManagementText($label)); ?></option><?php endforeach; ?></select><select name="status"><option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('all')); ?></option><option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_active')); ?></option><option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>><?php echo safe(adminManagementText('member_inactive')); ?></option></select><button class="submit" style="margin:0" type="submit"><?php echo safe(adminManagementText('member_search_button')); ?></button></form>
        <div class="table-wrap"><table><thead><tr><th><?php echo safe(adminManagementText('full_name')); ?></th><th><?php echo safe(adminManagementText('member_number')); ?></th><th><?php echo safe(adminManagementText('member_age')); ?></th><th><?php echo safe(adminManagementText('phone')); ?></th><th><?php echo safe(adminManagementText('member_family')); ?></th><th><?php echo safe(adminManagementText('member_class_group')); ?></th><th><?php echo safe(adminManagementText('member_service_group')); ?></th><th><?php echo safe(adminManagementText('status')); ?></th><th class="no-print"><?php echo safe(adminManagementText('action')); ?></th></tr></thead><tbody>
        <?php if (!$members): ?><tr><td colspan="9" class="empty"><?php echo safe(adminManagementText('member_no_results')); ?></td></tr><?php else: foreach ($members as $member): ?><tr><td><div class="person"><?php if (!empty($member['photo_path'])): ?><img class="photo" src="church_member_photo.php?id=<?php echo (int)$member['id']; ?>" alt="<?php echo safe(adminManagementText('member_photo')); ?>"><?php else: ?><span class="avatar"><?php echo safe(mb_substr($member['full_name'], 0, 1)); ?></span><?php endif; ?><span><?php echo safe($member['full_name']); ?><br><small class="muted"><?php echo safe($member['email'] ?? ''); ?></small></span></div></td><td><?php echo safe($member['member_number']); ?></td><td><?php echo $member['age'] !== null ? (int)$member['age'] : safe(adminManagementText('member_age_unknown')); ?></td><td><?php echo safe($member['phone'] ?? ''); ?></td><td><?php echo safe($member['family_name'] ? $member['family_number'] . ' · ' . $member['family_name'] : ''); ?></td><td><?php echo safe($member['class_group'] ?? ''); ?></td><td><?php echo safe($member['service_group'] ?? ''); ?></td><td><?php echo safe(adminManagementText($member['status'] === 'active' ? 'member_active' : 'member_inactive')); ?></td><td class="no-print"><div class="actions"><a href="church_member_id_card.php?id=<?php echo (int)$member['id']; ?>"><?php echo $adminManagementLanguage === 'en' ? 'ID card' : 'መታወቂያ'; ?></a><a href="admin_church_members.php?lang=<?php echo safe($adminManagementLanguage); ?>&edit=<?php echo (int)$member['id']; ?>"><?php echo safe(adminManagementText('resource_edit_action')); ?></a><form method="post"><input type="hidden" name="csrf_token" value="<?php echo safe($csrf); ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>"><input type="hidden" name="status" value="<?php echo $member['status'] === 'active' ? 'inactive' : 'active'; ?>"><button type="submit"><?php echo safe(adminManagementText($member['status'] === 'active' ? 'set_inactive' : 'set_active')); ?></button></form></div></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</main>
</body>
</html>