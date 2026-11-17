<?php

namespace App\Support;

use App\Models\{Company, EnergyBaseline, EnergyFuelLog, EnergyProduction, GudangBarang,
                HazardReport, Inspection, PostTrainingEvaluation, SmkpAudit,
                SopEvaluationAttempt, TpkkpAssessment};

/**
 * Pemeriksaan kesesuaian isi, penilaian, dan evaluasi tiap modul.
 *
 * Bedanya dengan Diagnosa: Diagnosa memeriksa SISTEMNYA — kunci aplikasi,
 * migrasi, tautan storage. Berkas ini memeriksa ANGKANYA. Sebuah sistem
 * dapat sehat sempurna sementara skor kematangan yang dipajangnya keliru,
 * dan kekeliruan semacam itu tidak pernah memunculkan galat: ia hanya
 * memunculkan angka. Angka yang salah terlihat persis seperti angka yang
 * benar.
 *
 * Tiga hal yang diperiksa tiap modul, dan urutannya disengaja:
 *
 * 1. ISI — apakah modulnya berisi. Modul kosong TIDAK dinyatakan aman,
 *    melainkan "belum dapat dinilai". Halaman kosong tidak pernah salah,
 *    dan justru karena itu ia tidak membuktikan apa pun. Menyatakannya
 *    hijau adalah cara tercepat membuat orang percaya pada pemeriksaan
 *    yang sebenarnya belum memeriksa apa-apa.
 *
 * 2. RENTANG — apakah angkanya masih mungkin. Persentase di luar 0..100,
 *    jumlah benar melebihi jumlah soal, stok di bawah nol: semuanya
 *    mustahil secara fisik maupun aritmetika, jadi kemunculannya berarti
 *    ada yang rusak di hulu.
 *
 * 3. KONSISTENSI — apakah angka jadi masih cocok dengan bagian-bagiannya
 *    bila dihitung ulang di sini, dengan jalur yang berbeda dari yang
 *    dipakai halamannya. Inilah pemeriksaan yang paling banyak menangkap:
 *    total yang tidak lagi sama dengan jumlah rinciannya adalah gejala
 *    khas pembobotan yang berubah di satu tempat dan tidak di tempat lain.
 *
 * Yang sengaja TIDAK dilakukan: menebak. Modul yang tidak dapat diperiksa
 * dijawab "belum dapat dinilai", bukan "aman" — sama seperti Diagnosa.
 */
final class Kesesuaian
{
    /* Keadaan memakai kosakata yang sama dengan Diagnosa, supaya keduanya
       dapat digambar oleh komponen yang sama dan dibaca dengan kebiasaan
       yang sama. */
    public const AMAN      = Diagnosa::AMAN;
    public const PERHATIAN = Diagnosa::PERHATIAN;
    public const GAWAT     = Diagnosa::GAWAT;
    public const TAK_TAHU  = Diagnosa::TAK_TAHU;

    private const BOBOT = [self::GAWAT => 0, self::PERHATIAN => 1, self::TAK_TAHU => 2, self::AMAN => 3];

    /** Selisih pembulatan yang masih wajar pada penjumlahan pecahan. */
    private const TOLERANSI = 0.01;

    /**
     * Jalankan seluruh pemeriksaan.
     *
     * Tiap modul dibungkus penangkap galatnya sendiri. Satu modul yang
     * meledak — kolom yang belum ada, acuan JSON yang rusak — tidak boleh
     * mematikan halaman yang justru dibuka untuk mencari tahu apa yang
     * rusak.
     *
     * @return list<array<string,mixed>>
     */
    public static function jalankan(?Company $c = null): array
    {
        $hasil = [];

        foreach (self::daftar() as $kode => $periksa) {
            try {
                foreach ($periksa($c) as $baris) {
                    $hasil[] = ['kode' => $kode] + $baris;
                }
            } catch (\Throwable $e) {
                $hasil[] = [
                    'kode'     => $kode,
                    'kelompok' => $kode,
                    'judul'    => 'Pemeriksaan gagal berjalan',
                    'keadaan'  => self::TAK_TAHU,
                    'nilai'    => 'gagal',
                    'uraian'   => 'Pemeriksaannya sendiri berhenti: '.$e->getMessage(),
                    'tindakan' => 'Laporkan pesan di atas. Pemeriksaan yang tidak dapat berjalan '
                        .'menyembunyikan keadaan yang seharusnya dilaporkannya.',
                ];
            }
        }

        usort($hasil, fn ($a, $b) => [self::BOBOT[$a['keadaan']], $a['kelompok']]
                                 <=> [self::BOBOT[$b['keadaan']], $b['kelompok']]);

        return $hasil;
    }

