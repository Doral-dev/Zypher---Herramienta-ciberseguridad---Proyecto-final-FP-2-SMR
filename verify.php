<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    exit('Token no válido');
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare('
        SELECT id
        FROM users
        WHERE verification_token = :token
        AND is_verified = FALSE
        LIMIT 1
    ');

    $stmt->execute([
        'token' => $token
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        exit('Token inválido o cuenta ya verificada');
    }

    $update = $pdo->prepare('
        UPDATE users
        SET is_verified = TRUE,
            verification_token = NULL
        WHERE id = :id
    ');

    $update->execute([
        'id' => $user['id']
    ]);

    exit('Cuenta verificada correctamente. Ya puedes iniciar sesión.');
} catch (Throwable $e) {
    exit('Error al verificar la cuenta: ' . $e->getMessage());
}
