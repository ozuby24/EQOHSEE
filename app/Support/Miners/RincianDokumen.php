<?php

namespace App\Support\Miners;

use App\Models\Miners\{Alur, Induksi, InduksiOrang, Mcu, McuOrang, McuRujukan,
    Permit, PermitBerkas, Simper, SimperUnit};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Waktu;

/**
 * Isi halaman rincian satu dokumen Miners.
 *
 * ── Kenapa halaman rincian ada sama sekali ──
 *
 * Sebelum ini, seluruh modul Miners hanya punya DAFTAR. Menyetujui
 * sebuah Mine Permit, membaca lampirannya, dan melihat sudah sampai
 * mana persetujuannya dikerjakan di dalam satu sel tabel yang
 * mengembang — sehingga tiga pekerjaan yang berbeda berebut lebar
 * kolom yang sama. Safe Track memisahkannya menjadi halaman sendiri
 * per pengajuan dengan tiga bagian tetap: rincian, lampiran,
 * persetujuan. Susunan itulah yang ditiru di sini; tampilannya tetap
 * memakai bahasa visual EQOHSEE.
 *
 * ── Kenapa SATU kelas untuk empat jenis dokumen ──
 *
 * Bingkainya sama bagi keempatnya, hanya isinya yang berbeda. Project1
 * menuliskannya berulang per jenis, dan akibatnya sudah tercatat di
 * Jalur: langkah pengesahan KTT ada pada satu jalur dan tidak pernah
 * ditambahkan pada jalur lain, tanpa satu galat pun yang menandainya.
 * Bentuk keluarannya karena itu DINORMALKAN di sini — daftar
 * baris label/nilai, daftar lampiran, daftar orang — sehingga layarnya
 * tidak perlu tahu sedang menggambar dokumen yang mana.
 */
final class RincianDokumen
{
    /** Label jenis dokumen sebagaimana disebut orang, bukan nama tabel. */
    public const LABEL = [
        'mcu'     => 'MCU',
        'induksi' => 'Induksi',
        'permit'  => 'Mine Permit',
        'simper'  => 'SIMPER',
        'ajuan'   => 'SIMPER Lanjutan',
        'perpanjangan_permit' => 'Perpanjangan Mine Permit',
    ];

    /** Alamat daftar tiap jenis, untuk tautan "kembali". */
    public const DAFTAR = [
        'mcu'     => '/miners/mcu',
        'induksi' => '/miners/induksi',
        'permit'  => '/miners/permit',
        'simper'  => '/miners/simper',
    ];

    /**
     * Alamat rincian sebuah dokumen — untuk surel dan daftar antrean.
     *
     * Pengajuan lanjutan SIMPER dan perpanjangan Mine Permit belum punya
     * halaman rinciannya sendiri; keduanya diarahkan ke kartu induknya.
     */
    public static function tautan(object $d): string
    {
        return match ($d::jenisDokumen()) {
            'ajuan'               => '/miners/simper/'.$d->simper_id,
            'perpanjangan_permit' => '/miners/permit/'.$d->permit_id,
            default               => (self::DAFTAR[$d::jenisDokumen()] ?? '/miners').'/'.$d->getKey(),
        };
    }

