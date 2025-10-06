<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerification extends Notification
{
    use Queueable;

    protected string $token;
    protected string $newEmail;
    protected bool $useApiRoute;
    protected int $userId;

    /**
     * Create a new notification instance.
     *
     * @param string $token The verification token
     * @param string $newEmail The new email address to verify
     * @param int $userId The user ID
     * @param bool $useApiRoute Whether to use API routes for verification
     */
    public function __construct(string $token, string $newEmail, int $userId, bool $useApiRoute = false)
    {
        $this->token = $token;
        $this->newEmail = $newEmail;
        $this->userId = $userId;
        $this->useApiRoute = $useApiRoute;
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
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your New Email Address')
            ->line('You have requested to change your email address on ' . config('app.name') . '.')
            ->line('Your new email address is: ' . $this->newEmail)
            ->line('Please click the button below to verify your new email address:')
            ->action('Verify New Email Address', $verificationUrl)
            ->line('This verification link will expire in 60 minutes.')
            ->line('If you did not request this email change, please ignore this email or contact support.');
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable): string
    {
        $verificationExpireTime = (int) Config::get(
            'auth.verification.expire',
            Config::get('auth.passwords.users.expire', 60)
        );

        $routeName = $this->useApiRoute ? 'api.email-change.verify' : 'email-change.verify';

        return URL::temporarySignedRoute(
            $routeName,
            Carbon::now()->addMinutes($verificationExpireTime),
            [
                'id' => $this->userId,
                'token' => $this->token,
                'email' => $this->newEmail,
            ]
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
