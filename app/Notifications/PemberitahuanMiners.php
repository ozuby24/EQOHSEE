<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan surel Miners di luar perpindahan alur — padanan Mailable
 * Safe Track `kembalikan_manpower`, `alert_rujukan`, dan
 * `PerpanjanganSubmit`.
 *
 * Satu kelas, isinya disusun pemanggil: ketiganya hanya berbeda judul,
 * kalimat, dan tautannya. Sengaja TIDAK diantrekan, sama seperti
 * AlurMinersBerpindah — kegagalannya harus dapat dikatakan ke layar.
 */
class PemberitahuanMiners extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $baris
     * @param  list<string>  $cc
     */
    public function __construct(
        public readonly string $judul,
        public readonly array $baris,
        public readonly ?string $tautan = null,
        public readonly string $tombol = 'Buka di EQOHSEE',
        public readonly array $cc = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage())
            ->subject($this->judul)
            ->greeting('Halo '.($notifiable->name ?? 'Bapak/Ibu').',');

        foreach ($this->baris as $b) $m->line($b);

        if ($this->cc !== []) $m->cc($this->cc);

        if ($this->tautan) $m->action($this->tombol, url($this->tautan));

        return $m->line('Surel ini dikirim otomatis oleh EQOHSEE. Tidak perlu dibalas.');
    }
}
