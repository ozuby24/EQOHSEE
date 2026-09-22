<?php

namespace App\Notifications;

use App\Models\Miners\Alur;
use App\Support\Miners\RincianDokumen;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan bahwa alur sebuah dokumen Miners berpindah langkah.
 *
 * ── Kenapa ada ──
 *
 * Tanpa ini, sebuah pengajuan yang sampai di meja OHSE tidak
 * memberitahu siapa pun. Ia menunggu sampai seseorang kebetulan membuka
 * daftar Outstanding — dan yang menunggu paling lama justru pengajuan
 * yang paling jarang dibuka jenisnya. Safe Track mengirim surel pada
 * tiap perpindahan, dan itulah satu-satunya hal yang membuat antreannya
 * bergerak tanpa ditanyakan lewat telepon.
 *
 * ── Kepada siapa ──
 *
 * Kepada pemegang peran yang GILIRANNYA TIBA, bukan kepada semua orang.
 * Pemberitahuan yang dikirim ke semua orang dibaca tidak oleh siapa
 * pun; yang menerimanya belajar bahwa surel ini tidak menyangkut
 * dirinya, dan sesudah itu yang benar-benar menyangkut dirinya ikut
 * terlewat.
 *
 * Pengaju ikut diberi tahu ketika pengajuannya DITOLAK atau
 * DIKEMBALIKAN — dua keadaan yang menuntut dia bertindak, dan dua
 * keadaan yang tanpa pemberitahuan hanya terlihat bila ia membuka
 * kembali daftarnya sendiri.
 *
 * ── Kenapa antre ──
 *
 * ShouldQueue TIDAK dipakai di sini dengan sengaja. Antrean menuntut
 * pekerja yang berjalan, dan pada pemasangan yang belum menjalankannya
 * pemberitahuan akan menumpuk di tabel jobs tanpa pernah terkirim —
 * gagal diam-diam, yang justru lebih buruk daripada tidak
 * memberitahukan sama sekali. Surel dikirim apa adanya lewat mailer
 * yang dikonfigurasi; pada pemasangan yang antreannya siap, konfigurasi
 * mailer-lah yang mengaturnya.
 */
class AlurMinersBerpindah extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $jenis,
        private readonly int $dokumenId,
        private readonly ?string $nomor,
        private readonly string $keadaan,
        private readonly ?string $oleh = null,
        private readonly ?string $catatan = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = RincianDokumen::LABEL[$this->jenis] ?? $this->jenis;
        $sebut = $this->nomor ? "{$label} {$this->nomor}" : "{$label} tanpa nomor";

        $m = (new MailMessage())
            ->subject($this->judul($sebut))
            ->greeting('Halo '.($notifiable->name ?? '').',')
            ->line($this->kalimat($sebut));

        if ($this->oleh) {
            $m->line('Ditindak oleh: '.$this->oleh.'.');
        }

        /* Catatan peninjau ikut, dan bukan sebagai hiasan: "dikembalikan"
           tanpa alasan memaksa pengaju menebak apa yang kurang, lalu
           mengajukan ulang dengan kekurangan yang sama. */
        if ($this->catatan) {
            $m->line('Catatan: '.$this->catatan);
        }

        return $m
            ->action('Buka '.$label, url("/miners/{$this->jenis}/{$this->dokumenId}"))
            ->line('Surel ini dikirim otomatis oleh EQOHSEE. Tidak perlu dibalas.');
    }

    private function judul(string $sebut): string
    {
        return match ($this->keadaan) {
            'tolak'        => "{$sebut} ditolak",
            'dikembalikan' => "{$sebut} dikembalikan",
            'menunggu'     => "{$sebut} menunggu tindakan Anda",
            default        => "{$sebut} disetujui",
        };
    }

    private function kalimat(string $sebut): string
    {
        return match ($this->keadaan) {
            'tolak'        => "Pengajuan {$sebut} ditolak dan tidak dilanjutkan.",
            'dikembalikan' => "Pengajuan {$sebut} dikembalikan kepada Anda untuk diperbaiki.",
            'menunggu'     => "Pengajuan {$sebut} sudah sampai pada giliran Anda dan menunggu tindakan.",
            default        => "Seluruh langkah persetujuan {$sebut} sudah selesai.",
        };
    }

    /**
     * Kunci peran yang menerima pemberitahuan pada langkah menunggu.
     *
     * Dipisah dari Alur::PERAN supaya jelas bahwa yang dipetakan di sini
     * PENERIMA SUREL, bukan label yang tampil di layar — keduanya
     * kebetulan sama sekarang, dan menyatukannya akan membuat perubahan
     * label ikut mengubah siapa yang disurati.
     */
    public const PERAN_PENERIMA = ['pjo', 'dokter', 'ohse', 'ktt'];

    public static function peranDikenal(string $peran): bool
    {
        return in_array($peran, self::PERAN_PENERIMA, true)
            && array_key_exists($peran, Alur::PERAN);
    }
}
