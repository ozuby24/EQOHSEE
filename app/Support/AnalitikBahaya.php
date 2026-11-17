<?php

namespace App\Support;

use App\Models\HazardReport;
use Illuminate\Support\Collection;

/**
 * Angka turunan halaman Analitik & KPI Hazard Report.
 *
 * Dipisahkan dari controllernya bukan demi kerapian melainkan supaya
 * dapat DIUJI langsung. Enam kartu ringkas, kalimat sorotan, dan
 * capaian per golongan adalah angka yang dibawa ke rapat bulanan; kalau
 * salah, ia tidak memulangkan galat apa pun — hanya angka yang tampak
 * masuk akal dan menyesatkan orang yang mengambil keputusan atasnya.
 *
 * ── Kosong bukan nol ──
 *
 * Rata-rata hari penutupan memulangkan NULL bila belum ada satu pun
 * laporan yang ditutup. Nol berarti "ditutup pada hari yang sama" —
 * capaian terbaik yang mungkin — dan menampilkannya untuk perusahaan
 * yang belum menutup apa pun membalik arti angkanya sepenuhnya.
 *
 * ── Kalimat sorotan hanya terbit bila ada dasarnya ──
 *
 * Tiap kalimat punya prasyaratnya sendiri dan DILEWATI bila prasyarat
 * itu tidak terpenuhi. Kalimat sorotan yang selalu terbit apa pun
 * datanya akan berbunyi "naik 0% dari bulan lalu" pada perusahaan yang
 * baru memakai aplikasinya — dan sesudah dua tiga kali begitu, tidak
 * ada lagi yang membacanya.
 */
final class AnalitikBahaya
{
    /**
     * @param Collection<int,HazardReport> $data laporan pada periode terpilih
     * @param int $bulanAktif jumlah bulan berisi laporan — pengali target
     */
    public function __construct(
        private Collection $data,
        private int $bulanAktif,
    ) {}

    /* ═════════════ per orang dan per golongan ═════════════ */

    /**
     * KPI tiap orang, dikelompokkan lewat Identitas.
     *
     * Bukan langsung dari teks namanya: satu orang yang mengetik namanya
     * sedikit berbeda pada tiga laporan akan terhitung sebagai tiga
     * orang, dan karena target dijumlahkan per orang, targetnya ikut
     * tiga kali lipat sementara laporannya tetap tiga — capaiannya
     * ambruk jadi sepertiga tanpa satu pun galat muncul.
     *
     * Nama dan jabatan diambil dari akunnya bila ada. Teks pada laporan
     * adalah salinan saat laporan dibuat; orang yang berganti jabatan
     * akan menyeret jabatan lamanya — beserta target lamanya — di
     * seluruh laporan terdahulunya.
     */
    public function perOrang(): array
    {
        $out = [];

        foreach ($this->data as $r) {
            /* Perusahaan ikut menjadi bagian kuncinya. Nama yang sama
               dipakai dua orang di dua perusahaan berbeda — dan pada
               layar administrator yang melihat seluruh perusahaan,
               keduanya memang dua orang yang berbeda. Tanpa ini,
               laporan keduanya menyatu dan capaian masing-masing
               menjadi setengah dari yang sebenarnya. */
            $kunci = $r->company_id.'|'
                .Identitas::kunci($r->user_id, $r->pelapor_nrp, $r->pelapor_nama);

            $jabatan = $r->user?->position ?: $r->pelapor_jabatan;

            $out[$kunci] ??= [
                'nama'       => $r->user?->name ?: $r->pelapor_nama,
                'jabatan'    => $jabatan,
                'perusahaan' => $r->company?->name,
                'gol'        => Hazard::golongan($jabatan),
                'target'     => Hazard::target($jabatan) * $this->bulanAktif,
                'aktual'     => 0,
            ];
            $out[$kunci]['aktual']++;
        }

        uasort($out, fn ($a, $b) => $b['aktual'] <=> $a['aktual'] ?: strcmp($a['nama'], $b['nama']));

        return $out;
    }

