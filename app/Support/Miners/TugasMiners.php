<?php

namespace App\Support\Miners;

use App\Models\Miners\{Alur, Induksi, Mcu, Permit, PermitPerpanjangan, Simper, SimperAjuan};
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen yang menunggu persetujuan orang yang sedang masuk — TugasSaya
 * Safe Track.
 *
 * SATU DAFTAR, DIPAKAI LONCENG, BILAH SAMPING, DAN ANTREAN. Safe Track
 * pernah menghitungnya sendiri-sendiri di lonceng dan dasbor, dan lonceng
 * hanya mengenal dua modul — sehingga ia berbunyi "tidak ada yang
 * menunggu" pada saat sebuah izin masuk tambang justru menunggu tanda
 * tangan orang itu.
 *
 * YANG DIHITUNG: langkah yang SEDANG giliran (seluruh langkah sebelumnya
 * sudah disetujui), DITUGASKAN kepada orang ini, dan bukan langkah
 * pengaju. Draf yang belum dikirim tidak terhitung — dokter tidak boleh
 * melihat surat yang belum diserahkan kepadanya.
 */
final class TugasMiners
{
    /**
     * Modul yang punya alur persetujuan, beserta rute antreannya.
     *
     * @var array<string,array{label:string,rute:string,sebut:string}>
     */
    public const MODUL = [
        'mcu'                 => ['label' => 'Approval MCU',                      'rute' => 'miners.daftar.outstanding-mcu',     'sebut' => 'data MCU'],
        'induksi'             => ['label' => 'Approval Induksi',                  'rute' => 'miners.daftar.outstanding-induksi', 'sebut' => 'data Induksi'],
        'permit'              => ['label' => 'Approval Mine Permit',              'rute' => 'miners.daftar.outstanding-permit',  'sebut' => 'data Mine Permit'],
        'simper'              => ['label' => 'Approval SIMPER',                   'rute' => 'miners.daftar.outstanding-simper',  'sebut' => 'data SIMPER'],
        'penambahan'          => ['label' => 'Approval Penambahan Unit',          'rute' => 'miners.daftar.penambahan-unit',     'sebut' => 'data Penambahan Unit'],
        'upgrade'             => ['label' => 'Approval Upgrade SIMPER',           'rute' => 'miners.daftar.upgrade-simper',      'sebut' => 'data Upgrade SIMPER'],
        'perpanjangan'        => ['label' => 'Approval Perpanjangan SIMPER',      'rute' => 'miners.daftar.perpanjangan',        'sebut' => 'data Perpanjangan SIMPER'],
        'perpanjangan_permit' => ['label' => 'Approval Perpanjangan Mine Permit', 'rute' => 'miners.daftar.perpanjangan-permit', 'sebut' => 'data Perpanjangan Mine Permit'],
    ];

    /** Kelas dokumen per kunci `mnr_alur.dokumen`. */
    public const KELAS = [
        'mcu'                 => Mcu::class,
        'induksi'             => Induksi::class,
        'permit'              => Permit::class,
        'simper'              => Simper::class,
        'ajuan'               => SimperAjuan::class,
        'perpanjangan_permit' => PermitPerpanjangan::class,
    ];

    /** Status dokumen yang berarti "masih berjalan". */
    public const BERJALAN = ['diajukan', 'diperiksa', 'ohse', 'ktt'];

    /**
     * Jumlah per modul bagi pengguna ini — termasuk yang nol.
     *
     * @return array<string,int>
     */
    public static function hitung(?User $u): array
    {
        /* Sekali per permintaan: lonceng dan bilah samping sama-sama
           memintanya. Disimpan pada atribut permintaan, BUKAN static —
           static hidup lintas uji dalam satu proses, dan angka basi dari
           uji sebelumnya muncul sebagai kegagalan di tempat lain. */
        $kunciIngat = 'tugas-miners:'.($u?->getKey() ?? 0);
        $atribut    = request()->attributes;

        if ($atribut->has($kunciIngat)) return $atribut->get($kunciIngat);

        $hasil = self::hitungSegar($u);
        $atribut->set($kunciIngat, $hasil);

        return $hasil;
    }

    /** @return array<string,int> */
    private static function hitungSegar(?User $u): array
    {
        $hasil = array_fill_keys(array_keys(self::MODUL), 0);

        if (! $u || ! self::siap()) {
            return $hasil;
        }

        $baris = Alur::query()->giliran()
            ->where('ditugaskan_user_id', $u->getKey())
            ->where('peran', '!=', 'pengaju')
            ->get(['dokumen', 'dokumen_id']);

        foreach ($baris->groupBy('dokumen') as $dokumen => $grup) {
            $kelas = self::KELAS[$dokumen] ?? null;
            if (! $kelas) continue;

            /* Dokumennya harus masih ada dan masih berjalan. Scope
               perusahaan sengaja DILEPAS: yang ditugasi sudah disebut
               namanya pada skema, dan menyaring lagi menurut perusahaan
               akan menyembunyikan berkas mitra dari HSE IUP-nya. */
            $q = $kelas::withoutGlobalScopes()
                ->whereIn('id', $grup->pluck('dokumen_id'))
                ->whereIn('status', self::BERJALAN);

            if ($dokumen === 'ajuan') {
                foreach ($q->pluck('jenis') as $jenis) {
                    $kunci = in_array($jenis, ['upgrade', 'perpanjangan'], true) ? $jenis : 'penambahan';
                    $hasil[$kunci]++;
                }

                continue;
            }

            $hasil[$dokumen] += $q->count();
        }

        return $hasil;
    }

    /** @var bool|null kesiapan skema, sekali per proses */
    private static ?bool $siap = null;

    /**
     * Skema alur sudah termigrasi.
     *
     * Lonceng ikut di SETIAP halaman, termasuk pada pemasangan yang belum
     * menjalankan migrasi Miners; tanpa pemeriksaan ini seluruh aplikasi
     * jatuh karena satu angka. Diingat per proses — berbeda dengan
     * jumlahnya, keberadaan kolom tidak berubah di antara dua uji.
     */
    private static function siap(): bool
    {
        /* Hanya jawaban "siap" yang diingat: proses yang memeriksanya
           sebelum migrasi berjalan tidak boleh terkunci pada "belum". */
        if (self::$siap) return true;

        return self::$siap = Schema::hasTable('mnr_alur') && Schema::hasColumn('mnr_alur', 'ditugaskan_user_id');
    }

    /**
     * Daftar siap gambar bagi lonceng — hanya yang ada isinya.
     *
     * @return list<array{kunci:string,label:string,jumlah:int,url:string,sebut:string}>
     */
    public static function daftar(?User $u): array
    {
        $daftar = [];

        foreach (self::hitung($u) as $kunci => $jumlah) {
            if ($jumlah < 1) continue;

            $daftar[] = [
                'kunci'  => $kunci,
                'label'  => self::MODUL[$kunci]['label'],
                'jumlah' => $jumlah,
                'sebut'  => self::MODUL[$kunci]['sebut'],
                'url'    => route(self::MODUL[$kunci]['rute'], [], false),
            ];
        }

        return $daftar;
    }

    /**
     * Peta rute → jumlah, untuk penanda butir bilah samping.
     *
     * @return array<string,int>
     */
    public static function lencana(?User $u): array
    {
        $peta = [];

        foreach (self::hitung($u) as $kunci => $jumlah) {
            if ($jumlah > 0) $peta[self::MODUL[$kunci]['rute']] = $jumlah;
        }

        return $peta;
    }
}
