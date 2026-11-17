<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};


/**
 * Satu surat pengantar MCU ke klinik, memuat banyak nama.
 *
 * Bentuk aslinya di lapangan, dan karena itu bentuknya di sini:
 * perusahaan mengirim SATU surat berisi daftar orang, bukan satu surat
 * per orang. Dimodelkan per orang, nomor suratnya harus dikarang ulang
 * untuk tiap baris — dan yang dipegang klinik tinggal satu kertas yang
 * tidak cocok dengan satu pun di antaranya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Mcu extends Model
{
    use BerpemilikPerusahaan;
    use PunyaAlur;

    protected static string $jenisDokumen = 'mcu';

    public const STATUS = [
        'draf'      => 'Draf',
        'diajukan'  => 'Diajukan',
        'diperiksa' => 'Sedang Diperiksa',
        'selesai'   => 'Selesai',
        'ditolak'   => 'Ditolak',
        'kedaluwarsa' => 'Kedaluwarsa',
    ];

    /** Pilihan masa berlaku yang ditetapkan dokter saat menutup surat. */
    public const BULAN_BERLAKU = [3, 6, 12];

    protected $table = 'mnr_mcu';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'bulan_berlaku' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function orang(): HasMany
    {
        return $this->hasMany(McuOrang::class, 'mcu_id')->orderBy('nama');
    }

    public function bolehDiajukan(): bool
    {
        return $this->status === 'draf' && $this->orang()->exists();
    }

    protected static function booted(): void
    {
        /* Nomor registrasi dibuatkan — `MCU00{id}` Safe Track — bila
           pengaju tidak menulis nomornya sendiri. */
        static::created(function (self $m) {
            if (blank($m->no_registrasi)) {
                $m->forceFill(['no_registrasi' => 'MCU00'.$m->getKey()])->saveQuietly();
            }
        });
    }

    /* ═══════════ kaitan dengan Jalur ═══════════ */

    /** Manpower yang hasilnya belum ditetapkan dokter. */
    public function jumlahBelumDinilai(): int
    {
        return $this->orang()->with('hasil')->get()->filter(fn (McuOrang $o) => $o->belumDinilai())->count();
    }

    /**
     * Syarat sebelum langkah dokter disetujui: tidak ada nama yang
     * masih "Belum Dinilai" — tombol approve Safe Track baru muncul
     * sesudah seluruh hasil terisi, dan ALASANNYA dikatakan.
     *
     * @return list<string>
     */
    public function syaratSetuju(Alur $langkah): array
    {
        if ($langkah->peran !== 'dokter') return [];

        $belum = $this->jumlahBelumDinilai();

        return $belum > 0
            ? [$belum.' manpower belum ditetapkan hasil MCU-nya. Tetapkan hasil tiap nama lebih dulu; '
               .'sesudah semuanya terisi, surat ini dapat disetujui.']
            : [];
    }

    /**
     * Sesudah dokter menyetujui: masa berlaku SELURUH manpower dihitung
     * dari tanggal pemeriksaan masing-masing ditambah pilihan dokter —
     * 3, 6, atau 12 bulan (`sending_mcu_dokter` Safe Track). Baris tanpa
     * tanggal pemeriksaan dilewati: menambah bulan pada tanggal kosong
     * menghasilkan masa berlaku yang dihitung dari hari ini.
     */
    public function setelahAlurTuntas(\App\Models\User $oleh): void
    {
        $bulan = in_array((int) $this->bulan_berlaku, self::BULAN_BERLAKU, true) ? (int) $this->bulan_berlaku : 12;

        foreach ($this->orang()->get() as $o) {
            if (! $o->tanggal_periksa) continue;

            $habis = $o->tanggal_periksa->copy()->addMonthsNoOverflow($bulan);

            $o->forceFill([
                'berlaku_sampai'  => $habis,
                'tanggal_berikut' => $habis,
                'bulan_berlaku'   => $bulan,
            ])->save();
        }

        if ((int) $this->bulan_berlaku !== $bulan) {
            $this->forceFill(['bulan_berlaku' => $bulan])->saveQuietly();
        }
    }

    /**
     * Saat dikirim ULANG sesudah ditolak atau dikembalikan: seluruh hasil
     * kembali "Belum Dinilai" (`form_resubmit_mcu` Safe Track). Berkas
     * yang diperbaiki adalah berkas yang berbeda dari yang pernah dinilai.
     */
    public function saatDiulang(): void
    {
        if ($id = HasilMcu::belumDinilai()) {
            $this->orang()->update(['hasil_id' => $id]);
        }
    }
}
