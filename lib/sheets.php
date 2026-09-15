<?php
/**
 * Google Sheets access for shared hosting — no Composer, pure PHP.
 * Signs a service-account JWT (RS256) with openssl, exchanges it for an
 * OAuth2 access token, then calls the Sheets API v4 over HTTPS (curl).
 *
 * Mirrors src/lib/google-sheets.ts from the Next.js app.
 */

function cfg(): array {
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../config.php';
        if (!file_exists($path)) {
            http_response_code(500);
            die('Missing config.php — copy config.sample.php to config.php and fill in your values.');
        }
        $config = require $path;
    }
    return $config;
}

function spreadsheet_id(string $category): string {
    $ids = cfg()['spreadsheets'] ?? [];
    if (empty($ids[$category])) {
        throw new Exception("Spreadsheet ID not configured for: $category");
    }
    return $ids[$category];
}

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** Get (and cache) an OAuth2 access token for the Sheets scope. */
function google_access_token(): string {
    $cacheFile = __DIR__ . '/../cache/token.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return $cached['access_token'];
        }
    }

    $c = cfg();
    $email = $c['google_service_account_email'];
    $key = str_replace('\\n', "\n", $c['google_private_key']);

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claim = [
        'iss'   => $email,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ];
    $signingInput = base64url(json_encode($header)) . '.' . base64url(json_encode($claim));

    $signature = '';
    $ok = openssl_sign($signingInput, $signature, $key, 'sha256WithRSAEncryption');
    if (!$ok) {
        throw new Exception('Failed to sign JWT — check google_private_key in config.php');
    }
    $jwt = $signingInput . '.' . base64url($signature);

    $resp = http_request('POST', 'https://oauth2.googleapis.com/token', [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
    ]));

    $data = json_decode($resp['body'], true);
    if (empty($data['access_token'])) {
        throw new Exception('Token exchange failed: ' . $resp['body']);
    }

    @mkdir(__DIR__ . '/../cache', 0775, true);
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => $now + (int)($data['expires_in'] ?? 3600),
    ]));

    return $data['access_token'];
}

/** Low-level HTTPS request. Returns ['status'=>int,'body'=>string]. */
function http_request(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $respBody = curl_exec($ch);
    if ($respBody === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("HTTP request failed: $err");
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => (string)$respBody];
}

function sheets_api(string $method, string $path, ?array $payload = null): array {
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $path;
    $body = $payload !== null ? json_encode($payload) : null;
    $attempts = 0;
    while (true) {
        $attempts++;
        $token = google_access_token();
        $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        $resp = http_request($method, $url, $headers, $body);
        // Retry on rate-limit / transient server errors with backoff.
        if (in_array($resp['status'], [429, 500, 503], true) && $attempts < 5) {
            sleep($attempts); // 1s, 2s, 3s, 4s
            continue;
        }
        $data = json_decode($resp['body'], true);
        if ($resp['status'] >= 400) {
            throw new Exception('Sheets API ' . $resp['status'] . ': ' . $resp['body']);
        }
        return is_array($data) ? $data : [];
    }
}

/** Clear the values in a range (keeps the sheet/tab). */
function sheets_clear(string $spreadsheetId, string $range): void {
    sheets_api('POST', $spreadsheetId . '/values/' . rawurlencode($range) . ':clear');
}

/** List all tab titles in a spreadsheet. */
function sheets_tab_titles(string $spreadsheetId): array {
    $meta = sheets_api('GET', $spreadsheetId . '?fields=sheets.properties.title');
    $out = [];
    foreach ($meta['sheets'] ?? [] as $s) {
        if (isset($s['properties']['title'])) $out[] = $s['properties']['title'];
    }
    return $out;
}

/** Read a range → array of rows (each row an array of string cells). */
function sheets_get(string $spreadsheetId, string $range): array {
    try {
        $data = sheets_api('GET', $spreadsheetId . '/values/' . rawurlencode($range));
        return $data['values'] ?? [];
    } catch (Exception $e) {
        // Missing sheet/range → behave like the JS app (empty).
        if (strpos($e->getMessage(), 'Unable to parse range') !== false || strpos($e->getMessage(), '400') !== false) {
            return [];
        }
        throw $e;
    }
}

/** Append a single row to a sheet tab. */
function sheets_append(string $spreadsheetId, string $sheetName, array $values): void {
    sheets_api(
        'POST',
        $spreadsheetId . '/values/' . rawurlencode($sheetName . '!A:A') . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
        ['values' => [$values]]
    );
}

/** Update a specific range with rows. */
function sheets_update(string $spreadsheetId, string $range, array $rows): void {
    sheets_api(
        'PUT',
        $spreadsheetId . '/values/' . rawurlencode($range) . '?valueInputOption=USER_ENTERED',
        ['values' => $rows]
    );
}

/** Look up the numeric sheetId for a tab title. */
function sheets_get_sheet_id(string $spreadsheetId, string $sheetName): ?int {
    $meta = sheets_api('GET', $spreadsheetId . '?fields=sheets.properties');
    foreach ($meta['sheets'] ?? [] as $s) {
        if (($s['properties']['title'] ?? null) === $sheetName) {
            return (int)$s['properties']['sheetId'];
        }
    }
    return null;
}

/** Delete a data row (0-based index into A2:.. data, i.e. sheet row index). */
function sheets_delete_row(string $spreadsheetId, string $sheetName, int $rowIndex): void {
    $sheetId = sheets_get_sheet_id($spreadsheetId, $sheetName);
    if ($sheetId === null) {
        throw new Exception("Sheet '$sheetName' not found");
    }
    sheets_api('POST', $spreadsheetId . ':batchUpdate', [
        'requests' => [[
            'deleteDimension' => [
                'range' => [
                    'sheetId'    => $sheetId,
                    'dimension'  => 'ROWS',
                    'startIndex' => $rowIndex,
                    'endIndex'   => $rowIndex + 1,
                ],
            ],
        ]],
    ]);
}

/** Ensure a tab exists (create if missing). */
function sheets_ensure_sheet(string $spreadsheetId, string $sheetName): void {
    try {
        $meta = sheets_api('GET', $spreadsheetId . '?fields=sheets.properties');
        foreach ($meta['sheets'] ?? [] as $s) {
            if (($s['properties']['title'] ?? null) === $sheetName) {
                return;
            }
        }
        sheets_api('POST', $spreadsheetId . ':batchUpdate', [
            'requests' => [['addSheet' => ['properties' => ['title' => $sheetName]]]],
        ]);
    } catch (Exception $e) {
        // best-effort
    }
}
