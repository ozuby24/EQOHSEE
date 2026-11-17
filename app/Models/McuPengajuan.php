<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Bertahap;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Authority;
use App\Support\NomorRegister;
use App\Support\Tahap;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu surat pengajuan MCU, berisi banyak nama.
 *
 * MCU TIDAK DIAJUKAN PER ORANG. Perusahaan mengirim satu surat berisi
 * daftar pekerja ke klinik pemeriksa; nomor registernya, tujuannya, dan
 * persetujuannya melekat pada surat itu — bukan pada masing-masing
 * orang di dalamnya.
 *
 * Bentuk sebelumnya menempelkan MCU langsung ke orangnya. Satu
 * pengajuan berisi empat puluh nama karena itu tercatat sebagai empat
 * puluh baris yang tidak saling tahu: tidak ada satu pun tempat untuk
 * menyimpan nomor suratnya, tidak ada cara mengetahui siapa saja yang
 * ikut dalam kiriman yang sama, dan persetujuannya harus ditekan empat
 * puluh kali. Ketika kliniknya membalas, balasannya juga satu — untuk
 * empat puluh baris yang tidak punya induk bersama.
 *
 * Persetujuannya memakai App\Support\Alur yang sama dengan seluruh
 * modul lain, bukan alur khusus.
 */
#[ScopedBy(MilikPerusahaan::class)]
class McuPengajuan extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;
    use Bertahap;

    protected $table = 'mcu_pengajuan';

    /**
     * `status` sengaja TIDAK ada di sini.
     *
     * Trait Ditinjau menuntutnya begitu: selama status masih dapat diisi
     * massal, satu `create($request->validated())` akan menyimpan
     * pengajuan yang langsung berstatus disetujui tanpa seorang pun
     * meninjaunya — dan tidak ada galat apa pun yang menandainya.
     */
    protected $fillable = [
        'company_id', 'user_id', 'nomor_register', 'tanggal',
        'kepada', 'judul', 'jenis', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    /**
     * Nomor register terbit sendiri sesudah barisnya punya ID.
     *
     * `created` dan bukan `creating`: nomornya berpijak pada ID baris,
     * dan ID baru ada setelah barisnya tersimpan. Menghitung baris
     * ("berapa surat tahun ini, tambah satu") akan memberi nomor yang
     * sama kepada dua permintaan yang datang bersamaan, dan memberi
     * nomor yang SUDAH DIPAKAI setelah satu surat dihapus.
     *
     * Yang sudah bernomor tidak disentuh — lihat NomorRegister::terbitkan().
     */
    protected static function booted(): void
    {
        static::created(function (self $m) {
            /* `MCU000035` — bentuk berawalan, sama keluarga dengan
               IND000996 dan SIMPER-002039 di D'Best. Yang berbentuk
               {KODE}.{tanggal}{id} di sana hanyalah KARTU, bukan surat
               pengajuan. */
            NomorRegister::terbitkan($m, 'nomor_register',
                fn (self $x) => NomorRegister::berawalan('MCU', $x->getKey()));
        });
    }

    /** Rantainya lewat paramedis dan ditutup KTT — lihat App\Support\Tahap. */
    public function modulTahap(): ?string { return 'mcu'; }

    public function company() { return $this->belongsTo(Company::class); }
    public function user()    { return $this->belongsTo(User::class); }

    /** Yang memutuskan hanya OHSE — lihat App\Support\Tahap. */
    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!Tahap::penentu($u))        return false;
        if (!$this->menungguTinjauan()) return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    public function hasil()
    {
        return $this->hasMany(PasporMcu::class, 'mcu_pengajuan_id');
    }

    /**
     * Berapa nama yang hasilnya sudah kembali dari klinik.
     *
     * Yang ditanyakan pengaju bukan "berapa yang dikirim" melainkan
     * "berapa yang belum kembali" — itulah yang menentukan apakah
     * suratnya masih perlu ditagih.
     */
    public function belumKembali(): int
    {
        return $this->hasil->whereNull('hasil')->count();
    }

    public function labelJenis(): string
    {
        return in_array($this->jenis, Authority::JENIS_MCU, true)
            ? $this->jenis
            : 'Berkala';
    }
}
