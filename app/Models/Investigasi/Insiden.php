<?php

namespace App\Models\Investigasi;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use App\Support\Investigasi\Triase;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu kejadian yang dilaporkan — sebelum diputuskan diselidiki atau tidak.
 *
 * Berdiri sendiri dari Investigasi, dan pemisahan itu disengaja: tidak
 * setiap insiden berujung investigasi. Pekerja yang tergores ranting
 * dicatat, ditriase, lalu ditutup tanpa tim. Menyatukan keduanya berarti
 * tiap insiden ringan melahirkan berkas investigasi kosong — dan berkas
 * kosong yang jumlahnya ribuan membuat berkas yang sungguh berisi tidak
 * lagi dapat ditemukan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Insiden extends Model
{
    use BerpemilikPerusahaan;
    use SoftDeletes;

    protected $table = 'inv_insiden';

    protected $fillable = [
        'company_id', 'no_insiden', 'judul', 'tanggal_kejadian', 'waktu_kejadian',
        'dilaporkan_pada', 'lokasi_id', 'lokasi_rinci', 'aktivitas',
        'jenis_insiden_id', 'klasifikasi_cedera_id', 'klasifikasi_regulasi_id', 'pelapor_id',
        'kemungkinan', 'keparahan', 'keparahan_potensial',
        'skor_risiko', 'pita_risiko', 'level_investigasi',
        'k1_benar_terjadi', 'k2_mencederai_pekerja', 'k3_akibat_kegiatan',
        'k4_jam_kerja', 'k5_wilayah_usaha',
        'wajib_lapor_kait', 'tenggat_lapor', 'tenggat_selidik', 'dilaporkan_kait_pada',
        'kronologi', 'tindakan_segera',
        'p_shift_malam', 'p_lembur_panjang', 'p_sop_tidak_ada',
        'p_belum_dilatih', 'p_inspeksi_absen', 'p_insiden_berulang',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kejadian'     => 'date',
            'dilaporkan_pada'      => 'datetime',
            'tenggat_lapor'        => 'datetime',
            'tenggat_selidik'      => 'datetime',
            'dilaporkan_kait_pada' => 'datetime',

            'k1_benar_terjadi'      => 'boolean',
            'k2_mencederai_pekerja' => 'boolean',
            'k3_akibat_kegiatan'    => 'boolean',
            'k4_jam_kerja'          => 'boolean',
            'k5_wilayah_usaha'      => 'boolean',
            'wajib_lapor_kait'      => 'boolean',

            'p_shift_malam'      => 'boolean',
            'p_lembur_panjang'   => 'boolean',
            'p_sop_tidak_ada'    => 'boolean',
            'p_belum_dilatih'    => 'boolean',
            'p_inspeksi_absen'   => 'boolean',
            'p_insiden_berulang' => 'boolean',
        ];
    }

    /* ═══════════ relasi ═══════════ */

    public function company()   { return $this->belongsTo(Company::class); }
    public function pelapor()   { return $this->belongsTo(User::class, 'pelapor_id'); }
    public function lokasi()    { return $this->belongsTo(Lokasi::class, 'lokasi_id'); }
    public function jenis()     { return $this->belongsTo(JenisInsiden::class, 'jenis_insiden_id'); }
    public function cedera()    { return $this->belongsTo(KlasifikasiCedera::class, 'klasifikasi_cedera_id'); }
    public function regulasi()  { return $this->belongsTo(KlasifikasiRegulasi::class, 'klasifikasi_regulasi_id'); }

    public function orang()
    {
        return $this->hasMany(InsidenOrang::class, 'insiden_id')->orderBy('id');
    }

    public function investigasi()
    {
        return $this->hasOne(Investigasi::class, 'insiden_id');
    }

    /* ═══════════ keadaan ═══════════ */

    public function sudahDitriase(): bool
    {
        return filled($this->level_investigasi);
    }

    /**
     * Lima kriteria kecelakaan tambang yang TIDAK terpenuhi.
     *
     * Yang ditanya Inspektur Tambang bukan "apakah ini kecelakaan
     * tambang" melainkan kriteria mana yang tidak terpenuhi. Kesimpulan
     * tunggal tidak dapat menjawabnya, jadi kelimanya disimpan dan
     * disebut satu per satu.
     *
     * @return list<string>
     */
    public function kriteriaKurang(): array
    {
        $kurang = [];

        foreach (Triase::KRITERIA as $kolom => $bunyi) {
            if (! $this->{$kolom}) $kurang[] = $bunyi;
        }

        return $kurang;
    }

    public function memenuhiKecelakaanTambang(): bool
    {
        return $this->kriteriaKurang() === [];
    }

    /**
     * Tenggat yang sudah lewat tanpa dipenuhi.
     *
     * Dipisah dari "mendekati tenggat": yang sudah lewat adalah
     * pelanggaran yang harus dijelaskan, bukan pekerjaan yang harus
     * dijadwalkan, dan menyatukan keduanya dalam satu daftar membuat
     * yang pertama tenggelam di antara yang kedua.
     */
    public function terlambatLapor(): bool
    {
        return $this->wajib_lapor_kait
            && $this->tenggat_lapor
            && ! $this->dilaporkan_kait_pada
            && $this->tenggat_lapor->isPast();
    }
}
