<?php

namespace App\Support\Investigasi;

use App\Models\Investigasi\Insiden;
use App\Models\Investigasi\KlasifikasiRegulasi;
use App\Models\Investigasi\MatriksRisiko;
use Carbon\Carbon;

/**
 * Triase insiden — langkah yang menentukan seluruh sisa alur.
 *
 * Dari kemungkinan × keparahan dihitung skor risiko, dari skor
 * ditentukan level investigasi, dan dari level ditentukan berapa tahap
 * yang harus dilalui, metode analisis apa yang wajib, serta siapa yang
 * menyetujui penutupannya.
 *
 * MENGAPA INI LAYAR TERSENDIRI. Tanpa triase, penilaian "seberapa
 * serius ini" jatuh kepada orang yang kebetulan menerima laporan — dan
 * dua kejadian yang sama beratnya berakhir dengan kedalaman investigasi
 * yang jauh berbeda, bergantung siapa yang sedang piket. Matriks membuat
 * keputusan itu dapat ditelusuri dan dibantah.
 *
 * KEPARAHAN 5 SELALU L4. Aturannya tersimpan di master matriks, bukan di
 * sini, tetapi perlu diingat saat membaca berkas ini: kejadian fatal
 * yang "kecil kemungkinannya" tetap menuntut investigasi penuh — itulah
 * seluruh alasan investigasi kejadian berpotensi tinggi ada.
 */
final class Triase
{
    /** Metode analisis yang diwajibkan tiap level. */
    public const METODE = [
        'L1' => ['5why', 'kronologi'],
        'L2' => ['scat', 'icam', '5why', 'kronologi', 'penghalang'],
        'L3' => ['icam', 'bowtie', '5why', 'kronologi', 'penghalang'],
        'L4' => ['icam', 'tripod', 'bowtie', 'hfacs', '5why', 'kronologi', 'penghalang'],
    ];

    public const NAMA_LEVEL = [
        'L1' => 'L1 · Ringan',
        'L2' => 'L2 · Menengah',
        'L3' => 'L3 · Serius',
        'L4' => 'L4 · Katastropik',
    ];

    /** Siapa yang menyetujui penutupan investigasi pada tiap level. */
    public const PENYETUJU = [
        'L1' => 'Investigator / Pengawas',
        'L2' => 'Ketua Investigasi',
        'L3' => 'Manajer Investigasi',
        'L4' => 'Manajemen + notifikasi Kepala Inspektur Tambang',
    ];

    public const LABEL_KEMUNGKINAN = [
        1 => 'Sangat jarang', 2 => 'Jarang', 3 => 'Mungkin', 4 => 'Sering', 5 => 'Hampir pasti',
    ];

    public const LABEL_KEPARAHAN = [
        1 => 'Tidak signifikan', 2 => 'Kecil', 3 => 'Sedang', 4 => 'Berat', 5 => 'Katastropik',
    ];

    /**
     * Lima kriteria kecelakaan tambang — Kepdirjen Minerba 185/2019.
     *
     * Kelimanya harus terpenuhi bersamaan. Disimpan satu per satu dan
     * bukan sebagai satu kesimpulan: yang ditanya Inspektur Tambang
     * adalah kriteria mana yang tidak terpenuhi, dan kesimpulan tunggal
     * tidak dapat menjawabnya.
     */
    public const KRITERIA = [
        'k1_benar_terjadi'      => 'Benar-benar terjadi',
        'k2_mencederai_pekerja' => 'Mencederai pekerja tambang atau orang yang diberi izin',
        'k3_akibat_kegiatan'    => 'Akibat kegiatan usaha pertambangan',
        'k4_jam_kerja'          => 'Terjadi pada jam kerja pekerja yang mendapat cedera',
        'k5_wilayah_usaha'      => 'Terjadi di dalam wilayah kegiatan usaha pertambangan',
    ];

    /**
     * Berapa jam sejak kejadian sebelum tenggatnya jatuh.
     *
     * Kepdirjen Minerba 185/2019 mewajibkan KTT/PTL menyelidiki paling
     * lambat 2×24 jam, dan pelaporan awal disampaikan sesaat sesudah
     * kejadian. Sumber sekunder menyebut 1×24 jam untuk pelaporan awal;
     * yang dipakai di sini angka yang LEBIH KETAT — keliru terlalu cepat
     * hanya merepotkan, keliru terlalu lambat melanggar.
     */
    public const JAM_LAPOR   = 24;
    public const JAM_SELIDIK = 48;