    /**
     * Seluruh prop halaman rincian.
     *
     * @return array<string,mixed>
     */
    public static function susun(string $jenis, object $d, ?User $u): array
    {
        return [
            'judul'    => 'Miners — '.(self::LABEL[$jenis] ?? $jenis),
            'subjudul' => self::subjudul($jenis, $d),

            'dokumen' => [
                'jenis'   => $jenis,
                'label'   => self::LABEL[$jenis] ?? $jenis,
                'id'      => $d->getKey(),
                'nomor'   => self::nomor($jenis, $d),
                'status'  => $d->status,
                'daftar'  => self::DAFTAR[$jenis] ?? '/miners',
            ],

            'rincian'  => array_merge(self::rincian($jenis, $d), [
                ['label' => 'IUP penerbit', 'nilai' => $d->iup?->name],
                ['label' => 'Pengaju',      'nilai' => $d->user?->name],
            ]),
            'lampiran' => self::lampiran($jenis, $d, $u),
            'orang'    => self::orang($jenis, $d, $u),
            'alur'     => self::alur($d),

            /* Kalimat "sedang nyangkut di mana" — posisiSaatIni Safe
               Track — dihitung dari alur yang sama dengan tabelnya. */
            'posisi'   => Jalur::posisi($d),

            'bolehKirim' => Jalur::bolehKirim($d, $u),

            /* Halaman ini menggambar kepalanya sendiri — judul dan remah
               roti gaya Safe Track — alih-alih kop bersampul. */
            'kop' => false,

            /* Tombol tindakan hanya digambar bila ADA langkah yang
               menunggu DAN peran pengguna ini yang ditunggu. Penjagaan
               sebenarnya tetap di Jalur — tombol yang disembunyikan
               masih dapat dikirim permintaannya — tetapi menggambar
               tombol yang pasti ditolak hanya membuat orang menekannya
               lalu bertanya-tanya. */
            'bolehTindak' => self::bolehTindak($d, $u),

            /* Daftar periksa SOP. Kosong bagi MCU dan induksi, yang
               memang tidak punya daftar wajib — lampirannya melekat
               pada ORANG di dalam suratnya, bukan pada suratnya. */
            'wajib' => Kelengkapan::daftar($d),

            /* Tombol "ajukan ulang" hanya bagi pengaju (atau admin) atas
               pengajuan yang DIKEMBALIKAN. Yang DITOLAK tidak muncul:
               ditolak dan dikembalikan bukan dua kata untuk satu hal. */
            'bolehAjukanUlang' => Jalur::bolehKirim($d, $u) && $d->alurTertahan(),

            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
            'PERAN'   => Alur::PERAN,
        ];
    }

    private static function subjudul(string $jenis, object $d): string
    {
        return match ($jenis) {
            'mcu'     => 'Surat pengajuan pemeriksaan kesehatan beserta hasil tiap orang di dalamnya.',
            'induksi' => 'Induksi keselamatan beserta nilai post test tiap peserta.',
            'permit'  => 'Kartu masuk tambang, beserta MCU dan induksi yang mendasarinya.',
            'simper'  => 'Izin mengemudikan unit, menempel pada Mine Permit yang mendasarinya.',
            default   => '',
        };
    }

    private static function nomor(string $jenis, object $d): ?string
    {
        return match ($jenis) {
            'mcu', 'induksi' => $d->no_registrasi,
            'permit'         => $d->no_registrasi,
            'simper'         => $d->no_simper,
            default          => null,
        };
    }

