<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acceso no permitido');
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $email === '' || $password === '') {
    exit('Faltan campos');
}

if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
    exit('El usuario debe tener entre 3 y 50 caracteres');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Correo no válido');
}

if (strlen($password) < 6) {
    exit('La contraseña debe tener al menos 6 caracteres');
}

try {
    $pdo = getPDO();

    $check = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email');
    $check->execute([
        'username' => $username,
        'email' => $email
    ]);

    if ($check->fetch()) {
        exit('Ese usuario o correo ya existe');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verificationToken = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, is_verified, verification_token)
        VALUES (:username, :email, :password_hash, FALSE, :verification_token)
    ');

    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
        'verification_token' => $verificationToken
    ]);

    echo 'Usuario registrado correctamente.';

} catch (Throwable $e) {
    exit('Error al registrar: ' . $e->getMessage());
}
