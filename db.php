<?php
declare(strict_types=1);

function getPDO(): PDO
{
    $host = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
    $port = '5432';
    $dbname = 'zypher_db_g2sb';
    $user = 'zypher_db_g2sb_user';
    $password = 'TU_PASSWORD_NUEVA';

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