    /**
     * Baris label/nilai untuk bagian Rincian.
     *
     * `nada` opsional dan hanya dipakai bagi baris yang MEMANG perlu
     * menonjol — tanggal efektif yang lebih awal daripada yang
     * tercetak, misalnya. Dipakai pada tiap baris, penekanan itu
     * berhenti berarti apa-apa.
     *
     * @return list<array{label:string,nilai:?string,nada?:string}>
     */
    private static function rincian(string $jenis, object $d): array
    {
        $tgl = fn ($t) => $t?->toDateString();

        return match ($jenis) {
            'mcu' => [
                ['label' => 'Tanggal surat', 'nilai' => $tgl($d->tanggal)],
                ['label' => 'Kepada',        'nilai' => $d->kepada],
                ['label' => 'Perihal',       'nilai' => $d->perihal],
                ['label' => 'Jumlah nama',   'nilai' => (string) $d->orang->count()],
            ],

            'induksi' => [
                ['label' => 'Tanggal',      'nilai' => $tgl($d->tanggal)],
                ['label' => 'Perihal',      'nilai' => $d->perihal],
                ['label' => 'Jumlah peserta', 'nilai' => (string) $d->orang->count()],
            ],

            'permit' => array_values(array_filter([
                ['label' => 'Pemegang',   'nilai' => $d->pekerja?->nama],
                ['label' => 'Terbit',     'nilai' => $tgl($d->tanggal)],
                ['label' => 'Jenis',      'nilai' => $d->tipe?->nama],
                ['label' => 'Kategori',   'nilai' => $d->kategori?->nama],
                ['label' => 'Zona akses', 'nilai' => $d->cakupan_area],
                ['label' => 'Warna kartu','nilai' => $d->kode_warna],
                ['label' => 'Tertulis berlaku sampai', 'nilai' => $tgl($d->berlaku_sampai)],

                /* Tanggal EFEKTIF ditampilkan hanya ketika ia berbeda
                   dari yang tercetak di kartu. Selalu ditampilkan, ia
                   menjadi baris yang diabaikan; ditampilkan hanya saat
                   berbeda, ia justru menjawab pertanyaan yang muncul —
                   kenapa kartu yang tertulis berlaku sampai Desember
                   sudah tidak berlaku hari ini. */
                $d->habisEfektif() && $tgl($d->habisEfektif()) !== $tgl($d->berlaku_sampai)
                    ? ['label' => 'Efektif habis', 'nilai' => $tgl($d->habisEfektif()),
                       'nada' => 'peringatan',
                       'sebab' => 'MCU yang mendasarinya habis lebih dahulu']
                    : null,

                ['label' => 'MCU dasar', 'nilai' => $tgl($d->mcuOrang?->berlaku_sampai)],
            ])),

            'simper' => array_values(array_filter([
                ['label' => 'Pemegang', 'nilai' => $d->pekerja?->nama],
                ['label' => 'Terbit',   'nilai' => $tgl($d->tanggal)],
                ['label' => 'Kelas',    'nilai' => Simper::KELAS[$d->kelas] ?? $d->kelas],
                ['label' => 'Jenis SIM','nilai' => $d->jenis_simpol],
                ['label' => 'Nomor SIM','nilai' => $d->no_simpol],
                ['label' => 'SIM berlaku sampai', 'nilai' => $tgl($d->simpol_berlaku_sampai)],
                ['label' => 'Tertulis berlaku sampai', 'nilai' => $tgl($d->berlaku_sampai)],

                $d->habisEfektif() && $tgl($d->habisEfektif()) !== $tgl($d->berlaku_sampai)
                    ? ['label' => 'Efektif habis', 'nilai' => $tgl($d->habisEfektif()),
                       'nada' => 'peringatan',
                       'sebab' => self::sebabSimper($d->penyebabHabis())]
                    : null,

                ['label' => 'Pengalaman kerja', 'nilai' => $d->pengalaman_kerja],
            ])),

            default => [],
        };
    }

    private static function sebabSimper(?string $sebab): string
    {
        return match ($sebab) {
            'mcu'    => 'MCU yang mendasarinya habis lebih dahulu',
            'simpol' => 'SIM kepolisiannya habis lebih dahulu',
            'permit' => 'Mine Permit yang mendasarinya habis lebih dahulu',
            default  => 'masa berlaku kartunya sendiri',
        };
    }