    /** @param array<string,array<string,mixed>> $perOrang */
    public function perGolongan(array $perOrang): array
    {
        $kumpul = [];

        foreach ($perOrang as $o) {
            $g = $o['gol'];
            $kumpul[$g] ??= ['target' => 0, 'aktual' => 0, 'orang' => 0, 'tercapai' => 0];
            $kumpul[$g]['target'] += $o['target'];
            $kumpul[$g]['aktual'] += $o['aktual'];
            $kumpul[$g]['orang']++;
            if ($o['aktual'] >= $o['target']) $kumpul[$g]['tercapai']++;
        }

        $out = [];

        foreach ($kumpul as $nama => $g) {
            $out[] = [
                'nama'     => $nama,
                'orang'    => $g['orang'],
                'target'   => $g['target'],
                'aktual'   => $g['aktual'],
                'tercapai' => $g['tercapai'],
                'pct'      => self::persen($g['aktual'], $g['target']),
            ];
        }

        /* Golongan yang paling tertinggal di ATAS. Diurut abjad, yang
           perlu ditindaklanjuti dapat berada di baris keenam — dan
           tabel capaian dibaca dari atas. */
        usort($out, fn ($a, $b) => $a['pct'] <=> $b['pct']);

        return $out;
    }

    /* ═════════════ enam kartu ringkas ═════════════ */

    /**
     * Enam angka yang menjawab "bagaimana keadaannya" tanpa menggulir.
     *
     * @param array<string,array<string,mixed>> $perOrang
     */
    public function kartu(array $perOrang): array
    {
        $total = $this->data->count();

        $target = array_sum(array_column($perOrang, 'target'));
        $aktual = array_sum(array_column($perOrang, 'aktual'));

        $capai = count(array_filter($perOrang, fn ($o) => $o['aktual'] >= $o['target'] && $o['target'] > 0));
        $orang = count($perOrang);

        $tinggi = $this->data->where('risiko', 'Tinggi')->count();
        $tutup  = $this->data->where('status', 'Closed')->count();

        $rata = $this->rataHariPenutupan();

        return [
            [
                'kunci'    => 'total',
                'label'    => 'Laporan Masuk',
                'nilai'    => (string) $total,
                'satuan'   => 'laporan',
                'ket'      => $orang ? "dari {$orang} pelapor" : 'belum ada pelapor',
                'nada'     => 'netral',
            ],
            [
                'kunci'    => 'capaian',
                'label'    => 'Capaian KPI',
                'nilai'    => $target ? self::persen($aktual, $target).'%' : '—',
                'satuan'   => null,
                'ket'      => $target ? "{$aktual} dari target {$target}" : 'target belum dapat dihitung',
                'nada'     => $target === 0 ? 'netral' : (self::persen($aktual, $target) >= 100 ? 'baik' : 'perhatian'),
            ],
            [
                'kunci'    => 'orang',
                'label'    => 'Pelapor Capai Target',
                'nilai'    => $orang ? "{$capai}/{$orang}" : '—',
                'satuan'   => 'orang',
                'ket'      => $orang ? ($orang - $capai).' orang masih di bawah target' : 'belum ada pelapor',
                'nada'     => $orang === 0 ? 'netral' : ($capai === $orang ? 'baik' : 'perhatian'),
            ],
            [
                'kunci'    => 'tinggi',
                'label'    => 'Risiko Tinggi',
                'nilai'    => (string) $tinggi,
                'satuan'   => 'laporan',
                'ket'      => $total ? self::persen($tinggi, $total).'% dari seluruh laporan' : 'belum ada laporan',
                'nada'     => $tinggi > 0 ? 'bahaya' : 'baik',
            ],
            [
                'kunci'    => 'tutup',
                'label'    => 'Tingkat Penutupan',
                'nilai'    => $total ? self::persen($tutup, $total).'%' : '—',
                'satuan'   => null,
                'ket'      => $total ? ($total - $tutup).' laporan belum ditutup' : 'belum ada laporan',
                'nada'     => $total === 0 ? 'netral' : ($tutup === $total ? 'baik' : 'perhatian'),
            ],
            [
                'kunci'    => 'lama',
                'label'    => 'Rata-rata Penutupan',
                /* NULL, bukan nol. Nol berarti "ditutup pada hari yang
                   sama" — capaian terbaik yang mungkin — dan
                   menampilkannya untuk perusahaan yang belum menutup
                   apa pun membalik arti angkanya. */
                'nilai'    => $rata === null ? '—' : (string) $rata,
                'satuan'   => $rata === null ? null : 'hari',
                'ket'      => $rata === null ? 'belum ada laporan yang ditutup' : "dihitung atas {$tutup} laporan tertutup",
                'nada'     => $rata === null ? 'netral' : ($rata <= 14 ? 'baik' : 'perhatian'),
            ],
        ];
    }