    /** @return array<string,int> hitungan per keadaan */
    public static function ringkas(array $hasil): array
    {
        $r = [self::GAWAT => 0, self::PERHATIAN => 0, self::TAK_TAHU => 0, self::AMAN => 0];

        foreach ($hasil as $h) $r[$h['keadaan']]++;

        return $r;
    }

    /** @return array<string,callable> */
    private static function daftar(): array
    {
        return [
            'tpkkp'    => fn (?Company $c) => self::tpkkp($c),
            'smkp'     => fn (?Company $c) => self::smkp($c),
            'energi'   => fn (?Company $c) => self::energi($c),
            'gudang'   => fn (?Company $c) => self::gudang($c),
            'hse'      => fn (?Company $c) => self::hse($c),
            'evaluasi' => fn (?Company $c) => self::evaluasi($c),
            'temuan'   => fn (?Company $c) => self::temuan($c),
        ];
    }

    /* ═══════════ temuan lintas modul ═══════════ */

    /**
     * Temuan yang tidak sedang ditangani siapa pun.
     *
     * Diperiksa di sini, bukan hanya ditampilkan di registernya, karena
     * inilah satu-satunya keadaan di seluruh aplikasi yang benar-benar
     * tidak punya gejala. Temuan tanpa penanggung jawab dan tanpa tenggat
     * tidak pernah lewat waktu — ia tidak punya waktu untuk dilewati —
     * sehingga tidak pernah menyalakan peringatan apa pun, di modul mana
     * pun, selamanya.
     */
    private static function temuan(?Company $c): array
    {
        $r = Temuan::ringkas(Temuan::semua($c));

        if ($r['semua'] === 0) {
            return [self::kosong('Temuan lintas modul', 'temuan',
                'Muat data contoh atau catat satu temuan, lalu periksa lagi.')];
        }

        $baris = [];

        $baris[] = [
            'kelompok' => 'Temuan lintas modul',
            'judul'    => 'Setiap temuan terbuka punya penanggung jawab dan tenggat',
            'keadaan'  => $r['takBertuan'] === 0 ? self::AMAN : self::PERHATIAN,
            'nilai'    => $r['takBertuan'].' dari '.$r['terbuka'].' temuan terbuka',
            'uraian'   => $r['takBertuan'] === 0
                ? 'Seluruh temuan terbuka sudah bertuan dan bertenggat.'
                : 'Ada temuan terbuka tanpa penanggung jawab maupun tenggat. Temuan semacam '
                  .'itu tidak pernah terhitung terlambat — ia tidak punya tanggal untuk '
                  .'dilewati — sehingga tidak akan pernah menyalakan peringatan apa pun.',
            'tindakan' => $r['takBertuan'] === 0 ? 'Tidak ada.'
                : 'Buka Register Temuan, saring "Tanpa penanggung jawab", lalu tetapkan '
                  .'pemilik dan tenggatnya.',
        ];

        if ($r['terlambat'] > 0) {
            $baris[] = [
                'kelompok' => 'Temuan lintas modul',
                'judul'    => 'Ada temuan yang lewat tenggat',
                'keadaan'  => self::GAWAT,
                'nilai'    => $r['terlambat'].' dari '.$r['terbuka'].' temuan terbuka',
                'uraian'   => 'Temuan ini sudah dijanjikan selesai pada tanggal yang telah lewat.',
                'tindakan' => 'Buka Register Temuan, saring "Lewat tenggat", lalu tagih '
                    .'penanggung jawabnya atau sepakati tenggat baru.',
            ];
        }

        return $baris;
    }

