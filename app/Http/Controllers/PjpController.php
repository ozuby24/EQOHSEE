<?php

namespace App\Http\Controllers;

use App\Models\Pjp\{Evaluasi, Laporan, Pjp, SmkpItem, SmkpJawaban, SmkpKategori};
use App\Support\{Berkas, Ekspor, KopDokumen};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pemantauan Perusahaan Jasa Pertambangan.
 *
 * SATU CONTROLLER UNTUK SELURUH MODUL, sama seperti Investigasi.
 * Daftar periksa, dokumen berkala, dan evaluasi semesteran adalah tiga
 * sisi dari satu berkas mitra yang dibuka orang yang sama dalam satu
 * duduk; memecahnya menjadi empat controller berarti empat tempat yang
 * harus diubah bersamaan setiap kali bentuk berkasnya bergeser.
 *
 * EKSPOR TIDAK MEMAKAI PUSTAKA TAMBAHAN. Kit asalnya membawa
 * maatwebsite/excel dan barryvdh/dompdf; keduanya tidak dipasang di
 * sini dan tidak ditambahkan. EQOHSEE sudah punya jawabannya sendiri —
 * CSV lewat App\Support\Ekspor dan halaman siap cetak lewat
 * Pages/Print — dan dua pustaka besar demi dua tombol adalah harga yang
 * dibayar setiap `composer install` berikutnya.
 */
class PjpController extends Controller
{
    /* ═══════════════════ DASBOR ═══════════════════ */