    /**
     * Rata-rata hari dari tanggal temuan sampai ditutup.
     *
     * Null bila belum ada satu pun yang ditutup — lihat kartu di atas.
     */
    public function rataHariPenutupan(): ?float
    {
        $hari = $this->data
            ->filter(fn (HazardReport $h) => $h->closed_at !== null && $h->tanggal !== null)
            ->map(fn (HazardReport $h) => max(0, $h->tanggal->startOfDay()->diffInDays($h->closed_at->startOfDay())));

        return $hari->isEmpty() ? null : round($hari->avg(), 1);
    }

    /* ═════════════ kalimat sorotan ═════════════ */

    /**
     * Kalimat yang menyebut apa yang perlu dikerjakan, bukan pujian.
     *
     * Tiap kalimat punya prasyaratnya dan dilewati bila tidak
     * terpenuhi — lihat catatan kelas.
     *
     * @param array<string,array<string,mixed>> $perOrang
     * @param array<int,array<string,mixed>> $golongan
     * @param array<string,int> $tren  berkunci YYYY-MM, menaik
     * @return array<int,array{nada:string,teks:string}>
     */
    public function insight(array $perOrang, array $golongan, array $tren): array
    {
        $out = [];
        $total = $this->data->count();

        if ($total === 0) {
            return [[
                'nada' => 'netral',
                'teks' => 'Belum ada laporan pada periode ini, jadi belum ada yang dapat disimpulkan.',
            ]];
        }

        /* 1. Perbandingan dua bulan terakhir yang BERISI laporan.
              Dibandingkan dengan bulan lalu apa adanya, perusahaan yang
              baru mulai memakai aplikasinya akan selalu membaca
              "naik tak terhingga dari 0". */
        $berisi = array_filter($tren, fn ($n) => $n > 0);

        if (count($berisi) >= 2) {
            $kunci = array_keys($berisi);
            $kini  = $berisi[$kunci[count($kunci) - 1]];
            $lalu  = $berisi[$kunci[count($kunci) - 2]];
            $beda  = $kini - $lalu;

            if ($beda !== 0) {
                $out[] = [
                    'nada' => $beda > 0 ? 'baik' : 'perhatian',
                    'teks' => 'Laporan bulan terakhir '.($beda > 0 ? 'naik' : 'turun').' '
                        .abs($beda).' laporan ('.abs(self::persen($beda, $lalu)).'%) '
                        .'dibandingkan bulan berisi sebelumnya.',
                ];
            }
        }

        /* 2. Golongan yang paling tertinggal — yang paling perlu
              ditindaklanjuti, dan satu-satunya kalimat yang menyebut
              siapa yang harus mengerjakannya. */
        $tertinggal = null;
        foreach ($golongan as $g) {
            if ($g['target'] > 0 && $g['pct'] < 100
                && ($tertinggal === null || $g['pct'] < $tertinggal['pct'])) {
                $tertinggal = $g;
            }
        }

        if ($tertinggal) {
            $kurang = $tertinggal['orang'] - $tertinggal['tercapai'];

            $out[] = [
                'nada' => $tertinggal['pct'] < 50 ? 'bahaya' : 'perhatian',
                'teks' => 'Golongan '.$tertinggal['nama'].' paling tertinggal di '
                    .$tertinggal['pct'].'% — '.$kurang.' dari '.$tertinggal['orang']
                    .' orang belum memenuhi targetnya.',
            ];
        }

        /* 3. Pemusatan risiko tinggi pada satu lokasi. Hanya bila
              memang terpusat: sebaran merata tidak memberi tahu
              siapa pun ke mana harus pergi. */
        $tinggi = $this->data->where('risiko', 'Tinggi');

        if ($tinggi->count() >= 3) {
            $perLokasi = $tinggi->groupBy(fn (HazardReport $h) => $h->lokasi ?: '—')
                ->map->count()->sortDesc();

            $teratas = $perLokasi->keys()->first();
            $jumlah  = $perLokasi->first();

            if ($teratas !== '—' && $jumlah / $tinggi->count() >= 0.3) {
                $out[] = [
                    'nada' => 'bahaya',
                    'teks' => $jumlah.' dari '.$tinggi->count().' temuan risiko tinggi berada di '
                        .$teratas.' — '.self::persen($jumlah, $tinggi->count()).'% terpusat di satu lokasi.',
                ];
            }
        }

        /* 4. Tenggat yang sudah lewat dan belum ditutup. */
        $lewat = $this->data->filter(fn (HazardReport $h) => $h->lewatTenggat())->count();

        if ($lewat > 0) {
            $out[] = [
                'nada' => 'bahaya',
                'teks' => $lewat.' laporan sudah melewati batas akhir tindakan perbaikannya '
                    .'dan belum ditutup.',
            ];
        }

        /* 5. Bila tak satu pun kalimat di atas terbit, keadaannya
              memang baik — dan itu pun perlu dikatakan, sekali. */
        if (!$out) {
            $out[] = [
                'nada' => 'baik',
                'teks' => 'Seluruh golongan memenuhi targetnya dan tidak ada tenggat yang terlewat.',
            ];
        }

        return $out;
    }

