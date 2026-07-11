<?php
/**
 * pusher_notify.php
 * Server-Side Pusher Event Trigger via REST API
 *
 * Uses cURL to trigger events — no Composer dependencies required.
 */

require_once __DIR__ . '/pusher_config.php';

/**
 * Trigger a Pusher event via the REST API.
 *
 * @param string $channel  Channel name (e.g., 'private-user-1234567890')
 * @param string $event    Event name (e.g., 'post-verified')
 * @param array  $data     Payload data
 * @return bool  True on success, false on failure
 */
function pusherTrigger(string $channel, string $event, array $data = []): bool {
    $url = "https://api.pusherapp.com/apps/" . PUSHER_APP_ID . "/events";

    $payload = json_encode([
        'name'     => $event,
        'channel'  => $channel,
        'data'     => json_encode($data),
    ]);

    $timestamp = time();
    $authKey   = PUSHER_KEY;
    $secret    = PUSHER_SECRET;

    // HMAC-SHA256 signature
    $toSign = "POST\n/apps/" . PUSHER_APP_ID . "/events\nauth_key={$authKey}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5=" . md5($payload);
    $signature = hash_hmac('sha256', $toSign, $secret);

    $auth = "auth_key={$authKey}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5=" . md5($payload) . "&auth_signature={$signature}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url . '?' . $auth,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[PUSHER_ERROR] HTTP ' . $httpCode . ' — ' . ($result ?: 'No response'));
        return false;
    }
    return true;
}

/**
 * Notify a specific user via their private channel.
 *
 * @param string $userId  The user's ID
 * @param string $event   Event name
 * @param array  $data    Payload
 */
function notifyUser(string $userId, string $event, array $data = []): void {
    $channel = PUSHER_CHANNEL_PREFIX . '-user-' . $userId;
    pusherTrigger($channel, $event, $data);
}

/**
 * Broadcast to all officers on the officers channel.
 *
 * @param string $event  Event name
 * @param array  $data   Payload
 */
function broadcastOfficers(string $event, array $data = []): void {
    $channel = PUSHER_CHANNEL_PREFIX . '-officers';
    pusherTrigger($channel, $event, $data);
}

/**
 * Broadcast to all citizens on the public announcements channel.
 *
 * @param string $event  Event name
 * @param array  $data   Payload
 */
function broadcastAll(string $event, array $data = []): void {
    $channel = PUSHER_CHANNEL_PREFIX . '-all';
    pusherTrigger($channel, $event, $data);
}