    /**
     * Hitung skor, pita, dan level dari kemungkinan × keparahan.
     *
     * Keparahan yang dipakai adalah yang TERTINGGI antara keparahan
     * nyata dan keparahan potensial. Sebuah unit yang lepas kendali lalu
     * berhenti satu meter dari pekerja tidak melukai siapa pun, jadi
     * keparahan nyatanya 1 — tetapi potensinya fatal. Memakai keparahan
     * nyata saja menurunkan kejadian semacam itu ke L1 dan membuang
     * justru pelajaran yang paling mahal.
     *
     * @return array{skor:int, pita:string, level:string, keparahanDipakai:int}|null
     */
    public static function hitung(mixed $kemungkinan, mixed $keparahan, mixed $keparahanPotensial = null): ?array
    {
        $kemungkinan = (int) $kemungkinan;
        $keparahan   = (int) $keparahan;

        if ($kemungkinan < 1 || $kemungkinan > 5 || $keparahan < 1 || $keparahan > 5) {
            return null;
        }

        $dipakai = min(5, max(1, max($keparahan, (int) $keparahanPotensial)));

        $sel = MatriksRisiko::where('kemungkinan', $kemungkinan)
            ->where('keparahan', $dipakai)->first();

        if (! $sel) return null;

        return [
            'skor'             => (int) $sel->skor,
            'pita'             => $sel->pita,
            'level'            => $sel->level_investigasi,
            'keparahanDipakai' => $dipakai,
        ];
    }

    /** Metode analisis yang berlaku bagi sebuah level. */
    public static function metode(?string $level): array
    {
        return self::METODE[$level] ?? self::METODE['L1'];
    }

    /**
     * Terapkan hasil triase pada sebuah insiden, sekalian hitung tenggatnya.
     *
     * Tenggat dihitung dari WAKTU KEJADIAN, bukan waktu pelaporan.
     * Dihitung dari pelaporan, keterlambatan melapor akan memperpanjang
     * tenggatnya sendiri — dan tidak ada satu pun laporan yang akan
     * pernah terlambat.
     */
    public static function terapkan(Insiden $insiden, array $isi): Insiden
    {
        $hasil = self::hitung(
            $isi['kemungkinan'] ?? null,
            $isi['keparahan'] ?? null,
            $isi['keparahan_potensial'] ?? null,
        );

        $insiden->kemungkinan         = $isi['kemungkinan'] ?? null;
        $insiden->keparahan           = $isi['keparahan'] ?? null;
        $insiden->keparahan_potensial = $isi['keparahan_potensial'] ?? null;

        if ($hasil) {
            $insiden->skor_risiko       = $hasil['skor'];
            $insiden->pita_risiko       = $hasil['pita'];
            $insiden->level_investigasi = $hasil['level'];
        }

        foreach (array_keys(self::KRITERIA) as $k) {
            $insiden->{$k} = ! empty($isi[$k]);
        }

        if (! empty($isi['klasifikasi_regulasi_id'])) {
            $insiden->klasifikasi_regulasi_id = $isi['klasifikasi_regulasi_id'];
        }

        /* Wajib lapor mengikuti klasifikasi regulasinya, BUKAN ditebak
           dari skor risiko: kejadian berbahaya tanpa korban satu pun
           tetap wajib dilaporkan, dan skornya bisa saja rendah. */
        $reg = $insiden->klasifikasi_regulasi_id
            ? KlasifikasiRegulasi::find($insiden->klasifikasi_regulasi_id)
            : null;

        $insiden->wajib_lapor_kait = (bool) $reg?->wajib_lapor_kait;

        if ($insiden->wajib_lapor_kait && $insiden->tanggal_kejadian) {
            $mulai = Carbon::parse(
                $insiden->tanggal_kejadian->format('Y-m-d').' '.($insiden->waktu_kejadian ?: '00:00:00')
            );

            $insiden->tenggat_lapor   = $mulai->copy()->addHours(self::JAM_LAPOR);
            $insiden->tenggat_selidik = $mulai->copy()->addHours(self::JAM_SELIDIK);
        } else {
            $insiden->tenggat_lapor   = null;
            $insiden->tenggat_selidik = null;
        }

        $insiden->status = 'ditriase';
        $insiden->save();

        return $insiden;
    }

    /**
     * Bentuk matriks 5×5 siap gambar.
     *
     * Dibaca dari tabelnya, bukan dihitung ulang di sini. Menghitungnya
     * ulang berarti layar dan penyimpanan memakai dua rumus yang harus
     * sama selamanya — dan yang pertama berselisih tidak akan pernah
     * memberi galat, hanya sel yang warnanya salah.
     */
    public static function matriks(): array
    {
        return MatriksRisiko::orderBy('kemungkinan')->orderBy('keparahan')->get()
            ->map(fn (MatriksRisiko $m) => [
                'kemungkinan' => $m->kemungkinan,
                'keparahan'   => $m->keparahan,
                'skor'        => $m->skor,
                'pita'        => $m->pita,
                'level'       => $m->level_investigasi,
            ])->values()->all();
    }

    /** Warna tiap pita risiko — dipakai matriks dan lencana. */
    public const WARNA_PITA = [
        'rendah' => '#15803D',
        'sedang' => '#B45309',
        'tinggi' => '#C2410C',
        'kritis' => '#991B1B',
    ];

    public static function warnaPita(?string $pita): string
    {
        return self::WARNA_PITA[$pita] ?? '#78716C';
    }
}
