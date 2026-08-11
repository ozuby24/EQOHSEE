<?php

namespace App\Http\Controllers;

use App\Models\{GudangBarang, GudangLokasi, GudangMutasi};
use App\Support\{Gudang, Waktu};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Sistem Informasi Gudang & Penyimpanan.
 *
 * Satu register untuk B3, material, dan APD. Yang membedakan ketiganya
 * hanya kolom tambahan dan aturan pengawasannya — alur penerimaan,
 * pengeluaran, dan opname sama persis.
 */
class GudangController extends Controller
{
    public function index()
    {
        $barang = GudangBarang::with(['mutasi', 'lokasi'])->where('aktif', true)->get();

        $kritis = $barang->filter(fn ($b) => Gudang::statusStok($b)['kode'] !== 'aman')
                         ->sortBy(fn ($b) => Gudang::stok($b))
                         ->take(10);

        // Pelanggaran diperiksa per lokasi: dua bahan berpantangan di
        // gudang yang berbeda bukan masalah.
        $langgar = [];
        foreach (GudangLokasi::with('barang.mutasi')->get() as $l) {
            foreach (Gudang::periksaPenyimpanan($l->barang) as $x) {
                $langgar[] = $x + ['lokasi' => $l->nama];
            }
        }

        return view('gudang.index', [
            'r'           => Gudang::ringkas($barang),
            'kritis'      => $kritis,
            'kedaluwarsa' => array_slice(Gudang::kedaluwarsa($barang), 0, 10),
            'langgar'     => $langgar,
            'terakhir'    => GudangMutasi::with('barang')->latest('tanggal')->latest('id')->take(8)->get(),
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

        return view('gudang.barang', [
            'barang' => $barang,
            'lokasi' => GudangLokasi::orderBy('nama')->get(),
            'f'      => $r->only(['kategori', 'lokasi', 'status', 'cari']),
        ]);
    }

    public function barangForm(?GudangBarang $barang = null)
    {
        return view('gudang.barang-form', [
            'b'      => $barang ?? new GudangBarang(['kategori' => 'material', 'satuan' => 'pcs', 'aktif' => true]),
            'lokasi' => GudangLokasi::orderBy('nama')->get(),
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
            'lokasi_id'        => ['nullable', 'exists:gudang_lokasi,id'],
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

        return view('gudang.mutasi', [
            'mutasi' => $q->latest('tanggal')->latest('id')->paginate(30)->withQueryString(),
            'barang' => GudangBarang::where('aktif', true)->orderBy('nama')->get(),
            'f'      => $r->only(['jenis', 'barang', 'dari', 'sampai']),
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
        return view('gudang.opname', [
            'barang' => GudangBarang::with(['mutasi', 'lokasi'])->where('aktif', true)->orderBy('nama')->get(),
            'lalu'   => GudangMutasi::with('barang')->where('jenis', 'opname')
                                    ->latest('tanggal')->latest('id')->take(20)->get(),
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

    public function lokasi()
    {
        $lokasi = GudangLokasi::with('barang.mutasi')->orderBy('nama')->get();

        $langgar = [];
        foreach ($lokasi as $l) {
            $langgar[$l->id] = Gudang::periksaPenyimpanan($l->barang);
        }

        return view('gudang.lokasi', ['lokasi' => $lokasi, 'langgar' => $langgar]);
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

        foreach (['berventilasi', 'tahan_api', 'ada_tanggul', 'ada_apar', 'ada_eyewash'] as $k) {
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

        $langgar = [];
        foreach (GudangLokasi::with('barang.mutasi')->get() as $l) {
            foreach (Gudang::periksaPenyimpanan($l->barang) as $x) {
                $langgar[] = $x + ['lokasi' => $l->nama];
            }
        }

        return view('gudang.b3', [
            'b3'          => $b3,
            'langgar'     => $langgar,
            'kedaluwarsa' => Gudang::kedaluwarsa($b3),
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
                'barang'     => $b,
                'awal'       => $awal,
                'masuk'      => round($masuk, 2),
                'keluar'     => round($keluar, 2),
                'penyesuaian'=> round($akhir - ($awal + $masuk - $keluar), 2),
                'akhir'      => $akhir,
                'opname'     => $adaOpname,
            ];
        }

        return view('gudang.laporan', [
            'baris'  => $baris,
            'dari'   => $dari,
            'sampai' => $sampai,
            'r'      => Gudang::ringkas($barang),
        ]);
    }
}
