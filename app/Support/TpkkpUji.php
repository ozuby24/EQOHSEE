<?php

namespace App\Support;

/**
 * Pengujian (metode PJ) — bank soal, penyusunan sesi, penilaian, dan
 * penarikannya ke skor penilaian.
 *
 * Metode PJ pada instrumen PTPKKP menilai satu item saja: 1.1.1
 * "Kesadaran Pekerja terhadap Risiko Keselamatan Pertambangan". Yang
 * dinilai adalah kesadaran ORANG BANYAK, bukan seorang peserta — karena
 * itu nilai perorangan tidak pernah ditampilkan dan tidak pernah
 * dipakai sendirian: tiap peserta dikonversi ke tingkat 1–5, lalu
 * seluruh peserta dirata-rata dan dibulatkan menjadi satu angka.
 *
 * ── Yang dijaga di sini, dan mengapa ──
 *
 * KUNCI JAWABAN TIDAK PERNAH DIKIRIM KE PERAMBAN. Bank soal menyimpan
 * jawaban benar sebagai elemen PERTAMA tiap pilihan; yang dikirim ke
 * layar hanya pilihan yang sudah diacak, tanpa penanda apa pun. Acuan
 * yang saya ikuti memeriksa jawaban di sisi klien lalu mengirim
 * nilainya — di sana, seluruh kunci jawaban ada di dalam berkas yang
 * dapat dibuka siapa saja lewat View Source, dan nilai yang dikirim
 * dapat diketik tangan. Di sini pemeriksaan terjadi di server dan yang
 * dikirim peserta hanyalah huruf pilihannya.
 *
 * ACAK DUA LAPIS. Soalnya diacak (15 dari 50) dan urutan pilihannya
 * diacak — jadi dua orang bersebelahan tidak melihat soal yang sama,
 * dan kalaupun sama, "jawabannya B" tidak berarti apa-apa.
 *
 * SATU ARAH. Sesi menyimpan nomor soal yang sedang dikerjakan; soal
 * berikutnya baru disusun setelah yang sekarang dijawab, dan yang sudah
 * lewat tidak dapat dibuka lagi.
 */
final class TpkkpUji
{
    /** Metode pada instrumen yang diisi hasil pengujian ini. */
    public const METODE = 'PJ';

    /**
     * Kelonggaran waktu di sisi server, dalam detik.
     *
     * Batas waktunya ditegakkan server, bukan hanya oleh pencacah di
     * layar — pencacah di layar dapat dihentikan lewat konsol peramban.
     * Tetapi menegakkannya tepat pada detik ke-90 akan menghukum
     * jaringan yang lambat: jawaban yang dikirim pada detik ke-89 dapat
     * tiba pada detik ke-91. Kelonggaran ini memisahkan keduanya.
     */
    public const KELONGGARAN = 5;

    private static ?array $ref = null;

    public static function ref(): array
    {
        return self::$ref ??= json_decode(
            file_get_contents(resource_path('data/tpkkp/pengujian.json')), true
        );
    }

    public static function meta(): array { return self::ref()['meta'] ?? []; }

    /** @return list<array{q:string,a:list<string>}> */
    public static function bank(): array { return self::ref()['questions'] ?? []; }

    public static function jumlahSoal(): int
    {
        return (int) min(self::meta()['pick'] ?? 15, count(self::bank()));
    }

    public static function detikPerSoal(): int
    {
        return (int) (self::meta()['secondsPerQuestion'] ?? 90);
    }

    public static function pita(): array { return self::ref()['levelBands'] ?? []; }

    /**
     * Persentase jawaban benar → tingkat rubrik 1–5.
     *
     * Pitanya dibaca berurutan: pct < lt → tingkat itu. Di atas pita
     * terakhir → 5.
     */
    public static function tingkatDari(?float $pct): ?int
    {
        if ($pct === null || is_nan($pct)) return null;

        foreach (self::pita() as $b) {
            if ($pct < (float) $b['lt']) return (int) $b['level'];
        }

        return 5;
    }

    /** Label tingkat sesuai penamaan instrumen (Dasar … Resilient). */
    public static function labelTingkat(?int $n): ?string
    {
        return $n === null ? null : (Tpkkp::LV[$n - 1] ?? null);
    }

    /* ================= penyusunan sesi ================= */

    /**
     * Susun satu sesi: soal acak, pilihan acak.
     *
     * Yang disimpan hanyalah NOMOR — nomor soal di bank dan urutan
     * pilihannya sebagai permutasi. Teks soalnya diambil dari bank saat
     * digambar, sehingga sesi yang tersimpan di session tetap kecil dan
     * kunci jawabannya tidak pernah ikut berpindah.
     *
     * @return list<array{s:int,o:list<int>}>
     */
    public static function susun(): array
    {
        $bank = self::bank();
        $pilih = self::acak(range(0, count($bank) - 1));
        $set = [];

        foreach (array_slice($pilih, 0, self::jumlahSoal()) as $n) {
            $set[] = ['s' => $n, 'o' => self::acak(range(0, count($bank[$n]['a']) - 1))];
        }

        return $set;
    }

