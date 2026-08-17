<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, SmkpAttendee, SmkpAudit, SmkpFinding};
use App\Support\{Ekspor, KopDokumen, Smkp, SmkpRubrik, SmkpTahap};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Audit SMKP Minerba — 7 elemen sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Alur: buat periode audit → nilai tiap kriteria per elemen → temuan
 * (ketidaksesuaian) diangkat jadi CAR → rekap skor & laporan.
 */
class SmkpController extends Controller
{
    /* ---------- Daftar periode audit ---------- */
    public function index()
    {
        $audits = SmkpAudit::with('company')
            ->withCount(['findings', 'findings as findings_open_count' => fn($b) => $b->where('status','<>','Closed')])
            ->orderByDesc('tahun')->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Smkp/Halaman', [
            'mode' => 'index',
            'audits' => $audits,
            'meta'   => Smkp::meta(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'form',
            'audit'     => new SmkpAudit(['tahun' => now()->year]),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $d['user_id'] = auth()->id();
        $d['hasil'] ??= [];

        $audit = SmkpAudit::create($d);
        ActivityLog::write('Buat audit SMKP', $audit->judul ?: ('Audit '.$audit->tahun), 'smkp');

        return redirect()->route('smkp.show', $audit)->with('ok','Periode audit dibuat.');
    }

    public function edit(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'form',
            'audit'     => $smkp,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SmkpAudit $smkp)
    {
        $smkp->update($this->validasi($request, $smkp));
        return redirect()->route('smkp.show', $smkp)->with('ok','Periode audit diperbarui.');
    }

    public function destroy(SmkpAudit $smkp)
    {
        $nama = $smkp->judul ?: ('Audit '.$smkp->tahun);
        $smkp->delete();
        ActivityLog::write('Hapus audit SMKP', $nama, 'smkp');
        return redirect()->route('smkp.index')->with('ok','Periode audit dihapus.');
    }

    /* ---------- Ringkasan satu audit ---------- */
    public function show(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'show',
            'audit'     => $smkp,
            'rekap'     => $smkp->rekap(),
            'elemen'    => Smkp::elemen(),
            'temuan'    => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get(),
            'kecukupan' => $smkp->rekapKecukupan(),
            'rencana'   => $smkp->rekapRencana(),
            'tahap'     => SmkpTahap::tahap(),
            'alur'      => SmkpTahap::alur(),
            'status'    => $smkp->statusAlur(),
            'tautan'    => [
                'rencanaCetak' => route('smkp.rencana.cetak', $smkp),
            ],
        ]);
    }

    /* ================= pintasan menu ================= */

    /**
     * Penyalur menu samping ke bagian tertentu pada audit yang sedang berjalan.
     *
     * Menu samping tidak tahu audit mana yang sedang dikerjakan, sementara
     * hampir seluruh halaman audit memerlukannya. Rute ini menjembatani
     * keduanya: memilih periode yang masih berjalan, lalu meneruskan.
     */
    public function lanjut(Request $request, string $bagian)
    {
        $rute = [
            'tahap1'        => 'smkp.tahap1',
            'rencana'       => 'smkp.rencana',
            'penilaian'     => 'smkp.penilaian',
            'rapat'         => 'smkp.rapat',
            'temuan'        => 'smkp.temuan',
            'berita'        => 'smkp.berita-acara',
            'rencana-cetak' => 'smkp.rencana.cetak',
            'laporan'       => 'smkp.laporan',

            /* Lima keluaran audit yang menyusul. Dilewatkan router yang
               sama supaya menu samping tidak perlu membawa id audit —
               menu tidak tahu periode mana yang sedang dikerjakan. */
            'kriteria'      => 'smkp.kriteria',
            'rekap-nc'      => 'smkp.rekapNc',
            'respon'        => 'smkp.respon',
            'rencana-tindak'=> 'smkp.rencanaTindak',
            'nc-tindak'     => 'smkp.ncTindak',
        ][$bagian] ?? null;

        abort_if(!$rute, 404, 'Bagian audit tidak dikenal.');

        $audit = $this->auditBerjalan();

        if (!$audit) {
            return redirect()->route('smkp.create')
                ->with('ok', 'Belum ada periode audit. Buat satu dulu untuk mulai bekerja.');
        }

        return redirect()->route($rute, $audit);
    }

    /**
     * Periode yang sedang dikerjakan: yang belum selesai dan paling baru.
     * Bila semuanya sudah selesai, yang terakhir tetap dipakai agar berkas
     * cetaknya masih dapat dibuka lewat menu.
     */
    private function auditBerjalan(): ?SmkpAudit
    {
        return SmkpAudit::where('status', '<>', 'selesai')->orderByDesc('tahun')->orderByDesc('id')->first()
            ?? SmkpAudit::orderByDesc('tahun')->orderByDesc('id')->first();
    }

    /** Acuan kriteria audit — 7 elemen beserta bobot dan rujukan halamannya. */
    public function acuan()
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'acuan',
            'elemen'  => Smkp::elemen(),
            'meta'    => Smkp::meta(),
            'kategori'=> Smkp::kategori(),
            'tingkat' => Smkp::tingkat(),
        ]);
    }

    /* ================= TAHAP I — Permulaan Audit ================= */

    public function tahap1(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'tahap1',
            'audit'     => $smkp,
            'elemen'    => Smkp::elemen(),
            'kelayakan' => SmkpTahap::indikatorKelayakan(),
            'faktor'    => SmkpTahap::faktorPenyesuaian(),
            'kinerja'   => SmkpTahap::butirKinerja(),
            'mandays'   => $smkp->mandays(),
            'rekap'     => $smkp->rekapKecukupan(),
        ]);
    }

    public function simpanTahap1(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'permulaan.tanggal_kontak'      => ['nullable','date'],
            'permulaan.media_kontak'        => ['nullable','string','max:150'],
            'permulaan.wakil_auditi'        => ['nullable','string','max:150'],
            'permulaan.jabatan_wakil'       => ['nullable','string','max:150'],
            'permulaan.surat_nomor'         => ['nullable','string','max:150'],
            'permulaan.surat_tanggal'       => ['nullable','date'],
            'permulaan.jumlah_pekerja'      => ['nullable','integer','min:0','max:1000000'],
            'permulaan.kelas_risiko'        => ['nullable', Rule::in(SmkpTahap::kelasRisiko())],
            'permulaan.mandays_dasar'       => ['nullable','numeric','min:0','max:1000'],
            'permulaan.jumlah_auditor'      => ['nullable','integer','min:1','max:50'],
            'permulaan.penyesuaian'         => ['nullable','numeric','min:0','max:1000'],
            'permulaan.kesimpulan'          => ['nullable','string','max:2000'],
            'permulaan.kelayakan.*'         => ['nullable','string','max:500'],
            'permulaan.faktor.*'            => ['nullable'],

            'kinerja.*'                     => ['nullable','string','max:50'],

            'kecukupan.*.status'            => ['nullable', Rule::in([SmkpTahap::LENGKAP, SmkpTahap::TIDAK_LENGKAP])],
            'kecukupan.*.ket'               => ['nullable','string','max:1000'],
        ]);

        $p = (array) ($d['permulaan'] ?? []);

        // Kotak centang yang tidak dicentang tidak ikut terkirim; disamakan
        // dulu agar "tidak" tersimpan sebagai jawaban, bukan sebagai kosong.
        $faktor = [];
        foreach (array_keys(SmkpTahap::faktorPenyesuaian()) as $k) {
            $faktor[$k] = !empty($p['faktor'][$k]);
        }
        $p['faktor'] = $faktor;

        // Kecukupan hanya disimpan untuk elemen yang benar-benar ada.
        $kecukupan = [];
        foreach (Smkp::elemen() as $e) {
            $baris = $d['kecukupan'][$e['kode']] ?? [];
            if (empty($baris['status'])) continue;
            $kecukupan[$e['kode']] = [
                'status' => $baris['status'],
                'ket'    => mb_substr(trim((string) ($baris['ket'] ?? '')), 0, 1000),
            ];
        }

        $smkp->update([
            'permulaan' => $p,
            'kinerja'   => array_map(fn ($v) => trim((string) $v), (array) ($d['kinerja'] ?? [])),
            'kecukupan' => $kecukupan,
        ]);

        return redirect()->route('smkp.tahap1', $smkp)->with('ok', 'Hasil Tahap I tersimpan.');
    }

    /** Berita Acara Hasil Pelaksanaan Tahapan Awal — siap cetak. */
    public function beritaAcara(SmkpAudit $smkp)
    {
        return Inertia::render('Print/Smkp', [
            'mode'       => 'berita',
            'totalLembar'=> 4,
            'audit'     => $smkp,
            'elemen'    => Smkp::elemen(),
            'kelayakan' => SmkpTahap::indikatorKelayakan(),
            'faktor'    => SmkpTahap::faktorPenyesuaian(),
            'kinerja'   => SmkpTahap::butirKinerja(),
            'mandays'   => $smkp->mandays(),
            'rekap'     => $smkp->rekapKecukupan(),
            'dok'       => $this->kop($smkp, 'berita-acara'),
            'kembali'   => route('smkp.tahap1', $smkp),
        ]);
    }

    /* ================= RENCANA AUDIT ================= */

    public function rencana(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'rencana',
            'audit'    => $smkp,
            'komponen' => SmkpTahap::komponenRencana(),
            'pengesah' => SmkpTahap::pengesah(),
            'kegiatan' => SmkpTahap::kegiatanLapangan(),
            'elemen'   => Smkp::elemen(),
            'rekap'    => $smkp->rekapRencana(),
            'mandays'  => $smkp->mandays(),
        ]);
    }

    public function simpanRencana(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'nomor'            => ['nullable','string','max:100'],
            'tujuan'           => ['nullable','string','max:3000'],
            'kriteria'         => ['nullable','string','max:3000'],
            'ruang_lingkup'    => ['nullable','string','max:3000'],
            'tanggal_mulai'    => ['nullable','date'],
            'tanggal_selesai'  => ['nullable','date','after_or_equal:tanggal_mulai'],
            'sumberdaya'       => ['nullable','string','max:3000'],
            'metode'           => ['nullable','string','max:3000'],
            'sampel'           => ['nullable','string','max:3000'],

            'susunan'          => ['nullable','array','max:60'],
            'susunan.*.tanggal'  => ['nullable','date'],
            'susunan.*.waktu'    => ['nullable','string','max:50'],
            'susunan.*.kegiatan' => ['nullable','string','max:300'],
            'susunan.*.auditi'   => ['nullable','string','max:200'],
            'susunan.*.auditor'  => ['nullable','string','max:200'],

            'tugas'            => ['nullable','array','max:30'],
            'tugas.*.nama'       => ['nullable','string','max:150'],
            'tugas.*.peran'      => ['nullable','string','max:100'],
            'tugas.*.registrasi' => ['nullable','string','max:100'],
            'tugas.*.lingkup'    => ['nullable','string','max:300'],

            'pengesahan'         => ['nullable','array'],
            'pengesahan.*.nama'    => ['nullable','string','max:150'],
            'pengesahan.*.jabatan' => ['nullable','string','max:150'],
            'pengesahan.*.tanggal' => ['nullable','date'],

            'risiko'             => ['nullable','array'],
            'risiko.present'     => ['nullable','array','max:20'],
            'risiko.future'      => ['nullable','array','max:20'],
            'risiko.*.*.kegiatan'=> ['nullable','string','max:200'],
            'risiko.*.*.risiko'  => ['nullable','string','max:200'],
            'risiko.*.*.nilai'   => ['nullable','numeric','min:0','max:100'],
        ]);

        // Baris tabel yang seluruhnya kosong dibuang supaya laporan tidak
        // memuat baris hampa hanya karena formulirnya menyediakan slot.
        $rencana = [
            'nomor'           => $d['nomor'] ?? null,
            'tujuan'          => $d['tujuan'] ?? null,
            'kriteria'        => $d['kriteria'] ?? null,
            'ruang_lingkup'   => $d['ruang_lingkup'] ?? null,
            'tanggal_mulai'   => $d['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $d['tanggal_selesai'] ?? null,
            'susunan'         => $this->baris($d['susunan'] ?? []),
            'tugas'           => $this->baris($d['tugas'] ?? []),
            'sumberdaya'      => $d['sumberdaya'] ?? null,
            'metode'          => $d['metode'] ?? null,
            'sampel'          => $d['sampel'] ?? null,
            'pengesahan'      => array_intersect_key(
                (array) ($d['pengesahan'] ?? []),
                SmkpTahap::pengesah()
            ),
        ];

        $risiko = [
            'present' => $this->baris($d['risiko']['present'] ?? []),
            'future'  => $this->baris($d['risiko']['future'] ?? []),
        ];

        $smkp->update(['rencana' => $rencana, 'risiko' => $risiko]);

        $rekap = $smkp->rekapRencana();
        $pesan = $rekap['lengkap']
            ? 'Rencana Audit lengkap — sembilan komponen terpenuhi.'
            : 'Rencana Audit tersimpan. Belum lengkap: '.implode(', ', $rekap['kurang']).'.';

        return redirect()->route('smkp.rencana', $smkp)->with('ok', $pesan);
    }

    /** Laporan Rencana Audit — sembilan komponen wajib, siap cetak. */
    public function rencanaCetak(SmkpAudit $smkp)
    {
        return Inertia::render('Print/Smkp', [
            'mode'       => 'rencana',
            'totalLembar'=> 3,
            'audit'    => $smkp,
            'komponen' => SmkpTahap::komponenRencana(),
            'pengesah' => SmkpTahap::pengesah(),
            'kegiatan' => SmkpTahap::kegiatanLapangan(),
            'rekap'    => $smkp->rekapRencana(),
            'mandays'  => $smkp->mandays(),
            'dok'      => $this->kop($smkp, 'rencana-audit'),
            'kembali'  => route('smkp.rencana', $smkp),
        ]);
    }

    /* ================= TAHAP II — rapat & daftar hadir ================= */

    public function rapat(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'rapat',
            'audit' => $smkp,
            'rapat' => SmkpTahap::rapat(),
            'hadir' => $smkp->attendees()->orderBy('id')->get()->groupBy('rapat'),
            'siap'  => $smkp->siapTahapDua(),
        ]);
    }

    public function simpanHadir(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'rapat'      => ['required', Rule::in(array_keys(SmkpTahap::rapat()))],
            'nama'       => ['required','string','max:150'],
            'jabatan'    => ['nullable','string','max:150'],
            'perusahaan' => ['nullable','string','max:150'],
        ]);

        $smkp->attendees()->create($d);

        // Rapat pembukaan menandai audit lapangan benar-benar dimulai.
        if ($d['rapat'] === 'pembukaan' && $smkp->tahap < SmkpTahap::LAPANGAN) {
            $smkp->update(['tahap' => SmkpTahap::LAPANGAN]);
        }

        return back()->with('ok', 'Peserta '.SmkpTahap::labelRapat($d['rapat']).' ditambahkan.');
    }

    public function hapusHadir(SmkpAudit $smkp, SmkpAttendee $hadir)
    {
        abort_if($hadir->audit_id !== $smkp->id, 404);
        $hadir->delete();
        return back()->with('ok','Peserta dihapus.');
    }

    /** Daftar hadir siap cetak, satu berkas per rapat. */
    public function daftarHadir(SmkpAudit $smkp, string $rapat)
    {
        abort_if(!array_key_exists($rapat, SmkpTahap::rapat()), 404, 'Rapat tidak dikenal.');

        return Inertia::render('Print/Smkp', [
            'mode'   => 'hadir',
            'totalLembar' => max(1, (int) ceil($smkp->hadir($rapat)->count() / 16)),
            'audit' => $smkp,
            'rapat' => $rapat,
            'judul' => SmkpTahap::labelRapat($rapat),
            'hadir' => $smkp->hadir($rapat),
            'dok'   => $this->kop($smkp, 'daftar-hadir'),
            'kembali' => route('smkp.rapat', $smkp),
        ]);
    }

    /** Naikkan tahap audit; Tahap I harus tuntas sebelum lapangan dibuka. */
    public function ubahTahap(Request $request, SmkpAudit $smkp)
    {
        $tahap = (int) $request->validate([
            'tahap' => ['required','integer', Rule::in(array_keys(SmkpTahap::tahap()))],
        ])['tahap'];

        if ($tahap >= SmkpTahap::LAPANGAN && !$smkp->siapTahapDua()) {
            $kurang = $smkp->rekapRencana()['kurang'];
            $sisa   = $smkp->rekapKecukupan()['belum'];

            return back()->withErrors(['tahap' => trim(
                'Tahap I belum tuntas. '
                .($sisa ? "$sisa elemen belum ditinjau kecukupan dokumentasinya. " : '')
                .($kurang ? 'Rencana Audit kurang: '.implode(', ', $kurang).'.' : '')
            )]);
        }

        $smkp->update(['tahap' => $tahap]);
        ActivityLog::write('Ubah tahap audit SMKP', SmkpTahap::labelTahap($tahap), 'smkp');

        return back()->with('ok', 'Audit berpindah ke '.SmkpTahap::labelTahap($tahap).'.');
    }

    /** Kop dokumen terkendali untuk berkas cetak audit ini. */
    private function kop(SmkpAudit $smkp, string $jenis): array
    {
        return KopDokumen::untuk($jenis, $smkp->company);
    }

    /** Buang baris tabel yang seluruh kolomnya kosong. */
    private function baris($rows): array
    {
        return array_values(array_filter((array) $rows, function ($r) {
            foreach ((array) $r as $v) {
                if (trim((string) $v) !== '') return true;
            }
            return false;
        }));
    }

    /* ---------- Formulir penilaian per elemen ---------- */
    public function nilai(SmkpAudit $smkp, string $elemen)
    {
        $ref = collect(Smkp::elemen())->firstWhere('kode', $elemen);
        abort_if(!$ref, 404, 'Elemen tidak dikenal.');

        return Inertia::render('Smkp/Halaman', [
            'mode' => 'nilai',
            'audit'     => $smkp,
            'elemen'    => $ref,
            'rekap'     => Smkp::rekapElemen($ref, $smkp->hasil ?? []),
            'semua'     => Smkp::elemen(),
        ]);
    }

    /** Simpan penilaian satu elemen; kriteria di elemen lain tidak tersentuh. */
    public function simpanNilai(Request $request, SmkpAudit $smkp, string $elemen)
    {
        $ref = collect(Smkp::elemen())->firstWhere('kode', $elemen);
        abort_if(!$ref, 404, 'Elemen tidak dikenal.');

        $this->terapkanNilai($smkp, [$ref], (array) $request->input('k', []));

        return redirect()->route('smkp.nilai', [$smkp, $elemen])
            ->with('ok', 'Penilaian elemen '.$ref['kode'].' tersimpan.');
    }

    /**
     * Terapkan masukan formulir penilaian ke hasil audit.
     *
     * Hanya butir yang benar-benar termasuk $elemen yang disentuh. Batas itu
     * penting: formulir satu lembar dan formulir per elemen memakai nama ruas
     * yang sama, sehingga tanpa penyaringan ini kiriman dari satu elemen dapat
     * menghapus nilai elemen lain hanya karena kodenya ikut terkirim.
     *
     * @param  array<int,array>  $elemen  acuan elemen yang boleh diubah
     * @param  array             $masuk   ruas `k` dari formulir
     * @return int  jumlah butir yang berubah isinya
     */
    private function terapkanNilai(SmkpAudit $smkp, array $elemen, array $masuk): int
    {
        $hasil  = $smkp->hasil ?? [];
        $ubah   = 0;

        foreach ($elemen as $ref) {
            foreach ($ref['sub'] as $sub) {
                foreach (Smkp::butirSub($sub) as $b) {
                    $kode  = $b['kode'];
                    $baris = $masuk[$kode] ?? null;
                    if (!is_array($baris)) continue;

                    $sebelum = $hasil[$kode] ?? null;
                    $v       = $baris['v'] ?? null;

                    if ($v === '' || $v === null) {
                        unset($hasil[$kode]);             // kembali ke "belum dinilai"
                        if ($sebelum !== null) $ubah++;
                        continue;
                    }

                    if (is_string($v) && strcasecmp($v, Smkp::NA) === 0) {
                        $nilai = Smkp::NA;                // di luar lingkup perusahaan
                    } elseif (is_numeric($v)) {
                        // Nilai dijepit ke rentang butir; formulir yang dikirim
                        // langsung tidak boleh menaikkan capaian melebihi maksimum.
                        $nilai = max(0, min((int) $v, (int) $b['maks']));
                    } else {
                        continue;                         // abaikan masukan asing
                    }

                    $hasil[$kode] = [
                        'v'     => $nilai,
                        'ket'   => mb_substr(trim((string) ($baris['ket']   ?? '')), 0, 2000),
                        'bukti' => mb_substr(trim((string) ($baris['bukti'] ?? '')), 0, 500),
                    ];

                    if ($hasil[$kode] !== $sebelum) $ubah++;
                }
            }
        }

        $smkp->update(['hasil' => $hasil]);

        if ($smkp->status === 'draft') $smkp->update(['status' => 'berjalan']);

        return $ubah;
    }

    /* ---------- Form Penilaian Audit — seluruh kriteria dalam satu lembar ---------- */

    /**
     * Formulir penilaian utuh: 100 butir tujuh elemen sekaligus.
     *
     * Sebelumnya penilaian hanya dapat dibuka satu elemen per halaman, lewat
     * kartu di ringkasan audit. Untuk MENGISI itu memadai; untuk MEMERIKSA
     * tidak, sebab pertanyaan yang sebenarnya diajukan — butir mana yang
     * belum sesuai — menuntut tujuh halaman dibuka satu per satu lalu
     * dibandingkan sendiri di kepala.
     *
     * Lembar ini menjawabnya langsung: tiap butir membawa keadaan
     * kesesuaiannya, dan penyaringnya bekerja di sisi peramban sehingga
     * "tampilkan yang belum sesuai" tidak memuat ulang halaman.
     */
    public function penilaian(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Penilaian', [
            'audit'     => $smkp->load('company'),
            'elemen'    => $this->butirPenilaian($smkp),
            'rekap'     => $smkp->rekap(),
            'ringkas'   => $this->ringkasKesesuaian($smkp),
            'keadaan'   => $this->keadaanPenilaian(),
            'rubrik'    => $this->rubrikPenilaian(),
            'prasyarat' => $this->prasyaratPenilaian($smkp),
            'tautan'    => [
                'audit'    => route('smkp.show', $smkp),
                'kriteria' => route('smkp.kriteria', $smkp),
                'ekspor'   => route('smkp.kriteria.ekspor', $smkp),
                'temuan'   => route('smkp.temuan', $smkp),
            ],
        ]);
    }

    /** Simpan formulir penilaian utuh; satu kiriman untuk seluruh elemen. */
    public function simpanPenilaian(Request $request, SmkpAudit $smkp)
    {
        $n = $this->terapkanNilai($smkp, Smkp::elemen(), (array) $request->input('k', []));

        return redirect()->route('smkp.penilaian', $smkp)
            ->with('ok', $n ? "$n butir kriteria diperbarui." : 'Tidak ada perubahan nilai.');
    }

    /**
     * Tujuh elemen beserta butirnya, masing-masing membawa keadaan kesesuaian.
     *
     * Keadaan butir diturunkan dari nilainya memakai ambang yang sama dengan
     * kategori temuan resmi — bukan ambang tersendiri — supaya tanda di layar
     * dan kategori di Formulir Kriteria tidak pernah berbeda.
     *
     * Satuan temuan yang sah tetap SUB-ELEMEN, sebagaimana Formulir
     * Rekapitulasi Ketidaksesuaian. Keadaan per butir di sini alat periksa,
     * bukan temuan: ia menunjukkan butir mana yang menarik capaian
     * sub-elemennya turun.
     */
    private function butirPenilaian(SmkpAudit $smkp): array
    {
        $hasil = (array) ($smkp->hasil ?? []);
        $out   = [];

        foreach (Smkp::elemen() as $e) {
            $rekapE = Smkp::rekapElemen($e, $hasil);
            $sub    = [];

            foreach ($e['sub'] as $s) {
                $rekapS = Smkp::rekapSub($s, $hasil);
                $butir  = [];

                foreach (Smkp::butirSub($s) as $b) {
                    $kode  = $b['kode'];
                    $maks  = (int) ($b['maks'] ?? 0);
                    $v     = Smkp::nilaiButir($hasil, $kode);
                    $sifat = $this->sifatButir($v, $maks);

                    $butir[] = [
                        'kode'    => $kode,
                        'nama'    => $b['nama'] ?? '',
                        'maks'    => $maks,
                        // Nilai dikirim sebagai teks: ruas isian menerima angka
                        // maupun 'N/A', dan 0 yang sah tidak boleh berubah
                        // menjadi kosong dalam perjalanan ke peramban.
                        'v'       => $v === null ? '' : (string) $v,
                        'ket'     => (string) ($hasil[$kode]['ket']   ?? ''),
                        'bukti'   => (string) ($hasil[$kode]['bukti'] ?? ''),
                        'keadaan' => $sifat['kode'],
                        'capaian' => $sifat['capaian'],
                    ];
                }

                $sub[] = [
                    'kode'     => $s['kode'],
                    'nama'     => $s['nama'] ?? '',
                    'ref'      => $s['ref'] ?? null,
                    // Sub-elemen tanpa rincian DINILAI LANGSUNG — butirnya
                    // adalah dirinya sendiri. Tanpa penanda ini tampilan
                    // menggambar kode dan namanya dua kali berturut-turut,
                    // sekali sebagai judul dan sekali sebagai barisnya.
                    'rinci'    => !empty($s['subsub']),
                    'maks'     => $rekapS['maks'],
                    'nilai'    => $rekapS['nilai'],
                    'berlaku'  => $rekapS['berlaku'],
                    'dinilai'  => $rekapS['dinilai'],
                    'capaian'  => round($rekapS['capaian'] * 100, 1),
                    // Kategori sub-elemen hanya bermakna bila sudah ada yang
                    // dinilai; sebelum itu ia akan selalu berbunyi "Mayor"
                    // semata-mata karena pembilangnya masih nol.
                    'kategori' => $rekapS['dinilai'] > 0 ? $rekapS['kategori'] : null,
                    'butir'    => $butir,
                ];
            }

            $out[] = [
                'kode'    => $e['kode'],
                'nama'    => $e['nama'],
                'bobot'   => (int) ($e['bobot'] ?? 0),
                'maks'    => $rekapE['maks'],
                'nilai'   => $rekapE['nilai'],
                'dinilai' => $rekapE['dinilai'],
                'berlaku' => $rekapE['berlaku'],
                'capaian' => round($rekapE['capaian'] * 100, 1),
                'sub'     => $sub,
            ];
        }

        return $out;
    }

    /**
     * Keadaan satu butir: belum dinilai, tidak berlaku, atau kategori nilainya.
     *
     * "Belum dinilai" sengaja BUKAN kategori. Bila ia ikut dihitung sebagai
     * capaian nol, seluruh audit yang baru dibuka akan tampak merah seolah
     * gagal — padahal belum ada yang diperiksa. Yang merah harus benar-benar
     * berarti diperiksa dan tidak memenuhi.
     */
    private function sifatButir($nilai, int $maks): array
    {
        if ($nilai === null)      return ['kode' => 'belum', 'capaian' => null];
        if ($nilai === Smkp::NA)  return ['kode' => 'na',    'capaian' => null];

        $capaian = $maks > 0 ? (float) $nilai / $maks : 0.0;

        return [
            'kode'    => Smkp::kategoriDari($capaian)['kode'],
            'capaian' => round($capaian * 100, 1),
        ];
    }

    /**
     * Nama, warna, dan AMBANG tiap keadaan — dipakai penyaring dan legenda.
     *
     * Ambangnya ikut dikirim, bukan ditulis ulang di sisi Vue. Tanda kesesuaian
     * berubah seketika saat nilai diketik, jadi peramban memang harus dapat
     * menghitungnya sendiri — tetapi angkanya tetap satu, bersumber dari berkas
     * acuan yang sama dengan kategori temuan. Menyalinnya ke Vue berarti dua
     * salinan ambang yang dapat berbeda diam-diam.
     *
     * Urutannya menurun sesuai berkas acuan, sehingga pencocokan pertama yang
     * memenuhi adalah kategori yang benar.
     */
    private function keadaanPenilaian(): array
    {
        $out = [['kode' => 'belum', 'label' => 'Belum dinilai', 'warna' => '#94A3B8', 'min' => null]];

        foreach (Smkp::kategori() as $k) {
            $out[] = [
                'kode'  => $k['kode'],
                'label' => $k['label'],
                'warna' => $k['warna'],
                'min'   => (float) $k['min'],
            ];
        }

        $out[] = ['kode' => 'na', 'label' => 'Tidak berlaku', 'warna' => Smkp::kategoriNa()['warna'], 'min' => null];

        return $out;
    }

    /**
     * Keterangan rubrik yang ikut halaman: nama tingkat dan sumbernya.
     *
     * BUNYI rubriknya sengaja TIDAK ikut. Seluruhnya 260 ribu aksara untuk
     * seratus butir — dikirim bersama halaman, ia menggandakan berat muatan
     * hanya demi teks yang pada satu kali pembukaan paling banyak dibaca
     * beberapa butir. Bunyinya diambil terpisah lewat SmkpController::rubrik()
     * saat auditor benar-benar membukanya.
     *
     * Nama tingkat tetap ikut: ia tercetak pada tiap tombol nilai, jadi
     * harus ada sebelum satu pun rubrik dibuka. Lima kata, bukan beban.
     */
    private function rubrikPenilaian(): array
    {
        return [
            'skala'   => SmkpRubrik::skala(),
            'sumber'  => SmkpRubrik::sumber(),
            'lengkap' => SmkpRubrik::jumlahLengkap(),
            'total'   => Smkp::jumlahButir(),
            'alamat'  => route('smkp.rubrik'),
        ];
    }

    /**
     * Bunyi rubrik beberapa butir sekaligus.
     *
     * Diambil terpisah dari halaman penilaian, dan dibatasi daftar kode yang
     * diminta — bukan seluruhnya — supaya membuka satu butir tidak menarik
     * 260 ribu aksara.
     *
     * Rubrik adalah teks peraturan, sama bagi semua perusahaan, sehingga
     * tidak ada data perusahaan yang dapat bocor lewat sini. Yang tetap
     * dijaga: hanya kode butir yang benar-benar ada pada acuan yang dilayani,
     * dan jumlah permintaan dibatasi.
     */
    public function rubrik(Request $request)
    {
        $maks  = collect(Smkp::butir())->keyBy('kode');
        $minta = array_slice(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('butir', ''))
        )), 0, 120);

        $out = [];

        foreach ($minta as $kode) {
            if (!$maks->has($kode)) continue;

            $out[$kode] = SmkpRubrik::tangga($kode, (int) $maks[$kode]['maks']);
        }

        return response()->json(['tangga' => $out]);
    }

    /** Berapa butir pada tiap keadaan — angka yang dibaca lebih dulu. */
    private function ringkasKesesuaian(SmkpAudit $smkp): array
    {
        $hasil = (array) ($smkp->hasil ?? []);
        $n     = ['belum' => 0, 'mayor' => 0, 'minor' => 0, 'kesesuaian' => 0, 'na' => 0, 'total' => 0];

        foreach (Smkp::butir() as $b) {
            $sifat = $this->sifatButir(Smkp::nilaiButir($hasil, $b['kode']), (int) ($b['maks'] ?? 0));
            $n[$sifat['kode']]++;
            $n['total']++;
        }

        return $n;
    }

    /**
     * Dua berkas yang mendahului penilaian lapangan.
     *
     * Ditampilkan, bukan dipakai mengunci. Menutup formulir sampai keduanya
     * selesai justru menghalangi pekerjaan yang sah — auditor lazim membaca
     * kriteria lebih dulu untuk menyiapkan sampel — sementara menyembunyikan
     * urutannya sama sekali membuat orang menilai sebelum lingkupnya
     * disepakati. Yang dibutuhkan urutannya terlihat, bukan dipaksakan.
     */
    private function prasyaratPenilaian(SmkpAudit $smkp): array
    {
        $status = $smkp->statusAlur();

        return [
            [
                'kunci'   => 'berita',
                'judul'   => 'Berita Acara Tahap I',
                'ket'     => $status['berita']['ket'] ?? '',
                'selesai' => (bool) ($status['berita']['selesai'] ?? false),
                'tautan'  => route('smkp.tahap1', $smkp),
            ],
            [
                'kunci'   => 'rencana-cetak',
                'judul'   => 'Rencana Audit',
                'ket'     => $status['rencana-cetak']['ket'] ?? '',
                'selesai' => (bool) ($status['rencana-cetak']['selesai'] ?? false),
                'tautan'  => route('smkp.rencana', $smkp),
            ],
        ];
    }

    /* ---------- Temuan / CAR ---------- */
    public function temuan(SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Halaman', [
            'mode' => 'temuan',
            'audit'    => $smkp,
            'temuan'   => $smkp->findings()->latest('id')->get(),
            'usulan'   => $this->usulanTemuan($smkp),
        ]);
    }

    /** Ketidaksesuaian dari formulir penilaian yang belum diangkat jadi CAR. */
    private function usulanTemuan(SmkpAudit $smkp): array
    {
        $sudah = $smkp->findings()->pluck('kode_kriteria')->all();
        return array_values(array_filter(
            Smkp::temuan($smkp->hasil ?? []),
            fn($t) => !in_array($t['kode'], $sudah, true)
        ));
    }

    /** Angkat seluruh ketidaksesuaian yang belum jadi CAR sekaligus. */
    public function angkatTemuan(SmkpAudit $smkp)
    {
        $n = 0;
        foreach ($this->usulanTemuan($smkp) as $t) {
            $smkp->findings()->create([
                'kode_kriteria' => $t['kode'],
                'jenis'         => $t['jenis'],
                'uraian'        => $t['uraian'],
                'akar_masalah'  => $t['ket'] ?: null,
                'status'        => 'Open',
            ]);
            $n++;
        }

        return back()->with('ok', $n ? "$n temuan diangkat menjadi tindakan perbaikan." : 'Tidak ada temuan baru.');
    }

    public function simpanTemuan(Request $request, SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);

        $d = $request->validate([
            'akar_masalah'     => ['nullable','string','max:2000'],
            'tindakan'         => ['nullable','string','max:2000'],
            'penanggung_jawab' => ['nullable','string','max:150'],
            'target_selesai'   => ['nullable','date'],
            'status'           => ['required','in:Open,In Progress,Closed'],
            'verifikasi'       => ['nullable','string','max:2000'],
        ]);

        if ($d['status'] === 'Closed' && $temuan->status !== 'Closed') {
            $d['tanggal_selesai'] = now();
        } elseif ($d['status'] !== 'Closed') {
            $d['tanggal_selesai'] = null;
        }

        $temuan->update($d);
        return back()->with('ok','Tindakan perbaikan tersimpan.');
    }

    public function hapusTemuan(SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $temuan->delete();
        return back()->with('ok','Temuan dihapus.');
    }

    /* ---------- Laporan siap cetak ---------- */
    public function laporan(SmkpAudit $smkp)
    {
        return Inertia::render('Print/Smkp', [
            'mode'   => 'laporan',
            'totalLembar' => 1 + max(1, (int) ceil($smkp->findings()->count() / 6)),
            'audit'  => $smkp,
            'rekap'  => $smkp->rekap(),
            'elemen' => Smkp::elemen(),
            'temuan' => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get(),
            'meta'   => Smkp::meta(),
            'dok'    => $this->kop($smkp, 'laporan-audit'),
            'kembali'=> route('smkp.show', $smkp),
        ]);
    }

    /* ═══════════ keluaran 1 — Formulir Kriteria Audit ═══════════ */

    /**
     * Seluruh butir kriteria beserta nilainya, satu baris satu butir.
     *
     * Inilah lembar kerja auditor: ia dibawa ke lapangan, diisi tangan
     * bila perlu, dan menjadi lampiran laporan. Berbeda dari Laporan
     * Audit yang meringkas per elemen, formulir ini menampilkan
     * SELURUH butir — termasuk yang sudah sesuai dan yang dikecualikan.
     *
     * Yang dikecualikan tetap dicetak, dan itu disengaja: butir yang
     * hilang dari lembar tidak dapat dibedakan antara "tidak berlaku"
     * dan "terlewat dinilai", dan pembedaan itu justru yang ditanyakan
     * inspektur.
     */
    public function kriteria(SmkpAudit $smkp)
    {
        return Inertia::render('Print/SmkpKriteria', [
            'audit'  => $smkp,
            'baris'  => $this->barisKriteria($smkp),
            'rekap'  => $smkp->rekap(),
            'meta'   => Smkp::meta(),
            'dok'    => $this->kop($smkp, 'formulir-kriteria'),
            'ekspor' => route('smkp.kriteria.ekspor', $smkp),
            'kembali'=> route('smkp.show', $smkp),
        ]);
    }

    /** Formulir kriteria sebagai CSV — dibuka Excel tanpa pustaka luar. */
    public function kriteriaEkspor(SmkpAudit $smkp)
    {
        $baris = array_map(fn ($b) => [
            $b['elemen'], $b['sub'], $b['kode'], $b['uraian'],
            $b['acuan'], $b['maks'], $b['nilai'], $b['capaian'], $b['keterangan'],
        ], $this->barisKriteria($smkp));

        return Ekspor::csv(
            'formulir-kriteria-smkp-'.$smkp->tahun,
            ['Elemen', 'Sub-elemen', 'Kode', 'Uraian kriteria',
             'Acuan', 'Nilai maksimum', 'Nilai', 'Capaian %', 'Keterangan'],
            $baris,
        );
    }

    /**
     * Satu baris per butir kriteria — dipakai layar cetak dan CSV.
     *
     * Dibentuk sekali di sini supaya keduanya tidak pernah berbeda.
     * Lembar cetak dan berkas Excel yang disusun terpisah adalah dua
     * daftar yang cepat atau lambat berselisih, dan yang membandingkan
     * keduanya adalah auditor eksternal.
     *
     * @return list<array<string,mixed>>
     */
    private function barisKriteria(SmkpAudit $smkp): array
    {
        $hasil = (array) ($smkp->hasil ?? []);
        $out   = [];

        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                foreach (Smkp::butirSub($s) as $b) {
                    $kode  = $b['kode'];
                    $nilai = Smkp::nilaiButir($hasil, $kode);
                    $maks  = (int) ($b['maks'] ?? 0);

                    $out[] = [
                        'elemen'  => $e['kode'].'. '.$e['nama'],
                        'sub'     => $s['kode'].' '.$s['nama'],
                        'kode'    => $kode,
                        'uraian'  => $b['nama'] ?? $b['uraian'] ?? '',
                        'acuan'   => $b['ref'] ?? $s['ref'] ?? '',
                        'maks'    => $maks,

                        /* Yang dikecualikan ditulis "N/A", bukan nol.
                           Nol berarti dinilai dan gagal; N/A berarti
                           tidak berlaku — dua hal yang berlawanan, dan
                           menyamakannya menurunkan skor perusahaan atas
                           butir yang memang tidak dapat berlaku
                           baginya. */
                        'nilai'   => Smkp::dikecualikan($hasil, $kode)
                            ? Smkp::NA : ($nilai === null ? '' : $nilai),

                        'capaian' => Smkp::dikecualikan($hasil, $kode) || $nilai === null || $maks === 0
                            ? '' : round($nilai / $maks * 100, 1),

                        'keterangan' => (string) ($hasil[$kode]['ket'] ?? ''),
                    ];
                }
            }
        }

        return $out;
    }

    /* ═══════════ keluaran 2 — Rekapitulasi Ketidaksesuaian ═══════════ */

    /**
     * Ringkasan ketidaksesuaian: berapa, jenis apa, tersebar di elemen mana.
     *
     * Dipakai rapat penutupan. Yang ditanyakan di sana bukan bunyi tiap
     * temuan melainkan sebarannya — elemen mana yang paling banyak
     * bermasalah, dan berapa yang mayor.
     */
    public function rekapNc(SmkpAudit $smkp)
    {
        /* Diurutkan mengikuti URUTAN KRITERIA, bukan urutan berat.
           Nomor NC diturunkan dari urutan ini dan disebut dalam rapat
           penutupan serta surat-menyurat sesudahnya; urutan berat membuat
           nomornya berpindah setiap kali satu temuan ditutup, sehingga
           "temuan nomor 3" pada risalah menunjuk temuan lain minggu depan. */
        $urutan = Smkp::urutanKriteria();

        $temuan = $smkp->findings()->get()
            ->sortBy(fn (SmkpFinding $t) => $urutan[$t->kode_kriteria] ?? PHP_INT_MAX)
            ->values();

        /* Sebaran per elemen dihitung dari kode kriterianya, bukan dari
           kolom tersendiri: kode "3.2.1" sudah menyebut elemennya, dan
           menyimpannya dua kali melahirkan dua sumber kebenaran yang
           berselisih begitu satu temuan dipindah kriterianya. */
        $perElemen = [];

        foreach (Smkp::elemen() as $e) {
            $milik = $temuan->filter(
                fn (SmkpFinding $t) => str_starts_with((string) $t->kode_kriteria, $e['kode'].'.'));

            $perElemen[] = [
                'kode'   => $e['kode'],
                'nama'   => $e['nama'],
                'mayor'  => $milik->where('jenis', 'mayor')->count(),
                'minor'  => $milik->where('jenis', 'minor')->count(),
                'obs'    => $milik->whereNotIn('jenis', ['mayor', 'minor'])->count(),
                'total'  => $milik->count(),
                'terbuka' => $milik->where('status', '!=', SmkpFinding::TUTUP)->count(),
            ];
        }

        return Inertia::render('Print/SmkpRekapNc', [
            'audit'  => $smkp,

            /* Nomor ikut dikirim, tidak dihitung dari posisi baris di layar.
               Pada formulir audit nomor itu DATA: ia disebut dalam rapat
               penutupan ("temuan nomor 3") dan dalam surat-menyurat
               sesudahnya, jadi ia harus sama pada lembar cetak, layar, dan
               berkas ekspor.

               Dua nomor sekaligus: NC-xx sebagai urutan berjalan, dan
               {kode dokumen perusahaan}-MAY/MIN-xx sebagai urutan per
               jenis. Yang kedua yang dipakai antar-perusahaan, tempat
               "NC-01" saja tidak cukup menunjuk temuan siapa. */
            'temuan' => collect(Smkp::beriNomor(
                $temuan->map(fn (SmkpFinding $t) => $t->toArray())->all(),
                (string) ($smkp->company?->doc_no_prefix ?: 'NC'),
            ))->map(fn (array $t, int $i) => $t + ['urut' => $i + 1])->values(),
            'perElemen' => $perElemen,
            'ringkas' => [
                'total'   => $temuan->count(),
                'mayor'   => $temuan->where('jenis', 'mayor')->count(),
                'minor'   => $temuan->where('jenis', 'minor')->count(),
                'obs'     => $temuan->whereNotIn('jenis', ['mayor', 'minor'])->count(),
                'tertutup' => $temuan->where('status', SmkpFinding::TUTUP)->count(),
                'terbuka'  => $temuan->where('status', '!=', SmkpFinding::TUTUP)->count(),
            ],
            'meta'    => Smkp::meta(),
            'dok'     => $this->kop($smkp, 'rekap-ketidaksesuaian'),
            'kembali' => route('smkp.show', $smkp),
        ]);
    }

    /* ═══════════ keluaran 3 — Respon Manajemen ═══════════ */

    /**
     * Pernyataan pihak yang diaudit atas tiap ketidaksesuaian.
     *
     * TERPISAH DARI TINDAKAN, dan itu bukan pemisahan administratif:
     * tindakan adalah apa yang akan dikerjakan, respon adalah apakah
     * temuannya diterima. Menyatukan keduanya menghapus kemungkinan
     * manajemen MENOLAK sebuah temuan — dan penolakan itu justru yang
     * paling perlu tercatat, sebab ia yang dibawa ke tingkat berikutnya.
     */
    public function responManajemen(SmkpAudit $smkp)
    {
        return Inertia::render('Print/SmkpRespon', [
            'audit'  => $smkp,
            'temuan' => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get()
                ->values()->map(fn (SmkpFinding $t, int $i) => $t->toArray() + ['urut' => $i + 1])
                ->values(),
            'meta'   => Smkp::meta(),
            'dok'    => $this->kop($smkp, 'respon-manajemen'),
            'kembali'=> route('smkp.show', $smkp),
        ]);
    }

    /* ═══════════ keluaran 4 — Rencana Tindak Lanjut ═══════════ */

    /**
     * Rencana tindak lanjut, diurutkan menurut TENGGATNYA.
     *
     * Berbeda dari daftar temuan yang diurut menurut beratnya. Yang
     * dipakai memantau bukan mana yang paling berat melainkan mana yang
     * paling dekat jatuh tempo — sebuah observasi yang tenggatnya lusa
     * lebih mendesak daripada mayor yang tenggatnya tiga bulan lagi.
     */
    public function rencanaTindak(SmkpAudit $smkp)
    {
        $temuan = $smkp->findings()
            ->orderByRaw('CASE WHEN target_selesai IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_selesai')
            ->orderByRaw(Smkp::urutJenisSql())
            ->get();

        $kini = now()->startOfDay();

        return Inertia::render('Print/SmkpRtl', [
            'audit'  => $smkp,
            'temuan' => $temuan->values()->map(fn (SmkpFinding $t, int $i) => $t->toArray() + [
                'urut' => $i + 1,
                /* Sisa hari dihitung di server supaya lembar cetak dan
                   layar tidak pernah berbeda karena zona waktu
                   perambannya. */
                'sisaHari' => $t->target_selesai
                    ? (int) $kini->diffInDays($t->target_selesai->copy()->startOfDay(), false)
                    : null,
                'lewat' => $t->target_selesai
                    && $t->status !== SmkpFinding::TUTUP
                    && $t->target_selesai->copy()->startOfDay()->lt($kini),
            ])->values(),
            'ringkas' => [
                'total'    => $temuan->count(),
                'tertutup' => $temuan->where('status', SmkpFinding::TUTUP)->count(),
                'lewat'    => $temuan->filter(fn ($t) => $t->target_selesai
                    && $t->status !== SmkpFinding::TUTUP
                    && $t->target_selesai->copy()->startOfDay()->lt($kini))->count(),
                'tanpaTarget' => $temuan->whereNull('target_selesai')->count(),
            ],
            'meta'   => Smkp::meta(),
            'dok'    => $this->kop($smkp, 'rencana-tindak-lanjut'),
            'kembali'=> route('smkp.show', $smkp),
        ]);
    }

    /* ═══════════ keluaran 8 — Ketidaksesuaian & Tindak Lanjut ═══════════ */

    /**
     * Satu lembar per temuan, dengan foto sebelum dan sesudah.
     *
     * Inilah berkas yang ditunjukkan saat penutupan temuan diperiksa.
     * Kedua foto berdampingan pada lembar yang sama — memisahkannya ke
     * dua lembar membuat pembacanya harus mengingat yang pertama sambil
     * melihat yang kedua, dan yang diingat orang setelah membalik
     * halaman bukanlah keadaan sebuah lereng.
     */
    public function ncTindak(SmkpAudit $smkp)
    {
        return Inertia::render('Print/SmkpNcTindak', [
            'audit'  => $smkp,
            'temuan' => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get(),
            'meta'   => Smkp::meta(),
            'dok'    => $this->kop($smkp, 'ketidaksesuaian-tindak-lanjut'),
            'kembali'=> route('smkp.show', $smkp),
        ]);
    }

    /** Menyimpan respon manajemen dan bukti penutupan satu temuan. */
    public function simpanRespon(Request $request, SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_unless($temuan->audit_id === $smkp->id, 404);

        $temuan->update($request->validate([
            'respon_diterima'  => ['nullable', 'boolean'],
            'respon_manajemen' => ['nullable', 'string', 'max:2000'],
            'respon_oleh'      => ['nullable', 'string', 'max:150'],
            'respon_pada'      => ['nullable', 'date'],
            'foto_open'        => ['nullable', 'string', 'max:255'],
            'foto_closed'      => ['nullable', 'string', 'max:255'],
            'verifikasi_oleh'  => ['nullable', 'string', 'max:150'],
            'verifikasi_pada'  => ['nullable', 'date'],
        ]));

        return back()->with('ok', 'Respon manajemen tersimpan.');
    }

    /* ---------- bantu ---------- */
    private function validasi(Request $r, ?SmkpAudit $abaikan = null): array
    {
        /* Perusahaan ditetapkan lebih dulu, sebelum dipakai membangun
           aturan keunikan. Memakai $r->input('company_id') mentah
           membuat batas "satu periode audit per perusahaan per tahun"
           dapat dilewati begitu saja: pengguna biasa cukup mengirim
           company_id perusahaan lain, keunikannya diperiksa terhadap
           perusahaan itu, lalu barisnya tetap tersimpan sebagai milik
           perusahaannya sendiri — dua audit pada tahun yang sama. */
        $milik = auth()->user()?->isAdmin()
            ? ($r->input('company_id') ?: null)
            : auth()->user()?->company_id;

        // Satu periode audit per perusahaan per tahun.
        $unik = Rule::unique('smkp_audits', 'tahun')
            ->where('company_id', $milik)
            ->ignore($abaikan?->id);

        return $this->pemilik($r->validate([
            'company_id'      => ['nullable','exists:companies,id'],
            'tahun'           => ['required','integer','min:2000','max:2100', $unik],
            'judul'           => ['nullable','string','max:200'],
            'status'          => ['required','in:draft,berjalan,selesai'],
            'tanggal_mulai'   => ['nullable','date'],
            'tanggal_selesai' => ['nullable','date','after_or_equal:tanggal_mulai'],
            'ketua_auditor'   => ['nullable','string','max:150'],
        ], [
            'tahun.unique' => 'Periode audit tahun ini sudah ada untuk perusahaan tersebut.',
        ]));
    }
}
