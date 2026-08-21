<?php

namespace App\Support;

use App\Models\PasporKartu;

/**
 * Lampiran syarat pada berkas Miners — apa saja, wajib atau tidak.
 *
 * Katalog ini ada supaya daftar lampirannya berada di SATU tempat.
 * Sebelumnya ia tersebar di tiga: aturan validasi di controller, medan
 * di berkas Vue, dan kolom di migrasi — dan ketiganya sudah pernah
 * berselisih. Kolom `berkas_lotto` ada di basis data dan di aturan
 * validasi, tetapi tidak punya medan di layar sama sekali.
 *
 * ── MENGAPA UNGGAHAN, BUKAN KOLOM TEKS ──
 *
 * Seluruh lampiran ini lahir sebagai `string max:255` dengan petunjuk
 * "jalur berkas": yang mengisinya mengetik nama berkas, dan berkasnya
 * sendiri tidak pernah ikut. Yang tersimpan karena itu bukan bukti
 * melainkan PERNYATAAN bahwa buktinya ada di suatu tempat — dan
 * pernyataan itu tidak dapat dibuka, tidak dapat diperiksa, dan tidak
 * dapat dibedakan dari salah ketik.
 *
 * Akibatnya paling terasa pada Mine Permit. Keempat lampiran wajibnya
 * diperiksa SEBELUM permit terbit; bila yang tersimpan hanya nama
 * berkas, yang memeriksa harus mencarinya di folder bersama, dan yang
 * tidak menemukannya tidak tahu apakah berkasnya belum diunggah atau
 * namanya salah ketik.
 */
final class LampiranMiners
{
    /**
     * [kolom => [label, wajib, untuk jenis kartu apa]]
     *
     * `jenis: null` berarti BERLAKU UNTUK SEMUA jenis kartu, termasuk
     * Visitor. Hanya KTP dan berkas induksi yang begitu — kartu tamu
     * tidak menuntut SPDK, form departemen, maupun permohonan permit,
     * dan menuntutnya akan membuat kartu tamu tidak pernah dapat
     * terbit padahal syaratnya memang lebih ringan.
     *
     * `wajib` mengikuti D'Best: yang bertanda bintang di sana wajib di
     * sini. Yang tidak wajib tetap didaftarkan — lampiran yang tidak
     * punya tempat mengunggah sama saja dengan tidak ada.
     *
     * @var array<string,array{label:string,wajib:bool,jenis:list<string>|null}>
     */
    public const KARTU = [
        'berkas_ktp' => [
            'label' => 'KTP',
            'wajib' => true,
            'jenis' => null,          // seluruh jenis kartu
        ],
        'berkas_permohonan' => [
            'label' => 'Permohonan Permit',
            'wajib' => true,
            'jenis' => [AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE],
        ],
        'berkas_spdk' => [
            'label' => 'Form SPDK',
            'wajib' => true,
            'jenis' => [AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE],
        ],
        'berkas_dept' => [
            'label' => 'Form Khusus Departemen',
            'wajib' => true,
            'jenis' => [AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE],
        ],
        'berkas_lotto' => [
            'label' => 'Lotto / Sertifikat Welder',
            'wajib' => false,
            'jenis' => [AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE],
        ],
        'berkas_blasting' => [
            'label' => 'Training / Blasting',
            'wajib' => false,
            'jenis' => [AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE],
        ],

        /* Khusus Mine License — dasar mengemudi. */
        'berkas_sim' => [
            'label' => 'Berkas SIM kepolisian',
            'wajib' => true,
            'jenis' => [AlurMiner::KARTU_LICENSE],
        ],
        'berkas_ddt' => [
            'label' => 'Sertifikat Defensive Driving',
            'wajib' => false,
            'jenis' => [AlurMiner::KARTU_LICENSE],
        ],
        'berkas_induksi' => [
            'label' => 'Berkas Induksi',
            'wajib' => false,
            'jenis' => null,
        ],
    ];

    /**
     * Berkas uji SATU UNIT SIMPER — "Attachment Simper" di D'Best.
     *
     * Melekat pada barisnya sendiri, bukan pada kartunya, dan itu bukan
     * kerapian melainkan dasar izinnya. Seorang operator diuji rambu,
     * teori, dan praktik SEKALI UNTUK TIAP UNIT: yang lulus untuk
     * Excavator PC 200 belum tentu pernah menyentuh PC 500. Menaruh
     * ketiga berkasnya di kartunya membuat satu berkas uji seolah
     * menjadi dasar bagi seluruh unit yang tercantum di sana.
     *
     * Evaluasi Test satu-satunya yang tidak wajib — di D'Best pun ia
     * yang tidak bertanda bintang. Ia lembar penilaian pengawas atas
     * ujiannya, bukan bukti ujiannya sendiri.
     *
     * @var array<string,array{label:string,wajib:bool}>
     */
    public const UNIT = [
        'berkas_rambu'  => ['label' => 'Berkas Rambu',  'wajib' => true],
        'berkas_teori'  => ['label' => 'Berkas Teori',  'wajib' => true],
        'hasil_praktek' => ['label' => 'Hasil Praktek', 'wajib' => true],
        'evaluasi'      => ['label' => 'Evaluasi Test', 'wajib' => false],
    ];

