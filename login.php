<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acceso no permitido');
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    exit('Faltan campos');
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('
        SELECT id, username, email, password_hash
        FROM users
        WHERE username = :username OR email = :email
        LIMIT 1
    ');

    $stmt->execute([
        'username' => $username,
        'email' => $username
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        exit('Usuario o contraseña incorrectos');
    }

    if (!password_verify($password, $user['password_hash'])) {
        exit('Usuario o contraseña incorrectos');
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['logged_in'] = true;

    header('Location: /panel.php');
    exit;

} catch (Throwable $e) {
    exit('Error al iniciar sesión: ' . $e->getMessage());
}
