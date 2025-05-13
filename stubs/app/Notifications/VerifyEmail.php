<?php

namespace App\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends \Illuminate\Auth\Notifications\VerifyEmail
{
    protected $useApiRoute;

    public function __construct(bool $useApiRoute = false)
    {
        $this->useApiRoute = $useApiRoute;
    }

    protected function verificationUrl($notifiable)
    {
        $verificationExpireTime = Config::get(
            'auth.verification.expire',
            Config::get('auth.passwords.users.expire', 60)
        );

        if ($this->useApiRoute) {
            return URL::temporarySignedRoute(
                'api.verification.verify',
                Carbon::now()->addMinutes($verificationExpireTime),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($verificationExpireTime),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
