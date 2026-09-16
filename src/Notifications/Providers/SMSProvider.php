<?php
namespace App\Notifications\Providers;

use App\Notifications\Contracts\NotifierInterface;

class SMSProvider implements NotifierInterface
{
    public function getChannel(): string { return 'SMS'; }

    public function isEnabled(): bool
    {
        return \get_setting('sms_enabled', '0') === '1'
            && !empty(\get_setting('sms_api_endpoint'))
            && !empty(\get_setting('sms_api_key'));
    }

    public function send(string $recipient, string $message, array $options = []): bool
    {
        if (!$this->isEnabled() || empty($recipient)) return false;

        $endpoint = \get_setting('sms_api_endpoint');
        $apiKey   = \get_setting('sms_api_key');
        $senderId = $options['sender_id'] ?? \get_setting('sms_sender_id', 'JOWHARA');

        $payload = json_encode([
            'to'        => $recipient,
            'message'   => $message,
            'sender_id' => $senderId,
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
