<?php

namespace App\Support;

/**
 * Audit Kinerja Pengelolaan dan Pemantauan Lingkungan.
 *
 * Instrumen audit internal ISO 14001 yang dipakai pemegang IUP untuk
 * menilai mitra kerjanya: enam bagian, seratus sembilan puluh satu
 * kriteria bernilai 0–3, berbobot, dikurangi nilai pengurang, lalu
 * menghasilkan predikat penghargaan dan peringkat warna.
 *
 * ── Kenapa terpisah dari mesin evaluasi pemenuhan ──
 *
 * Modul Pemenuhan menjawab "sudah atau belum" — tiga keadaan dan satu
 * persentase. Instrumen ini menjawab "seberapa baik" pada skala nol
 * sampai tiga, lalu menimbangnya: bagian Implementasi berbobot 0,6
 * sendirian, sementara Inovasi hanya 0,05. Dipaksa memakai mesin yang
 * sama, bobot itu harus disimpan entah di mana dan persentasenya
 * berhenti berarti sama di kedua tempat.
 *
 * ── Kriteria nilai ──
 *
 *   0 — tidak ada bukti dokumen maupun implementasi lapangan
 *   1 — ada bukti dokumen, implementasi lapangan belum dilakukan
 *   2 — ada bukti dokumen dan implementasi, tetapi perlu perbaikan
 *   3 — ada bukti dokumen dan implementasi, telah memenuhi persyaratan
 *
 * Bagian D (Kompetensi Personil) memakai tangga yang berbeda, karena
 * yang dinilai bukan penerapan melainkan status sertifikasi orangnya.
 */
final class AuditLingkungan
{
    private static ?array $ref = null;

    /** Nilai yang boleh diisi pada tiap kriteria. */
    public const NILAI = [0, 1, 2, 3];

    /**
     * Medan profil perusahaan, mengikuti lembar "Informasi Umum".
     *
     * Satu tempat, dibaca formulir pengisian DAN tampilan ikhtisarnya.
     * Ditulis dua kali, labelnya cepat berselisih — dan yang membaca
     * ikhtisar melihat nama kunci mentah seperti "KaryawanNonStaff"
     * alih-alih "Karyawan non-staff".
     *
     * @var array<string,string>
     */
    /**
     * Batas panjang keterangan satu kriteria.
     *
     * DISEBUT SEKALI di sini, lalu dipakai aturan validasi DAN medan
     * isiannya. Dua angka yang mengatur hal yang sama pada dua sisi
     * adalah cara paling pasti membuat layar menerima apa yang server
     * tolak — dan pada lembar berisi seratus lima puluh kriteria,
     * penolakan itu membuang seluruh isian sekaligus.
     */
    public const MAKS_KETERANGAN = 2000;

    public const PROFIL = [
        'alamat'            => 'Alamat lokasi kegiatan',
        'telepon'           => 'Telp. / Fax. lokasi',
        'alamatPusat'       => 'Alamat kantor pusat / perwakilan',
        'teleponPusat'      => 'Telp. / Fax. kantor pusat',
        'tahunBerdiri'      => 'Tahun berdiri / mulai beroperasi',
        'karyawanTotal'     => 'Jumlah total karyawan',
        'karyawanStaff'     => 'Karyawan staff',
        'karyawanNonStaff'  => 'Karyawan non-staff',
        'karyawanPria'      => 'Karyawan pria',
        'karyawanWanita'    => 'Karyawan wanita',
        'petugasLingkungan' => 'Petugas pengelola lingkungan',
        'kontak1'           => 'Nama personal kontak 1',
        'kontak1Hp'         => 'Nomor HP & e-mail kontak 1',
        'kontak2'           => 'Nama personal kontak 2',
        'kontak2Hp'         => 'Nomor HP & e-mail kontak 2',
    ];

    /**
     * Profil sebagai pasangan berlabel dan BERURUT, hanya yang terisi.
     *
     * @return array<int,array{kunci:string,label:string,nilai:string}>
     */
    public static function profil(?array $isi): array
    {
        $out = [];

        foreach (self::PROFIL as $kunci => $label) {
            $nilai = trim((string) ($isi[$kunci] ?? ''));

            if ($nilai !== '') $out[] = compact('kunci', 'label', 'nilai');
        }

        return $out;
    }

    /** Bagian wajib — syarat predikat menuntut nilai PENUH di keduanya. */
    public const WAJIB_PENUH = ['a', 'b'];

