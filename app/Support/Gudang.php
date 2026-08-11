<?php

namespace App\Support;

use App\Models\GudangBarang;
use Illuminate\Support\Carbon;

/**
 * Aturan gudang dan penyimpanan.
 *
 * Acuan:
 * · PP No. 22 Tahun 2021        — pengelolaan limbah B3 dan penyimpanannya
 * · PP No. 74 Tahun 2001        — pengelolaan bahan berbahaya dan beracun
 * · Permenaker No. 5 Tahun 2018 — syarat lingkungan kerja, penyimpanan bahan kimia
 * · Kepdirjen 185.K/37.04/DJB/2019 — elemen implementasi SMKP Minerba
 *
 * Seluruh angka di sini diturunkan dari mutasi, bukan dibaca dari kolom
 * ringkasan. Lihat catatan pada migrasi gudang.
 */
final class Gudang
{
    /* ══════════════ kategori barang ══════════════ */

    public const KATEGORI = [
        'b3'       => ['nama' => 'Bahan Berbahaya (B3)', 'nada' => 'merah',
                       'ket'  => 'Bahan kimia dan bahan berbahaya beracun.'],
        'material' => ['nama' => 'Material & Suku Cadang', 'nada' => 'biru',
                       'ket'  => 'Material umum, suku cadang, dan bahan habis pakai.'],
        'apd'      => ['nama' => 'APD & Alat Keselamatan', 'nada' => 'hijau',
                       'ket'  => 'Alat pelindung diri dan perlengkapan tanggap darurat.'],
    ];

    /* ══════════════ penggolongan B3 ══════════════ */

    /**
     * Kelas bahaya menurut GHS sebagaimana dipakai PP 74/2001.
     *
     * `simpan` adalah syarat penyimpanan yang paling menentukan; dipakai
     * pada halaman barang supaya petugas gudang tidak perlu membuka LDK
     * hanya untuk tahu di mana barang itu boleh diletakkan.
     */
    public const KELAS_B3 = [
        'mudah_meledak'   => ['nama' => 'Mudah Meledak',      'simpan' => 'Ruang khusus, jauh dari sumber panas dan benturan.'],
        'pengoksidasi'    => ['nama' => 'Pengoksidasi',       'simpan' => 'Terpisah dari bahan mudah menyala dan bahan organik.'],
        'mudah_menyala'   => ['nama' => 'Mudah Menyala',      'simpan' => 'Ruang berventilasi, tahan api, jauh dari pengoksidasi.'],
        'beracun'         => ['nama' => 'Beracun',            'simpan' => 'Lemari terkunci, berventilasi, akses terbatas.'],
        'berbahaya'       => ['nama' => 'Berbahaya',          'simpan' => 'Rak tertutup dengan pelabelan jelas.'],
        'korosif'         => ['nama' => 'Korosif',            'simpan' => 'Rak tahan korosi bertanggul, terpisah asam dan basa.'],
        'iritan'          => ['nama' => 'Iritan',             'simpan' => 'Rak tertutup, sediakan eyewash terdekat.'],
        'karsinogenik'    => ['nama' => 'Karsinogenik',       'simpan' => 'Akses terbatas, pencatatan pemakaian wajib.'],
        'berbahaya_air'   => ['nama' => 'Berbahaya bagi Lingkungan Perairan', 'simpan' => 'Bertanggul, jauh dari saluran air.'],
        'reaktif_air'     => ['nama' => 'Reaktif terhadap Air', 'simpan' => 'Ruang kering, jauh dari sumber air dan APAR air.'],
    ];

    /**
     * Pasangan kelas yang tidak boleh disimpan berdekatan.
     *
     * Ditulis satu arah saja; pencocokannya memeriksa kedua arah. Menulis
     * keduanya berarti dua baris yang harus selalu diubah bersama, dan
     * satu yang tertinggal membuat matriksnya melaporkan aman untuk
     * pasangan yang justru berbahaya.
     */
    public const PANTANGAN = [
        ['pengoksidasi',  'mudah_menyala',  'Pengoksidasi mempercepat pembakaran bahan mudah menyala.'],
        ['pengoksidasi',  'mudah_meledak',  'Campuran dapat meledak tanpa sumber api dari luar.'],
        ['pengoksidasi',  'reaktif_air',    'Reaksi hebat disertai panas dan gas.'],
        ['korosif',       'mudah_meledak',  'Kebocoran asam dapat memicu bahan peledak.'],
        ['korosif',       'reaktif_air',    'Asam dan basa umumnya mengandung air.'],
        ['mudah_menyala', 'mudah_meledak',  'Kebakaran kecil dapat berkembang menjadi ledakan.'],
        ['reaktif_air',   'mudah_menyala',  'Gas hidrogen yang timbul mudah menyala.'],
    ];

