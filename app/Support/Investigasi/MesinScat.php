<?php

namespace App\Support\Investigasi;

use App\Models\Investigasi\Analisis;
use App\Models\Investigasi\Taksonomi;
use Illuminate\Support\Collection;

/**
 * Mesin saran SCAT — rantai tiga lapis dari yang terlihat ke yang sistemik.
 *
 * ── MASALAH YANG DIPECAHKAN ──
 *
 * Kamusnya 252 butir. Diberikan sebagai satu daftar panjang, yang
 * terjadi selalu sama: investigator memilih dua atau tiga butir dari
 * bagian 5 — tindakan tidak aman, yang memang paling mudah dilihat —
 * lalu berhenti. Berkasnya menyimpulkan "operator tidak mematuhi
 * prosedur", tindakan perbaikannya "sosialisasi ulang SOP", dan enam
 * bulan kemudian kejadian yang sama terulang di pit sebelah.
 *
 * Bukan karena investigatornya malas. Bagian 9 ada 86 butir di bawah
 * empat belas judul grup; menemukan yang relevan di sana menuntut orang
 * yang sudah hafal SCAT. Yang tidak hafal berhenti di lapis pertama,
 * dan tidak ada satu pun galat yang memberitahunya bahwa ia berhenti.
 *
 * ── YANG DIKERJAKAN MESIN INI ──
 *
 * Model ILCI berjalan mundur dari kerugian:
 *
 *   lapis 1  tindakan & kondisi tidak aman  (5.x, 6.x)  — yang terlihat
 *   lapis 2  faktor pribadi & pekerjaan     (7.x, 8.x)  — sebab dasar
 *   lapis 3  lack of control                (9.x)       — kegagalan sistem
 *
 * Investigator memilih lapis 1 sendiri: itu yang dilihat saksi, dan
 * tidak ada yang perlu ditebak. Dari pilihan itu mesin MENGUSULKAN grup
 * lapis 2 yang lazim menyertainya, lalu dari lapis 2 mengusulkan grup
 * lapis 3.
 *
 * ── MENGAPA MENGUSULKAN GRUP, BUKAN BUTIR ──
 *
 * Memetakan 33 butir lapis 1 ke 252 butir satu per satu berarti
 * mengarang ketelitian yang tidak dimiliki siapa pun: tidak ada dalam
 * SCAT yang menyatakan "5.13 selalu berarti 8.3.4". Yang memang ada
 * dalam bagan SCAT adalah kaitan antar-KELOMPOK, dan itulah yang
 * dipetakan di sini. Butir persisnya tetap dipilih orang.
 *
 * ── MENGAPA SARANNYA SELALU MENYEBUT ASALNYA ──
 *
 * Tiap usulan membawa `dari` — butir lapis sebelumnya yang
 * memunculkannya. Usulan tanpa alasan tidak dapat dinilai, dan yang
 * tidak dapat dinilai akan dicentang semua. Dengan alasannya tertulis,
 * investigator dapat menolaknya — dan penolakan itulah gunanya.
 *
 * Yang dipilih dari saran juga ditandai (`dari_saran`, `lapis_saran` di
 * inv_scat_pilihan), supaya pertanyaan "apakah mesinnya menolong atau
 * justru menyetir" kelak dapat dijawab dengan angka, bukan dengan
 * pendapat.
 *
 * ── AWAS PERBANDINGAN LONGGAR ──
 *
 * Kode grup di sini string: '9.1' dan '9.10' adalah dua grup berbeda.
 * PHP menganggap '9.1' == '9.10' bernilai benar karena keduanya dibaca
 * sebagai angka. Karena itu SELURUH pembandingan kode di berkas ini
 * memakai `in_array(..., true)`, `array_keys(..., true)`, dan `===`.
 * Satu saja yang longgar, grup Pengembangan Karyawan dan grup Operasi &
 * Pemeliharaan saling tertukar tanpa galat.
 */
final class MesinScat
{
    /** Batas lapis menurut nomor bagian kode. */
    public const LAPIS = [
        1 => ['5', '6'],
        2 => ['7', '8'],
        3 => ['9'],
    ];

