<?php

namespace App\Http\Controllers;

use App\Rules\DalamPerusahaan;

use App\Models\{GudangBarang, GudangLokasi, GudangMutasi};
use App\Support\{Gudang, Waktu};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Sistem Informasi Gudang & Penyimpanan.
 *
 * Satu register untuk B3, material, dan APD. Yang membedakan ketiganya
 * hanya kolom tambahan dan aturan pengawasannya — alur penerimaan,
 * pengeluaran, dan opname sama persis.
 *
 * Seluruh angka stok dihitung di sini, tidak di sisi Vue: saldo
 * diturunkan dari mutasi lewat App\Support\Gudang, dan menghitungnya
 * ulang di peramban berarti dua penerapan aturan yang sama yang dapat
 * berselisih tanpa ketahuan.
 */
class GudangController extends Controller
{
    /* ══════════════ penyaji bersama ══════════════ */

    /**
     * Satu baris mutasi sebagaimana digambar Gudang/BarisMutasi.vue.
     *
     * Opname sengaja membawa `stokFisik` alih-alih `jumlah`: ia
     * menetapkan saldo, bukan menambah atau mengurangi, dan menampilkan
     * `jumlah`-nya (yang selalu nol) membuatnya terbaca sebagai mutasi
     * kosong.
     */
    private function barisMutasi(GudangMutasi $m): array
    {
        return [
            'id'         => $m->id,
            'jenis'      => $m->jenis,
            'nomor'      => $m->nomor,
            'tanggal'    => $m->tanggal?->format('d M Y'),
            'pihak'      => $m->pihak ?: null,
            'jumlah'     => (float) $m->jumlah,
            'stokFisik'  => $m->stok_fisik === null ? null : (float) $m->stok_fisik,
            'barang'     => $m->barang?->nama,
            'satuan'     => $m->barang?->satuan,
        ];
    }

    /** Satu baris barang beserta saldo dan statusnya. */
    private function barisBarang(GudangBarang $b): array
    {
        $s = Gudang::statusStok($b);

        return [
            'id'          => $b->id,
            'kode'        => $b->kode,
            'nama'        => $b->nama,
            'kategori'    => $b->kategori,
            'namaKategori'=> Gudang::namaKategori($b->kategori),
            'nadaKategori'=> Gudang::nadaKategori($b->kategori),
            'satuan'      => $b->satuan,
            'stok'        => Gudang::stok($b),
            'stokMin'     => (float) $b->stok_min,
            'lokasi'      => $b->lokasi?->nama,
            'partNumber'  => $b->part_number ?: null,
            'kelasB3'     => $b->kelas_b3,
            'namaKelas'   => $b->kelas_b3 ? Gudang::namaKelas($b->kelas_b3) : null,
            'msds'        => $b->msds ? asset('storage/'.$b->msds) : null,
            'status'      => $s,
            'urlUbah'     => route('gudang.barang.edit', $b),
            'urlHapus'    => route('gudang.barang.hapus', $b),
        ];
    }

    /** Kedaluwarsa dalam bentuk yang siap digambar. */
    private function barisKedaluwarsa(array $daftar): array
    {
        return array_map(fn ($k) => [
            'nama'    => $k['barang']->nama,
            'batch'   => $k['batch'],
            'tanggal' => $k['tanggal']->format('d M Y'),
            'sisa'    => $k['sisa'],
        ], $daftar);
    }

    /** Pelanggaran penyimpanan di seluruh lokasi, berikut nama lokasinya. */
    private function pelanggaranSemua(): array
    {
        $langgar = [];

        // Diperiksa per lokasi: dua bahan berpantangan di gudang yang
        // berbeda bukan masalah.
        foreach (GudangLokasi::with('barang.mutasi')->get() as $l) {
            foreach (Gudang::periksaPenyimpanan($l->barang) as $x) {
                $langgar[] = $x + ['lokasi' => $l->nama];
            }
        }

        return $langgar;
    }

    /* ══════════════ ringkasan ══════════════ */

