<?php

namespace App\Http\Controllers;

use App\Models\Pembelian\{Pembayaran, Pesanan, Produk};
use App\Support\{Berkas, Modules, Pembelian, Pillars};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pembelian website dan aplikasi di dalamnya.
 *
 * ── TIGA PINTU, DAN PENJAGAAN YANG BERBEDA-BEDA ──
 *
 * Katalog dan daftar tagihan hanya untuk pengguna yang masuk. Halaman
 * BAYAR terbuka tanpa login, sebab pembelinya belum tentu punya akun —
 * dan yang menjaganya token acak pada alamatnya, bukan sesi.
 *
 * Karena itu halaman bayar tidak boleh menampilkan apa pun di luar
 * tagihan itu sendiri: tanpa sesi, tidak ada yang dapat dipakai
 * memastikan siapa yang membuka. Yang tampil hanya nama pembeli, baris
 * tagihan, nilainya, dan cara membayar — tidak ada daftar tagihan lain,
 * tidak ada tautan ke bagian mana pun dari aplikasi.
 */
class PembelianController extends Controller
{
    /* ═══════════════════ katalog jual — tanpa login ═══════════════════ */

    /**
     * Etalase publik: apa yang dijual, berapa, dan bagaimana membelinya.
     *
     * ── TERBUKA, DAN MEMANG HARUS ──
     *
     * Yang membacanya calon pembeli yang belum punya akun. Katalog di
     * balik layar masuk hanya dapat dibaca orang yang sudah membeli —
     * yaitu satu-satunya orang yang tidak perlu membacanya.
     *
     * ── KARTUNYA TETAP UTUH MESKI HARGANYA BELUM DIISI ──
     *
     * Daftar aplikasinya digambar dari App\Support\Modules, bukan dari
     * baris produk. Digambar dari produk, katalog yang harganya belum
     * ditetapkan akan tampil sebagai halaman kosong — dan halaman kosong
     * terbaca sebagai perusahaan yang tidak punya apa-apa untuk dijual,
     * bukan sebagai harga yang belum diumumkan.
     *
     * Harganya menempel bila ada, dan hanya butir berharga yang dapat
     * dimasukkan ke pesanan. Yang belum berharga tampil apa adanya:
     * "hubungi untuk penawaran".
     */
    public function publik()
    {
        $produk = Produk::aktif()->get()->keyBy('modul_kunci');
        $paket  = Produk::aktif()->where('jenis', Produk::WEBSITE)
            ->orderBy('urutan')->first();

        $pilar = Pillars::all();

        $aplikasi = [];

        foreach (Modules::perKunciMenu() as $kunci => $modul) {
            $p = $produk->get($kunci);
            $w = $pilar[$modul['pilar']] ?? $pilar['engineering'];

            $aplikasi[] = [
                /* Tanpa produk, idnya null — dan layar memakai null itu
                   untuk menentukan kartu mana yang punya tombol beli,
                   alih-alih memeriksa harga di dua tempat berbeda. */
                'id'    => $p?->id,
                'nama'  => $modul['nama'],
                'ket'   => $modul['ket'],
                'ikon'  => $modul['ikon'],
                'harga' => $p?->harga,
                'masa'  => $p?->masaBerlaku(),
                'pilar'      => $modul['pilar'],
                'pilarNama'  => $w['nama'],
                'pilarWarna' => $w['warna'],
                'pilarDeep'  => $w['deep'],
            ];
        }

        return Inertia::render('Pembelian/Etalase', [
            'paket' => $paket ? [
                'id'    => $paket->id,
                'nama'  => $paket->nama,
                'ket'   => $paket->keterangan,
                'harga' => $paket->harga,
                'masa'  => $paket->masaBerlaku(),
            ] : null,

            'aplikasi' => $aplikasi,
            'pilar'    => array_map(fn ($w) => [
                'nama' => $w['nama'], 'warna' => $w['warna'], 'deep' => $w['deep'],
            ], $pilar),

            /* Kosong bila tidak satu butir pun berharga — layar memakainya
               untuk menukar seluruh ajakan beli menjadi ajakan bertanya,
               bukan untuk menyembunyikan katalognya. */
            'adaHarga' => $paket !== null || $produk->isNotEmpty(),

            'kontak' => [
                'whatsapp' => (string) config('pembelian.kontak.whatsapp'),
                'email'    => (string) config('pembelian.kontak.email'),
            ],

            'tahun' => now()->year,
        ]);
    }

