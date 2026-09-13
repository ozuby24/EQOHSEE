<?php

namespace App\Support;

use App\Models\Company;
use App\Models\MineMapLayer;
use App\Models\User;
use App\Models\WaterLog;
use Illuminate\Support\Facades\Schema;

/**
 * Keadaan situs: di mana, dan sedang bagaimana cuacanya.
 *
 * Dipasang pada sampul halaman awal tiap modul. Yang dijawabnya bukan
 * hiasan: pembaca laporan tambang selalu menanyakan dua hal yang sama
 * lebih dulu — ini situs mana, dan hari itu hujan atau tidak. Angka
 * produksi yang turun pada hari hujan lebat berarti lain daripada angka
 * yang sama pada hari cerah.
 *
 * Karena itu cuacanya DIBACA DARI DATA YANG SUDAH DICATAT SENDIRI —
 * kolom `curah_hujan_mm` pada catatan harian kolam — bukan dari layanan
 * ramalan di luar. Dua alasannya:
 *
 *  1. Yang dipertanggungjawabkan pada laporan adalah hujan yang terukur
 *     di situs, bukan hujan menurut stasiun kota terdekat yang bisa
 *     berpuluh kilometer jauhnya dan di balik bukit.
 *  2. Situs tambang sering tanpa jalan keluar ke internet. Sampul yang
 *     menunggu jawaban layanan luar akan menggantung pada tiap halaman.
 *
 * Konsekuensinya harus diterima jujur: bila belum ada catatan, kondisinya
 * `null` dan sampulnya diam. Menuliskan "Cerah" untuk hari yang tidak
 * seorang pun mengukurnya adalah mengarang — dan mengarang di tempat yang
 * terlihat seperti bacaan alat jauh lebih buruk daripada diam.
 */
final class KondisiSitus
{
    /**
     * Ambang curah hujan harian menurut klasifikasi BMKG, milimeter.
     *
     * Ditulis sebagai ambang BAWAH tiap kelas, urut dari yang terberat,
     * supaya pencocokannya berhenti pada yang pertama cocok.
     */
    private const KELAS_HUJAN = [
        ['min' => 100.0, 'kunci' => 'hujan_sangat_lebat', 'label' => 'Hujan Sangat Lebat'],
        ['min' => 50.0,  'kunci' => 'hujan_lebat',        'label' => 'Hujan Lebat'],
        ['min' => 20.0,  'kunci' => 'hujan_sedang',       'label' => 'Hujan Sedang'],
        ['min' => 0.5,   'kunci' => 'hujan_ringan',       'label' => 'Hujan Ringan'],
        ['min' => 0.0,   'kunci' => 'cerah',              'label' => 'Cerah'],
    ];

    /**
     * Berapa hari ke belakang sebuah catatan masih dianggap menggambarkan
     * keadaan sekarang.
     *
     * Catatan minggu lalu bukan cuaca hari ini. Menampilkannya tanpa batas
     * membuat sampul menyebut "Hujan Lebat" pada hari yang terik, dan
     * kesalahan itu justru paling mungkin terjadi pada situs yang
     * pencatatannya paling jarang.
     */
    private const UMUR_MAKSIMAL_HARI = 2;

    /**
     * Seluruh keterangan situs untuk sampul, atau null bila tidak ada
     * satu pun yang dapat disebut.
     */
    public static function untuk(?User $pengguna): ?array
    {
        $perusahaan = $pengguna?->company;

        $lokasi = self::lokasi($perusahaan);
        $cuaca  = self::cuaca($perusahaan);

        if ($lokasi === null && $cuaca === null) return null;

        return [
            'lokasi' => $lokasi,
            'cuaca'  => $cuaca,
            'waktu'  => [
                'jam'  => Waktu::kini()->format('H:i'),
                'zona' => Waktu::singkatan(),
                'tanggal' => Waktu::kini()->translatedFormat('j F Y'),
            ],
        ];
    }

    /**
     * Nama tempat beserta koordinatnya bila ada.
     *
     * Koordinat diambil dari titik tengah layer peta tambang yang sudah
     * digambar — bukan diketik terpisah. Koordinat yang diketik terpisah
     * akan berselisih dengan petanya sendiri cepat atau lambat, dan yang
     * salah adalah yang tampil sebagai geo tag di halaman depan.
     */
    public static function lokasi(?Company $perusahaan): ?array
    {
        if (!$perusahaan) return null;

        $nama = trim((string) ($perusahaan->location ?: $perusahaan->address));

        $titik = self::titikPeta($perusahaan);

        if ($nama === '' && $titik === null) return null;

        return [
            'nama'       => $nama !== '' ? $nama : null,
            'perusahaan' => $perusahaan->name,
            'lat'        => $titik['lat'] ?? null,
            'lon'        => $titik['lon'] ?? null,
            'koordinat'  => $titik !== null
                ? self::derajat($titik['lat'], 'LU', 'LS').' '.self::derajat($titik['lon'], 'BT', 'BB')
                : null,
        ];
    }