    /**
     * Tangga nilai, dan bunyinya berbeda untuk bagian D.
     *
     * Ditulis lengkap, bukan sekadar angka: yang mengisi kolom nilai
     * memerlukan bunyi kriterianya di layar, dan yang harus membuka
     * berkas acuan untuk mengingatnya akan menebak.
     */
    public const TANGGA = [
        'umum' => [
            0 => 'Tidak ada bukti dokumen dan implementasi lapangan',
            1 => 'Terdapat bukti dokumen namun implementasi lapangan belum dilakukan',
            2 => 'Terdapat bukti dokumen dan implementasi lapangan namun perlu perbaikan',
            3 => 'Terdapat bukti dokumen dan implementasi lapangan telah memenuhi persyaratan',
        ],
        'd' => [
            0 => 'Belum tersedia personil yang tersertifikasi',
            1 => 'Sudah tersedia personil yang didaftarkan untuk mengikuti sertifikasi',
            2 => 'Sudah tersedia personil yang mengikuti sertifikasi, menunggu hasil',
            3 => 'Tersedia personil yang tersertifikasi dan dinyatakan kompeten',
        ],
    ];

    /**
     * Nilai pengurang — dipotong dari skor akhir, bukan dari bagiannya.
     *
     * @var array<string,array{label:string,poin:int}>
     */
    public const PENGURANG = [
        'kecelakaan'      => ['label' => 'Environment accident',                     'poin' => 5],
        'sanksi-abai'     => ['label' => 'Sanksi dari KTT tidak ditindaklanjuti',    'poin' => 5],
        'sanksi-tindak'   => ['label' => 'Sanksi dari KTT ditindaklanjuti',          'poin' => 1],
        'pea-1'           => ['label' => 'Satu atau lebih PEA Class 1',              'poin' => 3],
        'pea-2'           => ['label' => 'Satu atau lebih PEA Class 2',              'poin' => 2],
        'pea-3'           => ['label' => 'Satu atau lebih PEA Class 3',              'poin' => 1],
        'epi-rendah'      => ['label' => 'Pencapaian EPI di bawah 70%',              'poin' => 3],
    ];

    /**
     * Predikat penghargaan, dari yang tertinggi.
     *
     * Ambangnya saja tidak cukup: syarat minimalnya menuntut NILAI
     * PENUH pada bagian Administrasi dan Implementasi. Perusahaan
     * berskor 92 yang kehilangan satu poin di bagian A tidak mendapat
     * ADITAMA — dan tanpa syarat itu ditegakkan, penghargaan terbit
     * untuk perusahaan yang administrasinya belum lengkap.
     */
    public const PREDIKAT = [
        ['nama' => 'ADITAMA', 'min' => 90, 'maks' => 100],
        ['nama' => 'UTAMA',   'min' => 80, 'maks' => 89],
        ['nama' => 'PRATAMA', 'min' => 70, 'maks' => 79],
    ];

    /** Peringkat warna — berlaku tanpa syarat nilai penuh. */
    public const PERINGKAT = [
        ['nama' => 'EMAS',  'kriteria' => 'MELEBIHI KETAATAN', 'min' => 90, 'warna' => '#CA9A04'],
        ['nama' => 'HIJAU', 'kriteria' => 'MELEBIHI KETAATAN', 'min' => 80, 'warna' => '#16A34A'],
        ['nama' => 'BIRU',  'kriteria' => 'TAAT',              'min' => 70, 'warna' => '#2563EB'],
        ['nama' => 'MERAH', 'kriteria' => 'TIDAK TAAT',        'min' => 60, 'warna' => '#DC2626'],
        ['nama' => 'HITAM', 'kriteria' => 'TIDAK TAAT',        'min' => 0,  'warna' => '#1C1917'],
    ];

    /** @return array<string,array<string,mixed>> berkunci a..f */
    public static function bagian(): array
    {
        if (self::$ref === null) {
            $berkas = resource_path('data/audit/lingkungan.json');

            self::$ref = is_file($berkas)
                ? (json_decode((string) file_get_contents($berkas), true) ?: [])
                : [];
        }

        return self::$ref;
    }

    public static function satu(string $kunci): ?array
    {
        return self::bagian()[$kunci] ?? null;
    }

