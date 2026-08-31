<?php

namespace Eqohsee\SmkpAudit\Http\Controllers;

use Eqohsee\SmkpAudit\Contracts\PencatatJejak;
use Eqohsee\SmkpAudit\Models\SmkpAttendee;
use Eqohsee\SmkpAudit\Models\SmkpAudit;
use Eqohsee\SmkpAudit\Models\SmkpFinding;
use Eqohsee\SmkpAudit\Support\Kop;
use Eqohsee\SmkpAudit\Support\Smkp;
use Eqohsee\SmkpAudit\Support\SmkpTahap;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Audit SMKP Minerba — 7 elemen sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Alur: buat periode audit → Tahap I (kelayakan, kecukupan dokumentasi,
 * Rencana Audit) → Tahap II (rapat pembukaan, penilaian tiap kriteria,
 * temuan) → pelaporan.
 *
 * Seluruh halaman dirender lewat DUA komponen Inertia saja — satu untuk
 * kerja, satu untuk berkas cetak — dan dibedakan oleh prop `mode`. Nama
 * komponennya diambil dari config supaya aplikasi induk bebas menaruhnya di
 * mana pun dalam resources/js miliknya.
 */
class SmkpController extends Controller
{
    /* ---------- Daftar periode audit ---------- */
    public function index()
    {
        $audits = SmkpAudit::query()
            ->when(SmkpAudit::pakaiPerusahaan(), fn ($q) => $q->with('company'))
            ->withCount(['findings', 'findings as findings_open_count' => fn ($b) => $b->where('status', '<>', 'Closed')])
            ->orderByDesc('tahun')->orderByDesc('id')
            ->paginate(15);

        return $this->halaman('index', [
            'audits' => $audits,
            'meta'   => Smkp::meta(),
        ]);
    }