    /**
     * Lampiran dokumen ini, dengan keadaan dan alamatnya.
     *
     * `ada` dan `url` datang terpisah dari Berkas::lampiran(), supaya
     * layar dapat membedakan "belum diunggah" dari "tidak boleh Anda
     * buka". Yang pertama menuntut tindakan, yang kedua tidak.
     *
     * @return list<array<string,mixed>>
     */
    private static function lampiran(string $jenis, object $d, ?User $u): array
    {
        if ($jenis === 'simper') {
            return [['label' => 'Salinan SIM kepolisian'] + Berkas::lampiran($u, $d, 'mnp')];
        }

        if ($jenis === 'permit') {
            /* Yang dijadikan JUDUL keterangannya, bukan kunci jenisnya.
               `jenis` adalah kunci yang dipakai updateOrCreate — bentuknya
               slug seperti "daftar-hadir-induksi-keselamatan" — dan
               membacanya sebagai judul membuat daftar lampiran tampak
               seperti keluaran mesin. Kuncinya tetap ditampilkan, kecil
               dan di samping, sebab ia yang harus diketik ulang ketika
               lampirannya diperbarui. */
            return $d->berkas->map(fn (PermitBerkas $b) => [
                'id'      => $b->id,
                'label'   => $b->catatan ?: $b->jenis,
                'kunci'   => $b->jenis,
                'nomor'   => $b->nomor,
            ] + Berkas::lampiran($u, $b, 'mnl'))->values()->all();
        }

        /* MCU dan induksi menyimpan lampirannya PER ORANG, bukan pada
           suratnya. Karena itu bagian Lampiran keduanya kosong, dan
           berkasnya ikut pada tiap baris orang di bawah. */
        return [];
    }

    /**
     * Orang di dalam surat MCU atau induksi.
     *
     * @return list<array<string,mixed>>
     */
    private static function orang(string $jenis, object $d, ?User $u): array
    {
        if ($jenis === 'mcu') {
            return $d->orang->map(fn (McuOrang $o) => [
                /* Bentuknya DIKATAKAN, bukan ditebak layar dari medan
                   mana yang kebetulan terisi. Menebak dari isian
                   membuat baris yang nilainya nol atau kosong digambar
                   sebagai jenis yang lain. */
                'bentuk'     => 'mcu',
                'id'         => $o->id,
                'pekerja_id' => $o->pekerja_id,
                'nama'       => $o->nama,
                'nik'        => $o->nik,
                'hasil'      => $o->hasil?->nama,
                'layak'      => $o->layak(),
                'napza'      => $o->hasil_napza,
                'periksa'    => $o->tanggal_periksa?->toDateString(),
                'sampai'     => $o->berlaku_sampai?->toDateString(),
                'keadaan'    => Keadaan::mcu($o),
                'berkas'     => [
                    ['label' => 'Surat hasil',  'jenis' => 'mnh'] + Berkas::lampiran($u, $o, 'mnh'),
                    ['label' => 'Rekomendasi',  'jenis' => 'mnk'] + Berkas::lampiran($u, $o, 'mnk'),
                    ['label' => 'Hasil napza',  'jenis' => 'mnz'] + Berkas::lampiran($u, $o, 'mnz'),
                ],
                'rujukan' => $o->rujukan->map(fn (McuRujukan $j) => [
                    'id'         => $j->id,
                    'tanggal'    => $j->tanggal_surat?->toDateString(),
                    'dokter'     => $j->dokter,
                    'poliklinik' => $j->poliklinik,
                ] + Berkas::lampiran($u, $j, 'mnj'))->values()->all(),
            ])->values()->all();
        }

        if ($jenis === 'induksi') {
            return $d->orang->map(fn (InduksiOrang $o) => [
                'bentuk'     => 'induksi',
                'id'         => $o->id,
                'pekerja_id' => $o->pekerja_id,
                'nama'       => $o->pekerja?->nama,
                'nilai'      => $o->nilai,
                'percobaan'  => $o->percobaan,
                'lulus'      => $o->lulus(),
                'sampai'     => $o->berlaku_sampai?->toDateString(),
                'keadaan'    => Keadaan::induksi($o),
                'berkas'     => [
                    ['label' => 'Sertifikat',  'jenis' => 'mns'] + Berkas::lampiran($u, $o, 'mns'),
                    ['label' => 'Daftar hadir','jenis' => 'mnd'] + Berkas::lampiran($u, $o, 'mnd'),
                ],
            ])->values()->all();
        }

        /* SIMPER tidak memuat orang, melainkan UNIT. Dibawa pada kunci
           yang sama supaya layarnya tidak perlu cabang tersendiri:
           keduanya daftar baris yang menempel pada dokumennya. */
        if ($jenis === 'simper') {
            return $d->unit->map(fn (SimperUnit $x) => [
                'bentuk'     => 'unit',
                'id'         => $x->id,
                'nama'       => $x->jenisUnit?->nama ?? $x->kendaraan?->nama,
                'golongan'   => $x->kendaraan?->nama,
                'kewenangan' => SimperUnit::KEWENANGAN[$x->kewenangan] ?? $x->kewenangan,
                'lulus'      => $x->lulus(),
                'nilai'      => ['P2H' => $x->nilai_p2h, 'Praktik' => $x->nilai_praktek, 'Teori' => $x->nilai_teori],
            ])->values()->all();
        }

        return [];
    }

