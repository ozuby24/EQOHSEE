<?php

namespace App\Support;

use App\Models\HazardReport;
use App\Models\InspectionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kecenderungan temuan berulang.
 *
 * Menjawab satu pertanyaan yang tidak dapat dijawab daftar temuan
 * maupun grafik tren: temuan mana yang TERUS KEMBALI di tempat yang
 * sama — dan apakah tindakan perbaikannya bertahan.
 *
 * ── Kenapa jumlah saja tidak cukup ──
 *
 * Lima temuan sejenis pada satu bulan adalah satu peristiwa; boleh jadi
 * sekali sapuan inspeksi menemukan lima hal sekaligus. Lima temuan
 * sejenis yang tersebar di lima bulan berbeda adalah masalah sistemik
 * yang tidak pernah tertutup. Keduanya berjumlah lima, dan daftar yang
 * mengurutkan menurut jumlah menaruh keduanya berdampingan.
 *
 * Yang diurutkan di sini karena itu bukan jumlahnya melainkan, berturut:
 * berapa kali ia KAMBUH sesudah ditangani, di berapa bulan BERBEDA ia
 * muncul, baru jumlahnya. Ketiganya ditampilkan sebagai kolom
 * tersendiri, bukan diringkas menjadi satu skor tanpa penjelasan —
 * angka gabungan yang tidak dapat diurai tidak dapat dibantah, dan
 * yang tidak dapat dibantah tidak dipercaya.
 *
 * ── Pengelompokan: lokasi + perihal, bukan kemiripan teks ──
 *
 * Deskripsi temuan diketik bebas di lapangan dan tidak pernah sama
 * persis. Mencocokkannya secara samar akan menyatukan dua temuan yang
 * sebenarnya berbeda — dan tidak ada seorang pun yang dapat memeriksa
 * apakah penyatuan itu benar. Yang dipakai di sini pasangan yang
 * memang terstruktur: LOKASI dan PERIHAL — kategori bagi laporan
 * bahaya, uraian butir bagi inspeksi. Kasar, tetapi tiap baris di
 * dalamnya sungguh-sungguh sebanding.
 *
 * ── Kambuh ──
 *
 * Kambuh = temuan yang muncul SESUDAH temuan sejenis di tempat yang
 * sama sudah ditangani. Inilah angka yang paling berarti di halaman
 * ini: ia berarti perbaikannya tidak bertahan, dan itu pertanyaan yang
 * berbeda sama sekali dari "berapa banyak temuan kita".
 */
final class TemuanBerulang
{
    /** Sebuah kelompok disebut berulang mulai dari kemunculan kedua. */
    public const AMBANG = 2;

    /** Berapa bulan ke belakang yang ditinjau, dan lebar garis bulanannya. */
    public const BULAN = 12;

    /**
     * @param Collection<int,HazardReport> $hazard
     * @param Collection<int,InspectionItem> $item butir inspeksi Tidak Sesuai
     */
    public function __construct(
        private Collection $hazard,
        private Collection $item,
    ) {}

    /* ═════════════ kelompok ═════════════ */

    /**
     * Seluruh kelompok berulang, sudah berurut paling mendesak di atas.
     *
     * @return array<int,array<string,mixed>>
     */
    public function kelompok(): array
    {
        $kumpul = [];

        foreach ($this->kemunculan() as $k) {
            /* PERUSAHAAN ikut menjadi bagian kuncinya, dan itu bukan
               kehati-hatian berlebih.

               Tujuh perusahaan yang masing-masing punya lokasi bernama
               "Kantor Site — Lantai 1 dan 2" dan diinspeksi sekali
               menghasilkan, tanpa ini, SATU kelompok berjumlah tujuh
               yang dilaporkan berulang tujuh kali dalam dua hari.
               Tidak ada satu pun temuan yang sebenarnya berulang di
               sana — dan yang membacanya mengirim orang memeriksa
               masalah yang tidak ada. */
            $kunci = $k['perusahaan_id'].'|'.$k['sumber']
                .'|'.mb_strtolower($k['lokasi']).'|'.mb_strtolower($k['perihal']);

            $kumpul[$kunci] ??= [
                'sumber'     => $k['sumber'],
                'perusahaan' => $k['perusahaan'],
                'lokasi'     => $k['lokasi'],
                'perihal'    => $k['perihal'],
                'muncul'     => [],
            ];
            $kumpul[$kunci]['muncul'][] = $k;
        }

        $out = [];

        foreach ($kumpul as $g) {
            if (count($g['muncul']) < self::AMBANG) continue;

            $out[] = $this->ringkasKelompok($g);
        }

        /* Berurut: kambuh, lalu bulan berbeda, lalu jumlah, lalu risiko
           tinggi. Bukan satu skor gabungan — lihat catatan kelas. */
        usort($out, fn ($a, $b) =>
            $b['kambuh'] <=> $a['kambuh']
            ?: $b['bulan'] <=> $a['bulan']
            ?: $b['jumlah'] <=> $a['jumlah']
            ?: $b['tinggi'] <=> $a['tinggi']);

        return $out;
    }

