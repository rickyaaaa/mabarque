<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email reset password streamer. Link mengarah ke halaman lupa-password frontend
 * (langkah "password baru") dengan query ?token=…&email=…, dikirim lewat database queue.
 */
class ResetStreamerPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function resetUrl(User $notifiable): string
    {
        return url('/forgot-password').'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset password MabarQueue')
            ->greeting("Hai {$notifiable->channel_name},")
            ->line('Kami menerima permintaan reset password untuk akun MabarQueue kamu.')
            ->action('Buat password baru', $this->resetUrl($notifiable))
            ->line("Link ini berlaku {$minutes} menit dan hanya bisa dipakai sekali.")
            ->line('Kalau kamu tidak meminta reset, abaikan email ini — password kamu tidak berubah.')
            ->salutation('Salam, tim MabarQueue');
    }
}
