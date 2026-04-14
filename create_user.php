<?php
$pdo = new PDO(
    'mysql:host=monorail.proxy.rlwy.net;port=18551;dbname=railway',
    'root',
    'xBMrxwPvNbIwrhTYNLPNxTuPBrwSecZW'
);
$hash = password_hash('Almasa03071970-', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (name, email, password, created_at, updated_at) VALUES ('pelrock', 'pelrock@gmail.com', '$hash', NOW(), NOW())");
echo "User created!\n";