    /** @param array<string,mixed> $g */
    private function ringkasKelompok(array $g): array
    {
        /* Diurut menurut tanggal lebih dulu: kambuh dihitung dengan
           membandingkan tiap kemunculan terhadap yang SEBELUMNYA, dan
           urutan basis data bukan urutan waktu. */
        $muncul = collect($g['muncul'])->sortBy('tanggal')->values();

        $bulan = $muncul->map(fn ($m) => $m['tanggal']->format('Y-m'))->unique();

        /* Kambuh dihitung sebagai PERALIHAN "sudah ditangani → muncul
           lagi", bukan sebagai setiap kemunculan sesudah penanganan
           yang pertama.

           Bedanya nyata. Satu temuan ditangani lalu muncul lagi lima
           kali tanpa pernah ditangani ulang adalah SATU perbaikan yang
           gagal, diikuti lima kemunculan yang memang belum tertangani.
           Dihitung sebagai enam kegagalan perbaikan, angka kambuh
           membengkak justru pada kelompok yang paling terbengkalai —
           dan kelompok yang ditangani berulang kali dengan sungguh-
           sungguh tampak sama buruknya. */
        $kambuh = 0;
        $menunggu = null;   // tanggal penanganan yang belum terbukti bertahan

        foreach ($muncul as $m) {
            if ($menunggu !== null && $m['tanggal']->gt($menunggu)) {
                $kambuh++;
                $menunggu = null;
            }

            if ($m['selesai'] !== null
                && ($menunggu === null || $m['selesai']->lt($menunggu))) {
                $menunggu = $m['selesai'];
            }
        }

        $pertama = $muncul->first()['tanggal'];
        $terakhir = $muncul->last()['tanggal'];
        $rentang = (int) $pertama->diffInDays($terakhir);

        return [
            'sumber'     => $g['sumber'],
            'perusahaan' => $g['perusahaan'],
            'lokasi'   => $g['lokasi'],
            'perihal'  => $g['perihal'],
            'jumlah'   => $muncul->count(),
            'bulan'    => $bulan->count(),
            'kambuh'   => $kambuh,
            'tinggi'   => $muncul->where('risiko', 'Tinggi')->count(),
            'selesai'  => $muncul->whereNotNull('selesai')->count(),
            'pertama'  => $pertama->format('d-m-Y'),
            'terakhir' => $terakhir->format('d-m-Y'),

            /* RENTANG hari dari kemunculan pertama ke terakhir, bukan
               jeda rata-rata antar kemunculan.

               Jeda rata-rata membulat menjadi nol begitu kemunculannya
               rapat — tujuh kali dalam tiga hari berbunyi "0 hari
               sekali", yang terbaca seperti temuan harian. Rentangnya
               menyatakan hal yang sama tanpa pembulatan yang
               menyesatkan: "7 kali dalam 3 hari". */
            'rentang'  => $rentang,

            'hirarki'  => $muncul->pluck('hirarki')->filter()->countBy()->sortDesc()->all(),
            'bulanan'  => $this->garisBulanan($muncul),
            'contoh'   => $muncul->pluck('kode')->filter()->take(3)->values()->all(),
        ];
    }

    /**
     * Kemunculan per bulan sepanjang jendela peninjauan.
     *
     * Seluruh dua belas bulan SELALU ada, termasuk yang bernilai nol.
     * Dibangun hanya dari bulan yang terisi, dua kelompok dengan pola
     * yang sangat berbeda menghasilkan garis yang bentuknya sama — dan
     * garis itu justru satu-satunya hal yang membedakan "sekali sapuan"
     * dari "tidak pernah tertutup".
     *
     * @param Collection<int,array<string,mixed>> $muncul
     */
    private function garisBulanan(Collection $muncul): array
    {
        $hitung = $muncul->countBy(fn ($m) => $m['tanggal']->format('Y-m'));

        return array_map(fn (string $k) => [
            'label' => Carbon::parse($k.'-01')->translatedFormat('M'),
            'nilai' => (int) ($hitung[$k] ?? 0),
        ], DeretBulan::kunciMundur(Carbon::now(), self::BULAN));
    }

