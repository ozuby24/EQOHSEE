<?php

namespace App\Support;

use App\Models\{CompliancePoint, ComplianceSubject};

/**
 * Identifikasi dan evaluasi pemenuhan — aturan hitungnya, satu tempat.
 *
 * Persentase pemenuhan adalah angka yang dibawa ke rapat, dikirim ke
 * pemegang IUP, dan ditempel di laporan audit. Angka seperti itu harus
 * dihitung dengan cara yang SAMA di mana pun ia muncul — di dasbor, di
 * bar tiap baris register, di rekap bulanan, dan di ketiga berkas
 * ekspornya. Rumus yang disalin ke enam tempat akan berbeda di salah
 * satunya dalam setahun, dan yang membandingkan dua angka yang
 * seharusnya sama tidak akan tahu mana yang benar.
 *
 * ── Tiga keadaan, bukan dua ──
 *
 * Comply dan Not Comply adalah penilaian. N/A juga penilaian: butir itu
 * tidak mengikat kegiatan perusahaan. Yang KEEMPAT — belum dinilai —
 * bukan penilaian sama sekali, melainkan pekerjaan yang belum
 * dikerjakan.
 *
 * N/A tidak ikut menjadi pembagi: perusahaan yang separuh pasalnya
 * memang tidak berlaku akan selamanya terbaca lima puluh persen
 * meskipun seluruh yang berlaku sudah dipenuhi. Belum dinilai juga
 * tidak ikut menjadi pembagi, tetapi ia DILAPORKAN terpisah dan besar —
 * sebab register yang baru sepuluh persen dinilai dapat menunjukkan
 * seratus persen pemenuhan, dan angka itu benar sekaligus menyesatkan.
 */
final class Kepatuhan
{
    /** Dari mana kewajibannya datang. */
    public const SUMBER = [
        'Peraturan' => 'Peraturan Perundangan',
        'ISO'       => 'Klausul Standar ISO',
        'Dokumen'   => 'Dokumen Terkendali',
    ];

    public const STATUS = ['Comply', 'Not Comply', 'N/A'];

    /**
     * Jenis peraturan, dari yang tertinggi.
     *
     * Urutannya bukan abjad melainkan hierarki perundangan: yang
     * membaca register berharap Undang-Undang berada di atas Peraturan
     * Menteri, bukan di bawah "Peraturan Daerah" karena huruf D.
     */
    public const JENIS = [
        'Undang-Undang',
        'Peraturan Pemerintah',
        'Peraturan Presiden',
        'Peraturan Menteri',
        'Keputusan Menteri',
        'Keputusan Direktur Jenderal',
        'Peraturan Daerah',
        'Standar Nasional / Internasional',
        'Persyaratan Lain',
    ];

    /**
     * Aspek memakai kunci pilar EQOHSEE, bukan taksonomi kelima.
     *
     * Warna, nama, dan penempatan tiap pilar sudah ditetapkan di
     * App\Support\Pillars. Daftar aspek sendiri berarti dua tempat yang
     * harus disamakan setiap kali warnanya berubah — dan yang satu
     * pasti tertinggal.
     *
     * NULL berarti lintas aspek, ditampilkan sebagai "Umum".
     */
    public const ASPEK = ['safety', 'occhealth', 'hygiene', 'environment', 'energy', 'quality', 'engineering', 'konservasi'];

    public static function namaAspek(?string $aspek): string
    {
        if (!$aspek) return 'Umum';

        return Pillars::get($aspek)['nama'] ?? ucfirst($aspek);
    }

    public static function warnaAspek(?string $aspek): string
    {
        if (!$aspek) return '#78716C';

        return Pillars::get($aspek)['warna'] ?? '#78716C';
    }

    /** Daftar aspek siap pakai untuk pemilih dan saringan. */
    public static function daftarAspek(): array
    {
        $out = [['nilai' => '', 'nama' => 'Umum', 'warna' => self::warnaAspek(null)]];

        foreach (self::ASPEK as $a) {
            $out[] = ['nilai' => $a, 'nama' => self::namaAspek($a), 'warna' => self::warnaAspek($a)];
        }

        return $out;
    }

    /**
     * Persentase pemenuhan: comply dibagi yang BENAR-BENAR DINILAI.
     *
     * Memulangkan null, bukan nol, bila belum ada satu pun yang dinilai.
     * Nol berarti "seluruhnya tidak comply" — pernyataan yang jauh lebih
     * keras daripada "belum ada yang dinilai", dan keduanya tidak boleh
     * tampil sebagai angka yang sama.
     */
    public static function persen(int $comply, int $notComply): ?float
    {
        $dinilai = $comply + $notComply;

        return $dinilai ? round($comply / $dinilai * 100, 1) : null;
    }