    /**
     * Kolom tabel persetujuan — kolomPersetujuan() Safe Track.
     *
     * Judul kolom adalah JABATAN beserta organisasinya ("HSE PT X"),
     * bukan nama orang; nama dicetak di dalam kotaknya. Langkah pengaju
     * yang sudah dikirim terbaca "Diajukan", bukan "Disetujui" — pengaju
     * mengajukan, tidak menyetujui pengajuannya sendiri.
     *
     * @return list<array<string,mixed>>
     */
    public static function alur(object $d): array
    {
        $berjalan = $d->langkahBerjalan();

        return $d->alur->map(function (Alur $a) use ($d, $berjalan) {
            $keadaan = $a->keadaan;

            $label = match (true) {
                $a->peran === 'pengaju' && $keadaan === 'setuju' => 'Diajukan',
                $berjalan && $berjalan->is($a)                    => 'Diproses',
                default                                           => Alur::KEADAAN[$keadaan] ?? $keadaan,
            };

            return [
                'urutan'     => $a->urutan,
                'peran'      => $a->peran,
                'sebutan'    => Alur::SEBUTAN[$a->peran] ?? '',
                'label'      => Jalur::labelLangkah($d, $a),
                'peranLabel' => Alur::PERAN[$a->peran] ?? $a->peran,
                'keadaan'    => $keadaan,
                'keadaanLabel' => $label,
                'giliran'    => $berjalan !== null && $berjalan->is($a),
                'oleh'       => $a->user?->name,
                'ditugaskan' => $a->ditugaskan?->name,
                'atasNama'   => $a->user_id && $a->ditugaskan_user_id
                    && (int) $a->user_id !== (int) $a->ditugaskan_user_id,
                'pada'       => $a->bertindak_pada ? Waktu::lokal($a->bertindak_pada)->format('d-m-Y H:i') : null,
                'catatan'    => $a->catatan,
            ];
        })->values()->all();
    }

    private static function bolehTindak(object $d, ?User $u): bool
    {
        return Jalur::bolehTindak($d, $u);
    }

    /**
     * Relasi yang perlu dimuat sebelum susun(), per jenis dokumen.
     *
     * Dipusatkan di sini supaya pemanggilnya tidak perlu mengingatnya:
     * relasi yang terlewat tidak memulangkan galat, hanya satu kueri
     * tambahan per baris — yang pada surat MCU berisi empat puluh nama
     * berarti empat puluh kueri yang tak seorang pun melihatnya.
     *
     * @return list<string>
     */
    public static function relasi(string $jenis): array
    {
        return match ($jenis) {
            'mcu'     => ['alur.user', 'alur.ditugaskan', 'user', 'iup', 'orang.hasil', 'orang.pekerja', 'orang.rujukan'],
            'induksi' => ['alur.user', 'alur.ditugaskan', 'user', 'iup', 'orang.pekerja'],
            'permit'  => ['alur.user', 'alur.ditugaskan', 'user', 'iup', 'pekerja', 'tipe', 'kategori', 'mcuOrang', 'berkas'],
            'simper'  => ['alur.user', 'alur.ditugaskan', 'user', 'iup', 'pekerja', 'permit.mcuOrang', 'unit.kendaraan', 'unit.jenisUnit'],
            default   => ['alur.user', 'alur.ditugaskan', 'user'],
        };
    }
}
