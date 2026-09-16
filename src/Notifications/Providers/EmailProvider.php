<?php
namespace App\Notifications\Providers;

use App\Notifications\Contracts\NotifierInterface;

class EmailProvider implements NotifierInterface
{
    public function getChannel(): string { return 'Email'; }

    public function isEnabled(): bool
    {
        return !empty(\get_setting('smtp_host')) && !empty(\get_setting('smtp_from'));
    }

    public function send(string $recipient, string $message, array $options = []): bool
    {
        if (!$this->isEnabled() || empty($recipient)) return false;

        $host     = \get_setting('smtp_host');
        $port     = (int)(\get_setting('smtp_port') ?: 587);
        $user     = \get_setting('smtp_user');
        $pass     = \get_setting('smtp_pass');
        $from     = \get_setting('smtp_from');
        $fromName = \get_setting('smtp_from_name', 'Jowhara Hotel');
        $subject  = $options['subject'] ?? 'Jowhara Hotel Luggage Notification';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        try {
            $headers  = "From: {$fromName} <{$from}>\r\n";
            $headers .= "Reply-To: {$from}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            // Use mail() as a universal fallback when no SMTP extension is available
            // For production, swap this body for a proper SMTP socket or PHPMailer
            $sent = @mail($recipient, $subject, $message, $headers);
            return (bool)$sent;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
