<?php
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll(string $sql, array $params = []): array {
    return query($sql, $params)->fetchAll();
}

function fetchOne(string $sql, array $params = []): array|false {
    return query($sql, $params)->fetch();
}

function fetchValue(string $sql, array $params = []): mixed {
    return query($sql, $params)->fetchColumn();
}

function insert(string $table, array $data): int {
    $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
    $phs  = implode(',', array_fill(0, count($data), '?'));
    query("INSERT INTO `$table` ($cols) VALUES ($phs)", array_values($data));
    return (int) db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $whereParams = []): int {
    $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
    $stmt = query("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$whereParams]);
    return $stmt->rowCount();
}