    /** Alasan dua kelas tidak boleh berdekatan, atau null bila aman. */
    public static function pantangan(?string $a, ?string $b): ?string
    {
        if (!$a || !$b) return null;

        foreach (self::PANTANGAN as [$x, $y, $alasan]) {
            if (($a === $x && $b === $y) || ($a === $y && $b === $x)) return $alasan;
        }

        return null;
    }

    /**
     * Pelanggaran penyimpanan pada sebuah lokasi.
     *
     * Diperiksa antar barang yang benar-benar bersaldo — bahan yang
     * stoknya nol tidak ada wujudnya di rak, dan melaporkannya sebagai
     * bahaya membuat daftar peringatan penuh oleh hal yang tidak ada.
     *
     * @param  iterable<GudangBarang>  $barang
     * @return array<int,array{a:string,b:string,alasan:string}>
     */
    public static function periksaPenyimpanan(iterable $barang): array
    {
        $isi = [];
        foreach ($barang as $b) {
            if ($b->kategori === 'b3' && $b->kelas_b3 && self::stok($b) > 0) $isi[] = $b;
        }

        $hasil = [];
        for ($i = 0; $i < count($isi); $i++) {
            for ($j = $i + 1; $j < count($isi); $j++) {
                $alasan = self::pantangan($isi[$i]->kelas_b3, $isi[$j]->kelas_b3);
                if ($alasan) {
                    $hasil[] = ['a' => $isi[$i]->nama, 'b' => $isi[$j]->nama, 'alasan' => $alasan];
                }
            }
        }

        return $hasil;
    }

    /* ══════════════ stok ══════════════ */

    public const MASUK  = ['masuk'];
    public const KELUAR = ['keluar', 'rusak'];

    /**
     * Stok berjalan sebuah barang.
     *
     * Dihitung dari mutasinya. Opname tidak dijumlahkan seperti mutasi
     * lain: ia menetapkan saldo, bukan menambah atau mengurangi. Karena
     * itu perhitungan dimulai dari opname terakhir, lalu mutasi sesudahnya
     * saja yang diperhitungkan — kalau tidak, koreksi hasil hitungan fisik
     * akan tertimpa kembali oleh riwayat yang sudah dikoreksi.
     */
    public static function stok(GudangBarang $b): float
    {
        return self::stokPada($b, null);
    }

    /**
     * Saldo sebuah barang pada akhir tanggal tertentu.
     *
     * `null` berarti seluruh riwayat — saldo berjalan hari ini.
     *
     * Dipakai juga oleh laporan untuk saldo awal periode, supaya saldo
     * awal ikut memperhitungkan opname dan bukan sekadar hasil kurang
     * dari saldo akhir. Menghitungnya sebagai `akhir − masuk + keluar`
     * benar hanya selama tidak pernah ada opname di dalam periodenya, dan
     * justru periode yang mengandung opname itulah yang paling perlu
     * dibaca orang.
     */
    public static function stokPada(GudangBarang $b, ?string $sampai): float
    {
        $mutasi = $b->relationLoaded('mutasi')
            ? $b->mutasi->sortBy([['tanggal', 'asc'], ['id', 'asc']])
            : $b->mutasi()->orderBy('tanggal')->orderBy('id')->get();

        if ($sampai !== null) {
            $mutasi = $mutasi->filter(
                fn ($m) => $m->tanggal !== null && $m->tanggal->toDateString() <= $sampai
            );
        }

        $saldo = 0.0;
        $mulai = null;

        // Opname menetapkan saldo, bukan menambahnya. Perhitungan karena
        // itu dimulai dari opname terakhir; tanpa itu, koreksi hasil
        // hitungan fisik akan tertimpa lagi oleh riwayat yang sudah
        // dikoreksinya.
        foreach ($mutasi as $m) {
            if ($m->jenis === 'opname' && $m->stok_fisik !== null) {
                $saldo = (float) $m->stok_fisik;
                $mulai = $m->id;
            }
        }

        $lewati = $mulai !== null;
        foreach ($mutasi as $m) {
            if ($lewati) {
                if ($m->id === $mulai) $lewati = false;
                continue;
            }
            if ($m->jenis === 'opname') continue;

            $saldo += in_array($m->jenis, self::MASUK, true)
                ? (float) $m->jumlah
                : -(float) $m->jumlah;
        }

        return round($saldo, 2);
    }