    public const NAMA_LAPIS = [
        1 => 'Tindakan & Kondisi Tidak Aman',
        2 => 'Faktor Pribadi & Pekerjaan',
        3 => 'Lack of Control',
    ];

    public const URAIAN_LAPIS = [
        1 => 'Yang terlihat di lapangan — dipilih sendiri dari keterangan saksi dan bukti.',
        2 => 'Sebab dasar di balik yang terlihat. Diusulkan dari pilihan lapis 1.',
        3 => 'Kegagalan sistem yang membiarkannya. Diusulkan dari pilihan lapis 2.',
    ];

    /**
     * Lapis 1 → grup lapis 2 yang lazim menyertainya.
     *
     * Dibaca begini: "kalau yang terlihat X, sebab dasarnya biasanya ada
     * di antara grup-grup ini". Bukan daftar tertutup — investigator
     * tetap boleh memilih grup mana pun di luar usulan.
     *
     * Pola yang berulang dan disengaja: hampir semua butir lapis 6
     * (kondisi tidak aman) mengarah ke grup bagian 8 (faktor pekerjaan),
     * bukan ke bagian 7 (faktor pribadi). Kondisi tidak aman adalah
     * keadaan tempat kerja; menautkannya ke faktor pribadi berarti
     * menyalahkan orang atas ventilasi yang kurang.
     */
    public const PETA_DASAR = [
        // ── 5.x tindakan tidak aman ──
        '5.1'  => ['7.5', '7.7', '8.1', '8.6'],   // gagal mematuhi prosedur
        '5.2'  => ['7.5', '8.1', '8.6'],          // inspeksi area tidak memadai
        '5.3'  => ['7.5', '7.6', '8.1', '8.6'],   // inspeksi pra-operasi
        '5.4'  => ['7.5', '8.1', '8.6'],          // penilaian risiko
        '5.5'  => ['7.7', '8.1', '8.6'],          // gagal memulai tindakan korektif
        '5.6'  => ['7.5', '7.7', '8.1', '8.6'],   // mengoperasikan tanpa izin
        '5.7'  => ['7.5', '7.7', '8.1', '8.6'],   // gagal memperingatkan
        '5.8'  => ['7.5', '7.6', '7.7', '8.6'],   // gagal mengamankan
        '5.9'  => ['7.7', '8.1', '8.6'],          // kecepatan tidak seharusnya
        '5.10' => ['7.7', '8.1', '8.2', '8.6'],   // melepas perangkat keselamatan
        '5.11' => ['7.5', '8.4', '8.5', '8.7'],   // memakai alat cacat
        '5.12' => ['7.5', '7.6', '8.5', '8.6'],   // memakai alat tidak benar
        '5.13' => ['7.7', '8.1', '8.3', '8.6'],   // tidak memakai APD
        '5.14' => ['7.5', '7.6', '8.6'],          // loading tidak benar
        '5.15' => ['7.5', '7.6', '8.6'],          // penempatan tidak benar
        '5.16' => ['7.1', '7.5', '7.6', '8.6'],   // pengangkatan tidak benar
        '5.17' => ['7.1', '7.5', '8.2', '8.6'],   // posisi bekerja tidak benar
        '5.18' => ['7.7', '8.1'],                 // bersenda gurau
        '5.19' => ['7.3', '8.1'],                 // pengaruh alkohol/obat
        '5.20' => ['7.1', '7.3', '8.1'],          // tidak sehat dalam bekerja

        // ── 6.x kondisi tidak aman ──
        '6.1'  => ['8.2', '8.4', '8.5', '8.7'],   // penjagaan/penghalang
        '6.2'  => ['8.3', '8.5', '8.7'],          // alat pelindung tidak memadai
        '6.3'  => ['8.3', '8.4', '8.5', '8.7'],   // perkakas cacat
        '6.4'  => ['8.2', '8.6'],                 // kepadatan/pergerakan terbatas
        '6.5'  => ['8.2', '8.4', '8.5'],          // sistem peringatan
        '6.6'  => ['8.2', '8.4', '8.6'],          // bahaya kebakaran/ledakan
        '6.7'  => ['8.1', '8.6'],                 // kebersihan/kerapihan
        '6.8'  => ['8.2', '8.4', '8.6'],          // lingkungan berbahaya
        '6.9'  => ['8.2', '8.3'],                 // kebisingan
        '6.10' => ['8.2', '8.6'],                 // radiasi
        '6.11' => ['8.2', '8.6'],                 // temperatur ekstrim
        '6.12' => ['8.2', '8.4'],                 // pencahayaan
        '6.13' => ['8.2', '8.4'],                 // ventilasi
    ];

