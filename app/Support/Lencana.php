<?php

namespace App\Support;

use App\Models\{Pesan, SmkpFinding};
use Illuminate\Support\Facades\Schema;

/**
 * Penanda jumlah pada butir menu — pesan belum dibaca, dan temuan audit
 * SMKP yang lewat target (porting Aurorasys).
 *
 * Tanpa penanda, percakapan hanya ditemukan oleh orang yang kebetulan
 * membuka halaman Pesan; pesan yang masuk saat ia sedang di modul lain
 * tidak pernah memanggil siapa pun. Itu bukan kekurangan kecil pada
 * fitur chat, itu yang menentukan fiturnya terpakai atau tidak.
 *
 * Dihitung sebagai peta rute → jumlah, sekali per permintaan, bukan
 * sebagai pemanggilan per butir menu: bilah samping menggambar sebelas
 * ikon modul dan seluruh butir modul aktif, dan satu kueri per butir
 * akan menjadi belasan kueri untuk menjawab satu angka.
 *
 * Tidak disimpan dalam cache statis dengan sengaja. Cache statis hidup
 * lintas metode uji dalam satu proses, dan angka basi dari uji sebelumnya
 * akan muncul sebagai kegagalan di tempat yang tidak ada hubungannya.
 */
final class Lencana
{
    /** Rute butir menu yang membawa jumlah temuan SMKP lewat target. */
    public const TEMUAN_SMKP = 'smkp.tindak-lanjut';

    /**
     * Peta nama rute → jumlah penanda.
     *
     * @return array<string,int>
     */
    public static function semua($pengguna): array
    {
        if (!$pengguna) return [];

        $peta = [];

        if ($n = static::pesanBelumDibaca($pengguna)) {
            $peta['pesan.index'] = $n;
        }

        if ($n = static::temuanSmkpLewatTarget()) {
            $peta[self::TEMUAN_SMKP] = $n;
        }

        return $peta;
    }

    /**
     * Temuan audit SMKP yang belum ditutup dan targetnya sudah lewat, dalam
     * jangkauan pengguna (global scope SmkpFinding) — angka lonceng
     * `peringatan` Aurorasys. Tanggal dibandingkan menurut tanggal
     * aplikasi, sama dengan SmkpFinding::terlambat().
     */
    private static function temuanSmkpLewatTarget(): int
    {
        if (!Schema::hasTable('smkp_findings')) return 0;

        return SmkpFinding::query()
            ->where('status', '<>', 'Closed')
            ->whereNotNull('target_selesai')
            ->whereDate('target_selesai', '<', now()->toDateString())
            ->count();
    }

    /**
     * Jumlah penanda seluruh butir sebuah modul — untuk ikon pemilih modul.
     *
     * @param array<string,int> $peta
     */
    public static function modul(array $m, array $peta): ?int
    {
        if (!$peta) return null;

        $total = 0;

        foreach ($m['groups'] as $butir) {
            foreach ($butir as $b) {
                $total += $peta[$b[1]] ?? 0;
            }
        }

        return $total ?: null;
    }

    /**
     * Pesan yang belum dibaca di seluruh percakapan langsung dan grup.
     *
     * Satu kueri, bukan perulangan atas percakapan: pengguna yang tergabung
     * di banyak grup akan membayar satu kueri per grup pada tiap gambar
     * bilah samping — pada tiap halaman, bukan hanya di halaman Pesan.
     */
    private static function pesanBelumDibaca($pengguna): int
    {
        // Bilah samping tergambar juga pada pemasangan yang tabelnya belum
        // dimigrasikan; kolom yang belum ada tidak boleh merobohkan seluruh
        // halaman hanya karena sebuah angka penanda.
        if (!Schema::hasTable('pesan') || !Schema::hasTable('percakapan_peserta')) {
            return 0;
        }

        return Pesan::query()
            ->join('percakapan_peserta as pp', 'pp.percakapan_id', '=', 'pesan.percakapan_id')
            ->join('percakapan as p', 'p.id', '=', 'pesan.percakapan_id')
            ->where('pp.user_id', $pengguna->id)
            ->whereIn('p.jenis', ['langsung', 'grup'])
            // Pesan sendiri tidak pernah dihitung belum dibaca.
            ->where(fn ($q) => $q->whereNull('pesan.user_id')
                                 ->orWhere('pesan.user_id', '!=', $pengguna->id))
            ->where(fn ($q) => $q->whereNull('pp.dibaca_sampai_id')
                                 ->orWhereColumn('pesan.id', '>', 'pp.dibaca_sampai_id'))
            ->count();
    }
}