    /** aman · menipis · habis */
    public static function statusStok(GudangBarang $b): array
    {
        $stok = self::stok($b);
        $min  = (float) $b->stok_min;

        if ($stok <= 0)              return ['kode' => 'habis',   'nama' => 'Habis',   'nada' => 'merah'];
        if ($min > 0 && $stok <= $min) return ['kode' => 'menipis', 'nama' => 'Menipis', 'nada' => 'kuning'];

        return ['kode' => 'aman', 'nama' => 'Aman', 'nada' => 'hijau'];
    }

    /* ══════════════ kedaluwarsa ══════════════ */

    public const AMBANG_KEDALUWARSA_HARI = 90;

    /**
     * Batch yang sudah atau hampir kedaluwarsa, beserta sisa harinya.
     *
     * Hanya batch penerimaan yang punya tanggal kedaluwarsa yang dilihat,
     * dan hanya bila barangnya masih bersaldo.
     *
     * @return array<int,array{barang:GudangBarang,batch:?string,tanggal:Carbon,sisa:int}>
     */
    public static function kedaluwarsa(iterable $barang, int $ambang = self::AMBANG_KEDALUWARSA_HARI): array
    {
        $hari_ini = Waktu::kini()->startOfDay();
        $hasil = [];

        foreach ($barang as $b) {
            if (self::stok($b) <= 0) continue;

            foreach ($b->mutasi as $m) {
                if ($m->jenis !== 'masuk' || !$m->kadaluarsa) continue;

                $tgl  = Carbon::parse($m->kadaluarsa)->startOfDay();
                $sisa = (int) round($hari_ini->diffInDays($tgl, false));

                if ($sisa <= $ambang) {
                    $hasil[] = ['barang' => $b, 'batch' => $m->batch, 'tanggal' => $tgl, 'sisa' => $sisa];
                }
            }
        }

        usort($hasil, fn ($x, $y) => $x['sisa'] <=> $y['sisa']);

        return $hasil;
    }

    /** Masa pakai APD berakhir bila melewati bulan yang ditetapkan. */
    public static function apdJatuhTempo(GudangBarang $b, $sejak): ?int
    {
        if ($b->kategori !== 'apd' || !$b->masa_pakai_bulan || !$sejak) return null;

        $habis = Carbon::parse($sejak)->addMonths((int) $b->masa_pakai_bulan)->startOfDay();

        return (int) round(Waktu::kini()->startOfDay()->diffInDays($habis, false));
    }

    /* ══════════════ ringkasan ══════════════ */

    /**
     * Angka utama untuk dashboard.
     *
     * @param  iterable<GudangBarang>  $barang
     */
    public static function ringkas(iterable $barang): array
    {
        $jumlah = 0; $habis = 0; $menipis = 0;
        $perKategori = array_fill_keys(array_keys(self::KATEGORI), 0);
        $tanpaMsds = 0;

        foreach ($barang as $b) {
            $jumlah++;
            $perKategori[$b->kategori] = ($perKategori[$b->kategori] ?? 0) + 1;

            $s = self::statusStok($b)['kode'];
            if ($s === 'habis')   $habis++;
            if ($s === 'menipis') $menipis++;

            // B3 tanpa LDK adalah temuan tersendiri: petugas tidak punya
            // rujukan penanganan tumpahan maupun pertolongan pertama.
            if ($b->kategori === 'b3' && !$b->msds) $tanpaMsds++;
        }

        return [
            'jumlah'      => $jumlah,
            'habis'       => $habis,
            'menipis'     => $menipis,
            'aman'        => $jumlah - $habis - $menipis,
            'kategori'    => $perKategori,
            'tanpa_msds'  => $tanpaMsds,
        ];
    }

    public static function namaKategori(?string $k): string
    {
        return self::KATEGORI[$k]['nama'] ?? ucfirst((string) $k);
    }

    public static function nadaKategori(?string $k): string
    {
        return self::KATEGORI[$k]['nada'] ?? 'toska';
    }

    public static function namaKelas(?string $k): string
    {
        return self::KELAS_B3[$k]['nama'] ?? '—';
    }

    /** Nomor mutasi berurut per jenis dan bulan. */
    public static function nomorBerikut(string $jenis): string
    {
        $awalan = match ($jenis) {
            'masuk'  => 'GDM',
            'keluar' => 'GDK',
            'opname' => 'GDO',
            default  => 'GDR',
        };

        $kini = Waktu::kini();
        $urut = \App\Models\GudangMutasi::where('jenis', $jenis)
            ->whereYear('created_at', $kini->year)
            ->whereMonth('created_at', $kini->month)
            ->count() + 1;

        return sprintf('%s/%s/%04d', $awalan, $kini->format('Y-m'), $urut);
    }
}