    /**
     * Grup lapis 2 → grup lapis 3.
     *
     * Ini bagian yang paling sering hilang dari investigasi, dan
     * paling menentukan. "Kurang pelatihan" (7.5) yang berhenti sebagai
     * sebab dasar melahirkan tindakan "beri pelatihan"; ditarik ke
     * lapis 3 ia menjadi Pengembangan Karyawan (9.1) — program
     * pelatihannya sendiri yang tidak menjangkau pekerjaan ini — dan
     * tindakannya berubah menjadi sesuatu yang mencegah pengulangan.
     *
     * ── DUA GRUP YANG SENGAJA TIDAK DIUSULKAN ──
     *
     * 9.6 Persiapan Keadaan Darurat dan 9.14 Lingkungan tidak muncul di
     * peta mana pun, dan itu bukan kelalaian.
     *
     * Keduanya bukan sebab KEJADIANNYA melainkan sebab BESARNYA
     * KERUGIAN. Rencana darurat yang tidak ada tidak membuat truknya
     * terguling; ia membuat korban terlambat dievakuasi. Dalam bagan
     * ILCI keduanya duduk di kotak Loss, bukan di rantai sebab, jadi
     * menariknya dari pilihan lapis 2 berarti menyatakan hubungan yang
     * tidak ada.
     *
     * Menambahkannya ke 8.1 sempat dicoba dan ditolak karena alasan
     * kedua: usulan dari 8.1 melonjak dari 29 butir menjadi 38 dari 86
     * — hampir separuh bagian 9 — dan usulan yang isinya hampir
     * segalanya tidak lagi menjadi usulan.
     *
     * Keduanya tetap dapat dipilih: layar analisis menyajikan seluruh
     * kamus per lapis di samping usulannya, dan usulan di sini
     * mempersempit, bukan membatasi.
     */
    public const PETA_KENDALI = [
        '7.1' => ['9.11', '9.12', '9.13'],  // kemampuan fisik  → kesehatan kerja, ergonomi, SDM
        '7.2' => ['9.1', '9.11', '9.13'],   // kemampuan mental → pengembangan, kesehatan kerja, SDM
        '7.3' => ['9.10', '9.11', '9.13'],  // tekanan fisik    → operasi, kesehatan kerja, SDM
        '7.4' => ['9.4', '9.11', '9.13'],   // tekanan mental   → komunikasi, kesehatan kerja, SDM
        '7.5' => ['9.1', '9.4', '9.7'],     // kurang pengetahuan
        '7.6' => ['9.1', '9.2', '9.7'],     // kurang keterampilan
        '7.7' => ['9.1', '9.2', '9.4'],     // salah motivasi
        '8.1' => ['9.1', '9.2', '9.4', '9.5'],  // kepemimpinan/pengawasan
        '8.2' => ['9.3', '9.9', '9.12'],    // rekayasa tidak memadai
        '8.3' => ['9.3', '9.8'],            // pembelian
        '8.4' => ['9.5', '9.10'],           // pemeliharaan
        '8.5' => ['9.5', '9.8', '9.10'],    // perkakas dan alat
        '8.6' => ['9.3', '9.5', '9.7'],     // standar kerja
        '8.7' => ['9.3', '9.5', '9.10'],    // aus dan sobek
        '8.8' => ['9.2', '9.5', '9.7'],     // penyalahgunaan
    ];

