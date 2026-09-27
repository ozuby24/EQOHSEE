<?php

namespace App\Http\Controllers;

use App\Models\Pembelian\Produk;
use App\Support\{Ekspor, IkonPadat, Media, Modules, Pillars, Smkp, TanyaJawab};
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
            'aman' => $this->aman(),
            'tanya' => TanyaJawab::semua(),
            'kontak' => [
                'whatsapp' => Ekspor::nomorWa(config('pembelian.kontak.whatsapp')) ?? '',
                'email' => (string) config('pembelian.kontak.email'),
            ],
            'elemenSmkp' => $this->elemenSmkp(),
            'smkpAngka' => ['poin' => Smkp::totalNilai(), 'butir' => Smkp::jumlahButir()],
            'jumlahItem' => 194,
            'modul' => $modules,
            'pilar' => Pillars::all(),
            'alur' => [
                ['judul' => 'Daftarkan perusahaan', 'ket' => 'Lengkapi spesifikasi IUP/IUJP, KTT, PJO, dan jumlah tenaga kerja.'],
                ['judul' => 'Susun materi & prosedur', 'ket' => 'Buat kursus, modul, kuis, serta prosedur dan evaluasi SOP.'],
                ['judul' => 'Jalankan penilaian', 'ket' => 'Isi PTPKKP, sebar kuesioner, dan nilai kompetensi peserta.'],
                ['judul' => 'Terbitkan & tindak lanjut', 'ket' => 'Sertifikat terbit otomatis, program peningkatan tersusun dari hasil.'],
            ],
            'jual' => $jual,
            'katalog' => $this->katalog(),

            /* Dua tangkapan layar ponsel untuk bagian aplikasi lapangan.
               Layar sungguhan dari platform ini, bukan gambar karangan —
               dan null bila berkasnya belum ditaruh, supaya bagian itu
               tetap berdiri tanpa bingkai gambar rusak. */
            'layarPonsel' => array_values(array_filter([
                ($u = Media::url('aplikasi/beranda.webp')) ? ['url' => $u, 'alt' => 'Layar beranda EQOHSEE di ponsel'] : null,
                ($u = Media::url('aplikasi/lapor.webp'))   ? ['url' => $u, 'alt' => 'Layar laporan bahaya EQOHSEE di ponsel'] : null,
            ])),

            'tautan' => [
                'masuk'   => route('login'),
                'katalog' => route('katalog.publik'),
                'pesan'   => route('katalog.pesan'),
                'privasi' => url('/kebijakan-privasi'),
            ],
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
     * Isi keranjang di bagian harga: paket, layanan tahunan, dan setiap
     * aplikasi yang berharga.
     *
     * Hanya ID, nama, dan harga yang dikirim — harga di layar sekadar
     * pengingat. Tagihannya dihitung ulang oleh App\Support\Pembelian
     * dari ID dan banyaknya, persis seperti pesanan dari /katalog.
     *
     * Urutan aplikasinya mengikuti Menu (Modules::perKunciMenu), bukan
     * urutan tabel produk, supaya daftar di keranjang sama urutannya
     * dengan daftar modul tepat di atasnya. Gagal membaca produk berarti
     * "harga belum diumumkan", sama seperti hargaJual() di bawah.
     *
     * @return array{paket: ?array<string, mixed>, layanan: ?array<string, mixed>, aplikasi: list<array<string, mixed>>}|null
     */
    private function katalog(): ?array
    {
        try {
            $aktif = Produk::aktif()->where('harga', '>', 0)->get();

            $satu = function (string $jenis) use ($aktif): ?array {
                $p = $aktif->where('jenis', $jenis)->sortBy('urutan')->first();

                return $p ? ['id' => $p->id, 'nama' => $p->nama, 'ket' => $p->keterangan,
                             'harga' => (int) $p->harga, 'masa' => $p->masaBerlaku()] : null;
            };

            $perKunci = $aktif->where('jenis', Produk::APLIKASI)->keyBy('modul_kunci');
            $aplikasi = [];

            foreach (Modules::perKunciMenu() as $kunci => $modul) {
                if (!$p = $perKunci->get($kunci)) continue;

                $aplikasi[] = ['id' => $p->id, 'nama' => $modul['nama'], 'harga' => (int) $p->harga,
                               'masa' => $p->masaBerlaku()];
            }

            $katalog = ['paket' => $satu(Produk::WEBSITE), 'layanan' => $satu(Produk::LAYANAN),
                        'aplikasi' => $aplikasi];

            return $katalog['paket'] || $katalog['layanan'] || $aplikasi ? $katalog : null;
        } catch (QueryException $e) {
            report($e);

            return null;
        }
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