    /* ═══════════ TPKKP — penilaian kematangan ═══════════ */

    private static function tpkkp(?Company $c): array
    {
        /* Nilainya ada di TpkkpAssessment.scores, BUKAN di jawaban kuesioner.
           Keduanya mudah tertukar karena sama-sama bernama "nilai" dalam
           percakapan sehari-hari: kuesioner adalah masukan dari responden,
           sedangkan penilaian adalah skor yang diberikan asesor. Membaca yang
           keliru menghasilkan "0 dari 308 sel" pada penilaian yang sebenarnya
           sudah terisi penuh. */
        $nilai = self::nilaiTpkkp($c);

        if ($nilai === []) {
            return [self::kosong('Kematangan (PTPKKP)', 'penilaian',
                'Muat data contoh atau isi penilaian kematangan, lalu periksa lagi.')];
        }

        $skor  = Tpkkp::totalCalc($nilai);
        $baris = [];

        /* Total harus sama dengan jumlah skor indikatornya. Keduanya
           dihitung lewat jalur yang berbeda; kalau berbeda hasilnya,
           salah satu pembobotan sudah bergeser. */
        $jumlahIndikator = 0.0;
        foreach ($skor['indicators'] as $i) {
            if ($i['score'] !== null) $jumlahIndikator += $i['score'];
        }
        $selisih = abs(($skor['score'] ?? 0.0) - $jumlahIndikator);

        $baris[] = [
            'kelompok' => 'Kematangan (PTPKKP)',
            'judul'    => 'Total sama dengan jumlah indikatornya',
            'keadaan'  => $selisih <= self::TOLERANSI ? self::AMAN : self::GAWAT,
            'nilai'    => self::angka($skor['score']).' vs '.self::angka($jumlahIndikator),
            'uraian'   => $selisih <= self::TOLERANSI
                ? 'Skor total cocok dengan penjumlahan ulang seluruh indikator.'
                : 'Skor total berbeda '.self::angka($selisih).' dari jumlah indikatornya.',
            'tindakan' => $selisih <= self::TOLERANSI
                ? 'Tidak ada.'
                : 'Periksa bobot indikator di berkas acuan PTPKKP — total dan rincian '
                  .'dihitung dari sumber yang sama, jadi selisih berarti salah satunya '
                  .'sudah tidak membaca bobot yang sama.',
        ];

        /* Kelengkapan adalah rasio, jadi ia mustahil di luar 0..1.
           Tetapi berada di dalam rentang saja belum cukup untuk hijau:
           nol pun berada di dalam rentang. Penilaian yang belum terisi
           menghasilkan skor nol yang terlihat persis seperti kematangan
           yang benar-benar rendah — dan menyatakannya "aman" adalah cara
           tercepat membuat orang percaya pada angka yang belum ada. */
        $lengkap = (float) ($skor['completeness'] ?? 0);
        $masukAkal = $lengkap >= 0 && $lengkap <= 1;

        $keadaan = match (true) {
            !$masukAkal      => self::GAWAT,
            $lengkap <= 0.0  => self::TAK_TAHU,
            $lengkap < 0.5   => self::PERHATIAN,
            default          => self::AMAN,
        };

        $baris[] = [
            'kelompok' => 'Kematangan (PTPKKP)',
            'judul'    => 'Kelengkapan pengisian cukup untuk dibaca',
            'keadaan'  => $keadaan,
            'nilai'    => self::persen($lengkap * 100).' · '
                          .$skor['filledCells'].'/'.$skor['totalCells'].' sel',
            'uraian'   => match ($keadaan) {
                self::GAWAT     => 'Rasio kelengkapan di luar 0–100%, yang secara aritmetika mustahil.',
                self::TAK_TAHU  => 'Penilaiannya ada tetapi belum satu sel pun terisi, sehingga skor '
                                   .'yang tampil bukan hasil penilaian melainkan nol bawaan.',
                self::PERHATIAN => 'Baru '.self::persen($lengkap * 100).' sel terisi. Sel yang belum '
                                   .'dinilai terhitung nol, bukan diabaikan, sehingga skornya tertarik '
                                   .'ke bawah oleh pengisian yang belum selesai.',
                default         => 'Sel terisi tidak melebihi sel yang tersedia, dan pengisiannya sudah '
                                   .'cukup banyak untuk dibaca sebagai capaian.',
            },
            'tindakan' => match ($keadaan) {
                self::GAWAT    => 'Periksa Tpkkp::itemCalc — sel terisi terhitung melebihi sel yang ada.',
                self::TAK_TAHU => 'Isi penilaian kematangan, atau muat data contoh.',
                self::PERHATIAN=> 'Selesaikan pengisiannya sebelum tingkat kematangannya dipakai '
                                  .'mengambil keputusan.',
                default        => 'Tidak ada.',
            },
        ];

        return $baris;
    }

