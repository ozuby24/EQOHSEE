<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\User;
use App\Support\Miners\Acuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Pengajuan lanjutan atas sebuah SIMPER yang sudah terbit.
 *
 * TIGA JENIS PADA SATU TABEL — penambahan unit, upgrade kelas,
 * perpanjangan — dibedakan kolom `jenis`. Project1 menulisnya sebagai
 * tiga rangkaian tabel terpisah yang isinya hampir sama, lengkap dengan
 * tiga tabel alur tersendiri; akibatnya tiap perubahan alur dikerjakan
 * tiga kali, dan yang tertinggal adalah yang paling jarang dibuka.
 */
class SimperAjuan extends Model
{
    use BerindukPerusahaan;
    use PunyaAlur;

    protected static string $indukPerusahaan = 'simper';

    protected static string $jenisDokumen = 'ajuan';

    public const JENIS = [
        'penambahan'   => 'Penambahan Unit',
        'upgrade'      => 'Upgrade SIMPER',
        'perpanjangan' => 'Perpanjangan',
    ];

    public const STATUS = [
        'draf'      => 'Draf',
        'diajukan'  => 'Diajukan',
        'diperiksa' => 'Pemeriksaan Dokter',
        'ohse'     => 'Verifikasi OHSE',
        'ktt'      => 'Menunggu Pengesahan KTT',
        'selesai'  => 'Selesai',
        'ditolak'  => 'Ditolak',
    ];

    /**
     * Perpanjangan paling awal boleh diajukan sebulan sebelum berakhir.
     *
     * SOP menyebutnya tegas. Di Project1 ambang ini hanya dipakai
     * memberi warna pada layar pemantauan dan tidak pernah membatasi
     * kapan pengajuan boleh dibuat — sehingga perpanjangan dapat masuk
     * sepanjang tahun, dan antrean OHSE terisi berkas yang belum
     * waktunya.
     */
    public const HARI_BOLEH_PERPANJANG = Acuan::MASA['buka_perpanjangan_hari'];

    /** Status yang masih berjalan (belum selesai/ditolak). */
    public const BERJALAN = ['draf', 'diajukan', 'diperiksa', 'ohse', 'ktt'];

