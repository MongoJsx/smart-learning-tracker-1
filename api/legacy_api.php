<?php

function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function get_bearer_token(): ?string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
  if (!$h) return null;
  if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return trim($m[1]);
  return null;
}

/**
 * ✅ ถ้าคุณใช้ Laravel Sanctum (createToken) token จะถูกเก็บใน personal_access_tokens.token เป็น sha256
 * เราเอา Bearer token มา sha256 แล้วหา tokenable_id (user id)
 */
function auth_user_id(PDO $pdo): int {
  $token = get_bearer_token();
  if (!$token) json_out(['message' => 'Unauthenticated'], 401);

  $hash = hash('sha256', $token);

  $stmt = $pdo->prepare("
    SELECT tokenable_id
    FROM personal_access_tokens
    WHERE token = :token
    LIMIT 1
  ");
  $stmt->execute([':token' => $hash]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row || empty($row['tokenable_id'])) {
    json_out(['message' => 'Unauthenticated'], 401);
  }

  return (int)$row['tokenable_id'];
}
