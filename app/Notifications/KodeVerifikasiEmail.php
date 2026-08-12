<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mengirimkan kode verifikasi enam angka.
 *
 * Kodenya dibawa sebagai nilai, bukan dibaca ulang dari basis data:
 * yang tersimpan di sana adalah hash-nya, dan hash tidak dapat
 * dikembalikan menjadi kode.
 */
class KodeVerifikasiEmail extends Notification
{
    use Queueable;

    public function __construct(private string $kode) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $menit = $notifiable::KODE_BERLAKU;

        return (new MailMessage)
            ->subject('Kode verifikasi EQOHSEE: '.$this->kode)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Masukkan kode berikut untuk mengaktifkan akun EQOHSEE Anda.')
            ->line('**'.$this->kode.'**')
            ->line("Kode berlaku {$menit} menit dan hanya dapat dipakai sekali.")
            ->line('Bila Anda tidak merasa mendaftar, abaikan surel ini — tanpa kode ini akun tersebut tidak dapat dipakai.')
            ->salutation('— Tim HSE EQOHSEE');
    }
}
