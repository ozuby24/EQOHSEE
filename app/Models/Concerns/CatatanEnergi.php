<?php

namespace App\Models\Concerns;

use App\Models\Energi\{Jejak, Periode};
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Catatan harian energi: alur verifikasi, kunci periode, dan jejak audit.
 *
 * Dipasang pada kelima tabel catatan harian (BBM per unit, listrik, energi
 * lain, produksi, rekonsiliasi). Tiga hal dijaga di sini, bukan di tiap
 * controller, sebab penjaga yang ditulis ulang di tiap tempat pasti ada
 * yang tertinggal — dan yang tertinggal adalah jalan mengubah angka yang
 * sudah dilaporkan ke regulator:
 *
 *  1. Baris berstatus `terkunci` tidak dapat diubah atau dihapus, kecuali
 *     lewat koreksi yang sudah disetujui (`CatatanEnergi::lewatKoreksi`).
 *  2. Baris baru tidak dapat ditulis ke periode yang sudah terkunci.
 *  3. Setiap pembuatan, perubahan, dan penghapusan oleh pengguna tercatat
 *     di energi_jejak beserta nilai lama → baru per kolom.
 *
 * Tanpa pengguna (penyemai, data contoh, antrean) jejak tidak ditulis:
 * tidak ada siapa yang dapat dicatat, dan baris jejak tanpa pelaku hanya
 * menyesatkan pemeriksanya.
 */
trait CatatanEnergi
{
    /** Status alur, urut dari input sampai terkunci. */
    public static array $STATUS_ALUR = ['draf', 'diajukan', 'ditolak', 'terverifikasi', 'terkunci'];

    /** Sumber data aktivitas — penentu ketidakpastian bawaan (FSD §6.9). */
    public static array $SUMBER_DATA = ['flowmeter' => 'Flow meter / meter', 'nota' => 'Nota / tiket',
                                        'estimasi' => 'Estimasi', 'fms' => 'FMS / sistem lain'];

    private static bool $izinKoreksi = false;

    /** Jalankan perubahan atas baris terkunci — HANYA dari koreksi yang disetujui. */
    public static function lewatKoreksi(callable $kerja): mixed
    {
        self::$izinKoreksi = true;

        try {
            return $kerja();
        } finally {
            self::$izinKoreksi = false;
        }
    }

    public static function bootCatatanEnergi(): void
    {
        static::creating(function ($m) {
            if (auth()->check() && empty($m->diinput_oleh)) $m->diinput_oleh = auth()->id();

            if (auth()->check() && $m->tanggal && Periode::terkunciPada($m->perusahaanCatatan(), $m->tanggal)) {
                throw new HttpException(423, 'Periode '.$m->tanggal->translatedFormat('F Y')
                    .' sudah terkunci. Ajukan koreksi bila data ini memang perlu ditambahkan.');
            }
        });

        static::updating(function ($m) {
            if ($m->getOriginal('status') === 'terkunci' && !self::$izinKoreksi) {
                throw new HttpException(423, 'Baris ini berada pada periode terkunci dan hanya dapat diubah lewat koreksi yang disetujui.');
            }
        });

        static::deleting(function ($m) {
            if ($m->getOriginal('status') === 'terkunci' && !self::$izinKoreksi) {
                throw new HttpException(423, 'Baris ini berada pada periode terkunci dan tidak dapat dihapus.');
            }
        });

        static::created(fn ($m) => $m->catatJejak('buat', $m->getAttributes()));
        static::updated(function ($m) {
            $ubah = [];
            foreach ($m->getChanges() as $k => $baru) {
                if ($k === 'updated_at') continue;
                $ubah[$k] = [$m->getOriginal($k), $baru];
            }
            if ($ubah) $m->catatJejak(array_key_exists('status', $ubah) ? self::aksiStatus($ubah['status'][1]) : 'ubah', $ubah);
        });
        static::deleted(fn ($m) => $m->catatJejak('hapus', $m->getOriginal()));
    }

    private static function aksiStatus(?string $status): string
    {
        return match ($status) {
            'terverifikasi' => 'verifikasi',
            'terkunci'      => 'kunci',
            'ditolak'       => 'tolak',
            default         => 'ubah',
        };
    }

    /** Perusahaan pemilik baris; BBM per unit mewarisinya dari unit alatnya. */
    public function perusahaanCatatan(): ?int
    {
        if (array_key_exists('company_id', $this->getAttributes())) {
            return $this->company_id !== null ? (int) $this->company_id : null;
        }

        if (method_exists($this, 'equipment')) {
            $id = $this->equipment()->withoutGlobalScopes()->value('company_id');

            return $id !== null ? (int) $id : null;
        }

        return null;
    }

    public function catatJejak(string $aksi, array $perubahan): void
    {
        if (!auth()->check()) return;

        unset($perubahan['created_at'], $perubahan['updated_at']);

        Jejak::withoutGlobalScopes()->create([
            'company_id'  => $this->perusahaanCatatan(),
            'subjek_type' => static::class,
            'subjek_id'   => $this->getKey(),
            'aksi'        => $aksi,
            'perubahan'   => $perubahan,
            'user_id'     => auth()->id(),
            'ip'          => request()?->ip(),
        ]);
    }

    public function scopeStatusAlur(Builder $q, string|array $status): Builder
    {
        return $q->whereIn($this->getTable().'.status', (array) $status);
    }
}
