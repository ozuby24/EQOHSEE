<?php

namespace App\Support;

/**
 * Matriks Metode dan Sampel Audit — komponen ke-8 Rencana Audit.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019, dibaca lewat berkas
 * Rencana Audit sungguhan (PT Indo Sejahtera Manunggal Site PT Multi
 * Harapan Utama, 2023), yang memuatnya sebagai tabel empat kolom:
 * NO | KRITERIA | METODE AUDIT | SAMPLE.
 *
 * TIGA METODE, BUKAN SATU KOTAK TEKS. Tiap kriteria dapat dibuktikan
 * dengan tinjauan dokumen dan rekaman, wawancara, observasi, atau
 * gabungannya — dan tiap metode punya sampelnya sendiri. Berkas acuan
 * memisahkannya persis begitu: "Tinjauan Dokumen dan Rekaman" mendaftar
 * dokumen, "Wawancara" mendaftar jabatan beserta cara triangulasinya,
 * "Observasi" mendaftar area.
 *
 * PENGECUALIAN DITETAPKAN DI SINI, SEKALI. Rencana Audit acuan memuat
 * daftar "Pengecualian" — sub-elemen yang tidak berlaku bagi auditi,
 * misalnya gudang bahan peledak pada perusahaan jasa pengeboran. Butir
 * yang dinyatakan tidak berlaku di sini tidak perlu dinyatakan ulang
 * satu per satu pada formulir penilaian, tempat lupa menandainya
 * membuat butirnya ikut membagi nilai akhir dan menekan skor tanpa
 * sebab.
 *
 * Bentuk simpanannya, satu baris tiap kode kriteria:
 *   $sampel['I.1'] = [
 *     'na'        => false,
 *     'dokumen'   => "Dokumen Risk profile\nDokumen Kebijakan",
 *     'wawancara' => "3 Orang (Triangulasi) Anggota Komite",
 *     'observasi' => "Area Office\nArea Workshop",
 *     'ket'       => 'alasan bila tidak berlaku',
 *   ];
 */
final class SmkpSampel
{
    /** Tiga metode pembuktian, dengan istilah persis seperti pada berkas audit. */
    public const METODE = [
        'dokumen'   => 'Tinjauan Dokumen dan Rekaman',
        'wawancara' => 'Wawancara',
        'observasi' => 'Observasi',
    ];

    /**
     * Seluruh kriteria yang dapat disampel, urut seperti pada acuan.
     *
     * Sub-elemen berincian TIDAK ikut didaftar sebagai kriteria tersendiri;
     * yang didaftar rinciannya. Mendaftar keduanya membuat auditor mengisi
     * sampel dua kali untuk hal yang sama — dan formulir penilaian pun
     * hanya menilai yang terdalam.
     *
     * @return list<array{kode:string,nama:string,elemen:string,elemenNama:string,induk:?string}>
     */
    public static function kriteria(): array
    {
        $out = [];

        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] ?? [] as $s) {
                if (!empty($s['subsub'])) {
                    foreach ($s['subsub'] as $x) {
                        $out[] = [
                            'kode'       => $x['kode'],
                            'nama'       => $x['nama'],
                            'elemen'     => $e['kode'],
                            'elemenNama' => $e['nama'],
                            'induk'      => $s['kode'].' '.$s['nama'],
                        ];
                    }
                    continue;
                }

                $out[] = [
                    'kode'       => $s['kode'],
                    'nama'       => $s['nama'],
                    'elemen'     => $e['kode'],
                    'elemenNama' => $e['nama'],
                    'induk'      => null,
                ];
            }
        }

        return $out;
    }

    /** Kode kriteria yang sah, untuk menolak kode karangan dari formulir. */
    public static function kodeSah(): array
    {
        return array_column(self::kriteria(), 'kode');
    }

    /**
     * Membersihkan kiriman formulir menjadi bentuk simpanan.
     *
     * Baris yang seluruhnya kosong dan tidak ditandai tidak berlaku
     * DIBUANG, bukan disimpan sebagai baris hampa: delapan puluh baris
     * kosong pada tiap audit membuat berkasnya berat dan membuat "sudah
     * diisi" tidak dapat dibedakan dari "belum disentuh".
     */
    public static function bersihkan(array $kiriman): array
    {
        $out = [];

        /* Ditelusuri menurut URUTAN ACUAN, bukan urutan kiriman formulir.
           Validasi Laravel menyusun ulang kunci menurut urutan aturannya
           — yang punya `na` muncul lebih dulu daripada yang hanya punya
           `dokumen` — dan urutan itulah yang akan tercetak pada Rencana
           Audit. Tabel kriteria yang melompat dari I.4 ke I.1 tidak dapat
           dibaca sebagai daftar acuan oleh siapa pun. */
        foreach (self::kodeSah() as $kode) {
            $baris = $kiriman[$kode] ?? null;

            if (!is_array($baris)) continue;

            $na   = !empty($baris['na']);
            $isi  = [];

            foreach (array_keys(self::METODE) as $m) {
                $t = trim((string) ($baris[$m] ?? ''));
                if ($t !== '') $isi[$m] = mb_substr($t, 0, 2000);
            }

            $ket = trim((string) ($baris['ket'] ?? ''));

            if (!$na && $isi === [] && $ket === '') continue;

            $out[$kode] = ['na' => $na] + $isi + ($ket !== '' ? ['ket' => mb_substr($ket, 0, 500)] : []);
        }

        return $out;
    }

    /** Kode kriteria yang dinyatakan tidak berlaku bagi auditi. */
    public static function dikecualikan(?array $sampel): array
    {
        $out = [];

        foreach ((array) $sampel as $kode => $baris) {
            if (!empty($baris['na'])) $out[] = (string) $kode;
        }

        return $out;
    }

    /** Metode yang dipilih untuk satu kriteria, sebagai daftar label. */
    public static function metodeTerpilih(?array $baris): array
    {
        $out = [];

        foreach (self::METODE as $k => $label) {
            if (trim((string) (($baris ?? [])[$k] ?? '')) !== '') $out[] = $label;
        }

        return $out;
    }

    /**
     * Sejauh mana matriksnya terisi.
     *
     * "Terisi" berarti kriteria itu punya sekurang-kurangnya satu metode
     * dengan sampel, ATAU dinyatakan tidak berlaku. Kriteria yang
     * dikosongkan sama sekali adalah kriteria yang belum direncanakan cara
     * pembuktiannya — dan itulah yang hendak ditunjukkan angka ini.
     *
     * @return array{total:int,terisi:int,na:int,kurang:int,persen:int,lengkap:bool}
     */
    public static function rekap(?array $sampel): array
    {
        $s      = (array) $sampel;
        $total  = count(self::kriteria());
        $terisi = 0;
        $na     = 0;

        foreach (self::kriteria() as $k) {
            $baris = $s[$k['kode']] ?? null;

            if (!empty($baris['na'])) { $na++; $terisi++; continue; }
            if (self::metodeTerpilih($baris) !== []) $terisi++;
        }

        return [
            'total'   => $total,
            'terisi'  => $terisi,
            'na'      => $na,
            'kurang'  => $total - $terisi,
            'persen'  => $total > 0 ? (int) round($terisi / $total * 100) : 0,
            'lengkap' => $total > 0 && $terisi === $total,
        ];
    }
}