    /**
     * Kondisi cuaca dari catatan hujan terakhir, atau null bila tidak ada
     * catatan yang cukup baru.
     */
    public static function cuaca(?Company $perusahaan): ?array
    {
        // Bilah sampul tergambar juga pada pemasangan yang tabelnya belum
        // dimigrasikan; tabel yang belum ada tidak boleh merobohkan
        // halaman hanya karena sebuah keterangan cuaca.
        if (!Schema::hasTable('water_logs')) return null;

        $batas = Waktu::kini()->copy()->startOfDay()->subDays(self::UMUR_MAKSIMAL_HARI);

        $catatan = WaterLog::query()
            ->when($perusahaan, fn ($q) => $q->where('company_id', $perusahaan->id))
            ->whereNotNull('curah_hujan_mm')
            ->whereDate('tanggal', '>=', $batas->toDateString())
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->first(['tanggal', 'curah_hujan_mm']);

        if (!$catatan) return null;

        $mm    = (float) $catatan->curah_hujan_mm;
        $kelas = self::kelasHujan($mm);

        return [
            'kunci'   => $kelas['kunci'],
            'label'   => $kelas['label'],
            'hujanMm' => round($mm, 1),

            /* Kedudukan pada skala BMKG, untuk meter intensitas di
               sampul. Angka 34 mm tidak berarti apa-apa bagi pembaca
               yang tidak hafal ambangnya; meter yang menunjukkan "tiga
               dari lima" menjawabnya tanpa perlu dihafal. */
            'tingkat' => self::tingkat($kelas['kunci']),
            'skala'   => count(self::KELAS_HUJAN),

            /* Tanggal catatannya ikut disebut. Sampul yang hanya menulis
               "Hujan Lebat" tidak dapat dibedakan antara hujan pagi tadi
               dan hujan kemarin — dan keduanya menuntut keputusan yang
               berbeda di lapangan. */
            'tanggal' => $catatan->tanggal?->translatedFormat('j M'),
            'hariIni' => $catatan->tanggal?->isSameDay(Waktu::kini()) ?? false,
        ];
    }

    /** Kelas hujan menurut ambang BMKG. */
    public static function kelasHujan(float $mm): array
    {
        foreach (self::KELAS_HUJAN as $kelas) {
            if ($mm >= $kelas['min']) return $kelas;
        }

        // Curah hujan negatif tidak ada artinya; diperlakukan seperti nol.
        return self::KELAS_HUJAN[array_key_last(self::KELAS_HUJAN)];
    }

    /**
     * Skala kelas hujan dari yang teringan ke yang terberat.
     *
     * Dipakai menggambar meter intensitas pada sampul. Dibaca dari
     * tetapan yang sama dengan penggolongannya, bukan ditulis ulang di
     * sisi peramban: skala yang disalin ke sana akan tetap menunjuk lima
     * kotak yang sama setelah ambangnya diubah, dan meter yang
     * menunjukkan tingkat yang salah lebih buruk daripada tidak ada
     * meter sama sekali.
     *
     * @return list<array{kunci:string,label:string,min:float}>
     */
    public static function skala(): array
    {
        return array_reverse(self::KELAS_HUJAN);
    }

    /** Urutan sebuah kelas pada skala, 0 untuk yang teringan. */
    public static function tingkat(string $kunci): int
    {
        foreach (self::skala() as $i => $kelas) {
            if ($kelas['kunci'] === $kunci) return $i;
        }

        return 0;
    }

    /** Titik tengah area tambang dari layer peta yang sudah digambar. */
    private static function titikPeta(Company $perusahaan): ?array
    {
        if (!Schema::hasTable('mine_map_layers')) return null;

        $layer = MineMapLayer::query()
            ->where('company_id', $perusahaan->id)
            ->whereNotNull('titik_lat')->whereNotNull('titik_lon')
            ->orderByDesc('luas_ha')
            ->first(['titik_lat', 'titik_lon']);

        if (!$layer) return null;

        return ['lat' => (float) $layer->titik_lat, 'lon' => (float) $layer->titik_lon];
    }

    /**
     * Koordinat desimal menjadi derajat-menit-detik berarah.
     *
     * Bentuk yang dipakai peta tambang dan laporan resmi; desimal
     * mentah terbaca sebagai angka basis data, bukan sebagai tempat.
     */
    private static function derajat(float $nilai, string $positif, string $negatif): string
    {
        $arah = $nilai >= 0 ? $positif : $negatif;
        $abs  = abs($nilai);

        $d = (int) floor($abs);
        $m = (int) floor(($abs - $d) * 60);
        $s = ($abs - $d - $m / 60) * 3600;

        return sprintf('%d°%02d\'%04.1f" %s', $d, $m, $s, $arah);
    }
}
