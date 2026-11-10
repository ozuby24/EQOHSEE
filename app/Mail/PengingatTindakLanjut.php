<?php

namespace App\Mail;

use App\Models\Company;
use App\Services\PengingatTindakLanjut as PengingatTindakLanjutLayanan;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Address, Content, Envelope};

/**
 * Email pengingat batas waktu tindak lanjut untuk akun auditi satu
 * perusahaan: daftar temuan yang targetnya sudah dekat beserta tautannya
 * (App\Services\PengingatTindakLanjut). Dikirim langsung, tanpa antrean —
 * server tidak menjalankan pekerja antrean.
 */
class PengingatTindakLanjut extends Mailable
{
    /**
     * @param list<array{nomor:string, kode:string, kategori:string, uraian:string, target:string, sisa:int, jadwal:string, status:string, audit:string, url:string}> $temuan
     */
    public function __construct(public Company $perusahaan, public array $temuan) {}

    public function envelope(): Envelope
    {
        $n = count($this->temuan);
        $dari = PengingatTindakLanjutLayanan::pengirim();

        return new Envelope(
            from: new Address($dari['address'], $dari['name']),
            subject: "Pengingat tindak lanjut: {$n} temuan audit SMKP {$this->perusahaan->name} mendekati batas waktu",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pengingat-tindak-lanjut', with: [
            'daftar' => route('smkp.tindak-lanjut'),
            'jadwal' => implode(' & ', array_map(fn (string $k) => PengingatTindakLanjutLayanan::JADWAL[$k], PengingatTindakLanjutLayanan::jadwal())),
        ]);
    }
}
