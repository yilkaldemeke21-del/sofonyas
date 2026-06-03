<?php
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT email, password_hash FROM students WHERE email = :e');
$stmt->execute([':e' => 'yilkaldemeke21@gmail.com']);
$row = $stmt->fetch();
if (!$row) { echo "NO_STUDENT\n"; exit; }

$candidates = [
 '123456','123456789','12345','password','Password1','password123','Password123','student','student123','Student123','student2024','student2025','student2026',
 'yilkal','Yilkal','Yilkal123','yilkal123','Yilkal2024','yilkal2025','demeke','Demeke','demeke123','Demeke123',
 'yilkaldemeke','yilkaldemeke21','YilkalDemeke','YilkalDemeke21','27','027','sofonyas','Sofonyas','sofonyas123','Sofonyas123',
 'admin','admin123','Admin123','welcome','Welcome1','qwerty','Qwerty123','abc123','Abc123','1234','0000','secret','test','test123','demo','demo123','school','school123'
];

foreach ($candidates as $p) {
    if (password_verify($p, $row['password_hash'])) {
        echo "FOUND:$p\n";
        exit;
    }
}
echo "NOT_FOUND\n";