    /* ═════════════ sebaran ═════════════ */

    /**
     * Sebaran satu kolom sebagai potongan donat berwarna.
     *
     * Urutannya DITETAPKAN, bukan mengikuti besar kecilnya: Rendah,
     * Sedang, Tinggi selalu di urutan itu, sehingga warna potongan
     * ketiga berarti hal yang sama pada tiap perusahaan dan tiap bulan.
     * Diurut menurut jumlah, potongan merah berpindah tempat setiap
     * kali datanya berubah.
     *
     * @param array<string,string> $warna nilai => warna
     */
    public function donat(string $kolom, array $warna): array
    {
        $hitung = $this->data->groupBy(fn (HazardReport $h) => (string) ($h->{$kolom} ?: '—'))
            ->map->count();

        $total = max(1, $this->data->count());
        $out = [];

        /* Nilai yang dikenal lebih dulu, menurut urutan yang
           ditetapkan; yang tidak dikenal menyusul di belakang supaya
           data lama tetap terlihat, bukan hilang diam-diam. */
        foreach (array_keys($warna) as $nilai) {
            if (!$hitung->has($nilai)) continue;

            $out[] = [
                'label' => $nilai,
                'nilai' => $hitung[$nilai],
                'pct'   => round($hitung[$nilai] / $total * 100, 1),
                'warna' => $warna[$nilai],
            ];
        }

        foreach ($hitung as $nilai => $n) {
            if (isset($warna[$nilai])) continue;

            $out[] = [
                'label' => $nilai,
                'nilai' => $n,
                'pct'   => round($n / $total * 100, 1),
                'warna' => '#A8A29E',
            ];
        }

        return $out;
    }

    /* ═════════════ bantu ═════════════ */

    /** Persentase bulat; nol pembagi memulangkan nol, bukan galat. */
    public static function persen(int|float $bagian, int|float $dari): int
    {
        return $dari ? (int) round($bagian / $dari * 100) : 0;
    }
}
