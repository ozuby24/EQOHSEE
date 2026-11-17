<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\{Blok, Pekerja};
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris per orang per tanggal — roster yang sudah diterbitkan.
 *
 * MENYIMPAN HASILNYA, bukan hanya polanya. Pola menghitung baseline;
 * baris di sini menyimpan apa yang benar-benar berlaku sesudah tukar
 * jaga, cuti, dan sakit. Tanpa lapis ini, seorang yang bertukar jaga
 * kembali ke polanya sendiri begitu layarnya dimuat ulang.
 *
 * `halangan` DISIMPAN APA ADANYA saat penerbitan. Dihitung ulang saat
 * dibaca, layar roster bulan lalu akan menampilkan blokir yang baru
 * muncul hari ini — dan yang membacanya menyangka jadwal yang sudah
 * berlalu itu memang melanggar, lalu menelusuri pelanggaran yang tidak
 * pernah terjadi.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Roster extends Model
{
    use BerpemilikPerusahaan;

    public const KEADAAN = [
        'kerja' => 'Kerja',
        'libur' => 'Libur / off-site',
        'cuti'  => 'Cuti',
        'sakit' => 'Sakit',
        'izin'  => 'Izin',
    ];

    /** Keadaan yang berarti orangnya berada di site dan bekerja. */
    public const BEKERJA = ['kerja'];

    public const SHIFT = ['siang' => 'Siang', 'malam' => 'Malam'];

    protected $table = 'hr_roster';

    protected $guarded = ['id'];

    protected $attributes = ['keadaan' => 'libur', 'jam' => 0, 'terbit' => false];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jam'     => 'integer',
            'terbit'  => 'boolean',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function regu(): BelongsTo    { return $this->belongsTo(Regu::class, 'regu_id'); }
    public function pola(): BelongsTo    { return $this->belongsTo(PolaRoster::class, 'pola_roster_id'); }
    public function blok(): BelongsTo    { return $this->belongsTo(Blok::class, 'blok_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    /**
     * Pengajuan cuti yang mengubah baris ini, bila ada.
     *
     * Yang membaca kalender berhak tahu MENGAPA sebuah hari berubah
     * menjadi cuti, dan menelusurinya ke pengajuannya — bukan sekadar
     * melihat hurufnya berganti.
     */
    public function cuti(): BelongsTo    { return $this->belongsTo(Cuti::class, 'cuti_id'); }

    public function bekerja(): bool
    {
        return in_array($this->keadaan, self::BEKERJA, true);
    }

    public function scopeBekerja(Builder $q): Builder
    {
        return $q->whereIn('keadaan', self::BEKERJA);
    }

    /**
     * Rentang tanggal, INKLUSIF pada kedua ujungnya.
     *
     * Batas atasnya dibuat sampai akhir hari, dan itu bukan kehati-hatian
     * berlebihan. Cast `date` Laravel memangkas jam saat DIBACA, tidak
     * saat DITULIS — kolomnya menyimpan "2026-09-22 00:00:00". Sebagai
     * perbandingan teks, "2026-09-22 00:00:00" <= "2026-09-22" bernilai
     * SALAH: yang kiri lebih panjang dan karena itu lebih besar.
     *
     * Akibatnya HARI TERAKHIR tidak pernah ikut terjaring. Pada
     * penyusunan ulang, baris hari itu tidak ditemukan sebagai "sudah
     * ada" lalu disisipkan lagi — dan batasan unik (pekerja, tanggal)
     * melemparkan galat 500 pada tombol yang baru saja berhasil ditekan
     * sekali. Terjadi sungguhan; uji penyusunan ulang yang
     * menemukannya.
     */
    public function scopeAntara(Builder $q, string $dari, string $sampai): Builder
    {
        return $q->where('tanggal', '>=', $dari)
            ->where('tanggal', '<=', $sampai.' 23:59:59');
    }
}