    /**
     * Lapis sebuah kode, dari nomor bagiannya.
     *
     * Nol berarti kodenya di luar tiga lapis — tidak terjadi pada kamus
     * yang terpasang, tetapi kamusnya boleh diganti berkasnya.
     */
    public static function lapis(string $kode): int
    {
        $bagian = explode('.', $kode)[0];

        foreach (self::LAPIS as $l => $bagianLapis) {
            if (in_array($bagian, $bagianLapis, true)) return $l;
        }

        return 0;
    }

    /**
     * Kunci grup sebuah kode.
     *
     * Bagian 5 dan 6 datar, jadi kodenya sendiri yang menjadi kuncinya
     * ('5.13'). Bagian 7–9 bergrup, jadi dua ruas pertama yang diambil
     * ('8.3.4' → '8.3'). Mengambil dua ruas pertama secara membabi buta
     * akan mengubah '5.13' menjadi '5.13' — kebetulan benar — tetapi
     * memotong tiga ruas menjadi dua pada kode yang cuma dua ruas
     * memerlukan penjagaan yang mudah salah, jadi keduanya dipisahkan.
     */
    public static function grup(string $kode): string
    {
        $ruas = explode('.', $kode);

        return count($ruas) >= 3 ? $ruas[0].'.'.$ruas[1] : $kode;
    }

    /**
     * Seluruh butir kamus SCAT, dikelompokkan per lapis.
     *
     * @return array<int, list<array<string,mixed>>>
     */
    public static function katalog(): array
    {
        $out = [1 => [], 2 => [], 3 => []];

        foreach (self::butir() as $t) {
            $l = self::lapis($t->kode);

            if (isset($out[$l])) $out[$l][] = self::baris($t);
        }

        return $out;
    }

    /**
     * Usulan lapis 2 dan lapis 3 bagi sebuah analisis.
     *
     * Yang SUDAH dipilih tidak diusulkan lagi — usulan yang mengulang
     * apa yang sudah ada di layar membuat daftarnya panjang tanpa
     * menambah apa pun, dan menutupi usulan yang benar-benar baru.
     *
     * Lapis 3 diusulkan dari pilihan lapis 2 yang SUDAH DIAMBIL, bukan
     * dari usulan lapis 2. Kalau diusulkan berantai dari usulan, satu
     * pilihan lapis 1 akan memunculkan hampir seluruh bagian 9 — dan
     * daftar yang isinya hampir segalanya sama tidak menolongnya dengan
     * daftar kosong.
     *
     * @return array{2: list<array<string,mixed>>, 3: list<array<string,mixed>>}
     */
    public static function saran(Analisis $analisis): array
    {
        $dipilih = self::dipilih($analisis);

        $kodeTerpilih = $dipilih->pluck('kode')->all();

        return [
            2 => self::usul($dipilih, 1, self::PETA_DASAR, $kodeTerpilih),
            3 => self::usul($dipilih, 2, self::PETA_KENDALI, $kodeTerpilih),
        ];
    }

