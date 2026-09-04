<?php

namespace App\Models\Pembelian;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu tagihan.
 *
 * ── STATUS DAN TOTAL TIDAK DAPAT DIISI MASSAL ──
 *
 * Keduanya sengaja di luar `$fillable`, dan itu penjagaan yang paling
 * menentukan di seluruh modul ini.
 *
 * `total` yang dapat dikirim dari formulir berarti siapa pun dapat
 * memesan sepuluh aplikasi seharga nol rupiah — cukup dengan mengubah
 * satu medan tersembunyi sebelum menekan kirim. Totalnya dihitung
 * server dari harga yang tersimpan, bukan diterima dari klien.
 *
 * `status` yang dapat dikirim dari formulir berarti pembeli dapat
 * menyatakan tagihannya sendiri lunas. Yang menetapkannya hanya
 * `tandaiLunas()`, dan itu hanya dipanggil sesudah seorang manusia
 * memverifikasi buktinya.
 *
 * ── MENGAPA TIDAK ADA GERBANG PEMBAYARAN ──
 *
 * Tanpa penyedia pembayaran, aplikasi TIDAK TAHU uangnya sudah masuk;
 * yang diketahuinya hanya bahwa seseorang mengunggah gambar. Karena itu
 * "lunas" ditetapkan orang, bukan disimpulkan sistem. Menyimpulkannya
 * dari adanya unggahan berarti siapa pun yang mengunggah gambar apa pun
 * memperoleh lisensi.
 */
class Pesanan extends Model
{
    protected $table = 'beli_pesanan';

    public const DRAF                = 'draf';
    public const MENUNGGU_BAYAR      = 'menunggu_bayar';
    public const MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    public const LUNAS               = 'lunas';
    public const DITOLAK             = 'ditolak';
    public const BATAL               = 'batal';
    public const KEDALUWARSA         = 'kedaluwarsa';

    public const LABEL = [
        self::DRAF                => 'Draf',
        self::MENUNGGU_BAYAR      => 'Menunggu pembayaran',
        self::MENUNGGU_VERIFIKASI => 'Menunggu verifikasi',
        self::LUNAS               => 'Lunas',
        self::DITOLAK             => 'Bukti ditolak',
        self::BATAL               => 'Dibatalkan',
        self::KEDALUWARSA         => 'Kedaluwarsa',
    ];

    /** `status`, `total`, dan `token` sengaja tidak ada di sini. */
    protected $fillable = [
        'company_id', 'pembeli_nama', 'pembeli_perusahaan', 'pembeli_email',
        'pembeli_telepon', 'catatan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'kedaluwarsa_pada'  => 'datetime',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /* Token dibuat di sini, bukan di controller: pesanan yang lahir
           dari mana pun — perintah artisan, data contoh, seeder — tetap
           harus punya tautan bayar yang tidak dapat ditebak. */
        static::creating(function (self $p) {
            $p->token ??= Str::random(48);
        });
    }

    public function items()       { return $this->hasMany(Item::class, 'pesanan_id'); }
    public function pembayaran()  { return $this->hasMany(Pembayaran::class, 'pesanan_id')->orderByDesc('id'); }
    public function lisensi()     { return $this->hasMany(Lisensi::class, 'pesanan_id'); }
    public function company()     { return $this->belongsTo(Company::class); }
    public function pembuat()     { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function pemverifikasi() { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }

    /**
     * Hitung ulang total dari barisnya.
     *
     * Dipanggil tiap kali barisnya berubah. Total yang disimpan sekali
     * lalu tidak pernah dihitung ulang akan meleset begitu satu baris
     * dihapus — dan yang tertulis di layar tetap angka lama, sehingga
     * selisihnya baru ketahuan saat uangnya masuk.
     */
    public function hitungUlang(): void
    {
        $this->forceFill([
            'total' => (int) $this->items()->sum('subtotal'),
        ])->save();
    }

    public function bolehDibayar(): bool
    {
        return in_array($this->status, [self::MENUNGGU_BAYAR, self::DITOLAK], true)
            && ! $this->sudahKedaluwarsa();
    }

    public function sudahKedaluwarsa(): bool
    {
        return $this->kedaluwarsa_pada !== null && $this->kedaluwarsa_pada->isPast();
    }

    public function selesai(): bool
    {
        return in_array($this->status, [self::LUNAS, self::BATAL, self::KEDALUWARSA], true);
    }

    /**
     * Keadaan yang ditampilkan, sudah memperhitungkan waktu.
     *
     * Tagihan yang lewat tenggat tetap berstatus 'menunggu_bayar' di
     * basis data sampai ada yang menjalankan pembersihnya. Yang dibaca
     * layar harus keadaan SEKARANG, bukan keadaan terakhir yang sempat
     * dituliskan — kalau tidak, halaman bayar tetap menerima pembayaran
     * untuk tagihan yang sudah mati.
     */
    public function keadaan(): string
    {
        if ($this->status === self::MENUNGGU_BAYAR && $this->sudahKedaluwarsa()) {
            return self::KEDALUWARSA;
        }

        return $this->status;
    }

    public function keadaanLabel(): string
    {
        return self::LABEL[$this->keadaan()] ?? $this->keadaan();
    }
}