    protected $table = 'mnr_simper_ajuan';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'simpol_berlaku_sampai' => 'date', 'tanggal_mcu' => 'date'];
    }

    /**
     * Rantainya menurut jenis pengajuan — RANTAI Safe Track: penambahan
     * HSE→KTT, upgrade HSE saja, perpanjangan dokter→HSE→KTT.
     */
    public function kunciRantai(): string
    {
        return match ($this->jenis) {
            'upgrade'      => 'upgrade',
            'perpanjangan' => 'perpanjangan',
            default        => 'penambahan',
        };
    }

    /** IUP penerbit ikut SIMPER-nya, bukan perusahaan pembuat pengajuan. */
    public function iupPenerbitAwal(): ?int
    {
        $iup = $this->simper_id
            ? Simper::withoutGlobalScopes()->whereKey($this->simper_id)->value('iup_id')
            : null;

        return $iup ? (int) $iup : \App\Support\Miners\Penyetuju::iupDari(auth()->user()?->company_id);
    }

    /**
     * Syarat kirim — penambahan dan upgrade harus memuat unitnya.
     *
     * @return list<string>
     */
    /** Awalan nomor per jenis — upgrade `SIMPER-AUTH00{id}` Safe Track. */
    public const AWALAN = ['penambahan' => 'SIMPER-ADD00', 'upgrade' => 'SIMPER-AUTH00', 'perpanjangan' => 'SIMPER-EXT00'];

    protected static function booted(): void
    {
        static::created(function (self $a) {
            if (blank($a->no_registrasi)) {
                $a->forceFill(['no_registrasi' => (self::AWALAN[$a->jenis] ?? 'SIMPER-AJU00').$a->getKey()])->saveQuietly();
            }
        });
    }

    /**
     * Syarat "Sending": penambahan butuh sedikitnya satu unit BARU (bukan
     * salinan unit lama) beserta berkas evaluasi dan pengalaman; upgrade
     * sedikitnya satu baris perubahan authority; perpanjangan seluruh
     * isian SIM dan MCU barunya.
     *
     * @return list<string>
     */
    public function syaratKirim(): array
    {
        return match ($this->jenis) {
            'penambahan' => array_values(array_filter([
                $this->unit()->whereNull('simper_unit_id')->exists() ? null : 'belum ada unit baru pada pengajuan ini',
                blank($this->berkas_evaluasi) ? 'berkas evaluasi' : null,
                blank($this->berkas_pengalaman) ? 'berkas pengalaman' : null,
            ])),
            'upgrade' => $this->unit()->whereNotNull('simper_unit_id')->exists()
                ? [] : ['belum ada unit yang di-upgrade pada pengajuan ini'],
            default => array_values(array_filter([
                blank($this->no_simpol_baru) ? 'nomor SIM baru' : null,
                $this->simpol_berlaku_sampai ? null : 'masa berlaku SIM baru',
                $this->tanggal_mcu ? null : 'tanggal MCU',
                blank($this->berkas_simpol) ? 'berkas SIM baru' : null,
                blank($this->berkas_mcu) ? 'sertifikat MCU' : null,
                blank($this->berkas_form) ? 'form perpanjangan' : null,
            ])),
        };
    }

    /**
     * Langkah dokter pada perpanjangan menulis balik MCU-nya — tanggal
     * periksa = tanggal MCU, berlaku = tanggal SIM baru (`nom = datesimpol`
     * Safe Track). SIMPER-nya sendiri BELUM berubah sampai KTT menyetujui.
     */
    public function setelahLangkahDisetujui(Alur $langkah, User $oleh): void
    {
        if ($this->jenis !== 'perpanjangan' || $langkah->peran !== 'dokter') return;

        $mcu = $this->simper?->permit?->mcuOrang;

        if (! $mcu || ! $this->simpol_berlaku_sampai) return;

        $mcu->forceFill([
            'tanggal_periksa' => $this->tanggal_mcu ?? $mcu->tanggal_periksa,
            'berlaku_sampai'  => $this->simpol_berlaku_sampai,
            'tanggal_berikut' => $this->simpol_berlaku_sampai,
        ])->save();
    }

    /**
     * Langkah terakhir disetujui:
     *   · perpanjangan — tanggal dan nomor SIM baru pindah ke SIMPER,
     *     sumber 'perpanjangan', riwayat dicatat dengan keadaan lamanya;
     *   · upgrade — authority baru ditulis ke unit SIMPER-nya (Safe Track
     *     belum pernah membangun persetujuannya; ini melengkapinya);
     *   · penambahan — tidak digabung ke unit SIMPER (Safe Track); unit
     *     barunya dibaca Simper::unitTambahan().
     */
    public function setelahAlurTuntas(User $oleh): void
    {
        $simper = $this->simper;
        if (! $simper) return;

        if ($this->jenis === 'perpanjangan' && $this->simpol_berlaku_sampai) {
            $lama = RiwayatPerpanjangan::keadaanSimper($simper);

            $simper->forceFill(array_filter([
                'simpol_berlaku_sampai' => $this->simpol_berlaku_sampai,
                'simpol_sumber'         => 'perpanjangan',
                'no_simpol'             => filled($this->no_simpol_baru) ? $this->no_simpol_baru : null,
                'berkas_simpol'         => $this->berkas_simpol,
            ], fn ($v) => $v !== null))->save();

            RiwayatPerpanjangan::catat('simper', (int) $simper->id, $lama,
                RiwayatPerpanjangan::keadaanSimper($simper->fresh()), (int) $this->id, $oleh->getKey());
        }

        if ($this->jenis === 'upgrade') {
            foreach ($this->unit()->whereNotNull('simper_unit_id')->get() as $u) {
                SimperUnit::withoutGlobalScopes()->whereKey($u->simper_unit_id)
                    ->where('simper_id', $simper->id)->update(['authority' => $u->authority]);
            }
        }
    }

    public function simper(): BelongsTo { return $this->belongsTo(Simper::class, 'simper_id'); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }

    public function unit(): HasMany
    {
        return $this->hasMany(SimperAjuanUnit::class, 'ajuan_id')->orderBy('id');
    }

    public function jenisLabel(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
