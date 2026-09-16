<?php
namespace App\Notifications\Contracts;

interface NotifierInterface
{
    /**
     * Send a notification.
     *
     * @param string $recipient  Phone number or email address
     * @param string $message    Body of the notification
     * @param array  $options    Provider-specific options (sender_id, subject, etc.)
     * @return bool  True on success, false on failure
     */
    public function send(string $recipient, string $message, array $options = []): bool;

    /**
     * Return the channel name (e.g. "SMS", "Email", "WhatsApp").
     */
    public function getChannel(): string;

    /**
     * Return true if this provider is enabled and configured.
     */
    public function isEnabled(): bool;
}
