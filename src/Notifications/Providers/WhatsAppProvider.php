<?php
namespace App\Notifications\Providers;

use App\Notifications\Contracts\NotifierInterface;

class WhatsAppProvider implements NotifierInterface
{
    public function getChannel(): string { return 'WhatsApp'; }

    public function isEnabled(): bool
    {
        return \get_setting('whatsapp_enabled', '0') === '1'
            && !empty(\get_setting('whatsapp_api_endpoint'))
            && !empty(\get_setting('whatsapp_api_token'));
    }

    public function send(string $recipient, string $message, array $options = []): bool
    {
        if (!$this->isEnabled() || empty($recipient)) return false;

        $endpoint = \get_setting('whatsapp_api_endpoint');
        $token    = \get_setting('whatsapp_api_token');

        // Normalize to international format (strip leading 0, add +252 if needed)
        $phone = \phone_for_whatsapp($recipient);
        if (empty($phone)) return false;

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
