<?php

namespace App\Models\Miners;

use App\Models\{Company, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan pelanggaran seorang pekerja — Riwayat Karyawan (mengikuti DBEST).
 *
 * Tanpa global scope sengaja: catatan yang TERVERIFIKASI memang dibaca
 * perusahaan lain dalam satu grup IUP. Batas siapa melihat apa dijaga
 * App\Support\Miners\RiwayatKaryawan, satu-satunya jalan bacanya.
 */
class Pelanggaran extends Model
{
    protected $table = 'mnr_pelanggaran';

    public const DIAJUKAN     = 'diajukan';
    public const DIVERIFIKASI = 'diverifikasi';
    public const DITOLAK      = 'ditolak';

    public const STATUS = [
        self::DIAJUKAN     => 'Menunggu verifikasi OHSE',
        self::DIVERIFIKASI => 'Terverifikasi OHSE',
        self::DITOLAK      => 'Ditolak OHSE',
    ];

    public const KATEGORI = [
        'golden_rules' => 'Golden Rules / aturan keselamatan utama',
        'prosedur_k3'  => 'Prosedur / aturan K3',
        'lalu_lintas'  => 'Lalu lintas tambang / SIMPER',
        'napza'        => 'Narkoba / alkohol',
        'kekerasan'    => 'Perkelahian / kekerasan / ancaman',
        'pencurian'    => 'Pencurian / penggelapan / kecurangan',
        'disiplin'     => 'Disiplin kerja (mangkir, tidur saat kerja, dll.)',
        'lingkungan'   => 'Lingkungan hidup',
        'etika'        => 'Pelecehan / etika',
        'lainnya'      => 'Lainnya',
    ];

    public const TINGKAT = ['ringan' => 'Ringan', 'sedang' => 'Sedang', 'berat' => 'Berat'];

    public const SANKSI = [
        'teguran'      => 'Teguran / pembinaan',
        'sp1'          => 'SP 1',
        'sp2'          => 'SP 2',
        'sp3'          => 'SP 3',
        'cabut_simper' => 'Pencabutan SIMPER',
        'skorsing'     => 'Skorsing',
        'cabut_akses'  => 'Pencabutan ID / akses site',
        'phk'          => 'PHK',
        'lainnya'      => 'Lainnya',
    ];

    /** Urutan berat sanksi (makin besar makin berat). */
    public const BERAT_SANKSI = [
        'lainnya' => 0, 'teguran' => 1, 'sp1' => 2, 'sp2' => 3, 'sp3' => 4,
        'cabut_simper' => 5, 'skorsing' => 6, 'cabut_akses' => 7, 'phk' => 8,
    ];

    protected $fillable = [
        'company_id', 'pekerja_id', 'tanggal', 'kategori', 'tingkat', 'sanksi',
        'uraian', 'lokasi', 'no_dokumen', 'status', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date:Y-m-d', 'diverifikasi_pada' => 'datetime'];
    }

    public function pekerja(): BelongsTo  { return $this->belongsTo(Pekerja::class, 'pekerja_id')->withoutGlobalScopes(); }
    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function pembuat(): BelongsTo  { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function verifikator(): BelongsTo { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
}