    /**
     * Skor penilaian terbaru dalam bentuk yang diminta Tpkkp::totalCalc.
     *
     * Tabelnya belum berperusahaan, jadi penyaringnya diabaikan di sini
     * dan yang dibaca selalu penilaian tahun terakhir. Menyaringnya
     * seolah-olah per perusahaan akan menghasilkan "kosong" yang keliru.
     */
    private static function nilaiTpkkp(?Company $c): array
    {
        $a = TpkkpAssessment::withoutGlobalScopes()->orderByDesc('tahun')->first();

        return $a && is_array($a->scores) ? $a->scores : [];
    }

    /* ═══════════ SMKP — audit ═══════════ */

    private static function smkp(?Company $c): array
    {
        $q = SmkpAudit::withoutGlobalScopes();
        if ($c) $q->where('company_id', $c->id);
        $audit = $q->latest('tahun')->first();

        if (!$audit) {
            return [self::kosong('Audit SMKP', 'periode audit',
                'Buat satu periode audit, isi penilaiannya, lalu periksa lagi.')];
        }

        $rekap = Smkp::rekap((array) $audit->hasil);
        $baris = [];

        /* Nilai tidak mungkin melebihi maksimum yang tersedia. */
        $wajar = $rekap['maks'] <= 0 || $rekap['nilai'] <= $rekap['maks'] + self::TOLERANSI;
        $baris[] = [
            'kelompok' => 'Audit SMKP',
            'judul'    => 'Nilai tidak melampaui nilai maksimum',
            'keadaan'  => $wajar ? self::AMAN : self::GAWAT,
            'nilai'    => self::angka($rekap['nilai']).' / '.self::angka($rekap['maks']),
            'uraian'   => $wajar
                ? 'Nilai perolehan berada di dalam batas maksimum elemen yang berlaku.'
                : 'Nilai perolehan melampaui maksimumnya — mustahil bila pembobotannya benar.',
            'tindakan' => $wajar ? 'Tidak ada.'
                : 'Periksa Smkp::rekapSub — kemungkinan satu butir dihitung dua kali, '
                  .'atau sub-elemen berinci ikut dinilai sekaligus lewat sub-subnya.',
        ];

        /* Skor akhir adalah persentase. */
        $skor = (float) $rekap['skor'];
        $baris[] = [
            'kelompok' => 'Audit SMKP',
            'judul'    => 'Skor akhir dalam rentang 0–100',
            'keadaan'  => ($skor >= 0 && $skor <= 100) ? self::AMAN : self::GAWAT,
            'nilai'    => self::persen($skor).' · '.($rekap['tingkat']['label'] ?? '—'),
            'uraian'   => ($skor >= 0 && $skor <= 100)
                ? 'Skor akhir berada dalam rentang persentase yang sah.'
                : 'Skor akhir di luar 0–100%.',
            'tindakan' => ($skor >= 0 && $skor <= 100) ? 'Tidak ada.'
                : 'Periksa pembagi bobot terpakai pada Smkp::rekap.',
        ];

        /* Yang dinilai tidak mungkin lebih banyak daripada yang berlaku,
           dan yang berlaku tidak mungkin lebih banyak daripada seluruhnya. */
        $urut = $rekap['dinilai'] <= $rekap['berlaku'] && $rekap['berlaku'] <= $rekap['total'];
        $baris[] = [
            'kelompok' => 'Audit SMKP',
            'judul'    => 'Butir dinilai ≤ berlaku ≤ seluruhnya',
            'keadaan'  => $urut ? self::AMAN : self::GAWAT,
            'nilai'    => $rekap['dinilai'].' ≤ '.$rekap['berlaku'].' ≤ '.$rekap['total'],
            'uraian'   => $urut
                ? 'Jumlah butir dinilai, berlaku, dan seluruhnya berurutan sebagaimana mestinya.'
                : 'Urutan jumlah butir tidak masuk akal — butir dikecualikan tetapi tetap ikut dinilai.',
            'tindakan' => $urut ? 'Tidak ada.'
                : 'Periksa Smkp::dikecualikan: butir yang ditandai tidak berlaku masih terhitung.',
        ];

        /* Skor rendah karena BELUM DIISI terlihat persis seperti skor rendah
           karena kinerja buruk — keduanya angka kecil dengan label merah yang
           sama. Bedanya menentukan: yang satu menuntut perbaikan lapangan,
           yang lain hanya menuntut penyelesaian pengisian. Karena itu skor
           SMKP tidak boleh dibaca sebagai capaian sebelum pengisiannya cukup. */
        $lengkap = $rekap['berlaku'] > 0 ? $rekap['dinilai'] / $rekap['berlaku'] : 0.0;

        if ($lengkap < 0.8) {
            $baris[] = [
                'kelompok' => 'Audit SMKP',
                'judul'    => 'Skor belum layak dibaca sebagai capaian',
                'keadaan'  => self::PERHATIAN,
                'nilai'    => $rekap['dinilai'].'/'.$rekap['berlaku'].' butir · '
                              .self::persen($lengkap * 100).' terisi',
                'uraian'   => 'Audit tahun '.$audit->tahun.' baru terisi '
                    .self::persen($lengkap * 100).', sehingga skor '.self::persen($skor)
                    .' ("'.($rekap['tingkat']['label'] ?? '—').'") lebih menggambarkan '
                    .'pengisian yang belum selesai daripada kinerja yang sesungguhnya. '
                    .'Butir yang belum dinilai terhitung nol, bukan diabaikan.',
                'tindakan' => 'Selesaikan penilaian butirnya, atau muat data contoh. '
                    .'Selama di bawah 80%, jangan mengambil keputusan dari tingkat yang tertera.',
            ];
        }

        return $baris;
    }

