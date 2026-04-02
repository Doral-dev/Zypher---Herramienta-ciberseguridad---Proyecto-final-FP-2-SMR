<?php
declare(strict_types=1);

session_start();
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

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('
        SELECT id, email, password_hash, is_verified
        FROM users
        WHERE email = :email
        LIMIT 1
    ');

    $stmt->execute([
        'email' => $email
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        exit('Correo o contraseña incorrectos');
    }

    if (!password_verify($password, $user['password_hash'])) {
        exit('Correo o contraseña incorrectos');
    }

    if (!(bool)$user['is_verified']) {
        exit('Debes verificar tu correo antes de iniciar sesión');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['logged_in'] = true;

    header('Location: /dashboard-inicio.php');
    exit;
} catch (Throwable $e) {
    exit('Error al iniciar sesión: ' . $e->getMessage());
}
