<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Alur;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Berkas kelayakan kerja satu orang.
 *
 * Menyatukan tiga hal yang selama ini tersimpan di tempat berbeda:
 * kompetensi, MCU, dan kartu masuk tambang. Satu layar menjawab
 * pertanyaan gerbang — boleh atau tidak orang ini bekerja hari ini —
 * dan menyebut sebabnya bila tidak.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Paspor extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'paspor';

    protected $fillable = [
        'company_id', 'user_id', 'nomor_register', 'nama', 'nik', 'jabatan',
        'departemen', 'klasifikasi', 'status', 'tgl_bergabung', 'foto', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tgl_bergabung' => 'date'];
    }

    /* ═══════════ relasi ═══════════ */

    public function user()    { return $this->belongsTo(User::class); }
    public function company() { return $this->belongsTo(Company::class); }

    public function sertifikat()
    {
        return $this->hasMany(PasporSertifikat::class)->orderBy('tgl_expired');
    }

    public function mcu()
    {
        return $this->hasMany(PasporMcu::class)->orderByDesc('tgl_periksa');
    }

    public function kartu()
    {
        return $this->hasMany(PasporKartu::class)->orderByDesc('tgl_terbit');
    }

    public function induksi()
    {
        return $this->hasMany(PasporInduksi::class)->orderByDesc('tanggal');
    }

    /* ═══════════ yang berlaku sekarang ═══════════ */

    /**
     * MCU TERAKHIR YANG SUDAH ADA HASILNYA — bukan yang masih berlaku.
     *
     * Bedanya dengan "yang masih berlaku" penting: bila MCU terakhir
     * sudah kadaluarsa, yang harus tampil adalah MCU kadaluarsa itu —
     * bukan MCU sebelumnya yang kebetulan belum lewat karena masa
     * berlakunya lebih panjang, dan bukan pula kosong. Orangnya memang
     * tidak layak, dan layarnya harus mengatakan itu.
     *
     * Bedanya dengan "yang terakhir dicatat" baru muncul setelah MCU
     * diajukan per rombongan. Baris pengajuan lahir saat suratnya
     * dikirim, berhari-hari sebelum orangnya diperiksa, dan hasilnya
     * masih kosong. Menghitungnya sebagai MCU terakhir membuat setiap
     * pekerja yang namanya baru dimasukkan ke surat pengajuan seketika
     * berubah menjadi "MCU belum ada" — yakni MENJADWALKAN pemeriksaan
     * berikutnya justru menggugurkan MCU yang sekarang masih sah.
     */
    public function mcuTerakhir(): ?PasporMcu
    {
        return $this->relationLoaded('mcu')
            ? $this->mcu->filter(fn (PasporMcu $m) => filled($m->hasil))
                        ->sortByDesc('tgl_periksa')->first()
            : $this->mcu()->whereNotNull('hasil')->first();
    }

    public function kartuTerakhir(): ?PasporKartu
    {
        return $this->relationLoaded('kartu')
            ? $this->kartu->sortByDesc('tgl_terbit')->first()
            : $this->kartu()->first();
    }

    /**
     * Kartu terakhir yang SUDAH DISETUJUI — yang benar-benar berlaku.
     *
     * Berbeda dari kartuTerakhir(), dan bedanya menentukan: yang terakhir
     * dicatat mungkin masih draf, dan draf bukan kartu. Yang ditampilkan
     * di layar tetap kartu terakhir apa pun statusnya — orang perlu
     * melihat bahwa pengajuannya ada — tetapi yang meloloskan di gerbang
     * hanya yang ini.
     */
    public function kartuBerlaku(): ?PasporKartu
    {
        return $this->relationLoaded('kartu')
            ? $this->kartu->where('status', Alur::DISETUJUI)
                          ->sortByDesc('tgl_terbit')->first()
            : $this->kartu()->where('status', Alur::DISETUJUI)->first();
    }

    /**
     * Induksi TERAKHIR YANG LULUS.
     *
     * Berbeda dari pola mcuTerakhir(), dan sengaja. MCU yang gagal tetap
     * harus tampil sebagai keadaan orangnya sekarang — hasil "Unfit"
     * adalah jawabannya, bukan ketiadaan jawaban. Induksi yang tidak
     * lulus tidak begitu: ia bukan keadaan yang berlaku sampai
     * diperbarui, melainkan percobaan yang belum berhasil, dan induksi
     * lulus sebelumnya masih sah sampai masa berlakunya habis.
     */
    public function induksiBerlaku(): ?PasporInduksi
    {
        $lulus = fn ($i) => $i->hasil === 'Lulus';

        return $this->relationLoaded('induksi')
            ? $this->induksi->filter($lulus)->sortByDesc('tanggal')->first()
            : $this->induksi()->where('hasil', 'Lulus')->first();
    }

    /* ═══════════ kelayakan ═══════════ */

    /** @return array{layak:bool, sebab:list<string>} */
    public function kelayakan(): array
    {
        $m = $this->mcuTerakhir();
        $k = $this->kartuBerlaku();
        $i = $this->induksiBerlaku();

        return Authority::kelayakan(
            mcuTgl:      $m?->tgl_expired?->toDateString(),
            mcuHasil:    $m?->hasil,
            kartuTgl:    $k?->tgl_expired?->toDateString(),
            kartuStatus: $this->kartuTerakhir()?->status,
            induksiTgl:  $i?->tgl_expired?->toDateString(),
        );
    }

    /**
     * Keadaan paling mendesak di antara SELURUH berkasnya.
     *
     * Orang yang MCU-nya aman tetapi satu sertifikatnya kadaluarsa
     * adalah orang yang perlu ditindak, dan daftar yang menampilkan
     * keadaan terbaiknya akan menyembunyikan itu.
     */
    public function keadaanTerburuk(): string
    {
        $tanggal = collect()
            ->merge($this->sertifikat->pluck('tgl_expired'))
            ->merge($this->mcu->pluck('tgl_expired'))
            ->merge($this->kartu->pluck('tgl_expired'))
            ->merge($this->induksi->pluck('tgl_expired'))
            ->filter();

        if ($tanggal->isEmpty()) return Authority::TAK_BERTANGGAL;

        return $tanggal
            ->map(fn ($t) => Authority::keadaan($t))
            ->sortBy(fn ($k) => Authority::URUT[$k] ?? 99)
            ->first();
    }

    public function labelKlasifikasi(): ?string
    {
        return $this->klasifikasi
            ? (Authority::KLASIFIKASI[$this->klasifikasi] ?? $this->klasifikasi)
            : null;
    }
}