    public function index()
    {
        $barang = GudangBarang::with(['mutasi', 'lokasi'])->where('aktif', true)->get();
        $r      = Gudang::ringkas($barang);

        $kritis = $barang->filter(fn ($b) => Gudang::statusStok($b)['kode'] !== 'aman')
                         ->sortBy(fn ($b) => Gudang::stok($b))
                         ->take(10);

        return Inertia::render('Gudang/Index', [
            'judul'    => 'Gudang & Penyimpanan',
            'subjudul' => 'Persediaan B3, material, dan APD dalam satu register',

            'r' => $r,

            'kategori' => array_map(fn ($kode) => [
                'kode'   => $kode,
                'nama'   => Gudang::KATEGORI[$kode]['nama'],
                'jumlah' => $r['kategori'][$kode] ?? 0,
                'url'    => route('gudang.barang', ['kategori' => $kode]),
            ], array_keys(Gudang::KATEGORI)),

            'kritis'      => $kritis->map(fn ($b) => $this->barisBarang($b))->values()->all(),
            'kedaluwarsa' => $this->barisKedaluwarsa(array_slice(Gudang::kedaluwarsa($barang), 0, 10)),
            'ambang'      => Gudang::AMBANG_KEDALUWARSA_HARI,
            'langgar'     => $this->pelanggaranSemua(),

            'terakhir' => GudangMutasi::with('barang')->latest('tanggal')->latest('id')->take(8)->get()
                ->map(fn ($m) => $this->barisMutasi($m))->all(),

            'tautan' => [
                'barang'  => route('gudang.barang'),
                'menipis' => route('gudang.barang', ['status' => 'menipis']),
                'mutasi'  => route('gudang.mutasi'),
            ],
        ]);
    }

    /* ══════════════ barang ══════════════ */

    public function barang(Request $r)
    {
        $q = GudangBarang::with(['mutasi', 'lokasi']);

        if ($k = $r->query('kategori')) $q->where('kategori', $k);
        if ($l = $r->query('lokasi'))   $q->where('lokasi_id', $l);
        if ($cari = trim((string) $r->query('cari'))) {
            $q->where(fn ($w) => $w->where('nama', 'like', "%{$cari}%")
                                   ->orWhere('kode', 'like', "%{$cari}%")
                                   ->orWhere('part_number', 'like', "%{$cari}%"));
        }

        $barang = $q->orderBy('nama')->get();

        // Penyaring status dikerjakan setelah pengambilan: statusnya
        // diturunkan dari mutasi, jadi tidak dapat dijadikan syarat query.
        if ($s = $r->query('status')) {
            $barang = $barang->filter(fn ($b) => Gudang::statusStok($b)['kode'] === $s)->values();
        }

        return Inertia::render('Gudang/Barang', [
            'judul'    => 'Daftar Barang',
            'subjudul' => 'Register B3, material, dan APD beserta saldo berjalannya',

            'barang' => $barang->map(fn ($b) => $this->barisBarang($b))->values()->all(),

            'f' => [
                'cari'     => $r->query('cari') ?: '',
                'kategori' => $r->query('kategori') ?: '',
                'status'   => $r->query('status') ?: '',
                'lokasi'   => $r->query('lokasi') ?: '',
            ],

            'opsi' => [
                'kategori' => array_map(fn ($k) => ['nilai' => $k, 'label' => Gudang::KATEGORI[$k]['nama']],
                                        array_keys(Gudang::KATEGORI)),
                'status'   => [
                    ['nilai' => 'aman', 'label' => 'Aman'],
                    ['nilai' => 'menipis', 'label' => 'Menipis'],
                    ['nilai' => 'habis', 'label' => 'Habis'],
                ],
                'lokasi' => GudangLokasi::orderBy('nama')->get()
                    ->map(fn ($l) => ['nilai' => (string) $l->id, 'label' => $l->nama])->all(),
            ],

            'bolehUbah' => Gate::allows('admin'),
            'tautan'    => ['baru' => route('gudang.barang.baru'), 'daftar' => route('gudang.barang')],
        ]);
    }

