<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCode extends Notification
{
  use Queueable;

  /**
   * The two-factor code.
   *
   * @var string
   */
  protected $code;

  /**
   * Create a new notification instance.
   */
  public function __construct($code = null)
  {
    // Store the code passed in the constructor or fetch from the user later
    $this->code = $code;
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
    // Use the code passed in the constructor or get it from the user model
    $code = $this->code ?? $notifiable->two_factor_code;

    return (new MailMessage)
      ->subject('Your Two-Factor Authentication Code')
      ->markdown('emails.auth.two-factor-code', [
        'code' => $code,
        'name' => $notifiable->name
      ]);
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
