<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class EmailChangeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $oldEmail;
    protected string $newEmail;
    protected string $userName;
    protected string $changedAt;

    /**
     * Create a new notification instance.
     *
     * @param string $oldEmail The old email address to send alert to
     * @param string $newEmail The new email address
     * @param string $userName The user's name
     * @param string $changedAt The timestamp when the change occurred
     */
    public function __construct(string $oldEmail, string $newEmail, string $userName, string $changedAt)
    {
        $this->oldEmail = $oldEmail;
        $this->newEmail = $newEmail;
        $this->userName = $userName;
        $this->changedAt = $changedAt;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔔 Security Alert: Email Address Changed')
            ->greeting('Hello ' . $this->userName . ',')
            ->line('This is a security notification to inform you that the email address for your ' . config('app.name') . ' account has been changed.')
            ->line('**Previous email:** ' . $this->oldEmail)
            ->line('**New email:** ' . $this->newEmail)
            ->line('**Changed on:** ' . $this->changedAt)
            ->line('---')
            ->line('**If you made this change:** No further action is needed. You can now sign in using your new email address.')
            ->line('**If you did NOT make this change:** Your account may have been compromised. **Please contact our support team immediately** to recover your account:')
            ->line('• Our security team will verify your identity and restore access')
            ->line('• Have your account information ready (registration date, account details, etc.)')
            ->line('• Do NOT attempt to sign in - your old email address no longer works')
            ->action('Report Unauthorized Change', 'mailto:' . config('mail.support', 'support@example.com') . '?subject=URGENT: Unauthorized Email Change on My Account&body=Hello Support Team,%0D%0A%0D%0AMy account email was changed without my authorization.%0D%0A%0D%0APrevious email: ' . urlencode($this->oldEmail) . '%0D%0ANew email: ' . urlencode($this->newEmail) . '%0D%0AChanged on: ' . urlencode($this->changedAt) . '%0D%0A%0D%0APlease help me restore access to my account.%0D%0A%0D%0AThank you.')
            ->line('For your security, this notification was sent to your previous email address.')
            ->salutation('Stay secure, ' . config('app.name') . ' Security Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'old_email' => $this->oldEmail,
            'new_email' => $this->newEmail,
            'changed_at' => $this->changedAt,
            'type' => 'security_alert',
        ];
    }
}