    /**
     * Peta kode kriteria satu bagian: "indeksKelompok:indeksButir" → kode.
     *
     * Kodenya b.3.1.f — bagian, subbagian, kelompok, huruf butir.
     * Dipakai sebagai kunci penyimpanan nilai. Menyimpan menurut urutan
     * baris akan menggeser seluruh nilai begitu satu kriteria disisipkan
     * di tengah — dan pergeseran itu tidak menimbulkan galat, hanya
     * jawaban yang menempel pada pertanyaan yang salah.
     *
     * ── Berkas acuannya punya nomor kembar, dan itu harus ditangani ──
     *
     * Dua keadaan nyata pada berkas CAM:
     *
     *   · Bagian B subbagian VI: "Administrasi" dan "Pengemasan Limbah
     *     B3" SAMA-SAMA bernomor 2.
     *   · Bagian D kelompok 1: dua kriteria berbeda (POIPAL dan PCUA)
     *     SAMA-SAMA berhuruf b.
     *
     * Dibiarkan, keduanya menghasilkan kode yang sama, dan nilai
     * kriteria yang satu menimpa nilai kriteria yang lain tanpa satu
     * pun tanda di layar. Yang kedua dan seterusnya karena itu mendapat
     * akhiran angka: b.6.2 lalu b.6.2-2; d.1.b lalu d.1.b-2. Nomor
     * tercetaknya tetap terbaca, dan kodenya tetap tidak bergeser bila
     * kriteria lain disisipkan di tempat lain.
     *
     * @return array<string,string>
     */
    private static function petaKode(string $bagian): array
    {
        static $peta = [];

        if (isset($peta[$bagian])) return $peta[$bagian];

        $out = [];
        $hitungGrup = [];

        foreach (self::satu($bagian)['kelompok'] ?? [] as $gi => $g) {
            $kunciGrup = ($g['sub'] ?? '').'|'.$g['no'];
            $keGrup = $hitungGrup[$kunciGrup] = ($hitungGrup[$kunciGrup] ?? 0) + 1;

            $nomorGrup = $keGrup === 1 ? (string) $g['no'] : $g['no'].'-'.$keGrup;
            $sub = $g['sub'] ? self::nomorSub($bagian, $g['sub']) : null;

            $hitungButir = [];

            foreach ($g['butir'] as $bi => $b) {
                $huruf = $b['huruf'];

                if ($huruf !== null && $huruf !== '') {
                    $ke = $hitungButir[$huruf] = ($hitungButir[$huruf] ?? 0) + 1;
                    if ($ke > 1) $huruf .= '-'.$ke;
                }

                $out[$gi.':'.$bi] = implode('.', array_filter(
                    [$bagian, $sub, $nomorGrup, $huruf],
                    fn ($x) => $x !== null && $x !== '',
                ));
            }
        }

        return $peta[$bagian] = $out;
    }

    /** Nomor urut subbagian di dalam bagiannya (1..7 pada bagian B). */
    private static function nomorSub(string $bagian, string $sub): int
    {
        static $peta = [];

        if (!isset($peta[$bagian])) {
            $urut = [];

            foreach (self::satu($bagian)['kelompok'] ?? [] as $g) {
                if ($g['sub'] && !in_array($g['sub'], $urut, true)) $urut[] = $g['sub'];
            }

            $peta[$bagian] = array_flip($urut);
        }

        return ($peta[$bagian][$sub] ?? 0) + 1;
    }

    /** Seluruh kriteria satu bagian, sudah rata dan berkode. */
    public static function kriteria(string $bagian): array
    {
        $out = [];

        $peta = self::petaKode($bagian);

        foreach (self::satu($bagian)['kelompok'] ?? [] as $gi => $g) {
            foreach ($g['butir'] as $bi => $b) {
                $out[] = [
                    'kode'     => $peta[$gi.':'.$bi],
                    'sub'      => $g['sub'],
                    'kelompok' => $g['no'].'. '.$g['judul'],
                    'huruf'    => $b['huruf'],
                    'uraian'   => $b['uraian'],
                    'kosong'   => $b['kosong'] ?? false,
                ];
            }
        }

        return $out;
    }

    public static function jumlahKriteria(string $bagian): int
    {
        return count(self::kriteria($bagian));
    }

