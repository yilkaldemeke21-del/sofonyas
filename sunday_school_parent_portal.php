<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/management_schema.php';
require_once __DIR__ . '/admin_management_i18n.php';

ensureManagementTables($pdo);
$guardianEmail = strtolower(trim((string)($_GET['guardian_email'] ?? '')));
$rows = [];

if ($guardianEmail !== '') {
    $stmt = $pdo->prepare('SELECT e.id, e.student_id, e.guardian_name, e.guardian_phone, e.guardian_email, e.enrolled_on, e.status, m.full_name AS student_name, c.class_name, c.education_level, c.location, cert.certificate_code FROM sunday_school_enrollments e JOIN church_members m ON m.id = e.member_id JOIN sunday_school_classes c ON c.id = e.class_id LEFT JOIN sunday_school_certificates cert ON cert.enrollment_id = e.id WHERE LOWER(e.guardian_email) = :guardian_email ORDER BY e.enrolled_on DESC');
    $stmt->execute([':guardian_email' => $guardianEmail]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?php echo safe($adminManagementLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe(adminManagementText('sunday_parent_portal')); ?></title>
    <style>
        :root{font-family:Arial,sans-serif;color:#1f2a2d;background:#f5f7f5}*{box-sizing:border-box}body{margin:0}header{background:#143b36;color:#fff;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px}.brand h1{margin:0;font-size:22px}.nav a{color:#fff;text-decoration:none;border:1px solid #6e8980;border-radius:4px;padding:8px 12px}.main{max-width:1100px;margin:26px auto;padding:0 18px}.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.08);margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:flex;flex-direction:column;gap:6px}label{font-weight:600;font-size:13px}input,button{padding:10px 12px;border-radius:6px;border:1px solid #c9d3cf;font:inherit}button{background:#215e52;color:#fff;border:0;cursor:pointer}.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:14px}.stat{padding:12px;background:#f6faf8;border-left:3px solid #236c5e}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{padding:10px 8px;border-bottom:1px solid #e6ece9;text-align:left}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;background:#edf4ef;color:#1d5f4e}.muted{color:#64736d}.empty{padding:10px 0;color:#64736d}.@media(max-width:700px){.grid{grid-template-columns:1fr}} </style>
</head>
<body>
<header>
    <div class="brand">
        <h1><?php echo safe(adminManagementText('sunday_parent_portal')); ?></h1>
    </div>
    <div class="nav">
        <a href="admin_sunday_school.php"><?php echo safe(adminManagementText('sunday_dashboard')); ?></a>
    </div>
</header>
<main class="main">
    <section class="card">
        <h2><?php echo safe(adminManagementText('sunday_parent_portal_intro')); ?></h2>
        <form method="get" class="grid">
            <div class="field">
                <label for="guardian_email"><?php echo safe(adminManagementText('sunday_parent_email_lookup')); ?></label>
                <input id="guardian_email" type="email" name="guardian_email" value="<?php echo safe($guardianEmail); ?>" placeholder="parent@example.com">
            </div>
            <div class="field" style="justify-content:flex-end">
                <label>&nbsp;</label>
                <button type="submit"><?php echo safe(adminManagementText('member_search_button')); ?></button>
            </div>
        </form>
    </section>

    <?php if ($guardianEmail === ''): ?>
        <section class="card">
            <p class="muted"><?php echo safe(adminManagementText('sunday_parent_portal_intro')); ?></p>
        </section>
    <?php elseif (!$rows): ?>
        <section class="card">
            <div class="empty"><?php echo safe(adminManagementText('sunday_no_guardian_match')); ?></div>
        </section>
    <?php else: ?>
        <section class="card">
            <h3><?php echo safe(adminManagementText('sunday_guardian_summary')); ?></h3>
            <div class="summary">
                <div class="stat"><strong><?php echo count($rows); ?></strong><div class="muted"><?php echo safe(adminManagementText('sunday_enrolled_students')); ?></div></div>
                <div class="stat"><strong><?php echo htmlspecialchars($rows[0]['guardian_name'] ?? ''); ?></strong><div class="muted"><?php echo safe(adminManagementText('sunday_guardian_name')); ?></div></div>
                <div class="stat"><strong><?php echo htmlspecialchars($rows[0]['guardian_phone'] ?? ''); ?></strong><div class="muted"><?php echo safe(adminManagementText('sunday_guardian_phone')); ?></div></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><?php echo safe(adminManagementText('full_name')); ?></th>
                        <th><?php echo safe(adminManagementText('sunday_classes')); ?></th>
                        <th><?php echo safe(adminManagementText('sunday_location')); ?></th>
                        <th><?php echo safe(adminManagementText('status')); ?></th>
                        <th><?php echo safe(adminManagementText('sunday_certificate_code')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo safe($row['student_name']); ?><br><span class="muted"><?php echo safe($row['student_id'] ?? ''); ?></span></td>
                            <td><?php echo safe($row['class_name']); ?><br><span class="muted"><?php echo safe($row['education_level'] ?? ''); ?></span></td>
                            <td><?php echo safe($row['location'] ?? ''); ?></td>
                            <td><span class="badge"><?php echo safe($row['status']); ?></span></td>
                            <td><?php echo safe($row['certificate_code'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
