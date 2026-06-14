#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://pan.yeyupan.cc}"
RISK_CODE="${RISK_CODE:-P4Q3ZZ}"

check_status() {
  local name="$1"
  local expected="$2"
  local url="$3"
  local code
  code="$(curl -s -o /tmp/yeyu-smoke.out -w '%{http_code}' "$url")"
  if [ "$code" != "$expected" ]; then
    printf '%s failed: expected %s got %s\n' "$name" "$expected" "$code" >&2
    return 1
  fi
  printf '%s ok: %s\n' "$name" "$code"
}

check_status "home" "200" "$BASE_URL/"
check_status "status-page" "200" "$BASE_URL/status"
check_status "config-api" "200" "$BASE_URL/api/v1/config"
check_status "status-api" "200" "$BASE_URL/api/v1/status"
check_status "risk-details" "200" "$BASE_URL/files/$RISK_CODE/threat-details"
check_status "risk-direct-download" "403" "$BASE_URL/api/v1/files/$RISK_CODE/download"

if [ -f artisan ]; then
  php artisan yeyu-file-express:ops-check --json --record > /tmp/yeyu-ops-check.json
  php -r '
  $data = json_decode(file_get_contents("/tmp/yeyu-ops-check.json"), true);
  if (! is_array($data) || ! array_key_exists("queue", $data) || ! array_key_exists("risk_review", $data) || ! array_key_exists("status", $data)) {
      fwrite(STDERR, "ops-check output invalid".PHP_EOL);
      exit(1);
  }
  echo "ops-check ok".PHP_EOL;
  '
fi

curl -s "$BASE_URL/api/v1/status" > /tmp/yeyu-status.json
php -r '
$data = json_decode(file_get_contents("/tmp/yeyu-status.json"), true);
$queue = $data["data"]["operations"]["queue"] ?? [];
if (! array_key_exists("workerHeartbeatFresh", $queue) || ! array_key_exists("actionableFailedJobs", $queue)) {
    fwrite(STDERR, "status queue fields missing".PHP_EOL);
    exit(1);
}
echo "status-queue-fields ok".PHP_EOL;
'

curl -s "$BASE_URL/api/v1/config" > /tmp/yeyu-config.json
php -r '
$data = json_decode(file_get_contents("/tmp/yeyu-config.json"), true);
$hits = [];
$sensitive = ["secret", "token", "cookie", "password", "accesskey"];
$walk = function ($value, $path = "") use (&$walk, &$hits, $sensitive) {
    if (! is_array($value)) return;
    foreach ($value as $key => $child) {
        $next = $path === "" ? (string) $key : $path.".".$key;
        foreach ($sensitive as $word) {
            if (str_contains(strtolower((string) $key), $word)) $hits[] = $next;
        }
        $walk($child, $next);
    }
};
$walk($data["data"] ?? []);
if ($hits) {
    fwrite(STDERR, "config sensitive key hits: ".implode(", ", $hits).PHP_EOL);
    exit(1);
}
echo "config-sensitive-fields ok: 0".PHP_EOL;
'