    /* ═══════════ Energi ═══════════ */

    private static function energi(?Company $c): array
    {
        $bahan = self::hitung(EnergyFuelLog::class, $c);
        $prod  = self::hitung(EnergyProduction::class, $c);

        if ($bahan === 0 && $prod === 0) {
            return [self::kosong('Kinerja Energi', 'catatan harian',
                'Muat data contoh atau catat pemakaian harian, lalu periksa lagi.')];
        }

        $baris = [];

        /* Intensitas dihitung ulang di sini dari sumber mentahnya, lalu
           dibandingkan dengan rumus yang dipakai halaman. Keduanya harus
           sampai pada angka yang sama. */
        $liter = (float) self::kueri(EnergyFuelLog::class, $c)->sum('liter');
        $ton   = (float) self::kueri(EnergyProduction::class, $c)->sum('ton');
        $gj    = Energi::literKeGj($liter);

        $ulang  = $ton > 0 ? $gj / $ton : 0.0;
        $rumus  = Energi::intensitas($gj, $ton);
        $cocok  = abs($ulang - $rumus) <= self::TOLERANSI;

        $baris[] = [
            'kelompok' => 'Kinerja Energi',
            'judul'    => 'Intensitas cocok saat dihitung ulang',
            'keadaan'  => $cocok ? self::AMAN : self::GAWAT,
            'nilai'    => self::angka($rumus, 4).' GJ/ton',
            'uraian'   => $cocok
                ? 'Gigajoule dibagi ton produksi menghasilkan angka yang sama dengan '
                  .'yang dipakai halaman.'
                : 'Intensitas berbeda '.self::angka(abs($ulang - $rumus), 4)
                  .' dari hitungan ulangnya.',
            'tindakan' => $cocok ? 'Tidak ada.'
                : 'Periksa Energi::intensitas dan faktor GJ per liter.',
        ];

        /* Produksi nol dengan bahan bakar terpakai membuat SELURUH rasio
           per ton menjadi nol — halaman terisi, indikatornya kosong. */
        if ($liter > 0 && $ton <= 0) {
            $baris[] = [
                'kelompok' => 'Kinerja Energi',
                'judul'    => 'Ada pemakaian tetapi produksi nol',
                'keadaan'  => self::PERHATIAN,
                'nilai'    => self::angka($liter).' L · 0 ton',
                'uraian'   => 'Seluruh rasio per ton — intensitas, L/ton, kWh/ton — akan '
                    .'bernilai nol, dan itu terbaca seperti hitungan yang rusak padahal '
                    .'penyebabnya data produksi yang belum masuk.',
                'tindakan' => 'Isi catatan produksi harian pada rentang yang sama dengan '
                    .'catatan bahan bakarnya.',
            ];
        }

        /* Sasaran energi berarti turun. Baseline dengan target lebih boros
           membuat seluruh grafik kemajuan terbalik arah. */
        $bl = self::kueri(EnergyBaseline::class, $c)->orderByDesc('tahun')->first();
        if ($bl) {
            $turun = (float) $bl->target_gj_ton <= (float) $bl->baseline_gj_ton;
            $baris[] = [
                'kelompok' => 'Kinerja Energi',
                'judul'    => 'Target baseline lebih rendah daripada baselinenya',
                'keadaan'  => $turun ? self::AMAN : self::GAWAT,
                'nilai'    => self::angka($bl->baseline_gj_ton, 3).' → '
                              .self::angka($bl->target_gj_ton, 3),
                'uraian'   => $turun
                    ? 'Targetnya menurun terhadap garis dasar, sebagaimana mestinya.'
                    : 'Targetnya lebih boros daripada garis dasarnya, sehingga kemajuan '
                      .'terhitung terbalik.',
                'tindakan' => $turun ? 'Tidak ada.'
                    : 'Betulkan target tahun '.$bl->tahun.' pada halaman Baseline & Target.',
            ];
        }

        return $baris;
    }

