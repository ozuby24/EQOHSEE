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
 * Satu surat pengajuan induksi, berisi banyak nama.
 *
 * INDUKSI TIDAK DIDAFTARKAN PER ORANG. Yang terjadi di lapangan adalah
 * satu kelas berisi puluhan pekerja pada satu tanggal di satu ruang;
 * nomor registrasinya, jadwalnya, dan persetujuannya melekat pada
 * kelas itu — bukan pada masing-masing orang di dalamnya.
 *
 * Bentuk sebelumnya memaksakan per orang: satu-satunya jalan mencatat
 * induksi adalah membuka berkas seorang pekerja lalu mengisi formulir
 * di sana. Satu kelas tiga puluh orang karena itu menjadi tiga puluh
 * baris yang tidak saling tahu — tidak ada tempat menyimpan nomor
 * suratnya, tidak ada cara mengetahui siapa saja yang ikut kelas yang
 * sama, dan persetujuannya harus ditekan tiga puluh kali.
 *
 * Sengaja KEMBAR dengan McuPengajuan. Keduanya benda yang sama secara
 * proses, dan membuat keduanya berbeda bentuk berarti tiap halaman,
 * rekap, dan penjagaan ditulis dua kali dengan dua cara.
 */
#[ScopedBy(MilikPerusahaan::class)]
class InduksiPengajuan extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;
    use Bertahap;

    protected $table = 'induksi_pengajuan';

    /**
     * `status` sengaja TIDAK ada di sini — sama alasannya dengan
     * McuPengajuan: selama status dapat diisi massal, satu
     * `create($request->validated())` menyimpan pengajuan yang langsung
     * berstatus disetujui tanpa seorang pun meninjaunya, dan tidak ada
     * galat apa pun yang menandainya.
     */
    protected $fillable = [
        'company_id', 'user_id', 'nomor_register', 'tanggal',
        'judul', 'jenis', 'lokasi', 'tgl_pelaksanaan', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'tgl_pelaksanaan' => 'date'];
    }

    /**
     * `IND000996` — bentuk D'Best, terbit sendiri sesudah punya ID.
     *
     * Lihat catatan pada McuPengajuan::booted() untuk alasan `created`
     * dan bukan `creating`.
     */
    protected static function booted(): void
    {
        static::created(function (self $m) {
            NomorRegister::terbitkan($m, 'nomor_register',
                fn (self $x) => NomorRegister::berawalan('IND', $x->getKey()));
        });
    }

    /**
     * Rantainya BERHENTI DI OHSE — lihat App\Support\Tahap.
     *
     * Berbeda dari MCU yang melewati paramedis lalu ditutup KTT.
     * Induksi diselenggarakan OHSE sendiri: rantai tiga meja di atasnya
     * hanyalah upacara, dan meja yang tidak menambah apa pun hanya
     * menambah tempat pengajuan tertahan.
     */
    public function modulTahap(): ?string { return 'induksi'; }

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
        return $this->hasMany(PasporInduksi::class, 'induksi_pengajuan_id');
    }

    /**
     * Berapa nama yang hasilnya belum diisi.
     *
     * Yang ditanyakan penyelenggara bukan "berapa yang didaftarkan"
     * melainkan "berapa yang belum dinilai" — itulah yang menentukan
     * apakah kelasnya masih perlu ditutup.
     */
    public function belumDinilai(): int
    {
        return $this->hasil->whereNull('hasil')->count();
    }

    public function labelJenis(): string
    {
        return in_array($this->jenis, Authority::JENIS_INDUKSI, true)
            ? $this->jenis
            : 'Awal';
    }
}
