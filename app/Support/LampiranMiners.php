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