    public function create()
    {
        return $this->halaman('form', [
            'audit'     => new SmkpAudit(['tahun' => now()->year]),
            'companies' => $this->perusahaan(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $d['user_id'] = auth()->id();
        $d['hasil'] ??= [];

        $audit = SmkpAudit::create($d);
        $this->catat('Buat audit SMKP', $audit->judul ?: ('Audit '.$audit->tahun));

        return redirect()->to($this->rute('show', $audit))->with('ok', 'Periode audit dibuat.');
    }

    public function edit(SmkpAudit $smkp)
    {
        return $this->halaman('form', [
            'audit'     => $smkp,
            'companies' => $this->perusahaan(),
        ]);
    }

    public function update(Request $request, SmkpAudit $smkp)
    {
        $smkp->update($this->validasi($request, $smkp));

        return redirect()->to($this->rute('show', $smkp))->with('ok', 'Periode audit diperbarui.');
    }

    public function destroy(SmkpAudit $smkp)
    {
        $nama = $smkp->judul ?: ('Audit '.$smkp->tahun);
        $smkp->delete();
        $this->catat('Hapus audit SMKP', $nama);

        return redirect()->to($this->rute('index'))->with('ok', 'Periode audit dihapus.');
    }

    /* ---------- Ringkasan satu audit ---------- */
    public function show(SmkpAudit $smkp)
    {
        return $this->halaman('show', [
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
                'rencanaCetak' => $this->rute('rencana.cetak', $smkp),
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
            'tahap1'        => 'tahap1',
            'rencana'       => 'rencana',
            'rapat'         => 'rapat',
            'temuan'        => 'temuan',
            'berita'        => 'berita-acara',
            'rencana-cetak' => 'rencana.cetak',
            'laporan'       => 'laporan',
        ][$bagian] ?? null;

        abort_if(!$rute, 404, 'Bagian audit tidak dikenal.');

        $audit = $this->auditBerjalan();

        if (!$audit) {
            return redirect()->to($this->rute('create'))
                ->with('ok', 'Belum ada periode audit. Buat satu dulu untuk mulai bekerja.');
        }

        return redirect()->to($this->rute($rute, $audit));
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
        return $this->halaman('acuan', [
            'elemen'   => Smkp::elemen(),
            'meta'     => Smkp::meta(),
            'kategori' => Smkp::kategori(),
            'tingkat'  => Smkp::tingkat(),
        ]);
    }

    /* ================= TAHAP I — Permulaan Audit ================= */

    public function tahap1(SmkpAudit $smkp)
    {
        return $this->halaman('tahap1', [
            'audit'     => $smkp,
            'elemen'    => Smkp::elemen(),
            'kelayakan' => SmkpTahap::indikatorKelayakan(),
            'faktor'    => SmkpTahap::faktorPenyesuaian(),
            'kinerja'   => SmkpTahap::butirKinerja(),
            'risikoKelas' => SmkpTahap::kelasRisiko(),
            'kecukupanPilihan' => [
                SmkpTahap::LENGKAP       => SmkpTahap::labelKecukupan(SmkpTahap::LENGKAP),
                SmkpTahap::TIDAK_LENGKAP => SmkpTahap::labelKecukupan(SmkpTahap::TIDAK_LENGKAP),
            ],
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

        return redirect()->to($this->rute('tahap1', $smkp))->with('ok', 'Hasil Tahap I tersimpan.');
    }

    /** Berita Acara Hasil Pelaksanaan Tahapan Awal — siap cetak. */
    public function beritaAcara(SmkpAudit $smkp)
    {
        return $this->cetak('berita', [
            'totalLembar' => 4,
            'audit'       => $smkp,
            'elemen'      => Smkp::elemen(),
            'kelayakan'   => SmkpTahap::indikatorKelayakan(),
            'faktor'      => SmkpTahap::faktorPenyesuaian(),
            'kinerja'     => SmkpTahap::butirKinerja(),
            'mandays'     => $smkp->mandays(),
            'rekap'       => $smkp->rekapKecukupan(),
            'dok'         => $this->kop($smkp, 'berita-acara'),
            'kembali'     => $this->rute('tahap1', $smkp),
        ]);
    }

    /* ================= RENCANA AUDIT ================= */

    public function rencana(SmkpAudit $smkp)
    {
        return $this->halaman('rencana', [
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

        return redirect()->to($this->rute('rencana', $smkp))->with('ok', $pesan);
    }

    /** Laporan Rencana Audit — sembilan komponen wajib, siap cetak. */
    public function rencanaCetak(SmkpAudit $smkp)
    {
        return $this->cetak('rencana', [
            'totalLembar' => 3,
            'audit'       => $smkp,
            'komponen'    => SmkpTahap::komponenRencana(),
            'pengesah'    => SmkpTahap::pengesah(),
            'kegiatan'    => SmkpTahap::kegiatanLapangan(),
            'rekap'       => $smkp->rekapRencana(),
            'mandays'     => $smkp->mandays(),
            'dok'         => $this->kop($smkp, 'rencana-audit'),
            'kembali'     => $this->rute('rencana', $smkp),
        ]);
    }

    /* ================= TAHAP II — rapat & daftar hadir ================= */

    public function rapat(SmkpAudit $smkp)
    {
        return $this->halaman('rapat', [
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

        return back()->with('ok', 'Peserta dihapus.');
    }

    /** Daftar hadir siap cetak, satu berkas per rapat. */
    public function daftarHadir(SmkpAudit $smkp, string $rapat)
    {
        abort_if(!array_key_exists($rapat, SmkpTahap::rapat()), 404, 'Rapat tidak dikenal.');

        return $this->cetak('hadir', [
            'totalLembar' => max(1, (int) ceil($smkp->hadir($rapat)->count() / 16)),
            'audit'       => $smkp,
            'rapat'       => $rapat,
            'judul'       => SmkpTahap::labelRapat($rapat),
            'hadir'       => $smkp->hadir($rapat),
            'dok'         => $this->kop($smkp, 'daftar-hadir'),
            'kembali'     => $this->rute('rapat', $smkp),
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
        $this->catat('Ubah tahap audit SMKP', SmkpTahap::labelTahap($tahap));

        return back()->with('ok', 'Audit berpindah ke '.SmkpTahap::labelTahap($tahap).'.');
    }

    /* ---------- Formulir penilaian per elemen ---------- */
    public function nilai(SmkpAudit $smkp, string $elemen)
    {
        $ref = $this->elemen($elemen);

        return $this->halaman('nilai', [
            'audit'  => $smkp,
            'elemen' => $ref,
            'rekap'  => Smkp::rekapElemen($ref, $smkp->hasil ?? []),
            'semua'  => Smkp::elemen(),
        ]);
    }

    /** Simpan penilaian satu elemen; kriteria di elemen lain tidak tersentuh. */
    public function simpanNilai(Request $request, SmkpAudit $smkp, string $elemen)
    {
        $ref = $this->elemen($elemen);

        $hasil = $smkp->hasil ?? [];
        $masuk = (array) $request->input('k', []);

        foreach ($ref['sub'] as $sub) {
            foreach (Smkp::butirSub($sub) as $b) {
                $kode  = $b['kode'];
                $baris = $masuk[$kode] ?? null;
                if (!is_array($baris)) continue;

                $v = $baris['v'] ?? null;

                if ($v === '' || $v === null) {
                    unset($hasil[$kode]);                 // kembali ke "belum dinilai"
                    continue;
                }

                if (is_string($v) && strcasecmp($v, Smkp::NA) === 0) {
                    $nilai = Smkp::NA;                    // di luar lingkup perusahaan
                } elseif (is_numeric($v)) {
                    // Nilai dijepit ke rentang butir; formulir yang dikirim
                    // langsung tidak boleh menaikkan capaian melebihi maksimum.
                    $nilai = max(0, min((int) $v, (int) $b['maks']));
                } else {
                    continue;                             // abaikan masukan asing
                }

                $hasil[$kode] = [
                    'v'     => $nilai,
                    'ket'   => mb_substr(trim((string) ($baris['ket']   ?? '')), 0, 2000),
                    'bukti' => mb_substr(trim((string) ($baris['bukti'] ?? '')), 0, 500),
                ];
            }
        }

        $smkp->update(['hasil' => $hasil]);

        if ($smkp->status === 'draft') $smkp->update(['status' => 'berjalan']);

        return redirect()->to($this->rute('nilai', $smkp, $elemen))
            ->with('ok', 'Penilaian elemen '.$ref['kode'].' tersimpan.');
    }

    /* ---------- Temuan / CAR ---------- */
    public function temuan(SmkpAudit $smkp)
    {
        return $this->halaman('temuan', [
            'audit'  => $smkp,
            'temuan' => $smkp->findings()->latest('id')->get(),
            'usulan' => $this->usulanTemuan($smkp),
        ]);
    }

    /** Ketidaksesuaian dari formulir penilaian yang belum diangkat jadi CAR. */
    private function usulanTemuan(SmkpAudit $smkp): array
    {
        $sudah = $smkp->findings()->pluck('kode_kriteria')->all();

        return array_values(array_filter(
            Smkp::temuan($smkp->hasil ?? []),
            fn ($t) => !in_array($t['kode'], $sudah, true)
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

        return back()->with('ok', 'Tindakan perbaikan tersimpan.');
    }

    public function hapusTemuan(SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $temuan->delete();

        return back()->with('ok', 'Temuan dihapus.');
    }

    /* ---------- Laporan siap cetak ---------- */
    public function laporan(SmkpAudit $smkp)
    {
        return $this->cetak('laporan', [
            'totalLembar' => 1 + max(1, (int) ceil($smkp->findings()->count() / 6)),
            'audit'       => $smkp,
            'rekap'       => $smkp->rekap(),
            'elemen'      => Smkp::elemen(),
            'temuan'      => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get(),
            'meta'        => Smkp::meta(),
            'dok'         => $this->kop($smkp, 'laporan-audit'),
            'kembali'     => $this->rute('show', $smkp),
        ]);
    }

    /* ================= bantu ================= */

    /** Halaman kerja: satu komponen Inertia, dibedakan oleh `mode`. */
    private function halaman(string $mode, array $prop)
    {
        return Inertia::render(config('smkp.inertia.halaman'), $prop + [
            'mode'   => $mode,
            'awalan' => $this->awalan(),
        ]);
    }

    /** Berkas cetak: komponen Inertia kedua, juga dibedakan oleh `mode`. */
    private function cetak(string $mode, array $prop)
    {
        return Inertia::render(config('smkp.inertia.cetak'), $prop + [
            'mode'   => $mode,
            'awalan' => $this->awalan(),
        ]);
    }

    /** Awalan URL modul, dikirim ke Vue supaya tautannya ikut berpindah. */
    private function awalan(): string
    {
        return '/'.trim((string) config('smkp.rute.awalan', 'smkp'), '/');
    }

    /** URL satu rute modul menurut nama yang disetel aplikasi induk. */
    private function rute(string $sufiks, ...$arg): string
    {
        return route(config('smkp.rute.nama', 'smkp.').$sufiks, $arg);
    }

    /** Elemen acuan menurut kodenya; kode asing berhenti sebagai 404. */
    private function elemen(string $kode): array
    {
        foreach (Smkp::elemen() as $e) {
            if ($e['kode'] === $kode) return $e;
        }

        abort(404, 'Elemen tidak dikenal.');
    }

    /** Daftar perusahaan untuk formulir; kosong bila modul dipakai tunggal. */
    private function perusahaan()
    {
        if (!SmkpAudit::pakaiPerusahaan()) return [];

        $model = SmkpAudit::modelPerusahaan();

        return $model::orderBy(config('smkp.perusahaan.nama', 'name'))->get();
    }

    /** Kop dokumen terkendali untuk berkas cetak audit ini. */
    private function kop(SmkpAudit $smkp, string $jenis): array
    {
        return Kop::untuk($jenis, SmkpAudit::pakaiPerusahaan() ? $smkp->company : null);
    }

    private function catat(string $aksi, ?string $rincian = null): void
    {
        app(PencatatJejak::class)->catat($aksi, $rincian);
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

    private function validasi(Request $r, ?SmkpAudit $abaikan = null): array
    {
        // Satu periode audit per perusahaan per tahun.
        $unik = Rule::unique('smkp_audits', 'tahun')
            ->where('company_id', $r->input('company_id') ?: null)
            ->ignore($abaikan?->id);

        return $r->validate([
            'company_id'      => ['nullable', ...(SmkpAudit::pakaiPerusahaan()
                                    ? ['exists:'.config('smkp.perusahaan.tabel', 'companies').',id']
                                    : [])],
            'tahun'           => ['required','integer','min:2000','max:2100', $unik],
            'judul'           => ['nullable','string','max:200'],
            'status'          => ['required','in:draft,berjalan,selesai'],
            'tanggal_mulai'   => ['nullable','date'],
            'tanggal_selesai' => ['nullable','date','after_or_equal:tanggal_mulai'],
            'ketua_auditor'   => ['nullable','string','max:150'],
        ], [
            'tahun.unique' => 'Periode audit tahun ini sudah ada untuk perusahaan tersebut.',
        ]);
    }
}