    /**
     * Seluruh kemunculan dari kedua sumber, dalam bentuk yang sama.
     *
     * @return array<int,array<string,mixed>>
     */
    private function kemunculan(): array
    {
        $out = [];

        foreach ($this->hazard as $h) {
            if (!$h->tanggal) continue;

            $out[] = [
                'sumber'  => 'Hazard',
                'perusahaan_id' => $h->company_id,
                'perusahaan'    => $h->company?->name,
                'lokasi'  => trim((string) ($h->lokasi ?: '—')),
                'perihal' => trim((string) ($h->kategori ?: '—')),
                'tanggal' => $h->tanggal,
                'risiko'  => $h->risiko,
                'hirarki' => $h->hirarki,
                'kode'    => $h->kode,
                /* Ditangani = ditutup. Tanggal penutupannya yang
                   dipakai, bukan statusnya: yang menentukan apakah
                   temuan berikutnya "kambuh" adalah KAPAN yang
                   sebelumnya selesai, bukan apakah ia selesai sekarang. */
                'selesai' => $h->closed_at,
            ];
        }

        foreach ($this->item as $i) {
            $ins = $i->inspection;

            if (!$ins?->tanggal) continue;

            $out[] = [
                'sumber'  => 'Inspeksi',
                'perusahaan_id' => $ins->company_id,
                'perusahaan'    => $ins->company?->name,
                'lokasi'  => trim((string) ($ins->lokasi ?: '—')),
                'perihal' => trim((string) ($i->uraian ?: '—')),
                'tanggal' => $ins->tanggal,
                'risiko'  => $i->risiko,
                /* Butir inspeksi tidak menyimpan hirarki pengendalian —
                   dibiarkan null, bukan diisi tebakan. Kolom hirarki
                   pada kelompok inspeksi karena itu memang kosong, dan
                   itu keadaan yang benar, bukan data yang hilang. */
                'hirarki' => null,
                'kode'    => $ins->kode,
                /* Butir inspeksi tidak punya tanggal penutupan
                   tersendiri. Yang ada tindakannya; bila tindakan
                   tercatat, ia dianggap tertangani pada tanggal
                   inspeksinya. Tanpa tindakan tercatat, ia TIDAK
                   dianggap selesai — dan kemunculan berikutnya karena
                   itu tidak terhitung kambuh. Menganggapnya selesai
                   akan melaporkan kambuh yang tidak pernah terjadi. */
                'selesai' => trim((string) $i->tindakan) !== '' ? $ins->tanggal : null,
            ];
        }

        return $out;
    }

    /* ═════════════ ringkasan ═════════════ */

    /**
     * Empat angka yang menjawab "seberapa besar masalahnya".
     *
     * @param array<int,array<string,mixed>> $kelompok
     */
    public function kartu(array $kelompok): array
    {
        $semua = count($this->kemunculan());

        $terulang = array_sum(array_column($kelompok, 'jumlah'));
        $kambuh   = array_sum(array_column($kelompok, 'kambuh'));
        $menahun  = count(array_filter($kelompok, fn ($g) => $g['bulan'] >= 3));

        return [
            [
                'kunci' => 'kelompok',
                'label' => 'Kelompok Berulang',
                'nilai' => (string) count($kelompok),
                'satuan' => 'kelompok',
                'ket'   => 'lokasi dan perihal yang sama muncul lebih dari sekali',
                'nada'  => count($kelompok) ? 'perhatian' : 'baik',
            ],
            [
                'kunci' => 'temuan',
                'label' => 'Temuan Terlibat',
                'nilai' => (string) $terulang,
                'satuan' => 'temuan',
                'ket'   => $semua
                    ? AnalitikBahaya::persen($terulang, $semua).'% dari '.$semua.' temuan yang ditinjau'
                    : 'belum ada temuan yang ditinjau',
                'nada'  => 'netral',
            ],
            [
                'kunci' => 'kambuh',
                'label' => 'Kambuh Sesudah Ditangani',
                'nilai' => (string) $kambuh,
                'satuan' => 'temuan',
                'ket'   => $kambuh
                    ? 'perbaikannya tidak bertahan — ini angka yang paling perlu dikejar'
                    : 'tidak ada temuan yang kembali sesudah ditangani',
                'nada'  => $kambuh ? 'bahaya' : 'baik',
            ],
            [
                'kunci' => 'menahun',
                'label' => 'Berulang ≥ 3 Bulan',
                'nilai' => (string) $menahun,
                'satuan' => 'kelompok',
                'ket'   => 'muncul pada tiga bulan berbeda atau lebih — bukan satu peristiwa',
                'nada'  => $menahun ? 'bahaya' : 'baik',
            ],
        ];
    }