    /**
     * Hitung skor lengkap dari nilai yang sudah diisi.
     *
     * ── Kriteria yang belum dinilai dihitung NOL, dan itu disengaja ──
     *
     * Berbeda dari modul Pemenuhan, di sini yang belum diisi memang
     * bernilai nol: tangga nilainya sudah menyediakan 0 untuk "tidak ada
     * bukti", dan audit yang belum selesai memang belum layak
     * mendapatkan predikat. Yang dilaporkan terpisah adalah BERAPA yang
     * belum diisi, supaya skor sementara tidak dibaca sebagai skor
     * akhir.
     *
     * @param  array<string,int>  $nilai  berkunci kode kriteria
     * @param  array<int,string>  $pengurang  kunci pengurang yang berlaku
     */
    public static function hitung(array $nilai, array $pengurang = []): array
    {
        $bagian = [];
        $tertimbang = 0.0;
        $belumTotal = 0;
        $kriteriaTotal = 0;

        foreach (self::bagian() as $kunci => $b) {
            $kriteria = self::kriteria($kunci);
            $maks = count($kriteria) * 3;

            $jumlah = 0;
            $belum = 0;

            foreach ($kriteria as $k) {
                if (!array_key_exists($k['kode'], $nilai) || $nilai[$k['kode']] === null) {
                    $belum++;
                    continue;
                }

                $jumlah += max(0, min(3, (int) $nilai[$k['kode']]));
            }

            $rasio = $maks ? $jumlah / $maks : 0.0;
            $tertimbang += $rasio * (float) $b['bobot'];

            $belumTotal    += $belum;
            $kriteriaTotal += count($kriteria);

            $bagian[$kunci] = [
                'kunci'    => $kunci,
                'judul'    => $b['judul'],
                'bobot'    => (float) $b['bobot'],
                'maks'     => $maks,
                'nilai'    => $jumlah,
                'kriteria' => count($kriteria),
                'belum'    => $belum,
                'persen'   => round($rasio * 100, 1),
                'penuh'    => $maks > 0 && $jumlah === $maks,
                'hasil'    => round($rasio * (float) $b['bobot'] * 100, 2),
            ];
        }

        $pemenuhan = round($tertimbang * 100, 2);

        $potong = 0;
        foreach ($pengurang as $p) {
            $potong += self::PENGURANG[$p]['poin'] ?? 0;
        }

        /* Skor akhir tidak dibiarkan negatif: angka minus tidak punya
           arti pada skala nol sampai seratus, dan HITAM sudah menampung
           seluruh keadaan di bawah enam puluh. */
        $akhir = max(0.0, round($pemenuhan - $potong, 2));

        $penuhWajib = array_reduce(
            self::WAJIB_PENUH,
            fn ($ya, $k) => $ya && ($bagian[$k]['penuh'] ?? false),
            true,
        );

        return [
            'bagian'      => $bagian,
            'pemenuhan'   => $pemenuhan,
            'pengurang'   => $potong,
            'rincianKurang' => array_map(
                fn ($p) => ['kunci' => $p] + (self::PENGURANG[$p] ?? ['label' => $p, 'poin' => 0]),
                array_values($pengurang),
            ),
            'akhir'       => $akhir,
            'belum'       => $belumTotal,
            'kriteria'    => $kriteriaTotal,
            'penuhWajib'  => $penuhWajib,
            'predikat'    => self::predikat($akhir, $penuhWajib),
            'peringkat'   => self::peringkat($akhir),
        ];
    }

    /**
     * Predikat, beserta ALASAN bila tidak terbit.
     *
     * Perusahaan berskor 92 yang kehilangan satu poin di bagian A tidak
     * mendapat ADITAMA. Tanpa alasannya disebut, yang membaca layar
     * menyimpulkan perhitungannya rusak.
     */
    public static function predikat(float $akhir, bool $penuhWajib): array
    {
        foreach (self::PREDIKAT as $p) {
            if ($akhir < $p['min']) continue;

            if (!$penuhWajib) {
                return [
                    'nama'   => null,
                    'alasan' => 'Nilai '.number_format($akhir, 2, ',', '.').' mencapai ambang '.$p['nama']
                               .', tetapi syarat minimalnya belum terpenuhi: bagian Administrasi '
                               .'Lingkungan dan Implementasi Pengelolaan & Pemantauan Lingkungan '
                               .'harus bernilai PENUH.',
                ];
            }

            return ['nama' => $p['nama'], 'alasan' => null];
        }

        return [
            'nama'   => null,
            'alasan' => 'Nilai akhir '.number_format($akhir, 2, ',', '.').' belum mencapai 70, '
                       .'ambang terendah untuk mendapatkan predikat penghargaan.',
        ];
    }

    public static function peringkat(float $akhir): array
    {
        foreach (self::PERINGKAT as $p) {
            if ($akhir >= $p['min']) return $p;
        }

        return self::PERINGKAT[count(self::PERINGKAT) - 1];
    }

    /** Ikhtisar bagian untuk pemilih dan bilah pindah. */
    public static function daftarBagian(): array
    {
        return array_map(fn ($kunci) => [
            'kunci'    => $kunci,
            'huruf'    => strtoupper($kunci),
            'judul'    => self::bagian()[$kunci]['judul'],
            'bobot'    => (float) self::bagian()[$kunci]['bobot'],
            'maks'     => self::bagian()[$kunci]['maks'],
            'kriteria' => self::jumlahKriteria($kunci),
            'wajib'    => in_array($kunci, self::WAJIB_PENUH, true),
        ], array_keys(self::bagian()));
    }
}