    /**
     * Pesanan dari etalase publik.
     *
     * Terpisah dari `simpan()` karena UJUNGNYA berbeda, bukan karena
     * aturannya berbeda: pembeli publik tidak punya akun, jadi ia tidak
     * boleh dilempar ke layar kelola tagihan yang berada di balik login —
     * yang terjadi di sana bukan halaman tagihan melainkan halaman masuk,
     * tepat sesudah orangnya menekan "beli".
     *
     * Pembatasan lajunya dipasang pada rutenya. Tanpa itu, satu skrip
     * dapat menerbitkan ribuan tagihan semalaman; tidak satu pun berisi
     * uang, tetapi daftar tagihan yang sebenarnya tenggelam di baliknya.
     */
    public function pesanPublik(Request $request)
    {
        $data = $this->validasiPesanan($request);

        $pesanan = Pembelian::buat(
            $this->pembeli($data) + ['company_id' => $request->user()?->company_id],
            $data['produk'],
            $request->user(),
        );

        if ($pesanan->items()->count() < 1) {
            $pesanan->delete();

            return back()->withErrors(['produk' => self::TAK_TERSEDIA]);
        }

        Pembelian::kirim($pesanan);

        /* Ke halaman bayar lewat TOKEN — satu-satunya alamat pesanan yang
           terbuka tanpa login. */
        return redirect()->route('pembelian.bayar', $pesanan->token);
    }

    private const TAK_TERSEDIA = 'Tidak satu pun produk yang dipilih masih tersedia. '
        .'Muat ulang katalognya lalu pilih kembali.';

    /** @return array<string, mixed> */
    private function validasiPesanan(Request $request): array
    {
        return $request->validate([
            'pembeli_nama'       => ['required', 'string', 'max:150'],
            'pembeli_perusahaan' => ['nullable', 'string', 'max:150'],
            'pembeli_email'      => ['nullable', 'email', 'max:150'],
            'pembeli_telepon'    => ['nullable', 'string', 'max:40'],
            'catatan'            => ['nullable', 'string', 'max:2000'],

            /* Yang diterima hanya ID dan banyaknya. HARGA TIDAK PERNAH
               DITERIMA dari layar — lihat App\Support\Pembelian. */
            'produk'             => ['required', 'array', 'min:1'],
            'produk.*'           => ['integer', 'min:1', 'max:99'],
        ]);
    }

    /** @return array<string, mixed> */
    private function pembeli(array $data): array
    {
        return [
            'pembeli_nama'       => $data['pembeli_nama'],
            'pembeli_perusahaan' => $data['pembeli_perusahaan'] ?? null,
            'pembeli_email'      => $data['pembeli_email'] ?? null,
            'pembeli_telepon'    => $data['pembeli_telepon'] ?? null,
            'catatan'            => $data['catatan'] ?? null,
        ];
    }

    /* ═══════════════════ katalog dan tagihan ═══════════════════ */

    public function katalog()
    {
        $katalog = Pembelian::katalog();

        return Inertia::render('Pembelian/Katalog', [
            'judul'    => 'Pembelian',
            'subjudul' => 'Website EQOHSEE dan aplikasi di dalamnya',

            'website'  => $this->barisProduk($katalog[Produk::WEBSITE] ?? []),
            'aplikasi' => $this->barisProduk($katalog[Produk::APLIKASI] ?? []),

            /* Katalog kosong disebut sebabnya, bukan dibiarkan sebagai
               halaman putih. Yang membacanya harus tahu bahwa yang
               kurang adalah datanya, bukan aplikasinya. */
            'kosong' => (! ($katalog[Produk::WEBSITE] ?? [])) && (! ($katalog[Produk::APLIKASI] ?? [])),
        ]);
    }

    /** @param iterable<Produk> $daftar */
    private function barisProduk(iterable $daftar): array
    {
        return collect($daftar)->map(fn (Produk $p) => [
            'id'         => $p->id,
            'kode'       => $p->kode,
            'nama'       => $p->nama,
            'keterangan' => $p->keterangan,
            'harga'      => $p->harga,
            'masa'       => $p->masaBerlaku(),
        ])->values()->all();
    }

