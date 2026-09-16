<?php
namespace App\Notifications;

use App\Notifications\Contracts\NotifierInterface;
use App\Notifications\Providers\EmailProvider;
use App\Notifications\Providers\SMSProvider;
use App\Notifications\Providers\WhatsAppProvider;

class NotificationService
{
    /** @var NotifierInterface[] */
    private array $providers = [];

    public function __construct()
    {
        $this->providers = [
            'SMS'       => new SMSProvider(),
            'WhatsApp'  => new WhatsAppProvider(),
            'Email'     => new EmailProvider(),
        ];
    }

    /**
     * Register a custom or additional provider.
     */
    public function registerProvider(string $channel, NotifierInterface $provider): void
    {
        $this->providers[$channel] = $provider;
    }

    /**
     * Send via a specific channel. Falls back to next available provider on failure.
     *
     * @param int         $luggageId
     * @param int         $guestId
     * @param string      $channel       Primary channel: 'SMS', 'WhatsApp', 'Email', or 'All'
     * @param string      $recipient     Phone or email address
     * @param string      $message       Notification body
     * @param array       $options       Extra options (subject, sender_id, etc.)
     * @param bool        $fallback      Try next available channel on failure
     */
    public function dispatch(
        int    $luggageId,
        int    $guestId,
        string $channel,
        string $recipient,
        string $message,
        array  $options  = [],
        bool   $fallback = false
    ): bool {
        if ($channel === 'All') {
            $sent = false;
            foreach ($this->providers as $ch => $provider) {
                if ($provider->isEnabled()) {
                    $ok = $this->sendVia($provider, $luggageId, $guestId, $recipient, $message, $options);
                    if ($ok) $sent = true;
                }
            }
            return $sent;
        }

        $provider = $this->providers[$channel] ?? null;
        if ($provider && $provider->isEnabled()) {
            $ok = $this->sendVia($provider, $luggageId, $guestId, $recipient, $message, $options);
            if ($ok) return true;
        }

        // Fallback: try remaining enabled providers
        if ($fallback) {
            foreach ($this->providers as $ch => $p) {
                if ($ch === $channel) continue;
                if ($p->isEnabled()) {
                    $ok = $this->sendVia($p, $luggageId, $guestId, $recipient, $message, $options);
                    if ($ok) return true;
                }
            }
        }

        // Log a failed attempt if all channels exhausted
        $this->logResult($luggageId, $guestId, $channel, $recipient, $message, 'Failed');
        return false;
    }

    private function sendVia(
        NotifierInterface $provider,
        int    $luggageId,
        int    $guestId,
        string $recipient,
        string $message,
        array  $options
    ): bool {
        try {
            $ok     = $provider->send($recipient, $message, $options);
            $status = $ok ? 'Sent' : 'Failed';
            $this->logResult($luggageId, $guestId, $provider->getChannel(), $recipient, $message, $status);
            return $ok;
        } catch (\Throwable $e) {
            $this->logResult($luggageId, $guestId, $provider->getChannel(), $recipient, $message, 'Failed');
            \error_log("[NotificationService] {$provider->getChannel()} error: " . $e->getMessage());
            return false;
        }
    }

    private function logResult(
        int    $luggageId,
        int    $guestId,
        string $channel,
        string $recipient,
        string $message,
        string $status
    ): void {
        $userId = $_SESSION['user_id'] ?? null;
        try {
            \db_execute(
                "INSERT INTO notifications (luggage_id, guest_id, channel, recipient, message, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                "iissssi",
                [$luggageId, $guestId, $channel, $recipient, $message, $status, $userId]
            );
        } catch (\Throwable $e) {
            \error_log("[NotificationService] Log error: " . $e->getMessage());
        }
    }

    /**
     * Get list of enabled provider channel names.
     */
    public function enabledChannels(): array
    {
        return array_keys(array_filter(
            $this->providers,
            fn($p) => $p->isEnabled()
        ));
    }
}