    /**
     * Berkas satu sertifikat kompetensi — "Attachment Sertifikasi".
     *
     * Kolomnya sudah ada sejak lama di `paspor_sertifikat`, dan selama
     * itu tidak pernah dapat diisi: formulirnya tidak punya medan, dan
     * aturan validasinya tidak menyebutkannya. Yang tersimpan karena itu
     * hanya NAMA sertifikatnya — dan nama sertifikat tidak dapat
     * dibedakan dari sertifikat yang tidak pernah ada.
     *
     * @var array<string,array{label:string,wajib:bool}>
     */
    public const SERTIFIKAT = [
        'berkas' => ['label' => 'File Sertifikasi', 'wajib' => true],
    ];

    /** Kolom lampiran yang berlaku bagi satu jenis kartu. */
    public static function untukJenis(?string $jenis): array
    {
        $out = [];

        foreach (self::KARTU as $kolom => $l) {
            if ($l['jenis'] === null || in_array($jenis, $l['jenis'], true)) {
                $out[$kolom] = $l;
            }
        }

        return $out;
    }

    /** @return list<string> seluruh nama kolom lampiran kartu */
    public static function kolom(): array
    {
        return array_keys(self::KARTU);
    }

    /**
     * Seluruh nama kolom yang boleh diunggah lewat satu pintu unggahan.
     *
     * Ketiga katalog disatukan DI SINI, bukan disalin ke aturan
     * validasi. Pintu unggahannya satu — `POST /miners/lampiran` —
     * sedangkan yang mengunggah tiga layar berbeda, dan daftar putih
     * yang ditulis tangan di controller akan tertinggal pada layar
     * keempat.
     *
     * @return list<string>
     */
    public static function kolomUnggah(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::KARTU),
            array_keys(self::UNIT),
            array_keys(self::SERTIFIKAT),
        )));
    }

    /**
     * Kode jenis berkas bagi satu kolom uji unit SIMPER.
     *
     * Berawalan `unt-` supaya tidak pernah bertabrakan dengan lampiran
     * kartu (`kar-`) maupun jenis yang ditulis tangan di Berkas::TERSAJI
     * — dua kolom di antaranya, `berkas_rambu` dan `berkas_teori`,
     * berejaan mirip dengan kolom kartu dan akan tertukar tanpa awalan.
     */
    public static function jenisUnit(string $kolom): string
    {
        return 'unt-'.str_replace('_', '-', $kolom);
    }

    /**
     * Berkas uji WAJIB yang belum ada pada satu unit.
     *
     * @return list<string> labelnya
     */
    public static function unitKurang(object $u): array
    {
        $kurang = [];

        foreach (self::UNIT as $kolom => $l) {
            if (!$l['wajib']) continue;
            if (blank($u->{$kolom})) $kurang[] = $l['label'];
        }

        return $kurang;
    }

    /**
     * Bentuk siap gambar bagi keempat berkas uji satu unit.
     *
     * @return list<array<string,mixed>>
     */
    public static function unitUntukLayar(?object $u): array
    {
        $out = [];

        foreach (self::UNIT as $kolom => $l) {
            $ada = $u && filled($u->{$kolom});

            $out[] = [
                'kolom' => $kolom,
                'label' => $l['label'],
                'wajib' => $l['wajib'],
                'ada'   => $ada,
                'url'   => $ada ? Berkas::url($u, self::jenisUnit($kolom)) : null,
            ];
        }

        return $out;
    }

    /**
     * Bentuk siap gambar bagi berkas satu sertifikat kompetensi.
     *
     * Dipanggil dengan null untuk memperoleh KATALOGNYA — medan unggah
     * bagi sertifikat yang belum ada. Bentuknya sengaja sama dengan
     * lampiran lain supaya layar dapat menggambar ketiganya dengan satu
     * potong markup.
     *
     * @return list<array<string,mixed>>
     */
    public static function sertifikatUntukLayar(?object $s): array
    {
        $out = [];

        foreach (self::SERTIFIKAT as $kolom => $l) {
            $ada = $s && filled($s->{$kolom});

            $out[] = [
                'kolom' => $kolom,
                'label' => $l['label'],
                'wajib' => $l['wajib'],
                'ada'   => $ada,
                'url'   => $ada ? Berkas::url($s, 'srt') : null,
            ];
        }

        return $out;
    }

    /**
     * Lampiran WAJIB yang belum ada pada satu kartu.
     *
     * Dipakai menahan penerbitan, bukan sekadar menandai. Permit yang
     * terbit tanpa SPDK adalah izin masuk area tambang yang syaratnya
     * tidak pernah diperiksa — dan setelah terbit, tidak ada yang
     * kembali memeriksanya.
     *
     * @return list<string> labelnya
     */
    public static function kurang(PasporKartu $k): array
    {
        $kurang = [];

        foreach (self::untukJenis($k->jenis) as $kolom => $l) {
            if (!$l['wajib']) continue;
            if (blank($k->{$kolom})) $kurang[] = $l['label'];
        }

        return $kurang;
    }

    /**
     * Bentuk siap gambar: tiap lampiran beserta keadaannya.
     *
     * @return list<array<string,mixed>>
     */
    public static function untukLayar(?PasporKartu $k, ?string $jenis = null): array
    {
        $out = [];

        foreach (self::untukJenis($jenis ?? $k?->jenis) as $kolom => $l) {
            $ada = $k && filled($k->{$kolom});

            $out[] = [
                'kolom' => $kolom,
                'label' => $l['label'],
                'wajib' => $l['wajib'],
                'ada'   => $ada,
                'url'   => $ada ? Berkas::url($k, Berkas::jenisLampiran($kolom)) : null,
            ];
        }

        return $out;
    }
}
