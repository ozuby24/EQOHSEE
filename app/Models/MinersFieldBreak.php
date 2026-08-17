<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Support\Tahap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Field break — giliran pulang pada pola kerja rotasi.
 *
 * BUKAN CUTI, dan bedanya bukan istilah. Field break adalah bagian dari
 * pola kerjanya sendiri: delapan minggu di lokasi, dua minggu pulang.
 * Ia giliran yang datang, bukan hak yang dipakai, sehingga tidak
 * mengurangi jatah cuti tahunan. Menyatukan keduanya membuat jatah cuti
 * seseorang habis hanya karena rosternya berjalan sebagaimana mestinya.
 *
 * ORANG YANG SEDANG FIELD BREAK TETAP LAYAK BEKERJA. Ia hanya sedang
 * tidak di sini. Karena itu keadaan ini TIDAK ikut ke dalam
 * Paspor::kelayakan() — daftar "tidak boleh bekerja" yang berisi
 * puluhan nama yang sebenarnya baik-baik saja akan berhenti dibaca
 * dalam seminggu, dan yang hilang bersamanya adalah nama-nama yang
 * benar-benar bermasalah.
 */
class MinersFieldBreak extends Model
{
    use BerindukPerusahaan;
    use Ditinjau;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'miners_field_break';

    /** `status` sengaja tidak dapat diisi massal — lihat trait Ditinjau. */
    protected $fillable = [
        'paspor_id', 'pola', 'mulai', 'selesai', 'kembali_aktual',
        'jenis', 'lokasi_tujuan', 'pengganti_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'mulai'          => 'date',
            'selesai'        => 'date',
            'kembali_aktual' => 'date',
        ];
    }

    public const JENIS = ['Roster', 'Darurat', 'Pengganti'];

    public function paspor()    { return $this->belongsTo(Paspor::class); }
    public function pengganti() { return $this->belongsTo(Paspor::class, 'pengganti_id'); }

    /** Yang memutuskan hanya OHSE — sama dengan seluruh modul Miners. */
    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!Tahap::penentu($u))        return false;
        if (!$this->menungguTinjauan()) return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    /**
     * Kolom yang masih boleh berubah setelah disetujui.
     *
     * `kembali_aktual` ditambahkan ke daftar dasar, dan itu bukan
     * kelonggaran: mencatat kapan orangnya BENAR-BENAR kembali adalah
     * kejadian yang terjadi sesudah persetujuan, bukan penyuntingan isi
     * pengajuannya. Tanpa ini, kepulangan yang meleset dari rencana
     * tidak dapat dicatat sama sekali — dan yang tidak tercatat adalah
     * justru selisih yang ingin diketahui.
     */
    protected function kolomSetelahDisetujui(): array
    {
        return [...\App\Support\Alur::KOLOM_SETELAH_DISETUJUI, 'kembali_aktual'];
    }

    /* ═══════════ keadaan ═══════════ */

    /** Sedang pergi pada tanggal itu. */
    public function sedangPergi(?Carbon $kini = null): bool
    {
        if (!$this->sudahDisetujui()) return false;

        $kini = ($kini ?: Carbon::now())->copy()->startOfDay();

        /* Yang sudah tercatat kembali tidak lagi pergi, walaupun tanggal
           selesainya belum tiba. Orang yang dipanggil balik lebih awal
           adalah orang yang ADA DI LOKASI, dan daftar kehadiran yang
           masih menyebutnya pergi akan dipakai membagi pekerjaan kepada
           orang yang sebenarnya siap bekerja. */
        if ($this->kembali_aktual) {
            return $kini->lt($this->kembali_aktual->copy()->startOfDay());
        }

        return $kini->betweenIncluded(
            $this->mulai->copy()->startOfDay(),
            $this->selesai->copy()->startOfDay(),
        );
    }

    public function jumlahHari(): int
    {
        return (int) $this->mulai->copy()->startOfDay()
            ->diffInDays($this->selesai->copy()->startOfDay()) + 1;
    }

    /** Kembali lebih lambat dari rencana — selisih hari, atau null. */
    public function telat(): ?int
    {
        if (!$this->kembali_aktual) return null;

        $selisih = (int) $this->selesai->copy()->startOfDay()
            ->diffInDays($this->kembali_aktual->copy()->startOfDay(), false);

        return $selisih > 0 ? $selisih : null;
    }

    /* ═══════════ saringan ═══════════ */

    public function scopeBerlangsung(Builder $q, ?Carbon $kini = null): Builder
    {
        $t = ($kini ?: Carbon::now())->toDateString();

        return $q->where('status', \App\Support\Alur::DISETUJUI)
            ->where('mulai', '<=', $t)->where('selesai', '>=', $t)
            ->where(fn ($w) => $w->whereNull('kembali_aktual')
                                 ->orWhere('kembali_aktual', '>', $t));
    }
}
