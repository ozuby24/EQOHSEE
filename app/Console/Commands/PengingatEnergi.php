<?php

namespace App\Console\Commands;

use App\Models\{ActivityLog, Company, User};
use App\Models\Energi\Personel;
use App\Notifications\PemberitahuanMiners;
use App\Services\PengingatTindakLanjut;
use App\Support\Energi\Kewajiban;
use Illuminate\Console\Command;

/**
 * Pengingat kalender kepatuhan energi & GRK (FSD §12).
 *
 * Kewajiban yang jatuh H-60, H-30, H-7, H-1, atau sudah terlambat disurati
 * ke Manajer Energi, Petugas GRK, dan KTT perusahaan itu; direksi ikut
 * disurati bila tinggal ≤7 hari dan belum selesai. Sertifikat manajer &
 * auditor energi diingatkan H-90 dan H-30.
 *
 * Kewajibannya diturunkan dari kenyataan (App\Support\Energi\Kewajiban),
 * jadi tidak ada daftar yang lupa dicentang: begitu laporan terbit dan
 * bukti penyampaiannya dicatat, pengingatnya berhenti sendiri.
 */
class PengingatEnergi extends Command
{
    protected $signature = 'energi:pengingat {--pratinjau : Tampilkan tanpa mengirim}';

    protected $description = 'Kirim pengingat tenggat kewajiban manajemen energi & inventarisasi GRK.';

    public function handle(): int
    {
        $baris = [];
        $kirim = 0;
        $siap = PengingatTindakLanjut::suratSiap();

        foreach (Company::withoutGlobalScopes()->orderBy('name')->get() as $c) {
            $butir = Kewajiban::perluDiingatkan(Kewajiban::daftar($c->id));

            foreach (Personel::withoutGlobalScopes()->where('company_id', $c->id)->where('aktif', true)
                         ->whereNotNull('berlaku_sampai')->get() as $p) {
                $sisa = (int) now()->startOfDay()->diffInDays($p->berlaku_sampai, false);
                if (in_array($sisa, [90, 30], true) || $sisa === -1) {
                    $butir[] = ['judul' => 'Sertifikat '.(Personel::PERAN[$p->peran] ?? $p->peran).' '.$p->nama,
                                'tenggat' => $p->berlaku_sampai->toDateString(), 'sisa_hari' => $sisa, 'kode' => 'SERT'];
                }
            }

            if ($butir === []) continue;

            $penerima = User::withoutGlobalScopes()->where('company_id', $c->id)->where('active', true)
                ->where(fn ($q) => $q->whereIn('energi_role', ['manajer_energi', 'petugas_grk'])->orWhere('lms_role', 'ktt'))->get();
            $bod = User::withoutGlobalScopes()->where('company_id', $c->id)->where('energi_role', 'bod')->where('active', true)->get();

            foreach ($butir as $b) {
                $mendesak = $b['sisa_hari'] !== null && $b['sisa_hari'] <= 7;
                $kepada = $mendesak ? $penerima->merge($bod)->unique('id') : $penerima;
                $teks = $b['sisa_hari'] < 0 ? 'terlambat '.(-$b['sisa_hari']).' hari' : $b['sisa_hari'].' hari lagi';

                $baris[] = [$c->name, $b['judul'], $b['tenggat'], $teks, $kepada->pluck('email')->implode(', ') ?: '— belum ada penerima —'];

                if ($this->option('pratinjau') || !$siap) continue;

                foreach ($kepada as $u) {
                    try {
                        $u->notify(new PemberitahuanMiners('Pengingat kewajiban energi: '.$b['judul'], [
                            $c->name.' — '.$b['judul'].'.',
                            'Tenggat: '.$b['tenggat'].' ('.$teks.').',
                            'Status kewajiban dibaca dari laporan yang terbit & disampaikan, audit yang selesai, dan sertifikat yang berlaku.',
                        ], '/energi/kalender'));
                        $kirim++;
                    } catch (\Throwable $e) {
                        $this->warn('Gagal mengirim ke '.$u->email.': '.$e->getMessage());
                    }
                }
            }
        }

        $baris ? $this->table(['Perusahaan', 'Kewajiban', 'Tenggat', 'Sisa', 'Penerima'], $baris)
               : $this->info('Tidak ada kewajiban energi yang perlu diingatkan hari ini.');

        if (!$siap && $baris && !$this->option('pratinjau')) {
            $this->warn('Server email belum diatur (MAIL_* di .env) — pengingat tidak dikirim.');
        }

        if ($kirim) ActivityLog::write('Pengingat kewajiban energi', "{$kirim} surel terkirim", 'energi');

        return self::SUCCESS;
    }
}