    /**
     * Fisher–Yates dengan sumber acak kriptografis.
     *
     * `shuffle()` bawaan PHP memakai Mt19937 yang dapat diramalkan bila
     * keadaannya diketahui. Untuk pengacakan yang seluruh gunanya adalah
     * agar tidak dapat diramalkan, sumbernya harus `random_int`.
     *
     * @param  list<int>  $a
     * @return list<int>
     */
    private static function acak(array $a): array
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }

    /**
     * Soal ke-$idx dari sebuah sesi, siap digambar — TANPA kunci.
     *
     * @param  list<array{s:int,o:list<int>}>  $set
     * @return array{teks:string,pilihan:list<string>}
     */
    public static function soalKe(array $set, int $idx): array
    {
        $butir = self::bank()[$set[$idx]['s']];

        return [
            'teks'    => $butir['q'],
            'pilihan' => array_map(fn ($n) => $butir['a'][$n], $set[$idx]['o']),
        ];
    }

    /**
     * Posisi jawaban benar pada soal ke-$idx, sesudah diacak.
     *
     * Elemen ke-0 pada bank selalu jawaban benar; yang dicari adalah
     * ke mana ia berpindah setelah pengacakan.
     */
    public static function kunciKe(array $set, int $idx): int
    {
        return (int) array_search(0, $set[$idx]['o'], true);
    }

    /**
     * @param  list<array{s:int,o:list<int>}>  $set
     * @param  list<int|null>  $jawaban
     */
    public static function hitungBenar(array $set, array $jawaban): int
    {
        $benar = 0;

        foreach ($set as $i => $_) {
            if (($jawaban[$i] ?? null) !== null && (int) $jawaban[$i] === self::kunciKe($set, $i)) {
                $benar++;
            }
        }

        return $benar;
    }

    /**
     * Kunci identitas peserta — dasar "satu orang satu kali".
     *
     * Disamakan huruf besar-kecil dan spasi gandanya lebih dulu: yang
     * mengetik namanya di lapangan adalah manusia, dan "Budi Santoso"
     * dengan "budi  santoso" adalah orang yang sama.
     */
    public static function kunciIdentitas(array $identitas): string
    {
        $samakan = fn ($x) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $x), 'UTF-8'));

        return implode('|', array_map($samakan, [
            $identitas['nama'] ?? '',
            $identitas['jabatan'] ?? '',
            $identitas['dept'] ?? '',
            $identitas['perusahaan'] ?? '',
        ]));
    }

    /* ================= rekap ================= */

    /**
     * Ringkasan seluruh peserta.
     *
     * `tingkat` adalah yang benar-benar masuk penilaian: rerata tingkat
     * tiap peserta, dibulatkan setengah ke bawah seperti seluruh rerata
     * lain di modul ini.
     *
     * Reratanya diambil dari TINGKAT tiap peserta, bukan dari persentase
     * gabungan. Keduanya berbeda dan yang kedua salah: peserta dengan 15
     * soal benar dan peserta dengan 0 benar tidak menghasilkan "tingkat
     * rata-rata" yang sama dengan seorang peserta bernilai 50%, sebab
     * pitanya tidak linear.
     *
     * @return array{peserta:int,tingkat:int|null,rerataTingkat:float|null,rerataPct:float|null,sebaran:array<int,int>,pindahLayar:int}
     */
    public static function ringkas($rows): array
    {
        $rows = collect($rows);
        $n = $rows->count();

        $sebaran = array_fill(1, 5, 0);
        foreach ($rows as $r) {
            $t = (int) $r->tingkat;
            if ($t >= 1 && $t <= 5) $sebaran[$t]++;
        }

        $rerataTingkat = $n ? round($rows->avg('tingkat'), 2) : null;

        return [
            'peserta'       => $n,
            'tingkat'       => Tpkkp::roundLevel($rerataTingkat),
            'rerataTingkat' => $rerataTingkat,
            'rerataPct'     => $n ? round($rows->avg('skor_pct') * 100, 1) : null,
            'sebaran'       => $sebaran,
            'pindahLayar'   => (int) $rows->sum('pindah_layar'),
        ];
    }

    /**
     * Tulis tingkat gabungan ke skor metode PJ.
     *
     * PJ adalah metode TANPA entitas — nilainya tunggal, masuk ke
     * `['v']`, bukan ke `['e'][…]`. Item yang ditulis hanya yang memang
     * ber-metode PJ pada instrumen; pada instrumen 2026 hanya 1.1.1,
     * tetapi dicari dari instrumen supaya tetap benar bila instrumennya
     * berubah.
     *
     * @return array{scores:array,ditulis:list<string>}
     */
    public static function tulisKeSkor(array $scores, int $tingkat, int $peserta): array
    {
        $ditulis = [];
        $scores[self::METODE] ??= [];

        foreach (Tpkkp::allItems() as $it) {
            if (!in_array(self::METODE, $it['methods'], true)) continue;

            $rec = $scores[self::METODE][$it['code']] ?? ['v' => null, 'e' => [], 'ket' => ''];
            $rec['v']   = $tingkat;
            $rec['ket'] = 'Dari pengujian · '.$peserta.' peserta · '.now()->format('d M Y H:i');

            $scores[self::METODE][$it['code']] = $rec;
            $ditulis[] = $it['code'];
        }

        return ['scores' => $scores, 'ditulis' => $ditulis];
    }
}