    /**
     * Rekap dari deretan status.
     *
     * @param  array<int,string|null>  $status
     * @return array{total:int,comply:int,notComply:int,na:int,belum:int,dinilai:int,persen:float|null}
     */
    public static function rekap(array $status): array
    {
        $comply    = 0;
        $notComply = 0;
        $na        = 0;
        $belum     = 0;

        foreach ($status as $s) {
            match ($s) {
                'Comply'     => $comply++,
                'Not Comply' => $notComply++,
                'N/A'        => $na++,
                default      => $belum++,
            };
        }

        return [
            'total'     => count($status),
            'comply'    => $comply,
            'notComply' => $notComply,
            'na'        => $na,
            'belum'     => $belum,
            'dinilai'   => $comply + $notComply,
            'persen'    => self::persen($comply, $notComply),
        ];
    }

    /**
     * Kode register per aspek: S1, S2, H1, E1, U1 …
     *
     * Hurufnya dari nama aspeknya, angkanya berurut dalam aspek itu
     * untuk perusahaan dan tahun yang sama. Auditor menunjuk baris
     * dengan kode ini di berita acara, jadi kodenya harus pendek dan
     * tidak berubah sesudah terbit.
     */
    public static function kodeBaru(?string $aspek, ?int $companyId, int $tahun): string
    {
        $huruf = strtoupper(mb_substr(self::namaAspek($aspek), 0, 1));

        $n = ComplianceSubject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('tahun', $tahun)
            ->when($aspek === null, fn ($q) => $q->whereNull('aspek'))
            ->when($aspek !== null, fn ($q) => $q->where('aspek', $aspek))
            ->count();

        return $huruf.($n + 1);
    }

    /**
     * Rekap satu himpunan subjek, beserta pecahannya per aspek.
     *
     * Draf hasil rangkuman mesin TIDAK ikut dihitung. Rangkuman mesin
     * salah dengan cara yang meyakinkan, dan angka pemenuhan yang
     * separuhnya berasal dari pasal yang belum pernah dibaca manusia
     * adalah angka yang lebih buruk daripada tidak ada angka.
     *
     * @param  iterable<ComplianceSubject>  $subjek  sudah memuat relasi points
     */
    public static function ringkas(iterable $subjek): array
    {
        $status  = [];
        $perAspek = [];
        $draf    = 0;
        $kosong  = [];

        foreach ($subjek as $s) {
            if ($s->status === 'Draf') { $draf++; continue; }

            $milik = $s->points->pluck('status')->all();

            if (!$milik) { $kosong[] = $s; continue; }

            $status = array_merge($status, $milik);

            $kunci = $s->aspek ?? '';
            $perAspek[$kunci] = array_merge($perAspek[$kunci] ?? [], $milik);
        }

        $aspek = [];
        foreach ($perAspek as $kunci => $daftar) {
            $aspek[] = [
                'aspek' => $kunci ?: null,
                'nama'  => self::namaAspek($kunci ?: null),
                'warna' => self::warnaAspek($kunci ?: null),
            ] + self::rekap($daftar);
        }

        /* Diurutkan menurut persentase menaik: yang paling tertinggal
           dibaca lebih dulu. Aspek yang belum dinilai sama sekali
           (persen null) ditaruh paling akhir — ia bukan yang terburuk,
           ia hanya belum diketahui. */
        usort($aspek, fn ($a, $b) => [$a['persen'] === null, $a['persen'] ?? 0]
                                 <=> [$b['persen'] === null, $b['persen'] ?? 0]);

        return self::rekap($status) + [
            'aspek'  => $aspek,
            'draf'   => $draf,
            'kosong' => $kosong,
        ];
    }

    /**
     * Butir yang menunggu tindak lanjut, yang paling mendesak lebih dulu.
     *
     * Urutannya: yang targetnya sudah lewat, lalu yang targetnya paling
     * dekat, lalu yang belum dijadwalkan sama sekali. Yang belum
     * dijadwalkan ditaruh terakhir bukan karena tidak penting melainkan
     * karena tidak ada tanggal yang dapat dibandingkan — dan daftar yang
     * mengurutkan NULL sebagai tanggal paling awal menaruh seluruh
     * pekerjaan tak terjadwal di puncak setiap hari.
     *
     * @return \Illuminate\Support\Collection<int,CompliancePoint>
     */
    public static function menunggu(?int $companyId, int $tahun, ?string $sumber = null)
    {
        return CompliancePoint::query()
            ->belumComply()
            ->whereHas('subject', function ($q) use ($companyId, $tahun, $sumber) {
                $q->where('tahun', $tahun)->where('status', 'Tetap')
                  ->when($companyId, fn ($b) => $b->where('company_id', $companyId))
                  ->when($sumber, fn ($b) => $b->where('sumber', $sumber));
            })
            ->with('subject')
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->target === null) <=> ($b->target === null),
                fn ($a, $b) => ($a->target?->timestamp ?? 0) <=> ($b->target?->timestamp ?? 0),
            ])
            ->values();
    }
}