    /* ═══════════ Gudang ═══════════ */

    private static function gudang(?Company $c): array
    {
        $barang = self::kueri(GudangBarang::class, $c)->with('mutasi')->get();

        if ($barang->isEmpty()) {
            return [self::kosong('Gudang', 'barang',
                'Muat data contoh atau daftarkan barang, lalu periksa lagi.')];
        }

        /* Stok fisik tidak dapat kurang dari nol. Kemunculannya berarti
           pengeluaran tercatat melebihi pemasukan — salah entri, atau
           mutasi yang terhapus sebagian. */
        $minus = $barang->filter(fn ($b) => Gudang::stok($b) < 0);

        return [[
            'kelompok' => 'Gudang',
            'judul'    => 'Tidak ada stok bernilai negatif',
            'keadaan'  => $minus->isEmpty() ? self::AMAN : self::GAWAT,
            'nilai'    => $minus->isEmpty()
                ? $barang->count().' barang'
                : $minus->count().' dari '.$barang->count().' barang',
            'uraian'   => $minus->isEmpty()
                ? 'Seluruh saldo barang bernilai nol atau lebih.'
                : 'Barang bersaldo negatif: '.$minus->take(5)->pluck('kode')->join(', ')
                  .($minus->count() > 5 ? ', dan lainnya' : '')
                  .'. Barang tidak dapat berjumlah kurang dari nol.',
            'tindakan' => $minus->isEmpty() ? 'Tidak ada.'
                : 'Telusuri mutasi barang tersebut; lakukan opname untuk menetapkan '
                  .'saldo sebenarnya, sebab opname menetapkan dan bukan menambah.',
        ]];
    }

