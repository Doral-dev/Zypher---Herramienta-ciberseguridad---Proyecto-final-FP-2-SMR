<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acceso no permitido');
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    exit('Faltan campos');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Correo no válido');
}

if (strlen($password) < 6) {
    exit('La contraseña debe tener al menos 6 caracteres');
}

try {
    $pdo = getPDO();

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute([
        'email' => $email
    ]);

    if ($check->fetch()) {
        exit('Ese correo ya existe');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, is_verified, verification_token)
        VALUES (NULL, :email, :password_hash, TRUE, NULL)
    ');

    $stmt->execute([
        'email' => $email,
        'password_hash' => $passwordHash
    ]);

    header('Location: /inicio-sesion.html');
    exit;

} catch (Throwable $e) {
    exit('Error al registrar: ' . $e->getMessage());
}
