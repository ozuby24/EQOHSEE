<?php

namespace App\Http\Controllers;

use App\Models\Pembelian\Produk;
use App\Support\{Ekspor, IkonPadat, Media, Modules, Pillars, Smkp};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        $modules = array_map(function (array $module): array {
            $slug = $module['pilar'] ?? Pillars::forModule($module['nama']);
            $pillar = Pillars::get($slug) ?? Pillars::get('engineering');
            $route = $module['rute'] ?? null;

            return $module + [
                'pilar' => $slug,
                'pilarNama' => $pillar['nama'],
                'pilarWarna' => $pillar['warna'],
                'pilarDeep' => $pillar['deep'],
                'url' => $route && Route::has($route) ? route($route) : null,
                'ikonPadat' => IkonPadat::untuk(Modules::kunci($module['nama'])),
            ];
        }, Modules::all());

        /* ── HARGA UNTUK HALAMAN DEPAN ──

           Hanya dua angka: paketnya, dan yang termurah satuan. Halaman
           depan menjawab "berapa kira-kira", bukan "berapa tepatnya
           untuk tiap butir" — daftar harga lengkap ada di etalase, dan
           menyalinnya ke sini berarti dua tempat yang harus sama-sama
           diperbarui, yang berarti cepat atau lambat dua harga berbeda
           untuk satu barang yang sama.

           Nol dianggap belum berharga, bukan gratis: butir baru memang
           dipasang berharga nol oleh pembelian:katalog, dan "mulai Rp 0"
           di halaman depan adalah janji yang tidak dimaksudkan siapa
           pun. */
        $jual = $this->hargaJual();

        return Inertia::render('Landing', [
            'hero' => [
                'video' => Media::heroVideo(),
                'poster' => Media::heroPoster(),
            ],
            'galeri' => array_map(fn (array $item): array => $item + [
                'gambarUrl' => Media::url($item['gambar']),
                'videoUrl' => Media::url($item['video'] ?? null),
            ], Media::galeriTerisi()),
            'klien' => Media::klien(),
            'standar' => [
                ['kode' => 'Kepdirjen 185.K/2019', 'ket' => 'Penerapan SMKP Minerba'],
                ['kode' => 'Permen ESDM 26/2018', 'ket' => 'Kaidah teknik pertambangan yang baik'],
                ['kode' => 'SNI ISO 45001', 'ket' => 'Keselamatan dan kesehatan kerja'],
                ['kode' => 'SNI ISO 14001', 'ket' => 'Manajemen lingkungan'],
                ['kode' => 'SNI ISO 50001', 'ket' => 'Manajemen energi'],
                ['kode' => 'SNI ISO 9001', 'ket' => 'Manajemen mutu'],
            ],
            'masalah' => $this->masalah(),
            'aman' => $this->aman(),
            'tanya' => $this->tanya(),
            'kontak' => [
                'whatsapp' => Ekspor::nomorWa(config('pembelian.kontak.whatsapp')) ?? '',
                'email' => (string) config('pembelian.kontak.email'),
            ],
            'elemenSmkp' => $this->elemenSmkp(),
            'smkpAngka' => ['poin' => Smkp::totalNilai(), 'butir' => Smkp::jumlahButir()],
            'jumlahItem' => 194,
            'modul' => $modules,
            'pilar' => Pillars::all(),
            'fitur' => [
                ['judul' => 'Sertifikat ber-barcode', 'ket' => 'Terbit otomatis saat kursus selesai, empat ragam desain, berlogo perusahaan, dan dapat diverifikasi publik lewat pemindaian.'],
                ['judul' => 'Penilaian PTPKKP lengkap', 'ket' => '194 item pengukuran, bobot resmi per parameter, level Dasar hingga Resilient, plus rekapitulasi siap cetak.'],
                ['judul' => 'Kuesioner bebas akses', 'ket' => 'Sebar tautan ke pekerja dan pimpinan unit kerja tanpa perlu akun — hasil langsung terangkum per parameter.'],
                ['judul' => 'Evaluasi trainer', 'ket' => 'Peserta yang menyelesaikan kursus otomatis masuk daftar tunggu penilaian trainer pada empat aspek kompetensi.'],
                ['judul' => 'Kunci jawaban aman', 'ket' => 'Soal evaluasi SOP dinilai sepenuhnya di server — kunci jawaban tidak pernah dikirim ke perangkat peserta.'],
                ['judul' => 'Kendali penuh admin', 'ket' => 'Kelola perusahaan, pengguna, peran, penanda tangan, hingga pemeliharaan sistem dari satu pusat kendali.'],
            ],
            'alur' => [
                ['judul' => 'Daftarkan perusahaan', 'ket' => 'Lengkapi spesifikasi IUP/IUJP, KTT, PJO, dan jumlah tenaga kerja.'],
                ['judul' => 'Susun materi & prosedur', 'ket' => 'Buat kursus, modul, kuis, serta prosedur dan evaluasi SOP.'],
                ['judul' => 'Jalankan penilaian', 'ket' => 'Isi PTPKKP, sebar kuesioner, dan nilai kompetensi peserta.'],
                ['judul' => 'Terbitkan & tindak lanjut', 'ket' => 'Sertifikat terbit otomatis, program peningkatan tersusun dari hasil.'],
            ],
            'jual' => $jual,
            'tahun' => now()->year,
        ]);
    }
    /**
     * Ketujuh elemen SMKP — ringkas, dan dipasangkan dengan modul yang
     * benar-benar menghasilkan buktinya.
     *
     * ── DUA HAL YANG DIPERBAIKI SEKALIGUS ──
     *
     * Pertama, muatannya. Sebelumnya seluruh Smkp::elemen() dikirim apa
     * adanya ke peramban: 8,7 KB berisi pohon 100 butir audit beserta
     * halaman acuannya, sementara yang digambar halaman depan hanya nama
     * dan bobot — 388 byte. Dua puluh dua kali lipat, pada satu-satunya
     * halaman yang dibuka dari jaringan site tambang.
     *
     * Kedua, sumber namanya. Kolom "modul" di bawah ditulis di sini,
     * tetapi NAMA dan BOBOT elemennya tetap dibaca dari elemen.json.
     * Ditulis ulang, tabel di halaman depan akan menyebut nama elemen
     * yang berbeda dari yang dipakai modul audit — dua nama untuk satu
     * elemen yang sama, keduanya tercetak di situs yang sama.
     *
     * Pemetaannya memakai KODE elemen (I..VII), bukan urutan larik:
     * urutan boleh berubah tanpa membuat siapa pun curiga, kode tidak.
     *
     * @return array<int, array{kode: string, nama: string, bobot: int, modul: string}>
     */
    private function elemenSmkp(): array
    {
        $modul = [
            'I'   => 'ISO & Dokumen · SMKP Audit',
            'II'  => 'Safety Maturity Level · Mining Engineering Hub',
            'III' => 'HRIS · LMS Learning Center · Authority Kelayakan Kerja',
            'IV'  => 'Hazard Report & Inspeksi · Izin Kerja Aman · Keselamatan Operasi',
            'V'   => 'Investigasi Insiden · Pemantauan Perusahaan Jasa',
            'VI'  => 'ISO & Dokumen · Sertifikat ber-barcode',
            'VII' => 'SMKP Audit · Safety Maturity Level',
        ];

        return array_map(fn (array $e): array => [
            'kode'  => $e['kode'],
            'nama'  => $e['nama'],
            'bobot' => $e['bobot'],
            'modul' => $modul[$e['kode']] ?? '',
        ], Smkp::elemen());
    }

    /**
     * Lima keadaan yang membuat orang mencari alat seperti ini.
     *
     * ── SETIAP ANGKA MEMBAWA SUMBERNYA ──
     *
     * Angka tanpa sumber pada halaman jualan terbaca sebagai angka yang
     * dikarang, dan pada produk keselamatan kerugiannya bukan sekadar
     * kredibilitas: pembacanya adalah KTT yang akan mengutip angka itu ke
     * atasannya. Karena itu 'sumber' bukan kolom hiasan — butir tanpa
     * angka boleh tidak punya, butir berangka wajib punya.
     *
     * Yang sengaja TIDAK ada di sini: keterbatasan sinyal. Itu memang
     * masalah nyata di site, tetapi EQOHSEE belum menjawabnya (lihat
     * jawaban FAQ tentang sinyal) — dan masalah yang dipajang pada
     * halaman jualan terbaca sebagai masalah yang dipecahkan produknya.
     *
     * @return array<int, array{judul: string, ket: string, sumber: string}>
     */
    private function masalah(): array
    {
        return [
            [
                'judul' => 'Laporan berhenti di kertas',
                'ket' => 'Laporan bahaya, lembar inspeksi, dan izin kerja masih ditulis di formulir '
                       . 'kertas, lalu difoto, lalu dikirim lewat pesan pribadi. Yang sampai ke '
                       . 'pengawas adalah gambar; yang dibutuhkan saat audit adalah angka. '
                       . 'Antar-site, satu temuan yang sama bisa tercatat tiga kali dengan tiga '
                       . 'nomor berbeda — dan tidak ada yang tahu mana yang sudah ditutup.',
                'sumber' => '',
            ],
            [
                'judul' => 'Bukti audit dikumpulkan mendadak',
                'ket' => 'Audit internal SMKP wajib dilakukan paling sedikit satu kali dalam satu '
                       . 'tahun — dan buktinya baru dicari ketika auditor sudah dijadwalkan.',
                'sumber' => 'Permen ESDM 26/2018 Pasal 18; Kepdirjen Minerba 185.K/37.04/DJB/2019',
            ],
            [
                'judul' => 'Risiko bertumpu pada kontraktor',
                'ket' => '80,95% korban kecelakaan fatal tambang adalah pekerja kontraktor dan '
                       . 'subkontraktor, dan 88,84% di antaranya berpengalaman 0–3 tahun.',
                'sumber' => 'Kementerian ESDM, data per 30 November 2024 (dilaporkan Kompas)',
            ],
            [
                'judul' => 'Temuan kehilangan jejaknya',
                'ket' => 'Temuan tercatat, lalu progres tindak lanjutnya tidak terlacak sampai '
                       . 'ke manajemen — sampai temuan yang sama muncul lagi tahun berikutnya.',
                'sumber' => '',
            ],
            [
                'judul' => 'Angka fatal belum turun',
                'ket' => 'Sepanjang 2024 tercatat 49 kecelakaan tambang yang menelan korban jiwa, '
                       . 'naik dari 48 kejadian pada 2023, ditambah 80 kecelakaan kategori berat.',
                'sumber' => 'Rekapitulasi Ditjen Minerba, Kementerian ESDM (via Bloomberg Technoz)',
            ],
        ];
    }

    /**
     * Empat pernyataan keamanan — masing-masing menunjuk mekanisme yang
     * benar-benar ada di kode ini.
     *
     * Bukan daftar lencana. Setiap butir di bawah dapat ditunjuk
     * berkasnya: pemisahan perusahaan pada trait BerindukPerusahaan dan
     * BerpemilikPerusahaan, peran pada Gate 'admin'/'trainer', penilaian
     * di server pada modul evaluasi SOP, dan verifikasi sertifikat pada
     * rute publik certificates.verify.
     *
     * Yang tidak disebut: ISO 27001 dan angka uptime. Keduanya diminta
     * PRD sebagai proof point, tetapi keduanya klaim yang harus dibuktikan
     * pihak ketiga — dan lencana kepatuhan yang tidak dimiliki adalah
     * jenis kebohongan yang paling mudah diperiksa orang.
     *
     * @return array<int, array{judul: string, ket: string}>
     */
    private function aman(): array
    {
        return [
            ['judul' => 'Terpisah per perusahaan',
             'ket' => 'Setiap catatan terikat pada perusahaannya. Akun satu perusahaan tidak '
                    . 'pernah membaca data perusahaan lain, termasuk lewat tautan langsung.'],
            ['judul' => 'Peran menentukan akses',
             'ket' => 'Admin, trainer, pengawas, dan pekerja melihat layar dan tombol yang '
                    . 'berbeda — dibatasi di server, bukan hanya disembunyikan di tampilan.'],
            ['judul' => 'Kunci jawaban tinggal di server',
             'ket' => 'Soal evaluasi dinilai sepenuhnya di server. Kunci jawabannya tidak '
                    . 'pernah dikirim ke perangkat peserta, jadi tidak dapat dibaca dari sana.'],
            ['judul' => 'Sertifikat dapat diperiksa siapa pun',
             'ket' => 'Tiap sertifikat membawa barcode menuju halaman verifikasi publik, '
                    . 'sehingga keasliannya dapat diperiksa tanpa perlu akun.'],
        ];
    }

    /**
     * Enam pertanyaan yang memang ditanyakan sebelum membeli.
     *
     * ── JAWABAN SINYAL SENGAJA MENGAKU ──
     *
     * EQOHSEE menuntut koneksi; tidak ada penyimpanan luring maupun
     * antrean sinkronisasi di dalamnya. Menuliskannya sebagai "siap
     * dipakai tanpa sinyal" akan menjadi janji yang runtuh pada hari
     * pertama pemakaian di site — pada produk keselamatan, tepat pada
     * saat orang paling bergantung padanya.
     *
     * @return array<int, array{t: string, j: string}>
     */
    private function tanya(): array
    {
        return [
            ['t' => 'Apakah seluruh modulnya harus diambil sekaligus?',
             'j' => 'Tidak. Modulnya dapat dibeli satuan dan dinyalakan bertahap — modul yang '
                  . 'ditambahkan kemudian tetap berbagi data perusahaan, pengguna, dan peran '
                  . 'yang sama, jadi tidak ada data yang perlu dipindahkan.'],
            ['t' => 'Apakah EQOHSEE menjamin perusahaan lulus audit SMKP?',
             'j' => 'Tidak, dan tidak ada perangkat lunak yang dapat menjaminnya. EQOHSEE '
                  . 'adalah alat bantu menyusun dan menyimpan bukti penerapan SMKP. Kewajiban '
                  . 'hukum serta hasil penilaiannya tetap berada pada perusahaan dan KTT.'],
            ['t' => 'Bagaimana kalau site tidak ada sinyal?',
             'j' => 'EQOHSEE berjalan di peramban dan menuntut koneksi saat data dikirim; '
                  . 'belum ada perekaman luring. Halamannya dibuat ringan agar tetap terbuka '
                  . 'pada jaringan site yang lambat, tetapi pengisian di titik tanpa sinyal '
                  . 'sama sekali masih perlu diulang ketika kembali terhubung.'],
            ['t' => 'Apakah perlu dipasang di server sendiri?',
             'j' => 'Tidak. Cukup peramban — dari kantor pusat maupun dari site. Pemasangan '
                  . 'di server sendiri dapat dibicarakan terpisah bila kebijakan TI menuntutnya.'],
            ['t' => 'Bagaimana data antar-perusahaan dipisahkan?',
             'j' => 'Setiap catatan terikat pada perusahaan pemiliknya dan disaring di server '
                  . 'pada setiap permintaan, bukan disembunyikan di tampilan. Peran pengguna '
                  . 'menentukan lebih lanjut apa yang boleh dibuka dan diubah.'],
            ['t' => 'Apakah harganya terbuka?',
             'j' => 'Ya. Harga paket menyeluruh dan harga tiap aplikasi satuan tercantum di '
                  . 'katalog, lengkap dengan masa berlakunya. Pemesanannya tidak menuntut akun '
                  . 'dan pembayarannya lewat QRIS.'],
        ];
    }

    /**
     * Dua angka untuk bagian harga: paketnya, dan yang termurah satuan.
     *
     * ── HALAMAN DEPAN TIDAK BOLEH IKUT JATUH ──
     *
     * Sebelum ada bagian harga, halaman depan tidak menyentuh basis data
     * sama sekali — dan itu ternyata sifat yang berharga, bukan
     * kebetulan. Satu tabel yang belum termigrasi sesudah pemasangan,
     * atau basis data yang sedang tersendat, kini cukup untuk membuat
     * satu-satunya halaman yang dilihat calon pembeli menjadi galat 500.
     *
     * Karena itu kegagalannya ditangkap dan diperlakukan sebagai "harga
     * belum diumumkan" — keadaan yang layarnya memang sudah tahu cara
     * menggambarnya, lengkap dengan ajakan meminta penawaran. Yang
     * hilang hanya dua angka; yang tetap berdiri seluruh halamannya.
     *
     * @return array{paket: ?array{nama: string, harga: int, masa: string}, termurah: ?int, jumlah: int}
     */
    private function hargaJual(): array
    {
        $kosong = ['paket' => null, 'termurah' => null, 'jumlah' => 0];

        try {
            $aktif  = Produk::aktif()->where('harga', '>', 0);
            $paket  = (clone $aktif)->where('jenis', Produk::WEBSITE)->orderBy('urutan')->first();
            $satuan = (clone $aktif)->where('jenis', Produk::APLIKASI)->min('harga');

            return [
                'paket'    => $paket ? ['nama' => $paket->nama, 'harga' => $paket->harga,
                                        'masa' => $paket->masaBerlaku()] : null,
                'termurah' => $satuan ? (int) $satuan : null,
                'jumlah'   => (clone $aktif)->where('jenis', Produk::APLIKASI)->count(),
            ];
        } catch (QueryException $e) {
            report($e);

            return $kosong;
        }
    }
}
