<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeSuccess extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $oldEmail;

    /**
     * Create a new notification instance.
     *
     * @param string $oldEmail The previous email address
     */
    public function __construct(string $oldEmail)
    {
        $this->oldEmail = $oldEmail;
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
            ->subject('Email Address Successfully Changed')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your email address has been successfully changed on ' . config('app.name') . '.')
            ->line('**Previous email:** ' . $this->oldEmail)
            ->line('**New email:** ' . $notifiable->email)
            ->line('You can now use your new email address to sign in to your account.')
            ->action('Go to Dashboard', config('app.frontend_url') . '/dashboard')
            ->line('If you did not make this change, please contact our support team immediately.')
            ->salutation('Best regards, ' . config('app.name') . ' Team');
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
            'new_email' => $notifiable->email,
            'changed_at' => now()->toDateTimeString(),
        ];
    }
}
