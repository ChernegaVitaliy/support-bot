<?php

namespace App\Services;

use Exception;

class TelegramWebAppService
{
    private string $botToken;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    /**
     * Validates Telegram WebApp initData and returns the parsed user payload.
     * Returns null when the data is missing or invalid.
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     */
    public function validate(string $initData, int $maxAgeSeconds = 86400): ?array
    {
        if (empty($initData)) {
            return null;
        }

        parse_str($initData, $data);

        if (!isset($data['hash']) || !isset($data['auth_date'])) {
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        $dataCheckString = $this->buildDataCheckString($data);

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($computedHash, $hash)) {
            return null;
        }

        if ((time() - (int)$data['auth_date']) > $maxAgeSeconds) {
            return null;
        }

        return $data;
    }

    public function parseUser(?array $data): ?array
    {
        if (!$data || !isset($data['user'])) {
            return null;
        }

        $user = json_decode($data['user'], true);

        if (!is_array($user) || !isset($user['id'])) {
            return null;
        }

        return $user;
    }

    private function buildDataCheckString(array $data): string
    {
        $keys = array_keys($data);
        sort($keys, SORT_STRING);

        $pairs = [];
        foreach ($keys as $key) {
            $pairs[] = $key . '=' . $data[$key];
        }

        return implode("\n", $pairs);
    }
}