    /**
     * Hirarki pengendalian pada temuan yang BERULANG.
     *
     * Bagian paling menjelaskan di halaman ini. Temuan yang terus
     * kembali hampir selalu ditutup dengan pengendalian lemah —
     * Administratif dan APD menyuruh orang lebih berhati-hati, dan
     * kehati-hatian tidak bertahan melewati pergantian regu. Sebarannya
     * ditampilkan supaya alasan berulangnya terlihat, bukan hanya
     * kenyataan bahwa ia berulang.
     *
     * @param array<int,array<string,mixed>> $kelompok
     */
    public function hirarki(array $kelompok): array
    {
        $kuat  = ['Eliminasi', 'Substitusi', 'Rekayasa'];
        $warna = [
            'Eliminasi'     => '#15803D',
            'Substitusi'    => '#16A34A',
            'Rekayasa'      => '#65A30D',
            'Administratif' => '#CA9A04',
            'APD'           => '#DC2626',
        ];

        $hitung = [];

        foreach ($kelompok as $g) {
            foreach ($g['hirarki'] as $nama => $n) {
                $hitung[$nama] = ($hitung[$nama] ?? 0) + $n;
            }
        }

        $total = array_sum($hitung);
        $potong = [];

        foreach (array_keys($warna) as $nama) {
            if (!isset($hitung[$nama])) continue;

            $potong[] = [
                'label' => $nama,
                'nilai' => $hitung[$nama],
                'pct'   => round($hitung[$nama] / max(1, $total) * 100, 1),
                'warna' => $warna[$nama],
            ];
        }

        foreach ($hitung as $nama => $n) {
            if (isset($warna[$nama])) continue;

            $potong[] = [
                'label' => $nama, 'nilai' => $n,
                'pct' => round($n / max(1, $total) * 100, 1), 'warna' => '#A8A29E',
            ];
        }

        $lemah = $total - array_sum(array_intersect_key($hitung, array_flip($kuat)));

        return [
            'potong' => $potong,
            'total'  => $total,
            'lemah'  => $lemah,
            'pctLemah' => $total ? AnalitikBahaya::persen($lemah, $total) : 0,
        ];
    }

    /**
     * Kalimat sorotan — tiap kalimat dengan prasyaratnya sendiri.
     *
     * @param array<int,array<string,mixed>> $kelompok
     * @param array<string,mixed> $hirarki
     * @return array<int,array{nada:string,teks:string}>
     */
    public function insight(array $kelompok, array $hirarki): array
    {
        if (!$kelompok) {
            return [[
                'nada' => 'baik',
                'teks' => 'Tidak ada temuan yang berulang di lokasi dan perihal yang sama '
                    .'pada periode ini.',
            ]];
        }

        $out = [];
        $teratas = $kelompok[0];

        if ($teratas['kambuh'] > 0) {
            $out[] = [
                'nada' => 'bahaya',
                'teks' => '"'.$teratas['perihal'].'" di '.$teratas['lokasi'].' kembali '
                    .$teratas['kambuh'].' kali SESUDAH ditangani — perbaikannya tidak bertahan.',
            ];
        } else {
            $out[] = [
                'nada' => 'perhatian',
                'teks' => 'Kelompok paling sering berulang: "'.$teratas['perihal'].'" di '
                    .$teratas['lokasi'].' — '.$teratas['jumlah'].' kali dalam '
                    .$teratas['bulan'].' bulan berbeda.',
            ];
        }

        /* Pengendalian lemah pada temuan berulang — alasan berulangnya. */
        if ($hirarki['total'] >= 3 && $hirarki['pctLemah'] >= 60) {
            $out[] = [
                'nada' => 'perhatian',
                'teks' => $hirarki['pctLemah'].'% temuan berulang ditutup dengan pengendalian '
                    .'administratif atau APD saja — keduanya menuntut kehati-hatian orang, '
                    .'dan kehati-hatian tidak bertahan melewati pergantian regu.',
            ];
        }

        /* Lokasi yang menyumbang kelompok berulang terbanyak. */
        $perLokasi = collect($kelompok)->countBy('lokasi')->sortDesc();

        if ($perLokasi->count() >= 2 && $perLokasi->first() >= 2) {
            $out[] = [
                'nada' => 'perhatian',
                'teks' => $perLokasi->keys()->first().' menyumbang '.$perLokasi->first()
                    .' dari '.count($kelompok).' kelompok berulang.',
            ];
        }

        /* Kelompok yang masih hidup — kemunculan terakhirnya baru. */
        $baru = array_filter($kelompok, fn ($g) =>
            Carbon::createFromFormat('d-m-Y', $g['terakhir'])->gte(Carbon::now()->subDays(30)));

        if ($baru) {
            $out[] = [
                'nada' => 'bahaya',
                'teks' => count($baru).' kelompok masih muncul dalam 30 hari terakhir — '
                    .'bukan riwayat lama, melainkan yang sedang berjalan.',
            ];
        }

        return $out;
    }
}