    /**
     * Keadaan rantai: berapa butir per lapis, dan lapis mana yang kosong.
     *
     * `lengkap` menuntut ketiga lapis terisi. Itu memang lebih tinggi
     * daripada yang dipalang TahapInvestigasi — di sana satu pilihan
     * SCAT mana pun sudah cukup untuk maju. Bedanya disengaja: yang di
     * sini adalah mutu analisis yang DITUNJUKKAN kepada investigator,
     * bukan palang yang menahannya. Menahan berkas sampai lapis 3
     * terisi akan membuat orang mengisi lapis 3 asal-asalan supaya
     * tombolnya menyala, dan analisis asal-asalan lebih buruk daripada
     * analisis yang jujur berhenti di lapis 2.
     *
     * @return array<string,mixed>
     */
    public static function rantai(Analisis $analisis): array
    {
        $dipilih = self::dipilih($analisis);

        $per = [];
        $kurang = [];

        foreach ([1, 2, 3] as $l) {
            $isi = $dipilih->filter(fn ($t) => self::lapis($t->kode) === $l)->values();

            $per[] = [
                'lapis'  => $l,
                'nama'   => self::NAMA_LAPIS[$l],
                'uraian' => self::URAIAN_LAPIS[$l],
                'jumlah' => $isi->count(),
                'butir'  => $isi->map(fn ($t) => self::baris($t))->all(),
            ];

            if ($isi->isEmpty()) $kurang[] = $l;
        }

        return [
            'lapis'   => $per,
            'kurang'  => $kurang,
            'lengkap' => $kurang === [],

            /* Pesannya menyebut AKIBATNYA, bukan cuma "lapis 3 kosong".
               Investigator yang membaca "lapis 3 kosong" menganggapnya
               kolom opsional; yang membaca akibatnya tahu apa yang
               hilang dari berkasnya. */
            'pesan' => match (true) {
                $kurang === []        => null,
                in_array(1, $kurang, true) => 'Belum ada tindakan atau kondisi tidak aman yang dipilih — '
                    .'rantai penyebabnya belum punya titik awal.',
                in_array(2, $kurang, true) => 'Sebab dasar belum dipilih. Tanpa lapis ini, temuannya berhenti '
                    .'pada apa yang dilakukan orang di lokasi.',
                default => 'Lack of control belum dipilih. Tanpa lapis ini, tindakan perbaikannya hampir '
                    .'selalu berupa teguran atau sosialisasi ulang, dan kejadian yang sama terulang.',
            },
        ];
    }

    /* ---------------------------------------------------------------- */

    /** Butir taksonomi SCAT, urut sesuai kamus. */
    private static function butir(): Collection
    {
        return Taksonomi::where('metode', 'scat')->orderBy('urutan')->get();
    }

    /** Butir yang sudah dipilih pada analisis ini. */
    private static function dipilih(Analisis $analisis): Collection
    {
        return Taksonomi::whereIn(
            'id',
            $analisis->pilihan()->pluck('taksonomi_id')
        )->orderBy('urutan')->get();
    }

    /**
     * Usulan satu lapis.
     *
     * @param  Collection  $dipilih       butir yang sudah dipilih
     * @param  int         $dariLapis     lapis pemicunya
     * @param  array       $peta          peta kode/grup pemicu → grup tujuan
     * @param  list<string> $kodeTerpilih kode yang tidak boleh diusulkan lagi
     * @return list<array<string,mixed>>
     */
    private static function usul(Collection $dipilih, int $dariLapis, array $peta, array $kodeTerpilih): array
    {
        /* Grup tujuan → daftar butir pemicu. Satu grup dapat dipicu
           beberapa pilihan sekaligus, dan menyebut SEMUANYA lebih
           berguna daripada menyebut yang pertama: tiga pemicu berbeda
           yang menunjuk grup yang sama adalah alasan kuat untuk
           memeriksanya. */
        $alasan = [];

        foreach ($dipilih as $t) {
            if (self::lapis($t->kode) !== $dariLapis) continue;

            $kunci = $dariLapis === 1 ? $t->kode : self::grup($t->kode);

            foreach ($peta[$kunci] ?? [] as $tujuan) {
                $alasan[$tujuan][] = $t->kode.' '.$t->label;
            }
        }

        if ($alasan === []) return [];

        $out = [];

        foreach (self::butir() as $t) {
            $grup = self::grup($t->kode);

            /* `array_key_exists` dan bukan `isset` — dan kuncinya string.
               Kunci larik PHP yang berbentuk angka desimal seperti '9.1'
               TIDAK diubah menjadi integer (hanya bilangan bulat yang
               diubah), jadi '9.1' dan '9.10' tetap dua kunci berbeda. */
            if (! array_key_exists($grup, $alasan)) continue;
            if (in_array($t->kode, $kodeTerpilih, true)) continue;

            $out[] = self::baris($t) + [
                'lapis' => self::lapis($t->kode),
                'dari'  => $alasan[$grup],
            ];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private static function baris(Taksonomi $t): array
    {
        return [
            'id'       => $t->id,
            'kode'     => $t->kode,
            'label'    => $t->label,
            'kategori' => $t->kategori,
            'grup'     => $t->definisi,
        ];
    }
}