    /* ═══════════ Hazard & Inspeksi ═══════════ */

    private static function hse(?Company $c): array
    {
        $hazard   = self::hitung(HazardReport::class, $c);
        $inspeksi = self::hitung(Inspection::class, $c);

        if ($hazard === 0 && $inspeksi === 0) {
            return [self::kosong('Hazard & Inspeksi', 'laporan',
                'Muat data contoh atau catat satu laporan, lalu periksa lagi.')];
        }

        $baris = [[
            'kelompok' => 'Hazard & Inspeksi',
            'judul'    => 'Kedua modul berisi',
            'keadaan'  => ($hazard > 0 && $inspeksi > 0) ? self::AMAN : self::PERHATIAN,
            'nilai'    => $hazard.' hazard · '.$inspeksi.' inspeksi',
            'uraian'   => ($hazard > 0 && $inspeksi > 0)
                ? 'Keduanya berisi, sehingga indikator KPI-nya dapat dibaca.'
                : 'Salah satu modul masih kosong, sehingga KPI gabungannya belum utuh.',
            'tindakan' => ($hazard > 0 && $inspeksi > 0) ? 'Tidak ada.'
                : 'Lengkapi modul yang masih kosong sebelum membaca KPI keselamatannya.',
        ]];

        /* Status di luar daftar yang sah adalah kegagalan yang paling diam
           di seluruh aplikasi ini. Barisnya tersimpan rapi, halamannya tetap
           membuka, dan tidak ada satu pun galat — tetapi baris itu tidak
           cocok dengan saringan status mana pun maupun corong KPI-nya,
           sehingga modulnya tampak berisi sementara seluruh angkanya nol.
           Penyebabnya biasanya sepele: padanan Indonesia dipakai di satu
           tempat sementara kosakata kanonisnya bahasa Inggris, atau
           sebaliknya. */
        $sah   = Hazard::STATUS;
        $liar  = self::kueri(HazardReport::class, $c)
            ->whereNotIn('status', $sah)->pluck('status')->unique();

        $baris[] = [
            'kelompok' => 'Hazard & Inspeksi',
            'judul'    => 'Status laporan memakai kosakata yang dikenal',
            'keadaan'  => $liar->isEmpty() ? self::AMAN : self::GAWAT,
            'nilai'    => $liar->isEmpty()
                ? $hazard.' laporan · '.implode(' / ', $sah)
                : $liar->count().' status asing: '.$liar->take(4)->join(', '),
            'uraian'   => $liar->isEmpty()
                ? 'Seluruh laporan memakai status yang dikenal saringan dan KPI.'
                : 'Ada laporan berstatus di luar daftar yang sah. Baris itu tidak akan '
                  .'muncul pada saringan status mana pun, dan tidak ikut terhitung pada '
                  .'KPI keselamatan — tanpa satu pun galat yang menandainya.',
            'tindakan' => $liar->isEmpty() ? 'Tidak ada.'
                : 'Samakan nilainya dengan App\Support\Hazard::STATUS, lalu telusuri '
                  .'jalur yang menuliskannya — impor, penyemai, atau perubahan langsung.',
        ];

        return $baris;
    }

    /* ═══════════ Evaluasi pembelajaran ═══════════ */

