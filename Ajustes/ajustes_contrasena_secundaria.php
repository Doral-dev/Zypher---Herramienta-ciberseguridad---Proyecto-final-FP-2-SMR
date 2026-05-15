<?php
declare(strict_types=1);

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

function db(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASSWORD;

    return new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

$pdo = db();
$ok = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password_login = (string)($_POST['password_login'] ?? '');
    $password_secundaria = (string)($_POST['password_secundaria'] ?? '');
    $password_secundaria_2 = (string)($_POST['password_secundaria_2'] ?? '');

    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo no válido.');
        }

        $stmt = $pdo->prepare("
            SELECT id, password_hash
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password_login, $user['password_hash'])) {
            throw new Exception('La contraseña de login no es correcta.');
        }

        if (strlen($password_secundaria) < 8) {
            throw new Exception('La contraseña secundaria debe tener al menos 8 caracteres.');
        }

        if ($password_secundaria !== $password_secundaria_2) {
            throw new Exception('Las contraseñas secundarias no coinciden.');
        }

        $hash = password_hash($password_secundaria, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO usuario_seguridad
                (user_id, secundaria_hash, activa, updated_at)
            VALUES
                (:user_id, :secundaria_hash, true, NOW())
            ON CONFLICT (user_id)
            DO UPDATE SET
                secundaria_hash = EXCLUDED.secundaria_hash,
                activa = true,
                updated_at = NOW()
        ");

        $stmt->execute([
            ':user_id' => (int)$user['id'],
            ':secundaria_hash' => $hash
        ]);

        $ok = 'Contraseña secundaria guardada correctamente.';

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ajustes - Contraseña secundaria</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            margin: 0;
            padding: 30px;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 14px;
            padding: 22px;
            max-width: 620px;
        }

        input, button {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            margin-bottom: 14px;
            border-radius: 8px;
            border: 0;
            box-sizing: border-box;
        }

        button {
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .ok { color: #22c55e; }
        .error { color: #ef4444; }
        .muted { color: #9ca3af; }
        a { color: #93c5fd; }
    </style>
</head>
<body>

<div class="card">
    <h1>Ajustes</h1>
    <h2>Contraseña secundaria</h2>

    <p class="muted">
        Esta contraseña se usará para acciones sensibles:
        descargar backups, eliminar backups y limpiar historial.
    </p>

    <?php if ($ok): ?>
        <p class="ok"><?php echo htmlspecialchars($ok); ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Email de login</label>
        <input type="email" name="email" required>

        <label>Contraseña de login actual</label>
        <input type="password" name="password_login" required>

        <label>Nueva contraseña secundaria</label>
        <input type="password" name="password_secundaria" required minlength="8">

        <label>Repetir contraseña secundaria</label>
        <input type="password" name="password_secundaria_2" required minlength="8">

        <button type="submit">Guardar contraseña secundaria</button>
    </form>

    <p>
        <a href="/Dashboard/dashboard-inicio.php">Volver al panel</a>
    </p>
</div>

</body>
</html>
