<?php
declare(strict_types=1);

session_start();

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

function db(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASSWORD;

    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";

    return new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

function user_id_actual(): int {
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    if (isset($_SESSION['usuario_id'])) {
        return (int)$_SESSION['usuario_id'];
    }

    if (isset($_SESSION['id'])) {
        return (int)$_SESSION['id'];
    }

    die('No hay sesión activa.');
}

$pdo = db();
$user_id = user_id_actual();

$mensaje_ok = '';
$mensaje_error = '';

$stmt = $pdo->prepare("
    SELECT secundaria_hash, activa
    FROM usuario_seguridad
    WHERE user_id = :user_id
    LIMIT 1
");
$stmt->execute([':user_id' => $user_id]);
$seguridad = $stmt->fetch(PDO::FETCH_ASSOC);

$tiene_secundaria = $seguridad && !empty($seguridad['secundaria_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_login = (string)($_POST['password_login'] ?? '');
    $password_secundaria = (string)($_POST['password_secundaria'] ?? '');
    $password_secundaria_2 = (string)($_POST['password_secundaria_2'] ?? '');

    try {
        $stmt = $pdo->prepare("
            SELECT password_hash
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['password_hash'])) {
            throw new Exception('No se ha encontrado el usuario.');
        }

        if (!password_verify($password_login, $user['password_hash'])) {
            throw new Exception('La contraseña de login no es correcta.');
        }

        if (strlen($password_secundaria) < 8) {
            throw new Exception('La contraseña secundaria debe tener al menos 8 caracteres.');
        }

        if ($password_secundaria !== $password_secundaria_2) {
            throw new Exception('Las contraseñas secundarias no coinciden.');
        }

        $hash_secundaria = password_hash($password_secundaria, PASSWORD_DEFAULT);

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
            ':user_id' => $user_id,
            ':secundaria_hash' => $hash_secundaria
        ]);

        $mensaje_ok = 'Contraseña secundaria guardada correctamente.';
        $tiene_secundaria = true;

    } catch (Throwable $e) {
        $mensaje_error = $e->getMessage();
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
            margin-bottom: 24px;
            max-width: 620px;
        }

        h1, h2 {
            margin-top: 0;
        }

        p {
            color: #9ca3af;
        }

        label {
            display: block;
            margin-top: 12px;
            color: #e5e7eb;
        }

        input, button {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border-radius: 8px;
            border: 0;
            margin-top: 8px;
            margin-bottom: 14px;
        }

        button {
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .ok {
            color: #22c55e;
        }

        .error {
            color: #ef4444;
        }

        .muted {
            color: #9ca3af;
        }

        a {
            color: #93c5fd;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Ajustes</h1>
    <h2>Contraseña secundaria</h2>

    <p>
        Esta contraseña se usará para acciones sensibles:
        descargar backups, eliminar backups y limpiar historial.
    </p>

    <?php if ($tiene_secundaria): ?>
        <p class="ok">Contraseña secundaria configurada.</p>
        <p class="muted">Puedes cambiarla introduciendo tu contraseña de login actual.</p>
    <?php else: ?>
        <p class="error">Todavía no tienes contraseña secundaria configurada.</p>
    <?php endif; ?>

    <?php if ($mensaje_ok): ?>
        <p class="ok"><?php echo htmlspecialchars($mensaje_ok); ?></p>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
        <p class="error"><?php echo htmlspecialchars($mensaje_error); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Contraseña de login actual</label>
        <input type="password" name="password_login" required>

        <label>Nueva contraseña secundaria</label>
        <input type="password" name="password_secundaria" required minlength="8">

        <label>Repetir nueva contraseña secundaria</label>
        <input type="password" name="password_secundaria_2" required minlength="8">

        <button type="submit">Guardar contraseña secundaria</button>
    </form>

    <p>
        <a href="../index.php">Volver</a>
    </p>
</div>

</body>
</html>
