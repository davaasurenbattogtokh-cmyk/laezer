<?php
declare(strict_types=1);
namespace Laezer;

final class AppError extends \RuntimeException {
    public function __construct(string $message, public readonly int $status = 400) { parent::__construct($message); }
}
function fail(string $message, int $status = 400): never { throw new AppError($message, $status); }
function now(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
function uid(): string { return bin2hex(random_bytes(16)); }
function json_encode_safe(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
function escape(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function config(): array {
    $path = getenv('LAEZER_CONFIG_FILE') ?: LAEZER_PRIVATE_DIR . '/config.php';
    if (!is_file($path)) fail('Сайтын суулгах тохиргоо хийгдээгүй байна.', 503);
    $config = require $path;
    if (!is_array($config) || !isset($config['origin'], $config['db'])) fail('Сайтын тохиргоо бэлэн биш байна.', 503);
    $origin = parse_url($config['origin']);
    if (!$origin || ($origin['scheme'] ?? '') !== 'https' || empty($origin['host']) || isset($origin['user']) || isset($origin['pass']) || isset($origin['port']) || isset($origin['query']) || isset($origin['fragment']) || !in_array($origin['path'] ?? '', ['', '/'], true)) fail('HTTPS домэйны тохиргоо буруу байна.', 503);
    $config['origin'] = rtrim($config['origin'], '/');
    return $config;
}
function database(array $config): \PDO {
    $d = $config['db'];
    if (!is_string($d['host'] ?? null) || !preg_match('/^[a-zA-Z0-9._-]+$/D', $d['host']) || !preg_match('/^[a-zA-Z0-9_]+$/D', $d['name'] ?? '') || !is_int($d['port'] ?? 3306)) fail('Database тохиргоо буруу байна.', 503);
    return new \PDO('mysql:host='.$d['host'].';port='.($d['port'] ?? 3306).';dbname='.$d['name'].';charset=utf8mb4', $d['user'], $d['password'], [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
}
function query(\PDO $db, string $sql, array $values = []): \PDOStatement {
    $stmt = $db->prepare($sql);
    foreach (array_values($values) as $i => $v) $stmt->bindValue($i + 1, $v, is_int($v) ? \PDO::PARAM_INT : ($v === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR));
    $stmt->execute(); return $stmt;
}
function one(\PDO $db, string $sql, array $values = []): ?array { return query($db, $sql, $values)->fetch() ?: null; }
function transaction(\PDO $db, callable $fn): mixed {
    $db->beginTransaction();
    try { $result = $fn(); $db->commit(); return $result; }
    catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
function text(mixed $value, string $label, int $min = 0, int $max = 500): string {
    if (!is_string($value)) fail($label.' буруу байна.'); $value = trim($value);
    $length = mb_strlen($value, 'UTF-8');
    if ($length < $min || $length > $max) fail($label.': '.$min.'–'.$max.' тэмдэгт оруулна уу.');
    return $value;
}
function whole(mixed $value, int $min, int $max, string $label): int {
    if (!is_int($value) || $value < $min || $value > $max) fail($label.' буруу байна.'); return $value;
}
function price(mixed $value, bool $nullable = false): ?int {
    if ($nullable && ($value === null || $value === '')) return null;
    return whole($value, 1, 100000000, 'Үнэ');
}
function body(int $limit = 20000): array {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) fail('JSON мэдээлэл шаардлагатай.', 415);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) fail('Хүсэлт хэт том байна.', 413);
    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if ($raw === false || strlen($raw) > $limit) fail('Хүсэлт хэт том байна.', 413);
    try { $object = json_decode($raw, false, 32, JSON_THROW_ON_ERROR); $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { fail('Мэдээлэл буруу байна.'); }
    if (!is_object($object) || !is_array($value)) fail('Мэдээлэл буруу байна.'); return $value;
}
function same_origin(array $config): void {
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $config['origin'] || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') fail('Зөвшөөрөгдөөгүй хүсэлт.', 403);
}
function respond(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode_safe($data); exit;
}
function installed(\PDO $db): bool {
    try { return one($db, "SELECT value FROM settings WHERE `key`='installed'") !== null; }
    catch (\PDOException $e) { if (in_array((int)($e->errorInfo[1] ?? 0), [1146], true)) return false; throw $e; }
}