    private static function evaluasi(?Company $c): array
    {
        $baris = [];

        /* Jawaban benar tidak mungkin melebihi jumlah soal, dan skornya
           harus merupakan persentase dari keduanya. */
        $percobaan = SopEvaluationAttempt::query()->get();

        if ($percobaan->isEmpty()) {
            $baris[] = self::kosong('Evaluasi & Kuis', 'percobaan kuis',
                'Kerjakan satu evaluasi SOP, lalu periksa lagi.');
        } else {
            $janggal = $percobaan->filter(function ($a) {
                $total   = (int) $a->total;
                $benar   = (int) $a->correct;
                $skor    = (float) $a->score;
                $harusnya = $total > 0 ? $benar / $total * 100 : 0.0;

                return $benar > $total
                    || $skor < 0 || $skor > 100
                    || abs($skor - $harusnya) > 1.0;
            });

            $baris[] = [
                'kelompok' => 'Evaluasi & Kuis',
                'judul'    => 'Skor cocok dengan jawaban benar per jumlah soal',
                'keadaan'  => $janggal->isEmpty() ? self::AMAN : self::GAWAT,
                'nilai'    => $janggal->isEmpty()
                    ? $percobaan->count().' percobaan'
                    : $janggal->count().' dari '.$percobaan->count().' percobaan',
                'uraian'   => $janggal->isEmpty()
                    ? 'Setiap skor sama dengan jawaban benar dibagi jumlah soal.'
                    : 'Ada percobaan yang skornya tidak sesuai jawaban benarnya, atau '
                      .'jumlah benarnya melebihi jumlah soal.',
                'tindakan' => $janggal->isEmpty() ? 'Tidak ada.'
                    : 'Periksa penilai kuis: skor tersimpan tidak lagi diturunkan dari '
                      .'jawaban yang tersimpan bersamanya.',
            ];
        }

        /* Nilai evaluasi pascapelatihan adalah skala; di luar rentangnya
           berarti formulirnya menerima nilai yang tidak dibatasi. */
        $pasca = PostTrainingEvaluation::query()->get();
        if ($pasca->isNotEmpty()) {
            $luar = $pasca->filter(function ($e) {
                foreach (['knowledge_score','skill_score','attitude_score','safety_score','overall_score'] as $k) {
                    $v = $e->{$k};
                    if ($v !== null && ((float) $v < 0 || (float) $v > 100)) return true;
                }

                return false;
            });

            $baris[] = [
                'kelompok' => 'Evaluasi & Kuis',
                'judul'    => 'Nilai evaluasi pascapelatihan dalam rentang',
                'keadaan'  => $luar->isEmpty() ? self::AMAN : self::GAWAT,
                'nilai'    => $luar->isEmpty()
                    ? $pasca->count().' evaluasi'
                    : $luar->count().' dari '.$pasca->count().' evaluasi',
                'uraian'   => $luar->isEmpty()
                    ? 'Seluruh nilai komponen berada dalam 0–100.'
                    : 'Ada nilai komponen di luar 0–100.',
                'tindakan' => $luar->isEmpty() ? 'Tidak ada.'
                    : 'Batasi nilai pada validasi formulir evaluasi pascapelatihan.',
            ];
        }

        return $baris;
    }

    /* ═══════════ perkakas ═══════════ */

    /** Baris "belum dapat dinilai" — sengaja bukan "aman". */
    private static function kosong(string $modul, string $apa, string $tindakan): array
    {
        return [
            'kelompok' => $modul,
            'judul'    => 'Belum ada '.$apa.' untuk dinilai',
            'keadaan'  => self::TAK_TAHU,
            'nilai'    => 'kosong',
            'uraian'   => 'Modul ini belum berisi, sehingga penilaiannya belum dapat '
                .'dibuktikan benar atau salah. Halaman kosong tidak pernah keliru — '
                .'dan justru karena itu ia tidak membuktikan apa pun.',
            'tindakan' => $tindakan,
        ];
    }

    /** Kueri satu model, dibatasi perusahaan bila diminta. */
    private static function kueri(string $kelas, ?Company $c)
    {
        $q = $kelas::withoutGlobalScopes();

        if ($c && (new $kelas)->getConnection()->getSchemaBuilder()
                 ->hasColumn((new $kelas)->getTable(), 'company_id')) {
            $q->where('company_id', $c->id);
        }

        return $q;
    }

    private static function hitung(string $kelas, ?Company $c): int
    {
        return self::kueri($kelas, $c)->count();
    }

    private static function angka(?float $n, int $desimal = 2): string
    {
        return $n === null ? '—' : number_format($n, $desimal, ',', '.');
    }

    private static function persen(float $n): string
    {
        return self::angka($n, 1).'%';
    }
}
