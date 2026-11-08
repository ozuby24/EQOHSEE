<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Miners\Acuan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keikutsertaan satu orang pada satu induksi.
 *
 * ATURAN SAFE TRACK (keputusan pemilik): yang dicatat per peserta adalah
 * tanggal, lokasi, dan Lulus / Tidak Lulus yang dipilih HSE. Peserta
 * dianggap SUDAH DIINDUKSI bila pendaftarannya disetujui dan ia tidak
 * dinyatakan Tidak Lulus — yang belum dinilai ikut diloloskan, sama
 * seperti Safe Track meloloskan status NULL. Induksi TIDAK punya masa
 * berlaku: sertifikatnya "berlaku selama yang bersangkutan bekerja di
 * area operasional" IUP penyelenggaranya.
 *
 * Kolom nilai post test dan percobaannya (aturan SOP lama EQOHSEE)
 * TIDAK dibuang — riwayatnya tetap tersimpan — tetapi tidak lagi
 * menentukan lulus atau tidaknya.
 *
 * NILAI DAN PERCOBAAN PUNYA KOLOMNYA SENDIRI. SOP menuntut kelulusan
 * post test dan membatasi remidi dua kali; di Project1 keduanya tidak
 * punya kolom sama sekali, sehingga aturan itu tidak pernah dapat
 * ditegakkan — yang tercatat hanya "sudah induksi" atau "belum", dan
 * sertifikat terbit bagi keduanya.
 */
class InduksiOrang extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'induksi';

    /**
     * Nilai kelulusan post test.
     *
     * SOP menyebut DUA ANGKA BERBEDA pada dua bagian: Ketentuan Umum
     * menulis 80, daftar syarat pengajuan baru menulis 70%. Yang dipakai
     * di sini 80 — angka yang lebih ketat — dan pilihan itu ditulis di
     * sini alih-alih disembunyikan supaya pemilik proses dapat
     * membantahnya dengan satu baris.
     */
    public const NILAI_LULUS = Acuan::POST_TEST['nilai_lulus'];

    /** Remidi paling banyak dua kali; percobaan ketiga adalah yang terakhir. */
    public const MAKS_PERCOBAAN = Acuan::POST_TEST['maks_ujian'];

    /** Berapa lama sertifikat induksi berlaku. */
    public const BULAN_BERLAKU = Acuan::MASA['induksi_bulan'];

    public const STATUS = [
        'belum'  => 'Belum Dinilai',
        'lulus'  => 'Lulus',
        'gagal'  => 'Tidak Lulus',
        'remidi' => 'Remidi',
    ];

    /** Pilihan HSE per peserta — Lulus / Tidak Lulus Safe Track. */
    public const PILIHAN = ['lulus' => 'Lulus', 'gagal' => 'Tidak Lulus'];

    protected $table = 'mnr_induksi_orang';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'belum', 'percobaan' => 1];

    protected function casts(): array
    {
        return [
            'tanggal_induksi' => 'date',
            'berlaku_sampai'  => 'date',
            'nilai'           => 'integer',
            'percobaan'       => 'integer',
        ];
    }

    public function induksi(): BelongsTo  { return $this->belongsTo(Induksi::class, 'induksi_id'); }
    public function pekerja(): BelongsTo  { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function mcuOrang(): BelongsTo { return $this->belongsTo(McuOrang::class, 'mcu_orang_id'); }

    /**
     * Status yang diturunkan dari nilai dan percobaannya.
     *
     * Diturunkan, bukan dipilih petugas: dua petugas yang menafsirkan
     * angka yang sama akan menghasilkan status berbeda bagi orang yang
     * sama, dan yang membaca kartunya tidak punya cara tahu mana yang
     * benar.
     */
    public static function statusDari(?int $nilai, int $percobaan): string
    {
        if ($nilai === null) return 'belum';
        if ($nilai >= self::NILAI_LULUS) return 'lulus';

        return $percobaan >= self::MAKS_PERCOBAAN ? 'gagal' : 'remidi';
    }

    public function lulus(): bool
    {
        return $this->status === 'lulus';
    }

    /** Dinyatakan Tidak Lulus oleh HSE. */
    public function tidakLulus(): bool
    {
        return $this->status === 'gagal';
    }

    /**
     * Sudah diinduksi menurut Safe Track: pendaftarannya disetujui dan ia
     * tidak dinyatakan Tidak Lulus. Syarat Mine Permit dan sertifikat.
     */
    public function sah(): bool
    {
        return $this->induksi?->status === 'selesai' && ! $this->tidakLulus();
    }

    /** Masih boleh mengulang — remidi belum habis. */
    public function bolehMengulang(): bool
    {
        return ! $this->lulus() && $this->percobaan < self::MAKS_PERCOBAAN;
    }

    public function kedaluwarsa(): bool
    {
        return $this->berlaku_sampai !== null
            && $this->berlaku_sampai->lt(Waktu::kini()->startOfDay());
    }

    /** Induksi terbaru yang sah bagi seorang pekerja. */
    public static function berlakuUntuk(int $pekerjaId): ?self
    {
        return static::query()
            ->where('pekerja_id', $pekerjaId)
            ->where('status', '!=', 'gagal')
            ->whereHas('induksi', fn ($q) => $q->where('status', 'selesai'))
            ->orderByDesc('tanggal_induksi')->orderByDesc('id')
            ->first();
    }

    /**
     * Nomor sertifikat — `IND/{kode IUP}/{id:04}/{mm}/{yyyy}` Safe Track.
     *
     * Dirakit, bukan disimpan: unik karena memuat id pesertanya, tanpa
     * penomoran yang dapat bentrok saat dua orang menerbitkan bersamaan.
     */
    public function nomorSertifikat(): string
    {
        $waktu = $this->tanggal_induksi ?? $this->induksi?->tanggal ?? Waktu::kini();
        $kode  = trim((string) $this->induksi?->iup?->code) ?: 'EQ';

        return sprintf('IND/%s/%04d/%s/%s', $kode, $this->getKey(), $waktu->format('m'), $waktu->format('Y'));
    }
}
