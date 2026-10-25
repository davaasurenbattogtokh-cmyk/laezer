<?php
declare(strict_types=1);
namespace Laezer;
const SESSION_TTL = 28800;
const COOKIE_NAME = '__Host-laezer_session';
function username(mixed $value): string {
    $name = strtolower(text($value, 'Нэвтрэх нэр', 3, 40));
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/D', $name)) fail('Нэвтрэх нэр 3–40 латин үсэг, тоо, цэг, зурааснаас бүрдэнэ.'); return $name;
}
function password_hash_safe(mixed $password): string {
    if (!is_string($password) || strlen($password) < 10 || strlen($password) > 72 || str_contains($password, "\0")) fail('Нууц үг 10–72 байт байна.');
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
function actor_view(array $account): array {
    return ['id'=>$account['id'], 'role'=>$account['role'], 'username'=>$account['username'], 'name'=>$account['name'], 'email'=>$account['email'], 'phone'=>$account['phone']];
}
function current_actor(\PDO $db, ?string $token = null, ?int $time = null): ?array {
    $token ??= $_COOKIE[COOKIE_NAME] ?? ''; $time ??= time();
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
    $row = one($db, 'SELECT a.* FROM login_sessions s JOIN accounts a ON a.id=s.account_id WHERE s.token_hash=? AND s.expires_at>? AND a.active=1 AND a.login_version=s.credential_version', [hash('sha256',$token),$time]);
    return $row && in_array($row['role'], ['admin','reseller'], true) ? actor_view($row) : null;
}
function authorize(?array $actor, ?string $role = null): array {
    if (!$actor) fail('Нэвтрэх шаардлагатай.', 401);
    if ($role !== null && $actor['role'] !== $role) fail('Энэ үйлдлийг хийх эрхгүй байна.', 403); return $actor;
}
function consume_limit(\PDO $db, string $name, string $network, int $time): string {
    $window = intdiv($time,900)*900;
    $keys = [hash('sha256','network|'.$network),hash('sha256','pair|'.$network.'|'.$name)];
    $counts = transaction($db, function() use ($db,$keys,$window) {
        $counts=[];
        foreach ($keys as $key) {
            query($db, 'INSERT INTO login_limits (`key`,window_start,attempts) VALUES (?,?,1) ON DUPLICATE KEY UPDATE attempts=IF(window_start=?,attempts+1,1),window_start=?', [$key,$window,$window,$window]);
            $counts[] = (int)one($db,'SELECT attempts FROM login_limits WHERE `key`=? FOR UPDATE',[$key])['attempts'];
        } return $counts;
    });
    if ($counts[0]>30 || $counts[1]>5) fail('Олон удаа оролдсон байна. 15 минутын дараа дахин оролдоно уу.',429);
    return $keys[1];
}
function sign_in(\PDO $db, mixed $name, mixed $password, string $network, ?int $time = null): array {
    $time ??= time();
    if (!is_string($name) || !is_string($password) || strlen($name)>40 || strlen($password)>72 || str_contains($password,"\0")) fail('Нэвтрэх нэр эсвэл нууц үг буруу байна.',401);
    $name = strtolower(trim($name)); $key = consume_limit($db,$name,$network,$time);
    $account = one($db,'SELECT * FROM accounts WHERE username=?',[$name]);
    // Constant-cost dummy verification avoids disclosing whether the name exists.
    $hash = $account['password_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    if (!password_verify($password,$hash) || !$account || !(int)$account['active'] || !in_array($account['role'],['admin','reseller'],true)) fail('Нэвтрэх нэр эсвэл нууц үг буруу байна.',401);
    $token = bin2hex(random_bytes(32));
    query($db,'INSERT INTO login_sessions (token_hash,account_id,credential_version,created_at,expires_at) VALUES (?,?,?,?,?)',[hash('sha256',$token),$account['id'],(int)$account['login_version'],$time,$time+SESSION_TTL]);
    query($db,'DELETE FROM login_limits WHERE `key`=?',[$key]);
    query($db,'DELETE FROM login_sessions WHERE expires_at<=? LIMIT 100',[$time]);
    query($db,'DELETE FROM login_limits WHERE window_start<? LIMIT 100',[$time-86400]);
    return ['actor'=>actor_view($account),'token'=>$token,'redirect'=>$account['role']==='admin'?'/admin':'/reseller'];
}
function set_session_cookie(string $token = '', bool $clear = false): void {
    setcookie(COOKIE_NAME,$clear?'':$token,['expires'=>$clear?time()-3600:time()+SESSION_TTL,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
}
function sign_out(\PDO $db, ?string $token = null): void {
    $token ??= $_COOKIE[COOKIE_NAME] ?? '';
    if (preg_match('/^[a-f0-9]{64}$/D',$token)) query($db,'DELETE FROM login_sessions WHERE token_hash=?',[hash('sha256',$token)]);
}