    public function barangForm(?GudangBarang $barang = null)
    {
        $b = $barang ?? new GudangBarang(['kategori' => 'material', 'satuan' => 'pcs', 'aktif' => true]);

        return Inertia::render('Gudang/BarangForm', [
            'judul'    => $b->exists ? 'Ubah Barang' : 'Barang Baru',
            'subjudul' => 'Kolom tambahan menyesuaikan kategori yang dipilih',

            'tersimpan' => $b->exists,
            'nama'      => $b->exists ? $b->nama : 'Barang Baru',

            'awal' => [
                'kode'             => (string) ($b->kode ?? ''),
                'nama'             => (string) ($b->nama ?? ''),
                'kategori'         => $b->kategori ?: 'material',
                'satuan'           => $b->satuan ?: 'pcs',
                'stok_min'         => (string) ($b->stok_min ?? 0),
                'lokasi_id'        => $b->lokasi_id ? (string) $b->lokasi_id : '',
                'kelas_b3'         => (string) ($b->kelas_b3 ?? ''),
                'wujud'            => (string) ($b->wujud ?? ''),
                'un_number'        => (string) ($b->un_number ?? ''),
                'masa_pakai_bulan' => (string) ($b->masa_pakai_bulan ?? ''),
                'ukuran'           => (string) ($b->ukuran ?? ''),
                'part_number'      => (string) ($b->part_number ?? ''),
                'merk'             => (string) ($b->merk ?? ''),
                'keterangan'       => (string) ($b->keterangan ?? ''),
                'aktif'            => $b->exists ? (bool) $b->aktif : true,
            ],

            'msds' => $b->msds ? asset('storage/'.$b->msds) : null,

            'opsi' => [
                'kategori' => array_map(fn ($k) => ['nilai' => $k, 'label' => Gudang::KATEGORI[$k]['nama']],
                                        array_keys(Gudang::KATEGORI)),
                'kelasB3'  => array_map(fn ($k) => ['nilai' => $k, 'label' => Gudang::KELAS_B3[$k]['nama']],
                                        array_keys(Gudang::KELAS_B3)),
                'wujud'    => ['padat', 'cair', 'gas'],
                'lokasi'   => GudangLokasi::orderBy('nama')->get()
                    ->map(fn ($l) => ['nilai' => (string) $l->id, 'label' => "{$l->nama} ({$l->kode})"])->all(),
            ],

            'tautan' => [
                'simpan' => $b->exists ? route('gudang.barang.ubah', $b) : route('gudang.barang.simpan'),
                'batal'  => route('gudang.barang'),
            ],
        ]);
    }

    public function barangSimpan(Request $r, ?GudangBarang $barang = null)
    {
        $data = $r->validate([
            'kode'             => ['required', 'string', 'max:40',
                                   Rule::unique('gudang_barang', 'kode')->ignore($barang?->id)],
            'nama'             => ['required', 'string', 'max:200'],
            'kategori'         => ['required', Rule::in(array_keys(Gudang::KATEGORI))],
            'satuan'           => ['required', 'string', 'max:20'],
            'stok_min'         => ['nullable', 'numeric', 'min:0'],
            'lokasi_id'        => ['nullable', new DalamPerusahaan('gudang_lokasi')],
            'kelas_b3'         => ['nullable', Rule::in(array_keys(Gudang::KELAS_B3))],
            'wujud'            => ['nullable', Rule::in(['padat', 'cair', 'gas'])],
            'un_number'        => ['nullable', 'string', 'max:12'],
            'masa_pakai_bulan' => ['nullable', 'integer', 'min:1', 'max:600'],
            'ukuran'           => ['nullable', 'string', 'max:40'],
            'part_number'      => ['nullable', 'string', 'max:80'],
            'merk'             => ['nullable', 'string', 'max:80'],
            'keterangan'       => ['nullable', 'string', 'max:1000'],
            'msds'             => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ], [], ['kode' => 'kode barang', 'nama' => 'nama barang', 'kelas_b3' => 'kelas bahaya']);

        // Kelas bahaya hanya bermakna untuk B3. Membiarkannya menempel
        // pada material membuat matriks pantangan memperingatkan hal yang
        // sebenarnya bukan bahan kimia.
        if ($data['kategori'] !== 'b3') {
            $data['kelas_b3'] = null;
            $data['un_number'] = null;
        }
        if ($data['kategori'] !== 'apd') {
            $data['masa_pakai_bulan'] = null;
        }

        $data['stok_min']   = $data['stok_min'] ?? 0;
        $data['wajib_msds'] = $data['kategori'] === 'b3';
        $data['aktif']      = $r->boolean('aktif', true);

        if ($r->hasFile('msds')) {
            if ($barang?->msds) Storage::disk('public')->delete($barang->msds);
            $data['msds'] = $r->file('msds')->store('gudang/msds', 'public');
        } else {
            unset($data['msds']);
        }

        $barang ? $barang->update($data) : GudangBarang::create($data);

        return redirect()->route('gudang.barang')->with('sukses', 'Data barang tersimpan.');
    }

