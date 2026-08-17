<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Alur;
use App\Support\Tahap;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Campaign keselamatan — poster, artikel, video, toolbox.
 *
 * Jangkauannya diisi tangan dan sengaja TIDAK dihitung otomatis dari
 * jumlah pekerja. Campaign yang dipasang di satu pos gerbang tidak
 * menjangkau seluruh perusahaan, dan angka yang dikarang sistem lebih
 * buruk daripada angka yang kosong: yang kosong terlihat kosong, yang
 * dikarang masuk ke laporan sebagai bukti.
 */
#[ScopedBy(MilikPerusahaan::class)]
class MinersCampaign extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    protected $table = 'miners_campaign';

    protected $fillable = [
        'company_id', 'judul', 'jenis', 'tema', 'mulai', 'selesai',
        'sasaran', 'ringkasan', 'isi', 'berkas', 'jangkauan',
    ];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'selesai' => 'date', 'jangkauan' => 'integer'];
    }

    public const JENIS = ['Poster', 'Artikel', 'Video', 'Toolbox', 'Spanduk'];

    public function company() { return $this->belongsTo(Company::class); }

    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!Tahap::penentu($u))        return false;
        if (!$this->menungguTinjauan()) return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    /**
     * Kolom yang masih boleh berubah setelah disetujui.
     *
     * Jangkauan ditambahkan: berapa orang yang menerima campaign baru
     * diketahui SESUDAH ia berjalan, dan mengunci angkanya pada saat
     * persetujuan berarti angka itu selamanya kosong.
     */
    protected function kolomSetelahDisetujui(): array
    {
        return [...Alur::KOLOM_SETELAH_DISETUJUI, 'jangkauan'];
    }

    /**
     * Sedang tayang hari ini.
     *
     * Tanpa tanggal selesai berarti berjalan terus — campaign semacam
     * "selalu pakai APD" memang tidak punya tanggal berakhir, dan
     * memaksanya punya akan membuat orang mengisi tanggal karangan yang
     * lalu membuatnya hilang dari layar tanpa ada yang menghentikannya.
     *
     * Namanya sedangTayang(), bukan tayang(), supaya tidak bertabrakan
     * dengan scopeTayang() di bawah. Nama metode biasa yang sama dengan
     * nama sebuah scope membuat pemanggilan statis MinersCampaign::tayang()
     * jatuh ke metode ini alih-alih ke scope-nya, dan PHP menggagalkannya
     * dengan galat "cannot be called statically" — bukan dengan hasil
     * yang salah, tetapi tetap di saat halaman dibuka pengguna. Nama ini
     * sekaligus seragam dengan sedangPergi() dan sedangCuti().
     */
    public function sedangTayang(?Carbon $kini = null): bool
    {
        if (!$this->sudahDisetujui()) return false;

        $kini = ($kini ?: Carbon::now())->copy()->startOfDay();

        if ($kini->lt($this->mulai->copy()->startOfDay())) return false;

        return $this->selesai === null
            || $kini->lte($this->selesai->copy()->startOfDay());
    }

    public function scopeTayang(Builder $q, ?Carbon $kini = null): Builder
    {
        $t = ($kini ?: Carbon::now())->toDateString();

        return $q->where('status', Alur::DISETUJUI)
            ->where('mulai', '<=', $t)
            ->where(fn ($w) => $w->whereNull('selesai')->orWhere('selesai', '>=', $t));
    }
}
