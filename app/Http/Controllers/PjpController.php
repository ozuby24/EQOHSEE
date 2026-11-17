<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Pjp;
use App\Models\PjpLaporan;
use App\Support\Ekspor;
use App\Support\KopDokumen;
use App\Support\PjpEkspor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Register Perusahaan Jasa Pertambangan: daftar, formulir, detail, ekspor.
 *
 * Ketiga halaman aspek ada di PjpAspekController; yang di sini adalah
 * data induknya. Pemisahan itu mengikuti bentuk modulnya — aspek
 * menjawab "bagaimana capaian seluruh PJP", register menjawab "siapa
 * saja PJP-nya dan apa isinya".
 */
class PjpController extends Controller
{
    public function daftar(Request $request)
    {
        $cari   = $request->query('cari');
        $status = $request->query('status');

        $pjps = Pjp::query()
            ->with('company:id,name')
            ->filter($cari, $status)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Pjp/Daftar', [
            'judul'    => 'Data Perusahaan Jasa Pertambangan',
            'subjudul' => 'Seluruh PJP yang terdaftar dan dipantau pemegang izin.',

            'pjps'    => $pjps,
            'saring'  => ['cari' => $cari ?? '', 'status' => $status ?? ''],
            'statusJumlah' => Pjp::statusCountsFor(),
            'statusOpsi'   => Pjp::STATUS,

            'tautan' => $this->tautanUmum() + [
                /* Penyaring yang sedang aktif ikut ke berkas unduhan:
                   tombol Ekspor yang mengabaikannya mengirim seluruh
                   data padahal layar menunjukkan sebagian, dan selisih
                   itu baru ketahuan setelah berkasnya beredar. */
                'ekspor' => route('pjp.ekspor', array_filter([
                    'cari' => $cari, 'status' => $status,
                ])),
                'hapusPola' => route('pjp.hapus', ['pjp' => '__ID__']),
            ],
        ]);
    }

    /**
     * Berkas CSV, bukan XLSX.
     *
     * Aplikasi asal memakai maatwebsite/excel; EQOHSEE tidak memasang
     * pustaka itu dan sudah punya App\Support\Ekspor yang dipakai
     * seluruh modul lain. Menambah satu pustaka untuk satu modul berarti
     * dua cara mengekspor yang harus dirawat bersama, dan yang satu
     * pasti tertinggal.
     */
    public function ekspor(Request $request)
    {
        $pjps = Pjp::query()
            ->filter($request->query('cari'), $request->query('status'))
            ->latest()
            ->get();

        ActivityLog::write('Ekspor data PJP', $pjps->count().' perusahaan', 'pjp');

        return Ekspor::csv(
            PjpEkspor::namaBerkas(),
            PjpEkspor::judul(),
            $pjps->map(fn (Pjp $pjp) => PjpEkspor::baris($pjp)),
        );
    }

    public function baru()
    {
        return $this->formulir(new Pjp(['status' => 'aktif']));
    }

    public function ubah(Pjp $pjp)
    {
        return $this->formulir($pjp);
    }

    public function simpan(Request $request)
    {
        $pjp = Pjp::create($this->pemilik($this->validasi($request)));

        ActivityLog::write('Tambah PJP', $pjp->nama_perusahaan, 'pjp');

        return redirect()->route('pjp.detail', $pjp)
            ->with('ok', 'Data Perusahaan Jasa Pertambangan tersimpan.');
    }

    public function perbarui(Request $request, Pjp $pjp)
    {
        $pjp->update($this->pemilik($this->validasi($request)));

        ActivityLog::write('Ubah PJP', $pjp->nama_perusahaan, 'pjp');

        return redirect()->route('pjp.detail', $pjp)
            ->with('ok', 'Data Perusahaan Jasa Pertambangan diperbarui.');
    }

    public function hapus(Pjp $pjp)
    {
        $nama = $pjp->nama_perusahaan;

        /*
         * Berkasnya ikut dihapus, bukan hanya barisnya. Kunci asing
         * menghapus baris dokumen secara berantai, tetapi berkas di disk
         * tidak ikut mati bersamanya — ia tinggal sebagai dokumen
         * perusahaan yang tidak lagi terdaftar, masih terbuka bagi siapa
         * pun yang menyimpan tautannya.
         */
        Storage::disk('public')->deleteDirectory("pjp-laporan/{$pjp->id}");
        $pjp->delete();

        ActivityLog::write('Hapus PJP', $nama, 'pjp');

        return redirect()->route('pjp.daftar')
            ->with('ok', 'Data Perusahaan Jasa Pertambangan dihapus.');
    }

    public function detail(Pjp $pjp)
    {
        $laporans = $pjp->laporans()->get();

        return Inertia::render('Pjp/Detail', [
            'pjp' => [
                'id'               => $pjp->id,
                'nama_perusahaan'  => $pjp->nama_perusahaan,
                'nib'              => $pjp->nib,
                'penanggung_jawab' => $pjp->penanggung_jawab,
                'alamat'           => $pjp->alamat,
                'status'           => $pjp->status,
                'statusLabel'      => Pjp::STATUS[$pjp->status] ?? $pjp->status,
                'catatan'          => $pjp->catatan,
                'perusahaan'       => $pjp->company?->name,
            ],

            /*
             * Dokumen dikelompokkan per jenis di server, bukan disaring
             * ulang di sisi peramban dari satu daftar rata. Label tiap
             * jenis ikut dibawa di dalam kelompoknya, sehingga tampilan
             * tidak perlu mengulang peta jenis→label dan mengambil
             * kuncinya — kunci itu bentuk dalam, bukan bahasa yang layak
             * tampil di layar.
             */
            'kelompokLaporan' => $this->kelompokLaporan($laporans),
            'evaluasis' => $pjp->evaluasis()->get()->map(fn ($e) => [
                'id'                         => $e->id,
                'tahun'                      => $e->tahun,
                'semester'                   => $e->semester,
                'semesterLabel'              => \App\Models\PjpEvaluasi::SEMESTER[$e->semester] ?? $e->semester,
                'skor_teknis'                => $e->skor_teknis,
                'skor_keselamatan_kesehatan' => $e->skor_keselamatan_kesehatan,
                'skor_lingkungan'            => $e->skor_lingkungan,
                'skor_rata_rata'             => $e->skor_rata_rata,
                'catatan'                    => $e->catatan,
            ])->values()->all(),

            'smkpScore'       => $pjp->smkpScore(),
            'legalitasStatus' => $pjp->smkpLegalitasStatus(),
            'pelaporanScore'  => $pjp->pelaporanScore(),

            /*
             * Jendela triwulan dikirim ke tampilan supaya formulir
             * unggahnya disembunyikan di luar bulan yang dibuka. Server
             * tetap memeriksanya sendiri di PjpLaporanController —
             * penjagaan di sisi peramban hanya menjelaskan, bukan
             * menegakkan.
             */
            'triwulanTerbuka'     => PjpLaporan::triwulanSedangDibuka(),
            'bulanTriwulanDibuka' => PjpLaporan::bulanTriwulanDibuka(),

            'kesesuaianOpsi'  => PjpLaporan::KESESUAIAN,
            'semesterOpsi'    => \App\Models\PjpEvaluasi::SEMESTER,
            'tahunSekarang'   => (int) now()->year,

            'tautan' => $this->tautanUmum() + [
                'checklist'      => route('pjp.checklist', $pjp),
                'ubah'           => route('pjp.ubah', $pjp),
                'hapus'          => route('pjp.hapus', $pjp),
                'cetak'          => route('pjp.cetak', $pjp),
                'laporanSimpan'  => route('pjp.laporan.simpan', $pjp),
                'laporanNilai'   => route('pjp.laporan.nilai', ['pjp' => $pjp->id, 'laporan' => '__ID__']),
                'laporanHapus'   => route('pjp.laporan.hapus', ['pjp' => $pjp->id, 'laporan' => '__ID__']),
                'evaluasiSimpan' => route('pjp.evaluasi.simpan', $pjp),
                'evaluasiHapus'  => route('pjp.evaluasi.hapus', ['pjp' => $pjp->id, 'evaluasi' => '__ID__']),
            ],
        ]);
    }

    /** Lembar siap cetak untuk satu PJP. */
    public function cetak(Pjp $pjp)
    {
        $perusahaan = $pjp->company ?: (auth()->user()?->company ?: Company::first());

        return Inertia::render('Print/Pjp', [
            'dok' => KopDokumen::untuk('laporan-pjp', $perusahaan),

            'pjp' => [
                'nama_perusahaan'  => $pjp->nama_perusahaan,
                'nib'              => $pjp->nib,
                'penanggung_jawab' => $pjp->penanggung_jawab,
                'alamat'           => $pjp->alamat,
                'statusLabel'      => Pjp::STATUS[$pjp->status] ?? $pjp->status,
                'catatan'          => $pjp->catatan,
                'terdaftar'        => $pjp->created_at?->format('d-m-Y'),
            ],

            'smkpScore'        => $pjp->smkpScore(),
            'rincianKategori'  => $pjp->smkpCategoryBreakdown(),
            'legalitasStatus'  => $pjp->smkpLegalitasStatus(),
            'pelaporanScore'   => $pjp->pelaporanScore(),
            'achievement'      => $pjp->achievement(),

            'kelompokLaporan' => $this->kelompokLaporan($pjp->laporans()->get()),

            'evaluasis' => $pjp->evaluasis()->get()->map(fn ($e) => [
                'periode'                    => $e->periodeSingkat(),
                'semesterLabel'              => \App\Models\PjpEvaluasi::SEMESTER[$e->semester] ?? $e->semester,
                'tahun'                      => $e->tahun,
                'skor_teknis'                => $e->skor_teknis,
                'skor_keselamatan_kesehatan' => $e->skor_keselamatan_kesehatan,
                'skor_lingkungan'            => $e->skor_lingkungan,
                'skor_rata_rata'             => $e->skor_rata_rata,
                'catatan'                    => $e->catatan,
            ])->values()->all(),

            'kembali' => route('pjp.detail', $pjp),
        ]);
    }

    /* ---------- bantu ---------- */

    private function formulir(Pjp $pjp)
    {
        return Inertia::render('Pjp/Form', [
            'judul' => $pjp->exists
                ? 'Ubah Perusahaan Jasa Pertambangan'
                : 'Tambah Perusahaan Jasa Pertambangan',

            'pjp' => [
                'id'               => $pjp->id,
                'nama_perusahaan'  => $pjp->nama_perusahaan ?? '',
                'nib'              => $pjp->nib ?? '',
                'penanggung_jawab' => $pjp->penanggung_jawab ?? '',
                'alamat'           => $pjp->alamat ?? '',
                'status'           => $pjp->status ?? 'aktif',
                'catatan'          => $pjp->catatan ?? '',
                'company_id'       => $pjp->company_id,
            ],

            'statusOpsi'  => Pjp::STATUS,
            'perusahaans' => auth()->user()?->isAdmin()
                ? Company::orderBy('name')->get(['id', 'name'])
                : [],

            'tautan' => $this->tautanUmum() + [
                'kirim'   => $pjp->exists ? route('pjp.perbarui', $pjp) : route('pjp.simpan'),
                'metode'  => $pjp->exists ? 'put' : 'post',
                'batal'   => $pjp->exists ? route('pjp.detail', $pjp) : route('pjp.daftar'),
            ],
        ]);
    }

    /**
     * Dokumen per jenis, dalam urutan tetap PjpLaporan::JENIS.
     *
     * Setiap jenis selalu muncul, termasuk yang belum punya dokumen sama
     * sekali: kartu yang hilang saat kosong membaca sebagai "jenis ini
     * tidak diwajibkan", padahal justru yang kosong itu yang perlu
     * ditagih.
     *
     * @param  \Illuminate\Support\Collection<int,PjpLaporan>  $laporans
     */
    private function kelompokLaporan($laporans): array
    {
        return collect(PjpLaporan::JENIS)
            ->map(fn (string $label, string $jenis) => [
                'jenis'  => $jenis,
                'label'  => $label,
                'berkas' => $laporans->where('jenis', $jenis)
                    ->map(fn (PjpLaporan $l) => $this->laporanView($l))->values()->all(),

                /* Alasan penutupan dihitung di sini supaya jendela
                   triwulan hanya ditulis di satu tempat; tampilan
                   cukup menggambarkan apa yang diterimanya. */
                'terkunci' => $jenis === 'laporan_triwulan' && !PjpLaporan::triwulanSedangDibuka()
                    ? 'Laporan Triwulan hanya dapat diunggah pada bulan '
                      .PjpLaporan::bulanTriwulanDibuka().'.'
                    : null,
            ])
            ->values()->all();
    }

    private function laporanView(PjpLaporan $l): array
    {
        return [
            'id'             => $l->id,
            'jenis'          => $l->jenis,
            'jenisLabel'     => PjpLaporan::JENIS[$l->jenis] ?? $l->jenis,
            'periode'        => $l->periode,
            'file_name'      => $l->file_name,
            'file_size'      => $l->file_size,
            'file_url'       => Storage::disk('public')->url($l->file_path),
            'catatan'        => $l->catatan,
            'kesesuaian_isi' => $l->kesesuaian_isi,
            'tepat_waktu'    => $l->tepat_waktu,
            'diunggah'       => $l->created_at?->format('d-m-Y'),
        ];
    }

    /** Tautan yang dipakai hampir setiap halaman modul. */
    private function tautanUmum(): array
    {
        return [
            'beranda'     => route('pjp.index'),
            'persyaratan' => route('pjp.persyaratan'),
            'pelaporan'   => route('pjp.pelaporan'),
            'evaluasi'    => route('pjp.evaluasi'),
            'daftar'      => route('pjp.daftar'),
            'baru'        => route('pjp.baru'),
            'bantuan'     => route('pjp.bantuan'),
            'detail'      => route('pjp.detail', ['pjp' => '__ID__']),
            'checklistUntuk' => route('pjp.checklist', ['pjp' => '__ID__']),
        ];
    }

    /*
     * `pemilik()` TIDAK ditulis ulang di sini: kelas Controller induk
     * sudah memuatnya, dipakai bersama seluruh modul lain. Salinan
     * keempat dari aturan yang sama akan berbeda isinya cepat atau
     * lambat, dan yang berbeda di sini berarti satu modul membiarkan
     * data lahir di perusahaan yang salah.
     */

    private function validasi(Request $request): array
    {
        return $request->validate([
            'company_id'       => ['nullable', 'exists:companies,id'],
            'nama_perusahaan'  => ['required', 'string', 'max:255'],
            'nib'              => ['nullable', 'string', 'max:255'],
            'penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'alamat'           => ['nullable', 'string', 'max:1000'],
            'status'           => ['required', Rule::in(array_keys(Pjp::STATUS))],
            'catatan'          => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