    public function simpan(Request $request)
    {
        $data = $this->validasiPesanan($request);

        $pesanan = Pembelian::buat(
            $this->pembeli($data) + ['company_id' => $request->user()?->company_id],
            $data['produk'],
            $request->user(),
        );

        if ($pesanan->items()->count() < 1) {
            $pesanan->delete();

            return back()->withErrors(['produk' => self::TAK_TERSEDIA]);
        }

        Pembelian::kirim($pesanan);

        return redirect()->route('pembelian.tagihan', $pesanan)
            ->with('ok', 'Tagihan '.$pesanan->no_pesanan.' dibuat.');
    }

    public function daftar(Request $request)
    {
        $baris = Pesanan::with(['items', 'company'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->get();

        return Inertia::render('Pembelian/Daftar', [
            'judul'    => 'Pembelian — Tagihan',
            'subjudul' => 'Seluruh tagihan beserta keadaannya',
            'saring'   => ['status' => $request->get('status')],
            'opsiStatus' => Pesanan::LABEL,
            'baris'    => $baris->map(fn (Pesanan $p) => $this->barisPesanan($p))->values(),
            'ringkas'  => [
                ['Menunggu bayar',      $baris->where('status', Pesanan::MENUNGGU_BAYAR)->count(), 'netral'],
                ['Menunggu verifikasi', $baris->where('status', Pesanan::MENUNGGU_VERIFIKASI)->count(), 'ingat'],
                ['Lunas',               $baris->where('status', Pesanan::LUNAS)->count(), 'baik'],
            ],
        ]);
    }

    private function barisPesanan(Pesanan $p): array
    {
        return [
            'id'        => $p->id,
            'nomor'     => $p->no_pesanan ?? '#'.$p->id,
            'pembeli'   => $p->pembeli_nama,
            'perusahaan' => $p->pembeli_perusahaan ?: $p->company?->name,
            'total'     => $p->total,
            'status'    => $p->keadaan(),
            'statusLabel' => $p->keadaanLabel(),
            'tanggal'   => $p->created_at?->toDateString(),
            'butir'     => $p->items->count(),
        ];
    }

    /** Halaman kelola satu tagihan — untuk penjual, bukan pembeli. */
    public function tagihan(Pesanan $pesanan)
    {
        $pesanan->load(['items', 'pembayaran', 'lisensi.produk', 'pemverifikasi']);

        return Inertia::render('Pembelian/Tagihan', [
            'judul'    => 'Tagihan '.($pesanan->no_pesanan ?? '#'.$pesanan->id),
            'subjudul' => 'Rincian, bukti bayar, dan lisensinya',
            'pesanan'  => $this->rincianPesanan($pesanan),

            /* Tautan bayar yang dikirim ke pembeli. Ditampilkan supaya
               dapat disalin — itu satu-satunya cara pembeli sampai ke
               halaman bayarnya. */
            'tautanBayar' => route('pembelian.bayar', $pesanan->token),
        ]);
    }

    private function rincianPesanan(Pesanan $p): array
    {
        return $this->barisPesanan($p) + [
            'email'    => $p->pembeli_email,
            'telepon'  => $p->pembeli_telepon,
            'catatan'  => $p->catatan,
            'alasanTolak' => $p->alasan_tolak,
            'kedaluwarsa' => $p->kedaluwarsa_pada?->toDateTimeString(),
            'diverifikasiOleh' => $p->pemverifikasi?->name,
            'selesai'  => $p->selesai(),

            'items' => $p->items->map(fn ($i) => [
                'id' => $i->id, 'nama' => $i->nama, 'harga' => $i->harga,
                'jumlah' => $i->jumlah, 'subtotal' => $i->subtotal,
            ])->values(),

            'pembayaran' => $p->pembayaran->map(fn (Pembayaran $b) => [
                'id'      => $b->id,
                'metode'  => Pembayaran::METODE[$b->metode] ?? $b->metode,
                'jumlah'  => $b->jumlah,
                'atasNama' => $b->atas_nama,
                'tanggal' => $b->tanggal_bayar?->toDateString(),
                'bukti'   => Berkas::url($b, 'bkt'),
                'catatan' => $b->catatan,
            ])->values(),

            'lisensi' => $p->lisensi->map(fn ($l) => [
                'id' => $l->id, 'kunci' => $l->kunci,
                'produk' => $l->produk?->nama,
                'mulai' => $l->mulai?->toDateString(),
                'berakhir' => $l->berakhir?->toDateString(),
                'berlaku' => $l->berlaku(),
            ])->values(),
        ];
    }

    /* ═══════════════════ halaman bayar — tanpa login ═══════════════════ */

    /**
     * Dicari lewat TOKEN, bukan id.
     *
     * `firstOrFail` atas kolom token: id berurutan pada alamat berarti
     * mengganti angkanya menampilkan tagihan orang lain lengkap dengan
     * nama, telepon, dan nilainya — pada halaman yang memang tidak
     * meminta login.
     */
    public function bayar(string $token)
    {
        $pesanan = Pesanan::with('items')->where('token', $token)->firstOrFail();

        return Inertia::render('Pembelian/Bayar', [
            'judul'    => 'Pembayaran',
            'subjudul' => $pesanan->no_pesanan ?? '',

            'pesanan' => [
                'nomor'   => $pesanan->no_pesanan ?? '#'.$pesanan->id,
                'pembeli' => $pesanan->pembeli_nama,
                'total'   => $pesanan->total,
                'status'  => $pesanan->keadaan(),
                'statusLabel' => $pesanan->keadaanLabel(),
                'bolehDibayar' => $pesanan->bolehDibayar(),
                'alasanTolak'  => $pesanan->alasan_tolak,
                'kedaluwarsa'  => $pesanan->kedaluwarsa_pada?->toDateTimeString(),
                'items' => $pesanan->items->map(fn ($i) => [
                    'nama' => $i->nama, 'jumlah' => $i->jumlah, 'subtotal' => $i->subtotal,
                ])->values(),
            ],

            'tujuan' => Pembelian::tujuanBayar($pesanan),
            'token'  => $pesanan->token,

            /* Menggantikan kalimat bawaan kerangka publik, yang menyebut
               jawaban anonim untuk penilaian PTPKKP — keliru di halaman
               tagihan, dan cukup keliru untuk membuat pembacanya menduga
               ia berada di halaman yang salah. */
            'catatanKaki' => 'Tagihan ini diterbitkan EQOHSEE. Simpan bukti pembayaran '
                .'Anda sampai tagihannya dinyatakan lunas.',
        ]);
    }

    /**
     * Pembeli mengunggah bukti bayar.
     *
     * Tidak menetapkan lunas. Yang terjadi hanya "ada yang mengaku sudah
     * membayar" — dan itu memang seluruh yang diketahui aplikasi tanpa
     * penyedia pembayaran.
     */
    public function unggahBukti(Request $request, string $token)
    {
        $pesanan = Pesanan::where('token', $token)->firstOrFail();

        abort_unless($pesanan->bolehDibayar(), 422,
            'Tagihan ini sudah '.strtolower($pesanan->keadaanLabel()).'.');

        $data = $request->validate([
            'metode'    => ['required', Rule::in(array_keys(Pembayaran::METODE))],
            'atas_nama' => ['required', 'string', 'max:150'],
            'tanggal_bayar' => ['required', 'date'],
            'catatan'   => ['nullable', 'string', 'max:1000'],

            /* Gambar atau PDF saja, dan berukuran wajar. Tanpa batas
               jenis, kolom ini menjadi tempat mengunggah berkas apa pun
               ke server oleh siapa pun yang punya tautannya. */
            'bukti'     => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        Pembayaran::create([
            'pesanan_id' => $pesanan->id,
            'metode'     => $data['metode'],

            /* Nilainya diambil dari TAGIHAN, bukan dari isian. Pembeli
               yang mengetik nilai lain hanya menimbulkan selisih yang
               harus ditelusuri manusia; yang menentukan tetap nilai
               tagihannya. */
            'jumlah'     => $pesanan->total,
            'atas_nama'  => $data['atas_nama'],
            'tanggal_bayar' => $data['tanggal_bayar'],
            'bukti'      => Berkas::simpan($request->file('bukti'), 'pembelian/bukti'),
            'catatan'    => $data['catatan'] ?? null,
        ]);

        $pesanan->forceFill(['status' => Pesanan::MENUNGGU_VERIFIKASI])->save();

        return back()->with('ok',
            'Bukti pembayaran terkirim. Tagihan akan diperiksa lebih dulu sebelum dinyatakan lunas.');
    }

    /* ═══════════════════ harga — hanya admin ═══════════════════ */

    /**
     * Daftar harga: satu-satunya layar yang membuat katalog dapat dijual.
     *
     * `pembelian:katalog` sengaja memasang butir baru berharga nol dan
     * tidak aktif — harga adalah keputusan dagang, bukan keputusan
     * pemrogram. Tanpa layar ini keputusan itu tidak punya tempat untuk
     * dituliskan selain langsung ke basis data, dan katalog yang hanya
     * dapat diisi lewat SQL adalah katalog yang tidak pernah terisi.
     */
    public function produk(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403,
            'Hanya admin yang dapat mengubah daftar harga.');

        $baris = Produk::orderBy('urutan')->orderBy('nama')->get();

        return Inertia::render('Pembelian/Produk', [
            'judul'    => 'Pembelian — Daftar harga',
            'subjudul' => 'Harga, masa berlaku, dan butir mana yang dijual',

            'baris' => $baris->map(fn (Produk $p) => [
                'id'    => $p->id,
                'kode'  => $p->kode,
                'nama'  => $p->nama,
                'jenis' => $p->jenis,
                'keterangan' => $p->keterangan,
                'harga' => $p->harga,
                'masa_bulan' => $p->masa_bulan,
                'aktif' => $p->aktif,

                /* Butir berharga nol tidak dapat diaktifkan — dan
                   layarnya menyebutkannya di baris itu sendiri, bukan
                   membiarkan tombolnya ditekan lalu ditolak diam-diam
                   oleh server. */
                'bolehAktif' => $p->harga > 0,
            ])->values(),

            'tautanEtalase' => route('katalog.publik'),
        ]);
    }

    /**
     * Simpan satu baris harga.
     *
     * ── NOL TIDAK DAPAT AKTIF ──
     *
     * Butir aktif berharga nol dapat dipesan, ditagihkan, "dibayar", dan
     * lisensinya terbit — tanpa satu rupiah pun masuk dan tanpa satu pun
     * angka yang terlihat janggal di layar mana pun, sebab nol itu
     * memang yang tersimpan. Karena itu keaktifan dipaksa mati ketika
     * harganya nol, di sini, bukan hanya disembunyikan tombolnya.
     */
    public function simpanProduk(Request $request, Produk $produk)
    {
        abort_unless($request->user()?->isAdmin(), 403,
            'Hanya admin yang dapat mengubah daftar harga.');

        $data = $request->validate([
            /* Rupiah bulat. Pecahan rupiah tidak ada di dunia nyata, dan
               harga pecahan yang tersimpan sebagai bilangan bulat
               dibulatkan diam-diam entah ke mana. */
            'harga'      => ['required', 'integer', 'min:0', 'max:99999999999'],
            'masa_bulan' => ['required', 'integer', 'min:0', 'max:600'],
            'aktif'      => ['required', 'boolean'],
        ]);

        $produk->update([
            'harga'      => $data['harga'],
            'masa_bulan' => $data['masa_bulan'],
            'aktif'      => $data['harga'] > 0 && $data['aktif'],
        ]);

        return back()->with('ok', $data['harga'] > 0 || ! $data['aktif']
            ? 'Harga '.$produk->nama.' disimpan.'
            : 'Harga '.$produk->nama.' disimpan, tetapi butir berharga nol tidak dapat diaktifkan.');
    }

    /* ═══════════════════ verifikasi — hanya admin ═══════════════════ */

    public function verifikasi(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isAdmin(), 403,
            'Hanya admin yang dapat menyatakan sebuah tagihan lunas.');

        if ($pesanan->status === Pesanan::LUNAS) {
            return back()->withErrors(['status' => 'Tagihan ini sudah lunas.']);
        }

        Pembelian::tandaiLunas($pesanan, $request->user());

        return back()->with('ok', 'Tagihan dinyatakan lunas dan lisensinya diterbitkan.');
    }

    public function tolak(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isAdmin(), 403,
            'Hanya admin yang dapat menolak bukti pembayaran.');

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        Pembelian::tolak($pesanan, $request->user(), $data['alasan']);

        return back()->with('ok', 'Bukti ditolak. Pembeli dapat mengirim ulang lewat tautan yang sama.');
    }

    public function batal(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang dapat membatalkan tagihan.');

        if ($pesanan->status === Pesanan::LUNAS) {
            return back()->withErrors([
                'status' => 'Tagihan yang sudah lunas tidak dapat dibatalkan — lisensinya sudah terbit.',
            ]);
        }

        $pesanan->forceFill(['status' => Pesanan::BATAL])->save();

        return back()->with('ok', 'Tagihan dibatalkan.');
    }
}
