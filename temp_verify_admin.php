<?php
$hash = '$2y$10$46zeuBUB4D6qkdAp9B7OG.ekP7SB3IljRJUHezvp3pfOEVWlTahxC';
$candidates = [
    'admin', 'admin123', 'Admin123', 'password', '123456', '12345678',
    'sofonyas', 'Sofnyas', 'sofonyas123', 'admin@123', 'Password123',
    'admin1234', '1234', 'qwerty', 'welcome', 'secret',
    'Admin@123', 'admin@2024', 'Admin2024', 'Sofonyas', 'Sofnyas123'
];
foreach ($candidates as $candidate) {
    echo $candidate . ': ' . (password_verify($candidate, $hash) ? 'MATCH' : 'no') . PHP_EOL;
}
