<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke streamer saat akses private alpha-nya disetujui Super Admin. */
class BetaAccessApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akses MabarQueue kamu sudah aktif')
            ->greeting("Hai {$notifiable->channel_name},")
            ->line('Akses private alpha MabarQueue untuk akun kamu sudah disetujui.')
            ->line('Sekarang kamu bisa membuat paket VIP, menghubungkan platform donasi, dan membuka sesi live.')
            ->action('Masuk ke workspace', url('/login'))
            ->line("Halaman antrean publik kamu: ".url('/'.$notifiable->slug))
            ->salutation('Salam, tim MabarQueue');
    }
}