    /**
     * Ringkasan yang menjawab "mitra mana yang menunggu saya".
     *
     * Bukan "berapa banyak mitra". Angka besar tanpa tautan hanya
     * memberi tahu bahwa datanya banyak; yang dicari pembacanya selalu
     * barisnya.
     */
    public function dasbor()
    {
        $jumlah = Pjp::statusCountsFor();

        /* denganSkor() memuat ketiga relasi sekali jalan. Tanpa itu tiap
           baris membaca basis data tiga kali lagi hanya untuk menghitung
           achievement-nya — 36 kueri untuk 7 mitra, terukur pada kit
           asalnya, dan memburuk lurus seiring bertambahnya mitra. */
        $perhatian = Pjp::query()->denganSkor()->get()
            ->map(fn (Pjp $p) => [
                'id'          => $p->id,
                'nama'        => $p->nama_perusahaan,
                'achievement' => $p->achievement(),
            ])
            ->filter(fn (array $b) => $b['achievement'] !== null
                && $b['achievement'] < Pjp::AMBANG_PERHATIAN)
            ->sortBy('achievement')
            ->take(8)
            ->values();

        return Inertia::render('Pjp/Dasbor', [
            'judul'    => 'Pemantauan Perusahaan Jasa Pertambangan',
            'subjudul' => 'Prakualifikasi SMKP, dokumen berkala, dan evaluasi kinerja mitra.',

            'ringkas' => [
                'total'             => array_sum($jumlah),
                'aktif'             => $jumlah['aktif'],
                'perluTindakLanjut' => $jumlah['perlu_tindak_lanjut'],
                'tidakAktif'        => $jumlah['tidak_aktif'],
                'perluPerhatian'    => $perhatian->count(),
            ],

            'statusCounts'   => $jumlah,
            'perluPerhatian' => $perhatian,
            'menunggak'      => Pjp::belumLaporanBulananBulanIni(),
            'batasTanggal'   => Laporan::BATAS_TANGGAL,

            /* Dokumen yang sudah masuk tetapi belum dibuka siapa pun.
               Menuntut tindakan orang yang BERBEDA dari daftar
               menunggak di atasnya: yang itu menuntut mitranya
               mengirim, yang ini menuntut pemegang IUP menilai. Digabung
               menjadi satu angka kepatuhan, tidak satu pun dari keduanya
               terpanggil. */
            'belumDinilai' => Laporan::query()
                ->whereNull('kesesuaian_isi')
                ->with('pjp:id,nama_perusahaan')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (Laporan $l) => [
                    'id'          => $l->pjp_id,
                    'mitra'       => $l->pjp?->nama_perusahaan,
                    'jenis'       => Laporan::JENIS[$l->jenis] ?? $l->jenis,
                    'periode'     => $l->periode,
                    'tepat_waktu' => $l->tepat_waktu,
                ]),
        ]);
    }

    /* ═══════════════════ DAFTAR ═══════════════════ */

    public function index(Request $r)
    {
        $baris = Pjp::query()
            ->denganSkor()
            ->filter($r->query('cari'), $r->query('status'))
            ->orderBy('nama_perusahaan')
            ->get()
            ->map(fn (Pjp $p) => [
                'id'               => $p->id,
                'nama_perusahaan'  => $p->nama_perusahaan,
                'nib'              => $p->nib,
                'penanggung_jawab' => $p->penanggung_jawab,
                'status'           => $p->status,
                'smkp'             => $p->smkpScore()['persentase'],
                'pelaporan'        => $p->pelaporanScore(),
                'evaluasi'         => $p->evaluasi->first()?->skor_rata_rata,
                'achievement'      => $p->achievement(),
            ]);

        return Inertia::render('Pjp/Daftar', [
            'judul'        => 'Daftar Perusahaan Jasa',
            'baris'        => $baris,
            'saring'       => ['cari' => $r->query('cari', ''), 'status' => $r->query('status', '')],
            'statusCounts' => Pjp::statusCountsFor(),
            'STATUS'       => Pjp::STATUS,
            'ambang'       => Pjp::AMBANG_PERHATIAN,
        ]);
    }

    public function csv(Request $r)
    {
        $baris = Pjp::query()
            ->denganSkor()
            ->filter($r->query('cari'), $r->query('status'))
            ->orderBy('nama_perusahaan')
            ->get()
            ->map(fn (Pjp $p) => [
                $p->nama_perusahaan,
                $p->nib,
                $p->penanggung_jawab,
                $p->alamat,
                Pjp::STATUS[$p->status] ?? $p->status,
                $p->smkpScore()['persentase'],
                $p->smkpScore()['kelayakan'],
                $p->pelaporanScore(),
                $p->evaluasi->first()?->skor_rata_rata,
                $p->achievement(),
                $p->catatan,
            ]);

        return Ekspor::csv('pjp-'.date('Ymd-Hi'), [
            'Nama Perusahaan', 'NIB', 'Penanggung Jawab', 'Alamat', 'Status',
            'Skor SMKP (%)', 'Layak s.d. Risiko', 'Skor Pelaporan (%)',
            'Skor Evaluasi Terakhir', 'Achievement', 'Catatan',
        ], $baris);
    }

    public function cetak(Request $r)
    {
        return Inertia::render('Print/Pjp', [
            'dok'     => KopDokumen::untuk('register-pjp', $this->perusahaanKop($r)),
            'data'    => Pjp::query()->denganSkor()
                ->filter($r->query('cari'), $r->query('status'))
                ->orderBy('nama_perusahaan')->get()
                ->map(fn (Pjp $p) => [
                    'nama_perusahaan'  => $p->nama_perusahaan,
                    'nib'              => $p->nib,
                    'penanggung_jawab' => $p->penanggung_jawab,
                    'status'           => Pjp::STATUS[$p->status] ?? $p->status,
                    'smkp'             => $p->smkpScore()['persentase'],
                    'pelaporan'        => $p->pelaporanScore(),
                    'evaluasi'         => $p->evaluasi->first()?->skor_rata_rata,
                    'achievement'      => $p->achievement(),
                ]),
            'filters' => $r->query(),
            'kembali' => route('pjp.index', $r->query()),
        ]);
    }

    /* ═══════════════════ SATU MITRA ═══════════════════ */

    public function baru()
    {
        return Inertia::render('Pjp/Form', [
            'judul'  => 'Tambah Perusahaan Jasa',
            'pjp'    => null,
            'STATUS' => Pjp::STATUS,
        ]);
    }

    public function simpan(Request $r)
    {
        $pjp = Pjp::create($this->pemilik($this->validasi($r)));

        return redirect()->route('pjp.rincian', $pjp)
            ->with('sukses', 'Perusahaan jasa berhasil ditambahkan.');
    }

    public function sunting(Pjp $pjp)
    {
        return Inertia::render('Pjp/Form', [
            'judul'  => 'Ubah Perusahaan Jasa',
            'pjp'    => $pjp,
            'STATUS' => Pjp::STATUS,
        ]);
    }

    public function perbarui(Request $r, Pjp $pjp)
    {
        $pjp->update($this->pemilik($this->validasi($r)));

        return redirect()->route('pjp.rincian', $pjp)
            ->with('sukses', 'Perusahaan jasa berhasil diperbarui.');
    }

    public function hapus(Pjp $pjp)
    {
        /* Berkasnya dibuang satu per satu lewat jalur yang tersimpan,
           bukan dengan menghapus foldernya. Nama folder dibentuk dari
           id, dan menghapus folder berdasarkan id yang salah — atau id
           yang sudah dipakai ulang — membuang berkas milik baris lain
           tanpa satu pun galat. */
        foreach ($pjp->laporan()->get() as $l) Berkas::buang($l->file_path);

        $pjp->delete();

        return redirect()->route('pjp.index')
            ->with('sukses', 'Perusahaan jasa berhasil dihapus.');
    }

    public function rincian(Pjp $pjp)
    {
        $pjp->load(['laporan', 'evaluasi', 'smkpJawaban']);

        return Inertia::render('Pjp/Rincian', [
            'judul' => $pjp->nama_perusahaan,
            'pjp'   => $pjp->only([
                'id', 'nama_perusahaan', 'nib', 'penanggung_jawab',
                'alamat', 'status', 'catatan',
            ]),

            'laporan' => $pjp->laporan->map(fn (Laporan $l) => [
                'id'             => $l->id,
                'jenis'          => $l->jenis,
                'jenis_label'    => Laporan::JENIS[$l->jenis] ?? $l->jenis,
                'periode'        => $l->periode,
                'catatan'        => $l->catatan,
                'kesesuaian_isi' => $l->kesesuaian_isi,
                'tepat_waktu'    => $l->tepat_waktu,
                'file_name'      => $l->file_name,
                'file_size'      => $l->file_size,
                'url'            => Berkas::url($l, 'pjl'),
                'dibuat'         => $l->created_at?->toIso8601String(),
            ]),

            'evaluasi' => $pjp->evaluasi->map(fn (Evaluasi $e) => [
                'id'                         => $e->id,
                'tahun'                      => $e->tahun,
                'semester'                   => $e->semester,
                'semester_label'             => Evaluasi::SEMESTER[$e->semester] ?? $e->semester,
                'skor_teknis'                => $e->skor_teknis,
                'skor_keselamatan_kesehatan' => $e->skor_keselamatan_kesehatan,
                'skor_lingkungan'            => $e->skor_lingkungan,
                'skor_rata_rata'             => $e->skor_rata_rata,
                'catatan'                    => $e->catatan,
            ]),

            'smkp'      => $pjp->smkpScore(),
            'legalitas' => $pjp->smkpLegalitasStatus(),
            'pelaporan' => $pjp->pelaporanScore(),
            'achievement' => $pjp->achievement(),

            'JENIS'       => Laporan::JENIS,
            'KESESUAIAN'  => Laporan::KESESUAIAN,
            'SEMESTER'    => Evaluasi::SEMESTER,
            'STATUS'      => Pjp::STATUS,
            'ambang'      => Pjp::AMBANG_PERHATIAN,

            'triwulanDibuka' => Laporan::triwulanSedangDibuka(),
            'bulanTriwulan'  => implode(', ', Laporan::BULAN_TRIWULAN),
            'batasTanggal'   => Laporan::BATAS_TANGGAL,
        ]);
    }

    /* ═══════════════════ DOKUMEN BERKALA ═══════════════════ */

    public function laporanSimpan(Request $r, Pjp $pjp)
    {
        $data = $r->validate([
            'jenis'   => ['required', Rule::in(array_keys(Laporan::JENIS))],
            'periode' => ['nullable', 'string', 'max:60'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'berkas'  => array_merge(['required'], Berkas::ATURAN_DOKUMEN),
        ]);

        if ($data['jenis'] === 'laporan_triwulan' && ! Laporan::triwulanSedangDibuka()) {
            return back()->withErrors([
                'berkas' => 'Laporan Triwulan hanya dapat diunggah pada bulan '
                    .implode(', ', Laporan::BULAN_TRIWULAN).'.',
            ]);
        }

        $berkas = $r->file('berkas');
        $jalur  = Berkas::simpan($berkas, "pjp/{$pjp->id}");

        if (! $jalur) {
            return back()->withErrors(['berkas' => 'Berkas tidak dapat disimpan.']);
        }

        $pjp->laporan()->create([
            'jenis'     => $data['jenis'],
            'periode'   => $data['periode'] ?? null,
            'catatan'   => $data['catatan'] ?? null,
            'file_path' => $jalur,
            'file_name' => $berkas->getClientOriginalName(),
            'file_size' => $berkas->getSize(),
        ]);

        return back()->with('sukses', 'Dokumen berhasil diunggah.');
    }

    public function laporanNilai(Request $r, Pjp $pjp, Laporan $laporan)
    {
        $this->pastikanMilik($laporan->pjp_id, $pjp->id);

        $laporan->update($r->validate([
            'kesesuaian_isi' => ['nullable', Rule::in(array_keys(Laporan::KESESUAIAN))],
        ]));

        return back()->with('sukses', 'Penilaian dokumen berhasil disimpan.');
    }

    public function laporanHapus(Pjp $pjp, Laporan $laporan)
    {
        $this->pastikanMilik($laporan->pjp_id, $pjp->id);

        Berkas::buang($laporan->file_path);
        $laporan->delete();

        return back()->with('sukses', 'Dokumen berhasil dihapus.');
    }

    /* ═══════════════════ EVALUASI SEMESTERAN ═══════════════════ */

    public function evaluasiSimpan(Request $r, Pjp $pjp)
    {
        $data = $r->validate([
            'tahun'                      => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester'                   => ['required', 'integer', Rule::in(array_keys(Evaluasi::SEMESTER))],
            'skor_teknis'                => ['required', 'integer', 'min:0', 'max:100'],
            'skor_keselamatan_kesehatan' => ['required', 'integer', 'min:0', 'max:100'],
            'skor_lingkungan'            => ['required', 'integer', 'min:0', 'max:100'],
            'catatan'                    => ['nullable', 'string', 'max:2000'],
        ]);

        /* Menimpa, bukan menambah baris. Dua penilaian atas satu semester
           membuat grafik trennya menggambar dua titik pada satu sumbu
           dengan nilai yang berselisih, dan tidak ada cara membaca mana
           yang berlaku. */
        $pjp->evaluasi()->updateOrCreate(
            ['tahun' => $data['tahun'], 'semester' => $data['semester']],
            array_diff_key($data, ['tahun' => null, 'semester' => null])
                + ['catatan' => $data['catatan'] ?? null],
        );

        return back()->with('sukses', 'Evaluasi kinerja berhasil disimpan.');
    }

    public function evaluasiHapus(Pjp $pjp, Evaluasi $evaluasi)
    {
        $this->pastikanMilik($evaluasi->pjp_id, $pjp->id);

        $evaluasi->delete();

        return back()->with('sukses', 'Evaluasi kinerja berhasil dihapus.');
    }

    /* ═══════════════════ DAFTAR PERIKSA SMKP ═══════════════════ */

    public function checklist(Pjp $pjp)
    {
        $jawaban = $pjp->smkpJawaban()->get()->keyBy('item_id');

        $kategori = SmkpKategori::query()->orderBy('urutan')->with('items')->get()
            ->map(fn (SmkpKategori $k) => [
                'id'    => $k->id,
                'kode'  => $k->kode,
                'nama'  => $k->nama,
                'bobot' => $k->bobot,
                'legalitas' => $k->kode === SmkpKategori::LEGALITAS,
                'butir' => $k->items->map(fn (SmkpItem $b) => [
                    'id'         => $b->id,
                    'grup_kode'  => $b->grup_kode,
                    'grup_nama'  => $b->grup_nama,
                    'nomor'      => $b->nomor,
                    'pertanyaan' => $b->pertanyaan,
                    'petunjuk'   => $b->petunjuk,
                    'bobot'      => $b->bobot,
                    'jawaban'    => $jawaban->get($b->id)?->jawaban,
                    'nilai'      => $jawaban->get($b->id)?->nilai,
                    'penjelasan' => $jawaban->get($b->id)?->penjelasan,
                ])->values(),
            ]);

        return Inertia::render('Pjp/Checklist', [
            'judul'     => 'Prakualifikasi SMKP — '.$pjp->nama_perusahaan,
            'pjp'       => $pjp->only(['id', 'nama_perusahaan']),
            'kategori'  => $kategori,
            'skor'      => $pjp->smkpScore(),
            'rincian'   => $pjp->smkpCategoryBreakdown(),
            'legalitas' => $pjp->smkpLegalitasStatus(),
            'JAWABAN'   => SmkpJawaban::JAWABAN,
            'NILAI'     => SmkpJawaban::NILAI,
        ]);
    }

    public function checklistSimpan(Request $r, Pjp $pjp)
    {
        $data = $r->validate([
            'jawaban'              => ['required', 'array', 'max:200'],
            'jawaban.*.item_id'    => ['required', 'integer', 'exists:pjp_smkp_item,id'],
            'jawaban.*.jawaban'    => ['nullable', Rule::in(array_keys(SmkpJawaban::JAWABAN))],
            'jawaban.*.nilai'      => ['nullable', Rule::in(array_keys(SmkpJawaban::NILAI))],
            'jawaban.*.penjelasan' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($data['jawaban'] as $j) {
            SmkpJawaban::updateOrCreate(
                ['pjp_id' => $pjp->id, 'item_id' => $j['item_id']],
                [
                    'jawaban'    => $j['jawaban'] ?? null,
                    'nilai'      => $j['nilai'] ?? null,
                    'penjelasan' => $j['penjelasan'] ?? null,
                ],
            );
        }

        return back()->with('sukses', 'Daftar periksa berhasil disimpan.');
    }

    /* ═══════════════════ pembantu ═══════════════════ */

    private function validasi(Request $r): array
    {
        return $r->validate([
            'nama_perusahaan'  => ['required', 'string', 'max:200'],
            'nib'              => ['nullable', 'string', 'max:60'],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'alamat'           => ['nullable', 'string', 'max:2000'],
            'status'           => ['required', Rule::in(array_keys(Pjp::STATUS))],
            'catatan'          => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * Anak yang diikat rute memang milik induk yang diikat rute.
     *
     * Laravel mengikat `{laporan}` sendiri-sendiri, tanpa memeriksa
     * hubungannya dengan `{pjp}` di depannya. Tanpa pemeriksaan ini,
     * `DELETE /pjp/1/laporan/99` menghapus dokumen milik mitra nomor
     * lain — batas perusahaannya memang tetap dijaga BerindukPerusahaan,
     * jadi kebocorannya berhenti di dalam satu perusahaan, tetapi di
     * dalamnya siapa pun dapat menghapus dokumen mitra yang salah dan
     * yang terlihat hanyalah dokumen yang hilang.
     */
    private function pastikanMilik(?int $milik, int $harus): void
    {
        abort_unless($milik === $harus, 404);
    }
}