    public function barangHapus(GudangBarang $barang)
    {
        // Barang yang pernah bermutasi tidak dihapus melainkan
        // dinonaktifkan: menghapusnya ikut membuang riwayat penerimaan dan
        // pengeluaran yang menjadi bukti penelusuran.
        if ($barang->mutasi()->exists()) {
            $barang->update(['aktif' => false]);

            return back()->with('sukses', 'Barang dinonaktifkan; riwayat mutasinya tetap tersimpan.');
        }

        $barang->delete();

        return back()->with('sukses', 'Barang dihapus.');
    }

    /* ══════════════ mutasi ══════════════ */

    public function mutasi(Request $r)
    {
        $q = GudangMutasi::with(['barang', 'penerima', 'user']);

        if ($j = $r->query('jenis'))   $q->where('jenis', $j);
        if ($b = $r->query('barang'))  $q->where('barang_id', $b);
        if ($d = $r->query('dari'))    $q->whereDate('tanggal', '>=', $d);
        if ($s = $r->query('sampai'))  $q->whereDate('tanggal', '<=', $s);

        $mutasi = $q->latest('tanggal')->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('Gudang/Mutasi', [
            'judul'    => 'Mutasi Keluar Masuk',
            'subjudul' => 'Penerimaan, pengeluaran, dan barang rusak',

            'mutasi' => array_map(fn ($m) => $this->barisMutasi($m), $mutasi->items()),

            'halaman' => [
                'kini'   => $mutasi->currentPage(),
                'akhir'  => $mutasi->lastPage(),
                'total'  => $mutasi->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $mutasi->linkCollection()->all()),
            ],

            // Sisa stok ikut di daftar pilihan supaya pencatat tahu batas
            // pengeluarannya sebelum menekan simpan, bukan setelah ditolak.
            'barang' => GudangBarang::with('mutasi')->where('aktif', true)->orderBy('nama')->get()
                ->map(fn ($b) => [
                    'id'     => $b->id,
                    'nama'   => $b->nama,
                    'satuan' => $b->satuan,
                    'stok'   => Gudang::stok($b),
                ])->all(),

            'f' => [
                'jenis'  => $r->query('jenis') ?: '',
                'barang' => $r->query('barang') ?: '',
                'dari'   => $r->query('dari') ?: '',
                'sampai' => $r->query('sampai') ?: '',
            ],

            'opsi' => [
                'jenis'      => [
                    ['nilai' => 'masuk', 'label' => 'Masuk'],
                    ['nilai' => 'keluar', 'label' => 'Keluar'],
                    ['nilai' => 'rusak', 'label' => 'Rusak'],
                ],
                'jenisSaring' => [
                    ['nilai' => 'masuk', 'label' => 'Masuk'],
                    ['nilai' => 'keluar', 'label' => 'Keluar'],
                    ['nilai' => 'rusak', 'label' => 'Rusak'],
                    ['nilai' => 'opname', 'label' => 'Opname'],
                ],
            ],

            'hariIni'    => Waktu::kini()->toDateString(),
            'bolehCatat' => Gate::allows('admin'),
            'tautan'     => ['simpan' => route('gudang.mutasi.simpan'), 'daftar' => route('gudang.mutasi')],
        ]);
    }

    public function mutasiSimpan(Request $r)
    {
        $data = $r->validate([
            'barang_id'   => ['required', 'exists:gudang_barang,id'],
            'jenis'       => ['required', Rule::in(['masuk', 'keluar', 'rusak'])],
            'tanggal'     => ['required', 'date'],
            'jumlah'      => ['required', 'numeric', 'gt:0'],
            'pihak'       => ['nullable', 'string', 'max:160'],
            'penerima_id' => ['nullable', 'exists:users,id'],
            'batch'       => ['nullable', 'string', 'max:60'],
            'kadaluarsa'  => ['nullable', 'date'],
            'keterangan'  => ['nullable', 'string', 'max:500'],
        ], [], ['jumlah' => 'jumlah', 'barang_id' => 'barang']);

        $barang = GudangBarang::with('mutasi')->findOrFail($data['barang_id']);

        // Pengeluaran melebihi saldo ditolak. Membiarkannya menghasilkan
        // stok minus — angka yang tidak punya arti fisik dan menutupi
        // kesalahan pencatatan yang sesungguhnya.
        if (in_array($data['jenis'], Gudang::KELUAR, true)) {
            $stok = Gudang::stok($barang);
            if ($data['jumlah'] > $stok) {
                return back()->withInput()->withErrors([
                    'jumlah' => "Stok tersedia hanya {$stok} {$barang->satuan}. Lakukan opname bila jumlah fisiknya berbeda.",
                ]);
            }
        }

        $data['nomor']   = Gudang::nomorBerikut($data['jenis']);
        $data['user_id'] = $r->user()->id;

        GudangMutasi::create($data);

        return redirect()->route('gudang.mutasi')->with('sukses', "Mutasi {$data['nomor']} tercatat.");
    }

    /* ══════════════ opname ══════════════ */

    public function opname(Request $r)
    {
        $barang = GudangBarang::with(['mutasi', 'lokasi'])->where('aktif', true)->orderBy('nama')->get();

        return Inertia::render('Gudang/Opname', [
            'judul'    => 'Stok Opname',
            'subjudul' => 'Hitung fisik dan koreksi saldo buku',

            'barang' => $barang->map(fn ($b) => [
                'id'     => $b->id,
                'kode'   => $b->kode,
                'nama'   => $b->nama,
                'satuan' => $b->satuan,
                'lokasi' => $b->lokasi?->nama,
                'buku'   => Gudang::stok($b),
            ])->all(),

            'lalu' => GudangMutasi::with('barang')->where('jenis', 'opname')
                ->latest('tanggal')->latest('id')->take(20)->get()
                ->map(fn ($m) => $this->barisMutasi($m))->all(),

            'hariIni'    => Waktu::kini()->toDateString(),
            'bolehCatat' => Gate::allows('admin'),
            'tautan'     => ['simpan' => route('gudang.opname.simpan')],
        ]);
    }

    public function opnameSimpan(Request $r)
    {
        $data = $r->validate([
            'tanggal'      => ['required', 'date'],
            'fisik'        => ['required', 'array'],
            'fisik.*'      => ['nullable', 'numeric', 'min:0'],
            'keterangan'   => ['nullable', 'string', 'max:500'],
        ]);

        $nomor = Gudang::nomorBerikut('opname');
        $n = 0;

        foreach ($data['fisik'] as $id => $fisik) {
            if ($fisik === null || $fisik === '') continue;

            $barang = GudangBarang::with('mutasi')->find($id);
            if (!$barang) continue;

            // Baris yang jumlah fisiknya sama dengan stok buku tidak
            // dicatat: opname tanpa selisih tidak mengubah apa pun, dan
            // mencatatnya membuat riwayat penuh baris yang tidak berarti.
            if (abs(Gudang::stok($barang) - (float) $fisik) < 0.005) continue;

            GudangMutasi::create([
                'barang_id'  => $barang->id,
                'jenis'      => 'opname',
                'tanggal'    => $data['tanggal'],
                'nomor'      => $nomor,
                'jumlah'     => 0,
                'stok_fisik' => (float) $fisik,
                'keterangan' => $data['keterangan'] ?? null,
                'user_id'    => $r->user()->id,
            ]);
            $n++;
        }

        return redirect()->route('gudang.opname')->with('sukses',
            $n > 0 ? "Opname {$nomor} tercatat untuk {$n} barang yang berselisih."
                   : 'Tidak ada selisih; tidak ada yang perlu dicatat.');
    }

    /* ══════════════ lokasi ══════════════ */

    /** Syarat penyimpanan yang dicatat per lokasi, beserta labelnya. */
    private const SYARAT = [
        'berventilasi' => ['Berventilasi', 'Ventilasi'],
        'tahan_api'    => ['Tahan api', 'Tahan api'],
        'ada_tanggul'  => ['Bertanggul (secondary containment)', 'Tanggul'],
        'ada_apar'     => ['Tersedia APAR', 'APAR'],
        'ada_eyewash'  => ['Tersedia eyewash', 'Eyewash'],
    ];

    public function lokasi()
    {
        $lokasi = GudangLokasi::with('barang.mutasi')->orderBy('nama')->get();

        return Inertia::render('Gudang/Lokasi', [
            'judul'    => 'Lokasi Penyimpanan',
            'subjudul' => 'Gudang, rak, dan syarat penyimpanannya',

            'lokasi' => $lokasi->map(fn ($l) => [
                'id'      => $l->id,
                'kode'    => $l->kode,
                'nama'    => $l->nama,
                'jenis'   => $l->jenis,
                'letak'   => $l->lokasi ?: null,
                'pj'      => $l->penanggung_jawab ?: null,
                'jumlah'  => $l->barang->count(),
                'syarat'  => array_map(fn ($k) => [
                    'kunci' => $k, 'label' => self::SYARAT[$k][1], 'ada' => (bool) $l->$k,
                ], array_keys(self::SYARAT)),
                'langgar' => Gudang::periksaPenyimpanan($l->barang),
                'urlUbah' => route('gudang.lokasi.ubah', $l),
            ])->all(),

            'opsi' => [
                'jenis'  => [
                    ['nilai' => 'umum', 'label' => 'Umum'],
                    ['nilai' => 'b3', 'label' => 'Khusus B3'],
                    ['nilai' => 'material', 'label' => 'Material'],
                    ['nilai' => 'apd', 'label' => 'APD'],
                ],
                'syarat' => array_map(fn ($k) => ['kunci' => $k, 'label' => self::SYARAT[$k][0]],
                                      array_keys(self::SYARAT)),
            ],

            'bolehUbah' => Gate::allows('admin'),
            'tautan'    => ['simpan' => route('gudang.lokasi.simpan')],
        ]);
    }

    public function lokasiSimpan(Request $r, ?GudangLokasi $lokasi = null)
    {
        $data = $r->validate([
            'kode'             => ['required', 'string', 'max:30',
                                   Rule::unique('gudang_lokasi', 'kode')->ignore($lokasi?->id)],
            'nama'             => ['required', 'string', 'max:160'],
            'jenis'            => ['required', Rule::in(['b3', 'material', 'apd', 'umum'])],
            'lokasi'           => ['nullable', 'string', 'max:200'],
            'penanggung_jawab' => ['nullable', 'string', 'max:120'],
            'suhu_maks'        => ['nullable', 'numeric'],
            'keterangan'       => ['nullable', 'string', 'max:500'],
        ]);

        foreach (array_keys(self::SYARAT) as $k) {
            $data[$k] = $r->boolean($k);
        }

        $lokasi ? $lokasi->update($data) : GudangLokasi::create($data);

        return redirect()->route('gudang.lokasi')->with('sukses', 'Lokasi penyimpanan tersimpan.');
    }

    /* ══════════════ B3 ══════════════ */

    public function b3()
    {
        $b3 = GudangBarang::with(['mutasi', 'lokasi'])
                          ->where('kategori', 'b3')->orderBy('nama')->get();

        $kelas = array_keys(Gudang::KELAS_B3);

        return Inertia::render('Gudang/B3', [
            'judul'    => 'Register B3',
            'subjudul' => 'Bahan berbahaya dan beracun beserta pantangan penyimpanannya',

            // Matriks disusun di server supaya aturan pantangannya hanya
            // punya satu penerapan. Menyusunnya di Vue berarti menyalin
            // App\Support\Gudang::pantangan ke TypeScript, dan salinan
            // yang tertinggal akan melaporkan aman untuk pasangan yang
            // justru berbahaya.
            'matriks' => array_map(fn ($a) => [
                'kelas' => $a,
                'nama'  => Gudang::namaKelas($a),
                'sel'   => array_map(fn ($b) => [
                    'sama'   => $a === $b,
                    'alasan' => $a === $b ? null : Gudang::pantangan($a, $b),
                ], $kelas),
            ], $kelas),

            'judulKolom' => array_map(fn ($k) => Gudang::namaKelas($k), $kelas),

            'b3' => $b3->map(fn ($b) => [
                'id'        => $b->id,
                'kode'      => $b->kode,
                'nama'      => $b->nama,
                'unNumber'  => $b->un_number ?: null,
                'kelas'     => $b->kelas_b3,
                'namaKelas' => $b->kelas_b3 ? Gudang::namaKelas($b->kelas_b3) : null,
                'caraSimpan'=> $b->kelas_b3 ? Gudang::KELAS_B3[$b->kelas_b3]['simpan'] : null,
                'wujud'     => $b->wujud ? ucfirst($b->wujud) : null,
                'lokasi'    => $b->lokasi?->nama,
                'stok'      => Gudang::stok($b),
                'satuan'    => $b->satuan,
                'msds'      => $b->msds ? asset('storage/'.$b->msds) : null,
            ])->all(),

            'langgar'     => $this->pelanggaranSemua(),
            'kedaluwarsa' => $this->barisKedaluwarsa(Gudang::kedaluwarsa($b3)),

            'tautan' => ['barang' => route('gudang.barang', ['kategori' => 'b3'])],
        ]);
    }

    /* ══════════════ laporan ══════════════ */

    public function laporan(Request $r)
    {
        $dari   = $r->query('dari')   ?: Waktu::kini()->startOfMonth()->toDateString();
        $sampai = $r->query('sampai') ?: Waktu::kini()->toDateString();

        $barang = GudangBarang::with(['mutasi', 'lokasi'])->orderBy('kategori')->orderBy('nama')->get();

        // Kartu stok per barang.
        //
        // Saldo awal dan akhir sama-sama dihasilkan dengan memutar ulang
        // mutasinya sampai tanggal tertentu, bukan dengan mengurangkan
        // yang satu dari yang lain. Menghitung awal sebagai
        // `akhir − masuk + keluar` benar hanya selama tidak pernah ada
        // opname di dalam periodenya — dan periode yang mengandung opname
        // itulah yang paling perlu dibaca orang.
        $sebelum = Waktu::lokal($dari)->subDay()->toDateString();

        $baris = [];
        foreach ($barang as $b) {
            $masuk = 0.0; $keluar = 0.0; $adaOpname = false;

            foreach ($b->mutasi as $m) {
                $tgl = $m->tanggal?->toDateString();
                if ($tgl === null || $tgl < $dari || $tgl > $sampai) continue;

                if ($m->jenis === 'masuk')  $masuk  += (float) $m->jumlah;
                if (in_array($m->jenis, Gudang::KELUAR, true)) $keluar += (float) $m->jumlah;
                if ($m->jenis === 'opname') $adaOpname = true;
            }

            $awal  = Gudang::stokPada($b, $sebelum);
            $akhir = Gudang::stokPada($b, $sampai);

            if ($masuk == 0.0 && $keluar == 0.0 && $awal == 0.0 && $akhir == 0.0) continue;

            // Selisih yang tidak dijelaskan oleh masuk dan keluar berasal
            // dari opname. Ditampilkan sebagai kolom tersendiri supaya
            // barisnya tetap dapat dijumlahkan pembaca — angka yang tidak
            // berjumlah membuat seluruh laporan dicurigai.
            $baris[] = [
                'nama'        => $b->nama,
                'kode'        => $b->kode,
                'satuan'      => $b->satuan,
                'namaKategori'=> Gudang::namaKategori($b->kategori),
                'nadaKategori'=> Gudang::nadaKategori($b->kategori),
                'awal'        => $awal,
                'masuk'       => round($masuk, 2),
                'keluar'      => round($keluar, 2),
                'penyesuaian' => round($akhir - ($awal + $masuk - $keluar), 2),
                'akhir'       => $akhir,
                'opname'      => $adaOpname,
            ];
        }

        return Inertia::render('Gudang/Laporan', [
            'judul'    => 'Laporan Stok',
            'subjudul' => 'Kartu stok per barang untuk periode terpilih',

            'baris'  => $baris,
            'dari'   => $dari,
            'sampai' => $sampai,
            'label'  => [
                'dari'   => Waktu::lokal($dari)?->format('d M Y'),
                'sampai' => Waktu::lokal($sampai)?->format('d M Y'),
            ],
            'r'      => Gudang::ringkas($barang),
            'tautan' => ['laporan' => route('gudang.laporan')],
        ]);
    }
}
