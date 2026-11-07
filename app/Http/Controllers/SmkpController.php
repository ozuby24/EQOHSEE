<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, SmkpAttendee, SmkpAudit, SmkpBukti, SmkpFinding, SmkpTindakLanjut, User};
use App\Support\{KopDokumen, Smkp, SmkpTahap};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Audit SMKP Minerba — 7 elemen sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Alur: buat periode audit → Tahap I (permulaan, kecukupan dokumentasi,
 * Rencana Audit) → Tahap II (rapat, penilaian tiap butir, temuan/CAR) →
 * pelaporan. Setiap berkas resmi tersedia dalam bentuk siap cetak.
 */
class SmkpController extends Controller
{
    /* ---------- Daftar periode audit ---------- */
    public function index(Request $request)
    {
        $q       = $this->teks($request, 'q');
        $status  = $this->teks($request, 'status');
        $company = (int) $this->teks($request, 'perusahaan', '0');

        $dasar = SmkpAudit::query()
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('judul', 'like', "%{$q}%")
                ->orWhere('ketua_auditor', 'like', "%{$q}%")
                ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($company > 0, fn ($b) => $b->where('company_id', $company));

        $jumlah = ['semua' => (clone $dasar)->count()];
        foreach (array_keys(SmkpAudit::STATUS) as $st) $jumlah[$st] = (clone $dasar)->where('status', $st)->count();

        $audits = SmkpAudit::with('company:id,name,logo,kategori,parent_id')
            ->withCount([
                'findings',
                'findings as findings_open_count' => fn ($b) => $b->where('status', '<>', 'Closed'),
            ])
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('judul', 'like', "%{$q}%")
                ->orWhere('ketua_auditor', 'like', "%{$q}%")
                ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when(array_key_exists($status, SmkpAudit::STATUS), fn ($b) => $b->where('status', $status))
            ->when($company > 0, fn ($b) => $b->where('company_id', $company))
            ->orderByDesc('tahun')->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $audits->getCollection()->transform(function (SmkpAudit $a) {
            $r = $a->rekap();

            return [
                'id'        => $a->id,
                'judul'     => $a->namaTampil(),
                'tahun'     => $a->tahun,
                'status'    => $a->status,
                'tahap'     => $a->tahap,
                'labelTahap'=> SmkpTahap::labelTahap((int) $a->tahap),
                'perusahaan'=> $a->company?->name,
                'logo'      => $a->company?->logoUrl(),
                'ketua'     => $a->ketua_auditor,
                'mulai'     => $a->tanggal_mulai?->translatedFormat('d M Y'),
                'selesai'   => $a->tanggal_selesai?->translatedFormat('d M Y'),
                'skor'      => $r['skor'],
                'tingkat'   => $r['tingkat'],
                'kemajuan'  => $r['berlaku'] ? $r['dinilai'] / $r['berlaku'] : 0,
                'temuan'    => (int) $a->findings_count,
                'terbuka'   => (int) $a->findings_open_count,
            ];
        });

        return Inertia::render('Smkp/Index', [
            'audits'      => $audits,
            'saring'      => ['q' => $q, 'status' => $status, 'perusahaan' => $company ?: ''],
            'jumlah'      => $jumlah,
            'perusahaan'  => $this->perusahaan($request)->map->pilihan()->values(),
            'statusPilihan' => SmkpAudit::STATUS,
            'meta'        => Smkp::meta(),
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    public function create(Request $request)
    {
        $daftar = $this->perusahaan($request);

        return Inertia::render('Smkp/Form', [
            'audit' => [
                'id' => null, 'tahun' => (int) now()->year, 'judul' => '',
                'company_id' => $daftar->count() === 1 ? $daftar->first()->id : '',
                'status' => 'draft', 'tanggal_mulai' => '', 'tanggal_selesai' => '', 'ketua_auditor' => '',
            ],
            'perusahaan'    => $daftar->map->pilihan()->values(),
            'statusPilihan' => $this->statusPilihan(null),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $d['user_id'] = $request->user()->id;
        $d['hasil']   = [];
        $d['tahap']   = SmkpTahap::PERMULAAN;

        $audit = SmkpAudit::create($d);
        ActivityLog::write('Buat audit SMKP', $audit->namaTampil().' · '.$audit->company?->name, 'smkp');

        return redirect()->route('smkp.show', $audit)->with('ok', 'Periode audit dibuat. Mulai dari Tahap I — Permulaan Audit.');
    }

    public function edit(Request $request, SmkpAudit $smkp)
    {
        return Inertia::render('Smkp/Form', [
            'audit' => [
                'id'              => $smkp->id,
                'tahun'           => $smkp->tahun,
                'judul'           => (string) $smkp->judul,
                'company_id'      => $smkp->company_id,
                'status'          => $smkp->status,
                'tanggal_mulai'   => $smkp->tanggal_mulai?->format('Y-m-d') ?? '',
                'tanggal_selesai' => $smkp->tanggal_selesai?->format('Y-m-d') ?? '',
                'ketua_auditor'   => (string) $smkp->ketua_auditor,
            ],
            'perusahaan'    => $this->perusahaan($request)->map->pilihan()->values(),
            'statusPilihan' => $this->statusPilihan($smkp),
            'bisaHapus'     => $request->user()->isAdmin(),
        ]);
    }

    public function update(Request $request, SmkpAudit $smkp)
    {
        $smkp->update($this->validasi($request, $smkp));
        ActivityLog::write('Ubah audit SMKP', $smkp->namaTampil(), 'smkp');

        return redirect()->route('smkp.show', $smkp)->with('ok', 'Periode audit diperbarui.');
    }

    public function destroy(SmkpAudit $smkp)
    {
        $nama = $smkp->namaTampil().' · '.$smkp->company?->name;
        $smkp->delete();
        Storage::disk('local')->deleteDirectory(SmkpTindakLanjut::folder($smkp->id));
        Storage::disk('local')->deleteDirectory(SmkpBukti::folder($smkp->id));
        Storage::disk('local')->deleteDirectory(SmkpAudit::folderTtd($smkp->id));
        ActivityLog::write('Hapus audit SMKP', $nama, 'smkp');

        return redirect()->route('smkp.index')->with('ok', 'Periode audit dihapus.');
    }

    /* ---------- Ringkasan satu audit ---------- */
    public function show(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $rekap  = $smkp->rekap();
        $status = $smkp->statusAlur();

        $alur = [];
        foreach (SmkpTahap::alur() as $kunci => $babak) {
            $langkah = [];
            foreach ($babak['langkah'] as $l) {
                $langkah[] = [
                    'kunci'   => $l['kunci'],
                    'judul'   => $l['judul'],
                    'ket'     => $l['ket'],
                    'jenis'   => $l['jenis'],
                    'url'     => isset($l['arg']) ? route($l['rute'], [$smkp, $l['arg']]) : route($l['rute'], $smkp),
                    'selesai' => $status[$l['kunci']]['selesai'] ?? false,
                    'status'  => $status[$l['kunci']]['ket'] ?? '',
                ];
            }
            $alur[] = ['kunci' => $kunci] + array_diff_key($babak, ['langkah' => 1]) + ['langkah' => $langkah];
        }

        $ulang  = Smkp::perluDinilaiUlang($smkp->hasil ?? []);
        $elemen = [];
        foreach (Smkp::elemen() as $e) {
            $r = $rekap['elemen'][$e['kode']];
            $elemen[] = [
                'kode'    => $e['kode'], 'nama' => $e['nama'], 'bobot' => $r['bobot'],
                'dinilai' => $r['dinilai'], 'berlaku' => $r['berlaku'], 'total' => $r['total'],
                'capaian' => $r['capaian'], 'skor' => $r['skor'], 'nilai' => $r['nilai'], 'maks' => $r['maks'],
                'ulang'   => count(array_filter($ulang, fn ($x) => $x['elemen'] === $e['kode'])),
                'url'     => route('smkp.nilai', [$smkp, $e['kode']]),
            ];
        }

        $temuan = $smkp->findings()->get();

        return Inertia::render('Smkp/Show', [
            'audit'  => $this->ringkasAudit($smkp),
            'rekap'  => [
                'skor' => $rekap['skor'], 'tingkat' => $rekap['tingkat'],
                'dinilai' => $rekap['dinilai'], 'berlaku' => $rekap['berlaku'], 'total' => $rekap['total'],
                'nilai' => $rekap['nilai'], 'maks' => $rekap['maks'],
            ],
            'hitung' => Smkp::hitungTemuan($smkp->hasil ?? []) + [
                'terbuka'   => $temuan->where('status', '<>', 'Closed')->count(),
                'ditutup'   => $temuan->where('status', 'Closed')->count(),
                'diangkat'  => $temuan->count(),
                'belum'     => count($this->usulanTemuan($smkp)),
            ],
            'elemen'      => $elemen,
            'ulang'       => array_map(fn ($k, $x) => ['kode' => $k] + $x, array_keys($ulang), $ulang),
            'alur'        => $alur,
            'tahap'       => collect(SmkpTahap::tahap())->map(fn ($t, $k) => $t + ['kode' => $k])->values(),
            'siapTahapDua'=> $smkp->siapTahapDua(),
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    /* ================= pintasan menu ================= */

    /** Penyalur menu samping ke bagian tertentu pada audit yang sedang berjalan. */
    public function lanjut(string $bagian)
    {
        $rute = [
            'tahap1'  => 'smkp.tahap1',
            'rencana' => 'smkp.rencana',
            'rapat'   => 'smkp.rapat',
            'temuan'  => 'smkp.temuan',
            'laporan' => 'smkp.laporan',
        ][$bagian] ?? null;

        abort_if(!$rute, 404, 'Bagian audit tidak dikenal.');

        $audit = SmkpAudit::where('status', '<>', 'selesai')->orderByDesc('tahun')->orderByDesc('id')->first()
            ?? SmkpAudit::orderByDesc('tahun')->orderByDesc('id')->first();

        if (!$audit) {
            return redirect()->route('smkp.index')->with('ok', 'Belum ada periode audit. Buat satu dulu untuk mulai bekerja.');
        }

        return redirect()->route($rute, $audit);
    }

    /** Acuan kriteria audit — 7 elemen beserta bobot dan rujukan halamannya. */
    public function acuan()
    {
        $elemen = [];
        foreach (Smkp::elemen() as $e) {
            $sub = [];
            foreach ($e['sub'] as $s) {
                $sub[] = [
                    'kode' => $s['kode'], 'nama' => $s['nama'], 'ref' => $s['ref'] ?? null,
                    'maks' => Smkp::maksSub($s),
                    'subsub' => array_map(fn ($x) => ['kode' => $x['kode'], 'nama' => $x['nama'], 'maks' => $x['maks'] ?? null], $s['subsub'] ?? []),
                    'butir' => array_map(fn ($b) => [
                        'kode' => $b['kode'], 'nama' => $b['nama'], 'maks' => (int) $b['maks'], 'rubrik' => Smkp::rubrik($b['kode']),
                    ], Smkp::butirSub($s)),
                ];
            }
            $elemen[] = ['kode' => $e['kode'], 'nama' => $e['nama'], 'bobot' => $e['bobot'], 'maks' => Smkp::maksElemen($e), 'sub' => $sub];
        }

        return Inertia::render('Smkp/Acuan', [
            'elemen'   => $elemen,
            'meta'     => Smkp::meta(),
            'kategori' => Smkp::kategori(),
            'tingkat'  => Smkp::tingkat(),
            'ringkas'  => [
                'elemen'    => count(Smkp::elemen()),
                'subElemen' => array_sum(array_map(fn ($e) => count($e['sub']), Smkp::elemen())),
                'butir'     => Smkp::jumlahButir(),
                'maks'      => Smkp::totalNilai(),
            ],
            'alur' => collect(SmkpTahap::alur())->map(fn ($b) => [
                'nomor' => $b['nomor'], 'judul' => $b['judul'], 'ket' => $b['ket'], 'warna' => $b['warna'],
                'langkah' => array_map(fn ($l) => ['judul' => $l['judul'], 'jenis' => $l['jenis']], $b['langkah']),
            ])->values(),
        ]);
    }

    /* ================= TAHAP I — Permulaan Audit ================= */

    public function tahap1(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $p  = (array) ($smkp->permulaan ?? []);
        $kc = (array) ($smkp->kecukupan ?? []);

        $faktor = [];
        foreach (array_keys(SmkpTahap::faktorPenyesuaian()) as $k) $faktor[$k] = !empty($p['faktor'][$k]);

        $kelayakan = [];
        foreach (array_keys(SmkpTahap::indikatorKelayakan()) as $k) $kelayakan[$k] = (string) ($p['kelayakan'][$k] ?? '');

        $kecukupan = [];
        foreach (Smkp::elemen() as $e) {
            $kecukupan[$e['kode']] = [
                'status' => (string) ($kc[$e['kode']]['status'] ?? ''),
                'ket'    => (string) ($kc[$e['kode']]['ket'] ?? ''),
            ];
        }

        $kinerja = [];
        foreach (array_keys(SmkpTahap::butirKinerja()) as $k) $kinerja[$k] = (string) ($smkp->kinerja[$k] ?? '');

        return Inertia::render('Smkp/Tahap1', [
            'audit' => $this->ringkasAudit($smkp),
            'isian' => [
                'permulaan' => [
                    'tanggal_kontak' => (string) ($p['tanggal_kontak'] ?? ''),
                    'media_kontak'   => (string) ($p['media_kontak'] ?? ''),
                    'wakil_auditi'   => (string) ($p['wakil_auditi'] ?? ''),
                    'jabatan_wakil'  => (string) ($p['jabatan_wakil'] ?? ''),
                    'surat_nomor'    => (string) ($p['surat_nomor'] ?? ''),
                    'surat_tanggal'  => (string) ($p['surat_tanggal'] ?? ''),
                    'izin'           => (string) ($p['izin'] ?? ''),
                    'alamat'         => (string) ($p['alamat'] ?? ''),
                    'periode'        => (string) ($p['periode'] ?? ''),
                    'sifat_kontak'   => (string) ($p['sifat_kontak'] ?? ''),
                    'tanggal_kelayakan' => (string) ($p['tanggal_kelayakan'] ?? ''),
                    'layak'          => (string) ($p['layak'] ?? ''),
                    'bukti_integritas' => (string) ($p['bukti_integritas'] ?? ''),
                    'tanggal_ba'     => (string) ($p['tanggal_ba'] ?? ''),
                    'alamat_site'    => (string) ($p['alamat_site'] ?? ''),
                    'sk_auditor'     => (string) ($p['sk_auditor'] ?? ''),
                    'form_rapat'     => (string) ($p['form_rapat'] ?? ''),
                    'jumlah_pekerja' => (string) ($p['jumlah_pekerja'] ?? ''),
                    'kelas_risiko'   => (string) ($p['kelas_risiko'] ?? ''),
                    'mandays_dasar'  => (string) ($p['mandays_dasar'] ?? ''),
                    'jumlah_auditor' => (string) ($p['jumlah_auditor'] ?? '1'),
                    'penyesuaian'    => $this->penyesuaianTampil($smkp, $p),
                    'kesimpulan'     => (string) ($p['kesimpulan'] ?? ''),
                    'kelayakan'      => $kelayakan,
                    'faktor'         => $faktor,
                ],
                'kinerja'   => $kinerja,
                'kecukupan' => $kecukupan,
                'persiapan' => $this->persiapan($p),
            ],
            'elemen'     => array_map(fn ($e) => ['kode' => $e['kode'], 'nama' => $e['nama']], Smkp::elemen()),
            'kelayakan'  => SmkpTahap::indikatorKelayakan(),
            'faktor'     => SmkpTahap::faktorPenyesuaian(),
            'kinerja'    => SmkpTahap::butirKinerja(),
            'kelasRisiko'=> SmkpTahap::kelasRisiko(),
            'mandays'    => $smkp->mandays(),
            // Audit selesai: berkas memakai angka yang dibekukan, bukan hitungan formulir.
            'hariTetap'  => $smkp->tahap === SmkpTahap::PELAPORAN ? $smkp->mandays_tetap : null,
            'rekap'      => $smkp->rekapKecukupan(),
            'nilaiStatus'=> ['lengkap' => SmkpTahap::LENGKAP, 'tidak' => SmkpTahap::TIDAK_LENGKAP],
            'bisaSunting'=> $request->user()->bisaAudit(),
            // Data induk perusahaan — dipakai bila isian Tahap I dikosongkan.
            'dataPerusahaan' => ['izin' => (string) $smkp->company?->izin_type, 'alamat' => (string) ($smkp->company?->address ?: $smkp->company?->location), 'alamatSite' => $this->alamatSiteBawaan($smkp)],
            'tren'           => SmkpTahap::trenData(),
            'auditLalu'      => SmkpTahap::auditLalu(),
            'kliBawaan'      => SmkpTahap::KLI_BAWAAN,
            'buktiIntegritas'=> $this->buktiIntegritasBawaan($smkp),
            'nomorRapat'     => KopDokumen::untuk('daftar-hadir', $smkp->company)['nomor'],
            'jumlahRisiko'   => ['present' => count((array) ($smkp->risiko['present'] ?? [])), 'future' => count((array) ($smkp->risiko['future'] ?? []))],
            'saranAuditLalu' => $request->user()->bisaAudit() ? $this->saranAuditLalu($smkp) : null,
        ]);
    }

    public function simpanTahap1(Request $request, SmkpAudit $smkp)
    {
        // Pengguna Indonesia mengetik desimal dengan koma ("12,5"); halaman pun
        // menghitungnya begitu, jadi server menerimanya juga.
        $this->desimalKoma($request, ['permulaan.mandays_dasar', 'permulaan.penyesuaian']);
        // Pemisah ribuan pada jumlah pekerja ("1.250" / "1 250").
        $pekerja = $request->input('permulaan.jumlah_pekerja');
        if (is_string($pekerja) && preg_match('/^\s*\d{1,3}([.\s]\d{3})+\s*$/', $pekerja)) {
            $request->merge(['permulaan' => ['jumlah_pekerja' => preg_replace('/\D/', '', $pekerja)] + (array) $request->input('permulaan')]);
        }

        $d = $request->validate([
            'permulaan.tanggal_kontak'  => ['nullable', 'date'],
            'permulaan.media_kontak'    => ['nullable', 'string', 'max:150'],
            'permulaan.wakil_auditi'    => ['nullable', 'string', 'max:150'],
            'permulaan.jabatan_wakil'   => ['nullable', 'string', 'max:150'],
            'permulaan.surat_nomor'     => ['nullable', 'string', 'max:150'],
            'permulaan.surat_tanggal'   => ['nullable', 'date'],
            'permulaan.izin'            => ['nullable', 'string', 'max:150'],
            'permulaan.alamat'          => ['nullable', 'string', 'max:500'],
            'permulaan.periode'         => ['nullable', 'string', 'max:150'],
            'permulaan.sifat_kontak'    => ['nullable', Rule::in(['formal', 'informal'])],
            'permulaan.tanggal_kelayakan' => ['nullable', 'date'],
            'permulaan.layak'           => ['nullable', Rule::in(['layak', 'tidak'])],
            'permulaan.bukti_integritas'=> ['nullable', 'string', 'max:300'],
            'permulaan.tanggal_ba'      => ['nullable', 'date'],
            'permulaan.alamat_site'     => ['nullable', 'string', 'max:500'],
            'permulaan.sk_auditor'      => ['nullable', 'string', 'max:500'],
            'permulaan.form_rapat'      => ['nullable', 'string', 'max:200'],
            'permulaan.jumlah_pekerja'  => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'permulaan.kelas_risiko'    => ['nullable', Rule::in(SmkpTahap::kelasRisiko())],
            'permulaan.mandays_dasar'   => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'permulaan.jumlah_auditor'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'permulaan.penyesuaian'     => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'permulaan.kesimpulan'      => ['nullable', 'string', 'max:2000'],
            'permulaan.kelayakan'       => ['nullable', 'array'],
            'permulaan.kelayakan.*'     => ['nullable', 'string', 'max:500'],
            'permulaan.faktor'          => ['nullable', 'array'],
            'permulaan.faktor.*'        => ['nullable'],

            'kinerja'                   => ['nullable', 'array'],
            'kinerja.*'                 => ['nullable', 'string', 'max:50'],

            'kecukupan'                 => ['nullable', 'array'],
            'kecukupan.*.status'        => ['nullable', Rule::in([SmkpTahap::LENGKAP, SmkpTahap::TIDAK_LENGKAP])],
            'kecukupan.*.ket'           => ['nullable', 'string', 'max:1000'],

            'persiapan'                    => ['nullable', 'array'],
            'persiapan.tren'               => ['nullable', 'array'],
            'persiapan.tren.*'             => ['nullable', 'array', 'max:20'],
            'persiapan.tren.*.*'           => ['nullable', 'string', 'max:300'],
            'persiapan.peralatan'          => ['nullable', 'array', 'max:30'],
            'persiapan.peralatan.*.jenis'  => ['nullable', 'string', 'max:150'],
            'persiapan.peralatan.*.pa'     => ['nullable', 'string', 'max:30'],
            'persiapan.peralatan.*.ma'     => ['nullable', 'string', 'max:30'],
            'persiapan.kli'                => ['nullable', 'array', 'max:30'],
            'persiapan.kli.*.indikator'    => ['nullable', 'string', 'max:300'],
            'persiapan.kli.*.status'       => ['nullable', 'string', 'max:150'],
            'persiapan.audit_lalu'         => ['nullable', 'array'],
            'persiapan.audit_lalu.*'       => ['nullable', 'string', 'max:1000'],
            'persiapan.masukan'            => ['nullable', 'array', 'max:30'],
            'persiapan.masukan.*.sumber'   => ['nullable', 'string', 'max:200'],
            'persiapan.masukan.*.isi'      => ['nullable', 'string', 'max:2000'],
        ], [
            'kecukupan.*.status.in'     => 'Status kecukupan dokumentasi tidak dikenal.',
            'permulaan.kelas_risiko.in' => 'Kelas risiko harus Rendah, Sedang, atau Tinggi.',
            'persiapan.tren.*.max'      => 'Paling banyak 20 baris per data trend.',
        ]);

        $masuk = (array) ($d['permulaan'] ?? []);

        // Faktor penyesuaian paling banyak 10% dari hari kerja per auditor,
        // dan hanya bila sedikitnya satu kondisi dicentang.
        if (isset($masuk['penyesuaian']) && trim((string) $masuk['penyesuaian']) !== '') {
            $md = SmkpTahap::mandays(['penyesuaian' => ''] + $masuk);
            if ((float) $masuk['penyesuaian'] > $md['maks_penyesuaian'] + 1e-9) {
                throw \Illuminate\Validation\ValidationException::withMessages(['permulaan.penyesuaian' => $md['kondisi']
                    ? 'Faktor penyesuaian paling banyak 10% dari hari kerja per auditor: '.number_format($md['maks_penyesuaian'], 2, ',', '.').' hari.'
                    : 'Tidak ada kondisi penyesuaian yang dicentang — hari kerja tidak dapat ditambah.']);
            }
        }

        // Hanya kunci yang dikenal yang disimpan; nilai kosong tetap disimpan
        // sebagai kosong agar isian yang dihapus benar-benar terhapus.
        $p = [];
        foreach (['tanggal_kontak', 'media_kontak', 'wakil_auditi', 'jabatan_wakil', 'surat_nomor', 'surat_tanggal', 'izin', 'alamat',
                  'periode', 'sifat_kontak', 'tanggal_kelayakan', 'layak', 'bukti_integritas', 'tanggal_ba', 'alamat_site', 'sk_auditor', 'form_rapat',
                  'jumlah_pekerja', 'kelas_risiko', 'mandays_dasar', 'jumlah_auditor', 'penyesuaian', 'kesimpulan'] as $k) {
            $v = $masuk[$k] ?? null;
            $p[$k] = $v === null ? '' : trim((string) $v);
        }

        $p['kelayakan'] = [];
        foreach (array_keys(SmkpTahap::indikatorKelayakan()) as $k) {
            $p['kelayakan'][$k] = trim((string) ($masuk['kelayakan'][$k] ?? ''));
        }

        // Kotak yang tidak dicentang disimpan sebagai "tidak", bukan kosong.
        $p['faktor'] = [];
        foreach (array_keys(SmkpTahap::faktorPenyesuaian()) as $k) {
            $v = $masuk['faktor'][$k] ?? false;
            $p['faktor'][$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }

        $p['persiapan'] = $this->persiapan(['persiapan' => $d['persiapan'] ?? []]);

        $kinerja = [];
        foreach (array_keys(SmkpTahap::butirKinerja()) as $k) {
            $kinerja[$k] = trim((string) ($d['kinerja'][$k] ?? ''));
        }

        $kecukupan = [];
        foreach (Smkp::elemen() as $e) {
            $baris = $d['kecukupan'][$e['kode']] ?? [];
            if (empty($baris['status'])) continue;
            $kecukupan[$e['kode']] = [
                'status' => $baris['status'],
                'ket'    => mb_substr(trim((string) ($baris['ket'] ?? '')), 0, 1000),
            ];
        }

        $smkp->update(['permulaan' => $p, 'kinerja' => $kinerja, 'kecukupan' => $kecukupan]);
        if ($smkp->status === 'draft') $smkp->update(['status' => 'berjalan']);

        return redirect()->route('smkp.tahap1', $smkp)->with('ok', 'Hasil Tahap I tersimpan.');
    }

    /** Berita Acara Hasil Pelaksanaan Tahapan Awal — siap cetak. */
    public function beritaAcara(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $kc = (array) ($smkp->kecukupan ?? []);

        $p = (array) ($smkp->permulaan ?? []);
        $r = (array) ($smkp->rencana ?? []);
        $audit = $this->ringkasAudit($smkp);
        $kop = fn (string $k) => KopDokumen::untuk($k, $smkp->company)['nomor'];
        $isi = fn ($rows) => array_values(array_filter((array) $rows, fn ($x) => trim((string) (((array) $x)['kegiatan'] ?? '').(((array) $x)['risiko'] ?? '').(((array) $x)['nilai'] ?? '')) !== ''));

        return Inertia::render('Print/Smkp/BeritaAcara', [
            'audit'     => $audit,
            'permulaan' => $p,
            // Periode yang diaudit (mis. 1 Januari – 31 Desember 2025), bukan tanggal pelaksanaan.
            'periode'   => trim((string) ($p['periode'] ?? '')) ?: $audit['periode'],
            'persiapan' => $this->persiapan($p),
            'tren'      => SmkpTahap::trenData(),
            'auditLalu' => SmkpTahap::auditLalu(),
            'alamatSite' => trim((string) ($p['alamat_site'] ?? '')) ?: $this->alamatSiteBawaan($smkp),
            'buktiIntegritas' => trim((string) ($p['bukti_integritas'] ?? '')) ?: $this->buktiIntegritasBawaan($smkp),
            'risiko'    => ['present' => $isi($smkp->risiko['present'] ?? []), 'future' => $isi($smkp->risiko['future'] ?? [])],
            // Nomor formulir pada III.C Penyiapan Dokumen Kerja.
            'nomorForm' => [
                'rencana'         => trim((string) ($r['nomor'] ?? '')) ?: $kop('rencana-audit'),
                'kesesuaian'      => $kop('formulir-kesesuaian'),
                'ketidaksesuaian' => $kop('formulir-ketidaksesuaian'),
                'rekapitulasi'    => $kop('rekapitulasi-ketidaksesuaian'),
                'tindakLanjut'    => $kop('rencana-tindak-lanjut'),
                'rekomendasi'     => $kop('rekomendasi-ofi'),
                'rapat'           => trim((string) ($p['form_rapat'] ?? '')) ?: $kop('daftar-hadir'),
            ],
            'kinerjaIsi'=> (array) ($smkp->kinerja ?? []),
            'tim'       => $smkp->tim(),
            'elemen'    => array_map(fn ($e) => [
                'kode' => $e['kode'], 'nama' => $e['nama'],
                'status' => $kc[$e['kode']]['status'] ?? null,
                'label' => SmkpTahap::labelKecukupan($kc[$e['kode']]['status'] ?? null),
                'ket' => $kc[$e['kode']]['ket'] ?? '',
            ], Smkp::elemen()),
            'kelayakan' => SmkpTahap::indikatorKelayakan(),
            'faktor'    => SmkpTahap::faktorPenyesuaian(),
            'kinerja'   => SmkpTahap::butirKinerja(),
            'mandays'   => $smkp->mandays(),
            'rekap'     => $smkp->rekapKecukupan(),
            'pihak'     => $this->pihakBeritaAcara($smkp),
            'ttd'       => $this->isianTtd($request, $smkp),
            'dok'       => KopDokumen::cetak('berita-acara', $smkp->company),
            'kembali'   => route('smkp.tahap1', $smkp),
        ]);
    }

    /* ================= RENCANA AUDIT ================= */

    public function rencana(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $r  = (array) ($smkp->rencana ?? []);
        $rk = (array) ($smkp->risiko ?? []);

        $pengesahan = [];
        foreach (SmkpTahap::pengesah($smkp->company?->kategori) as $k => $def) {
            $o = (array) ($r['pengesahan'][$k] ?? []);
            $pengesahan[$k] = [
                'nama'    => (string) ($o['nama'] ?? ''),
                'jabatan' => (string) (($o['jabatan'] ?? '') ?: $def['peran']),
                'tanggal' => (string) ($o['tanggal'] ?? ''),
            ];
        }

        return Inertia::render('Smkp/Rencana', [
            'audit' => $this->ringkasAudit($smkp),
            'isian' => [
                'nomor'           => (string) ($r['nomor'] ?? ''),
                'jenis_audit'     => (string) ($r['jenis_audit'] ?? SmkpTahap::JENIS_AUDIT[0]),
                'alamat'          => (string) ($r['alamat'] ?? ($smkp->company?->address ?: $smkp->company?->location ?: '')),
                'tujuan'          => (string) ($r['tujuan'] ?? ''),
                'kriteria'        => (string) (($r['kriteria'] ?? '') ?: SmkpTahap::KRITERIA_AUDIT),
                'pengecualian'    => (string) ($r['pengecualian'] ?? ''),
                'dokumentasi'     => (string) ($r['dokumentasi'] ?? SmkpTahap::dokumentasiBawaan()),
                'distribusi'      => (string) ($r['distribusi'] ?? SmkpTahap::distribusiBawaan($smkp->company?->kategori)),
                'metode_sampel'   => (object) ((array) ($r['metode_sampel'] ?? [])),
                'ruang_lingkup'   => (string) ($r['ruang_lingkup'] ?? ''),
                'tanggal_mulai'   => (string) ($r['tanggal_mulai'] ?? ''),
                'tanggal_selesai' => (string) ($r['tanggal_selesai'] ?? ''),
                'sumberdaya'      => (string) ($r['sumberdaya'] ?? SmkpTahap::fasilitasBawaan()),
                'metode'          => (string) ($r['metode'] ?? ''),
                'sampel'          => (string) ($r['sampel'] ?? ''),
                'susunan'         => array_values((array) ($r['susunan'] ?? [])),
                'tugas'           => array_values((array) ($r['tugas'] ?? [])),
                'pengesahan'      => $pengesahan,
                'risiko'          => [
                    'present' => array_values((array) ($rk['present'] ?? [])),
                    'future'  => array_values((array) ($rk['future'] ?? [])),
                ],
            ],
            'komponen'    => SmkpTahap::komponenRencana(),
            'jenisAudit'  => SmkpTahap::JENIS_AUDIT,
            'kriteriaDaftar' => $this->daftarSubElemen(),
            'pengesah'    => SmkpTahap::pengesah($smkp->company?->kategori),
            'kegiatan'    => SmkpTahap::kegiatanLapangan(),
            'rekap'       => $smkp->rekapRencana(),
            'mandays'     => $smkp->mandays(),
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    public function simpanRencana(Request $request, SmkpAudit $smkp)
    {
        $kunci = [];
        foreach (['present', 'future'] as $jenis) {
            foreach (array_keys((array) $request->input("risiko.{$jenis}", [])) as $i) $kunci[] = "risiko.{$jenis}.{$i}.nilai";
        }
        $this->desimalKoma($request, $kunci);

        $d = $request->validate([
            'nomor'              => ['nullable', 'string', 'max:100'],
            'jenis_audit'        => ['nullable', 'string', 'max:300'],
            'alamat'             => ['nullable', 'string', 'max:500'],
            'pengecualian'       => ['nullable', 'string', 'max:3000'],
            'dokumentasi'        => ['nullable', 'string', 'max:3000'],
            'distribusi'         => ['nullable', 'string', 'max:3000'],
            'metode_sampel'           => ['nullable', 'array', 'max:200'],
            'metode_sampel.*.metode'  => ['nullable', 'string', 'max:300'],
            'metode_sampel.*.sampel'  => ['nullable', 'string', 'max:300'],
            'tujuan'             => ['nullable', 'string', 'max:3000'],
            'kriteria'           => ['nullable', 'string', 'max:3000'],
            'ruang_lingkup'      => ['nullable', 'string', 'max:3000'],
            'tanggal_mulai'      => ['nullable', 'date'],
            'tanggal_selesai'    => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'sumberdaya'         => ['nullable', 'string', 'max:3000'],
            'metode'             => ['nullable', 'string', 'max:3000'],
            'sampel'             => ['nullable', 'string', 'max:3000'],

            'susunan'            => ['nullable', 'array', 'max:60'],
            'susunan.*.tanggal'  => ['nullable', 'date'],
            'susunan.*.waktu'    => ['nullable', 'string', 'max:50'],
            'susunan.*.kegiatan' => ['nullable', 'string', 'max:300'],
            'susunan.*.auditi'   => ['nullable', 'string', 'max:200'],
            'susunan.*.auditor'  => ['nullable', 'string', 'max:200'],

            'tugas'              => ['nullable', 'array', 'max:30'],
            'tugas.*.nama'       => ['nullable', 'string', 'max:150'],
            'tugas.*.peran'      => ['nullable', 'string', 'max:100'],
            'tugas.*.registrasi' => ['nullable', 'string', 'max:100'],
            'tugas.*.lingkup'    => ['nullable', 'string', 'max:300'],

            'pengesahan'           => ['nullable', 'array'],
            'pengesahan.*.nama'    => ['nullable', 'string', 'max:150'],
            'pengesahan.*.jabatan' => ['nullable', 'string', 'max:150'],
            'pengesahan.*.tanggal' => ['nullable', 'date'],

            'risiko'              => ['nullable', 'array'],
            'risiko.present'      => ['nullable', 'array', 'max:20'],
            'risiko.future'       => ['nullable', 'array', 'max:20'],
            'risiko.*.*.kegiatan' => ['nullable', 'string', 'max:200'],
            'risiko.*.*.risiko'   => ['nullable', 'string', 'max:200'],
            'risiko.*.*.nilai'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $kolom = fn (array $rows, array $kunci) => array_map(
            fn ($r) => array_map(fn ($k) => trim((string) (((array) $r)[$k] ?? '')), array_combine($kunci, $kunci)),
            $rows
        );

        $pengesahan = [];
        foreach (array_keys(SmkpTahap::pengesah()) as $k) {
            $o = (array) ($d['pengesahan'][$k] ?? []);
            $pengesahan[$k] = [
                'nama'    => trim((string) ($o['nama'] ?? '')),
                'jabatan' => trim((string) ($o['jabatan'] ?? '')),
                'tanggal' => trim((string) ($o['tanggal'] ?? '')),
            ];
        }

        // Metode & sampel per sub-elemen: hanya kode kriteria yang dikenal, baris kosong dibuang.
        $kodeSah = array_column(array_merge(...array_column($this->daftarSubElemen(), 'sub')), 'kode');
        $metodeSampel = [];
        foreach ((array) ($d['metode_sampel'] ?? []) as $kode => $v) {
            $m = trim((string) (((array) $v)['metode'] ?? ''));
            $sp = trim((string) (((array) $v)['sampel'] ?? ''));
            if (in_array((string) $kode, $kodeSah, true) && ($m !== '' || $sp !== '')) {
                $metodeSampel[(string) $kode] = ['metode' => $m, 'sampel' => $sp];
            }
        }

        $rencana = [
            'nomor'           => trim((string) ($d['nomor'] ?? '')),
            'jenis_audit'     => trim((string) ($d['jenis_audit'] ?? '')),
            'alamat'          => trim((string) ($d['alamat'] ?? '')),
            'pengecualian'    => trim((string) ($d['pengecualian'] ?? '')),
            'dokumentasi'     => trim((string) ($d['dokumentasi'] ?? '')),
            'distribusi'      => trim((string) ($d['distribusi'] ?? '')),
            'metode_sampel'   => $metodeSampel,
            'tujuan'          => trim((string) ($d['tujuan'] ?? '')),
            'kriteria'        => trim((string) ($d['kriteria'] ?? '')),
            'ruang_lingkup'   => trim((string) ($d['ruang_lingkup'] ?? '')),
            'tanggal_mulai'   => $d['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $d['tanggal_selesai'] ?? null,
            'susunan'         => $this->baris($kolom((array) ($d['susunan'] ?? []), ['tanggal', 'waktu', 'kegiatan', 'auditi', 'auditor'])),
            'tugas'           => $this->baris($kolom((array) ($d['tugas'] ?? []), ['nama', 'peran', 'registrasi', 'lingkup'])),
            'sumberdaya'      => trim((string) ($d['sumberdaya'] ?? '')),
            'metode'          => trim((string) ($d['metode'] ?? '')),
            'sampel'          => trim((string) ($d['sampel'] ?? '')),
            'pengesahan'      => $pengesahan,
        ];

        $risiko = [
            'present' => $this->baris($kolom((array) ($d['risiko']['present'] ?? []), ['kegiatan', 'risiko', 'nilai'])),
            'future'  => $this->baris($kolom((array) ($d['risiko']['future'] ?? []), ['kegiatan', 'risiko', 'nilai'])),
        ];

        $smkp->update(['rencana' => $rencana, 'risiko' => $risiko]);
        if ($smkp->status === 'draft') $smkp->update(['status' => 'berjalan']);

        $rekap = $smkp->rekapRencana();
        $pesan = $rekap['lengkap']
            ? 'Rencana Audit lengkap — sembilan komponen terpenuhi.'
            : 'Rencana Audit tersimpan. Belum lengkap: '.implode(', ', $rekap['kurang']).'.';

        return redirect()->route('smkp.rencana', $smkp)->with('ok', $pesan);
    }

    /** Laporan Rencana Audit — sembilan komponen wajib, siap cetak. */
    public function rencanaCetak(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $r = (array) ($smkp->rencana ?? []);

        // Nama/jabatan diisi saat menyusun Rencana Audit; tanda tangan &
        // tanggalnya sendiri memakai mekanisme digital yang sama dengan
        // formulir lain (diisi langsung di halaman cetak ini).
        $pihak = [];
        foreach (SmkpTahap::pengesah($smkp->company?->kategori) as $k => $def) {
            $o = (array) ($r['pengesahan'][$k] ?? []);
            if (!$def['wajib'] && trim((string) ($o['nama'] ?? '')) === '') continue;
            $pihak[] = ['peran' => (($o['jabatan'] ?? '') ?: $def['peran']), 'kunci' => $k, 'nama' => (string) ($o['nama'] ?? '')];
        }

        return Inertia::render('Print/Smkp/RencanaAudit', [
            'audit'    => $this->ringkasAudit($smkp),
            'rencana'  => $r,
            'bawaan'   => [
                'jenis_audit' => SmkpTahap::JENIS_AUDIT[0],
                'kriteria'    => SmkpTahap::KRITERIA_AUDIT,
                'dokumentasi' => SmkpTahap::dokumentasiBawaan(),
                'sumberdaya'  => SmkpTahap::fasilitasBawaan(),
                'distribusi'  => SmkpTahap::distribusiBawaan($smkp->company?->kategori),
            ],
            'kriteriaDaftar' => $this->daftarSubElemen(),
            'jasa'     => $smkp->company?->kategori === 'pjp',
            'risiko'   => (array) ($smkp->risiko ?? []),
            'komponen' => SmkpTahap::komponenRencana(),
            'kegiatan' => SmkpTahap::kegiatanLapangan(),
            'rekap'    => $smkp->rekapRencana(),
            'mandays'  => $smkp->mandays(),
            'pihak'    => $pihak,
            'ttd'      => $this->isianTtd($request, $smkp),
            'dok'      => KopDokumen::cetak('rencana-audit', $smkp->company),
            'kembali'  => route('smkp.rencana', $smkp),
        ]);
    }

    /* ================= TAHAP II — rapat & daftar hadir ================= */

    public function rapat(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $hadir = $smkp->attendees()->orderBy('id')->get()->groupBy('rapat');

        $rapat = [];
        foreach (SmkpTahap::rapat() as $k => $label) {
            $rapat[] = [
                'kunci'   => $k,
                'label'   => $label,
                'cetak'   => route('smkp.hadir.cetak', [$smkp, $k]),
                'peserta' => ($hadir[$k] ?? collect())->map(fn (SmkpAttendee $h) => [
                    'id' => $h->id, 'nama' => $h->nama, 'jabatan' => $h->jabatan, 'perusahaan' => $h->perusahaan,
                ])->values(),
            ];
        }

        return Inertia::render('Smkp/Rapat', [
            'audit'       => $this->ringkasAudit($smkp),
            'rapat'       => $rapat,
            'siap'        => $smkp->siapTahapDua(),
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    public function simpanHadir(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'rapat'      => ['required', Rule::in(array_keys(SmkpTahap::rapat()))],
            'nama'       => ['required', 'string', 'max:150'],
            'jabatan'    => ['nullable', 'string', 'max:150'],
            'perusahaan' => ['nullable', 'string', 'max:150'],
        ]);

        $smkp->attendees()->create($d);
        ActivityLog::write('Tambah peserta rapat', $d['nama'].' · '.SmkpTahap::labelRapat($d['rapat']).' · '.$smkp->namaTampil(), 'smkp');

        // Rapat pembukaan menandai audit lapangan benar-benar dimulai — tetapi
        // hanya bila Tahap I tuntas, sama dengan syarat pada ubahTahap().
        if ($d['rapat'] === 'pembukaan' && $smkp->tahap < SmkpTahap::LAPANGAN && $smkp->siapTahapDua()) {
            $smkp->update(['tahap' => SmkpTahap::LAPANGAN]);
        }

        return back()->with('ok', 'Peserta '.SmkpTahap::labelRapat($d['rapat']).' ditambahkan.');
    }

    public function hapusHadir(SmkpAudit $smkp, SmkpAttendee $hadir)
    {
        abort_if($hadir->audit_id !== $smkp->id, 404);
        $hadir->delete();
        ActivityLog::write('Hapus peserta rapat', $hadir->nama.' · '.SmkpTahap::labelRapat($hadir->rapat).' · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Peserta dihapus.');
    }

    /** Daftar hadir siap cetak, satu berkas per rapat. */
    public function daftarHadir(SmkpAudit $smkp, string $rapat)
    {
        abort_if(!array_key_exists($rapat, SmkpTahap::rapat()), 404, 'Rapat tidak dikenal.');
        $smkp->load('company');

        return Inertia::render('Print/Smkp/DaftarHadir', [
            'audit'   => $this->ringkasAudit($smkp),
            'judul'   => SmkpTahap::labelRapat($rapat),
            'hadir'   => $smkp->hadir($rapat)->map(fn ($h) => [
                'id' => $h->id, 'nama' => $h->nama, 'jabatan' => $h->jabatan, 'perusahaan' => $h->perusahaan,
            ])->values(),
            'dok'     => KopDokumen::cetak('daftar-hadir', $smkp->company),
            'kembali' => route('smkp.rapat', $smkp),
        ]);
    }

    /** Naikkan/turunkan tahap audit; Tahap I harus tuntas sebelum lapangan dibuka. */
    public function ubahTahap(Request $request, SmkpAudit $smkp)
    {
        $tahap = (int) $request->validate([
            'tahap' => ['required', 'integer', Rule::in(array_keys(SmkpTahap::tahap()))],
        ])['tahap'];

        if ($tahap >= SmkpTahap::LAPANGAN && !$smkp->siapTahapDua()) {
            $kurang = $smkp->rekapRencana()['kurang'];
            $sisa   = $smkp->rekapKecukupan()['belum'];

            return back()->withErrors(['tahap' => trim(
                'Tahap I belum tuntas. '
                .($sisa ? "{$sisa} elemen belum ditinjau kecukupan dokumentasinya. " : '')
                .($kurang ? 'Rencana Audit kurang: '.implode(', ', $kurang).'.' : '')
            )]);
        }

        // Status Selesai melekat pada tahap Pelaporan: masuk menutup audit,
        // keluar membukanya kembali.
        $status = $tahap === SmkpTahap::PELAPORAN ? 'selesai' : ($smkp->status === 'selesai' ? 'berjalan' : $smkp->status);
        // Hari kerja dibekukan saat masuk Pelaporan, dihitung ulang bila dibuka kembali.
        $tetap = $tahap === SmkpTahap::PELAPORAN
            ? ($smkp->mandays_tetap ?: SmkpTahap::mandays((array) ($smkp->permulaan ?? [])))
            : null;
        $smkp->forceFill(['tahap' => $tahap, 'status' => $status, 'mandays_tetap' => $tetap])->save();

        ActivityLog::write('Ubah tahap audit SMKP', $smkp->namaTampil().' → '.SmkpTahap::labelTahap($tahap), 'smkp');

        return back()->with('ok', 'Audit berpindah ke '.SmkpTahap::labelTahap($tahap).'.');
    }

    /* ---------- Formulir penilaian per elemen ---------- */
    public function nilai(Request $request, SmkpAudit $smkp, string $elemen)
    {
        $ref = collect(Smkp::elemen())->firstWhere('kode', $elemen);
        abort_if(!$ref, 404, 'Elemen tidak dikenal.');
        $smkp->load('company');

        $hasil = $smkp->hasil ?? [];
        $ulang = Smkp::perluDinilaiUlang($hasil);
        // Dokumen & sampel yang diminta Rencana Audit, dan berkas bukti yang sudah diunggah.
        $diminta = BuktiAuditController::diminta($smkp);
        $kodeElemen = array_column(array_merge(...array_map(fn ($s) => Smkp::butirSub($s), $ref['sub'])), 'kode');
        $berkas = BuktiAuditController::perButir($smkp, $request->user(), $kodeElemen);
        $sub = [];
        foreach ($ref['sub'] as $s) {
            $r = Smkp::rekapSub($s, $hasil);
            $butir = [];
            foreach (Smkp::butirSub($s) as $b) {
                $v = Smkp::nilaiButir($hasil, $b['kode']);
                $butir[] = [
                    'kode'   => $b['kode'],
                    'nama'   => $b['nama'],
                    'maks'   => (int) $b['maks'],
                    'v'      => $v === null ? '' : ($v === Smkp::NA ? Smkp::NA : (string) (int) $v),
                    'ket'    => (string) ($hasil[$b['kode']]['ket'] ?? ''),
                    'bukti'  => (string) ($hasil[$b['kode']]['bukti'] ?? ''),
                    // Dinilai pada skala lama / di atas maksimum Kepdirjen: perlu dinilai ulang.
                    'ulang'  => $ulang[$b['kode']] ?? null,
                    'rubrik' => Smkp::rubrik($b['kode']),
                    'berkas' => $berkas[$b['kode']] ?? [],
                ];
            }
            $sub[] = [
                'kode' => $s['kode'], 'nama' => $s['nama'], 'ref' => $s['ref'] ?? null,
                'rinci' => !empty($s['subsub']),
                'nilai' => $r['nilai'], 'maks' => $r['maks'], 'dinilai' => $r['dinilai'], 'berlaku' => $r['berlaku'],
                'kategori' => $r['kategori'], 'butir' => $butir,
                'diminta' => $diminta[$s['kode']] ?? null,
            ];
        }

        $rekap = Smkp::rekapElemen($ref, $hasil);

        return Inertia::render('Smkp/Nilai', [
            'audit'   => $this->ringkasAudit($smkp),
            'elemen'  => ['kode' => $ref['kode'], 'nama' => $ref['nama'], 'bobot' => $ref['bobot']],
            'sub'     => $sub,
            'rekap'   => array_diff_key($rekap, ['sub' => 1]),
            'semua'   => array_map(function ($e) use ($hasil, $smkp, $ulang) {
                $r = Smkp::rekapElemen($e, $hasil);
                return ['kode' => $e['kode'], 'nama' => $e['nama'], 'dinilai' => $r['dinilai'], 'berlaku' => $r['berlaku'],
                        'ulang' => count(array_filter($ulang, fn ($x) => $x['elemen'] === $e['kode'])),
                        'url' => route('smkp.nilai', [$smkp, $e['kode']])];
            }, Smkp::elemen()),
            'kategori'    => Smkp::kategori(),
            'na'          => Smkp::NA,
            'bisaSunting' => $request->user()->bisaAudit(),
            // Sub-elemen yang dibuka lebih dulu (tautan dari halaman Bukti Audit).
            'pilihSub'    => in_array($request->query('sub'), array_column($ref['sub'], 'kode'), true) ? $request->query('sub') : null,
            'bukti'       => [
                'bisaUnggah' => BuktiAuditController::bisaUnggah($request->user(), $smkp),
                'batas'      => BuktiAuditController::batas(),
                'adaRencana' => (bool) $diminta,
            ],
        ]);
    }

    /** Simpan penilaian satu elemen; kriteria di elemen lain tidak tersentuh. */
    public function simpanNilai(Request $request, SmkpAudit $smkp, string $elemen)
    {
        $ref = collect(Smkp::elemen())->firstWhere('kode', $elemen);
        abort_if(!$ref, 404, 'Elemen tidak dikenal.');

        $request->validate([
            'k'         => ['array'],
            'k.*.ket'   => ['nullable', 'string', 'max:2000'],
            'k.*.bukti' => ['nullable', 'string', 'max:500'],
        ]);

        $masuk = (array) $request->input('k', []);

        // Baca-ubah-tulis dalam satu transaksi dengan isi terbaru: elemen lain
        // (atau butir lain) yang baru disimpan rekan tidak tertimpa salinan
        // yang dimuat permintaan ini di awal. Bentrok dengan penulis lain
        // diulang otomatis.
        DB::transaction(function () use ($smkp, $ref, $masuk) {
            $segar = SmkpAudit::withoutGlobalScopes()->whereKey($smkp->id)->lockForUpdate()->firstOrFail();
            $hasil = $lama = (array) ($segar->hasil ?? []);

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

                    $isi = [
                        'ket'   => mb_substr(trim((string) ($baris['ket'] ?? '')), 0, 2000),
                        'bukti' => mb_substr(trim((string) ($baris['bukti'] ?? '')), 0, 500),
                    ];

                    // Nilai yang perlu dinilai ulang (skala lama / di atas maksimum /
                    // diberikan sebelum deskripsi tingkatnya dikoreksi)
                    // dan dikirim kembali apa adanya — mis. hanya catatannya yang
                    // diubah — tetap tersimpan beserta penandanya. Hanya pilihan
                    // nilai oleh auditor ('ulang') yang menggantinya.
                    $sebelum = is_array($lama[$kode] ?? null) ? $lama[$kode] : [];
                    $usang = isset(Smkp::perluDinilaiUlang([$kode => $sebelum])[$kode]);
                    if ($usang && empty($baris['ulang']) && is_numeric($v) && (float) $v === (float) $sebelum['v']) {
                        $hasil[$kode] = array_intersect_key($sebelum, ['v' => 1, 'maks_lama' => 1, 'koreksi' => 1]) + $isi;
                        continue;
                    }

                    if (is_string($v) && strcasecmp($v, Smkp::NA) === 0) {
                        $nilai = Smkp::NA;                    // di luar lingkup perusahaan
                    } elseif (is_numeric($v)) {
                        // Dijepit ke rentang butir: kiriman langsung tidak boleh
                        // menaikkan capaian melebihi maksimum.
                        $nilai = max(0, min((int) $v, (int) $b['maks']));
                    } else {
                        continue;                             // abaikan masukan asing
                    }

                    $hasil[$kode] = ['v' => $nilai] + $isi;
                }
            }

            $segar->hasil = $hasil;
            if ($segar->status === 'draft') $segar->status = 'berjalan';
            $segar->save();

            $this->selaraskanJenisTemuan($segar, $ref);
        }, 3);

        ActivityLog::write('Nilai elemen SMKP', $smkp->namaTampil().' · elemen '.$ref['kode'], 'smkp');

        return redirect()->route('smkp.nilai', [$smkp, $elemen])
            ->with('ok', 'Penilaian elemen '.$ref['kode'].' tersimpan.');
    }

    /* ---------- Temuan / CAR ---------- */
    public function temuan(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');

        return Inertia::render('Smkp/Temuan', [
            'audit'  => $this->ringkasAudit($smkp),
            'temuan' => $smkp->findings()->with('tindakLanjut.pengirim:id,name')->get()->sort([SmkpFinding::class, 'banding'])->values()->map(fn (SmkpFinding $t, int $i) => [
                'id'               => $t->id,
                'nomor'            => (string) $t->nomor,
                'nomorTampil'      => $t->nomorTampil($i + 1),
                'kode'             => $t->kode_kriteria,
                'jenis'            => $t->jenis,
                'uraian'           => $t->uraian,
                'lokasi'           => (string) $t->lokasi,
                'divisi'           => (string) $t->divisi,
                'departemen'       => (string) $t->departemen,
                'bukti'            => (string) $t->bukti,
                'deskripsi'        => (string) $t->deskripsi,
                'deskripsiOtomatis'=> Smkp::deskripsiTemuan((string) $t->kode_kriteria, $t->uraian, $t->bukti, $t->lokasi),
                'acuan'            => Smkp::acuanTemuan((string) $t->kode_kriteria),
                'namaKriteria'     => Smkp::namaKriteria()[trim((string) $t->kode_kriteria)] ?? '',
                'akar_masalah'     => (string) $t->akar_masalah,
                'tindakan'         => (string) $t->tindakan,
                'bukti_perbaikan'  => (string) $t->bukti_perbaikan,
                'penanggung_jawab' => (string) $t->penanggung_jawab,
                'target_selesai'   => $t->target_selesai?->format('Y-m-d') ?? '',
                'tanggal_selesai'  => $t->tanggal_selesai?->format('Y-m-d') ?? '',
                'status'           => $t->status,
                'verifikasi'       => (string) $t->verifikasi,
                'terlambat'        => $t->terlambat(),
                'kiriman'          => $t->tindakLanjut->map(fn (SmkpTindakLanjut $k) => [
                    'id'       => $k->id,
                    'oleh'     => $k->pengirim?->name ?? 'Akun dihapus',
                    'waktu'    => $k->created_at?->translatedFormat('d M Y · H:i'),
                    'akar_masalah' => (string) $k->akar_masalah,
                    'tindakan' => $k->tindakan,
                    'bukti_perbaikan' => (string) $k->bukti_perbaikan,
                    'penanggung_jawab' => (string) $k->penanggung_jawab,
                    'lampiran' => collect($k->lampiran ?? [])->values()->map(fn (array $l, int $i) => [
                        'nama'   => (string) ($l['nama'] ?? 'lampiran'),
                        'ukuran' => (int) ($l['ukuran'] ?? 0),
                        'gambar' => str_starts_with((string) ($l['mime'] ?? ''), 'image/'),
                        'url'    => route('smkp.tindak-lanjut.unduh', [$smkp, $t, $k->id, $i]),
                    ])->all(),
                    'bisaHapus' => $request->user()->bisaAudit() || ($k->user_id === $request->user()->id && $t->status !== 'Closed'),
                ])->values()->all(),
            ])->values(),
            'kategoriPilihan' => SmkpFinding::KATEGORI,
            'usulan'      => $this->usulanTemuan($smkp),
            'statusPilihan' => SmkpFinding::STATUS,
            'bisaSunting' => $request->user()->bisaAudit(),
            'bisaTindakLanjut' => $request->user()->can('tindak-lanjut'),
            'buka'        => $request->integer('buka') ?: null,
            'batasBerkas' => ['jumlah' => TindakLanjutController::MAKS_BERKAS, 'kb' => TindakLanjutController::MAKS_KB, 'ekstensi' => TindakLanjutController::EKSTENSI],
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
        // Satu transaksi dengan isi terbaru: klik ganda atau dua auditor yang
        // mengangkat bersamaan tidak menggandakan CAR maupun nomornya. Nomor
        // berjalan per kategori (AIC-MYR-01-2025 …, AIC-MNR-01-2025 …) mengikuti
        // urutan usulan: Mayor lalu Minor, urut sub-elemen/sub-sub-elemen.
        $n = DB::transaction(function () use ($smkp) {
            $segar = SmkpAudit::withoutGlobalScopes()->with('company')->whereKey($smkp->id)->lockForUpdate()->firstOrFail();
            $ada = $segar->findings()->pluck('nomor');
            $terpakai = $ada->filter()->mapWithKeys(fn ($x) => [mb_strtolower(trim($x)) => true])->all();
            $urut = [];
            $n = 0;
            foreach ($this->usulanTemuan($segar) as $t) {
                $j = $t['jenis'];
                $urut[$j] ??= (int) $ada->map(fn ($x) => Smkp::urutanNomor((string) $x, $j))->max();
                do { $nomor = $segar->nomorTemuanBaru($j, ++$urut[$j]); } while (isset($terpakai[mb_strtolower($nomor)]));
                $terpakai[mb_strtolower($nomor)] = true;

                $segar->findings()->create([
                    'nomor'         => $nomor,
                    'kode_kriteria' => $t['kode'],
                    'jenis'         => $j,
                    'uraian'        => $t['uraian'],
                    'akar_masalah'  => $t['ket'] ?: null,
                    'status'        => 'Open',
                ]);
                $n++;
            }
            return $n;
        }, 3);

        if ($n) ActivityLog::write('Angkat temuan SMKP', $n.' temuan · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', $n ? "{$n} temuan diangkat menjadi tindakan perbaikan." : 'Tidak ada temuan baru.');
    }

    /**
     * Susun ulang nomor seluruh temuan audit: Mayor lalu Minor, masing-masing
     * urut sub-elemen/sub-sub-elemen, dengan format (kode perusahaan)-MYR-01-2025.
     * Nomor yang diketik manual ikut diganti.
     */
    public function nomorUlangTemuan(SmkpAudit $smkp)
    {
        $n = DB::transaction(function () use ($smkp) {
            $segar = SmkpAudit::withoutGlobalScopes()->with('company')->whereKey($smkp->id)->lockForUpdate()->firstOrFail();
            $daftar = $segar->findings()->get()->sort([SmkpFinding::class, 'banding'])->values();
            // Dua tahap agar indeks unik (audit, nomor) tidak bentrok saat nomor bertukar.
            foreach ($daftar as $f) $f->forceFill(['nomor' => "~{$f->id}"])->save();
            $urut = [];
            foreach ($daftar as $f) {
                $urut[$f->jenis] = ($urut[$f->jenis] ?? 0) + 1;
                $f->forceFill(['nomor' => $segar->nomorTemuanBaru((string) $f->jenis, $urut[$f->jenis])])->save();
            }
            return $daftar->count();
        }, 3);

        if ($n) ActivityLog::write('Nomor ulang temuan SMKP', $n.' temuan · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', $n ? "Nomor {$n} temuan disusun ulang." : 'Belum ada temuan.');
    }

    /**
     * CAR terbuka mengikuti kategori turunan nilai pada elemen yang baru
     * dinilai — termasuk sub-elemen berincian yang berpindah antara satu
     * temuan Mayor (berkode sub-elemen) dan temuan Minor per rincian.
     * Kategori Kritikal (ditetapkan auditor) dan temuan yang sudah ditutup
     * dibiarkan.
     */
    private function selaraskanJenisTemuan(SmkpAudit $smkp, array $ref): void
    {
        $awalan = $ref['kode'].'.';
        foreach (Smkp::temuan($smkp->hasil ?? []) as $t) {
            if (!str_starts_with($t['kode'], $awalan)) continue;

            $smkp->findings()->where('kode_kriteria', $t['kode'])->where('status', '<>', 'Closed')
                ->whereIn('jenis', ['mayor', 'minor'])->where('jenis', '<>', $t['jenis'])
                ->update(['jenis' => $t['jenis']]);
        }
    }

    public function simpanTemuan(Request $request, SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);

        // Tanggal penutupan (hanya bermakna bila Closed): tidak di masa depan
        // dan tidak sebelum audit dimulai. "Hari ini" menurut WIT — tanggal
        // paling akhir di Indonesia — agar auditor di WITA/WIT tidak ditolak
        // selepas tengah malam.
        $tutup = ['exclude_unless:status,Closed', 'nullable', 'date', 'before_or_equal:'.now('Asia/Jayapura')->toDateString()];
        if ($smkp->tanggal_mulai) $tutup[] = 'after_or_equal:'.$smkp->tanggal_mulai->toDateString();

        $d = $request->validate([
            'nomor'               => ['nullable', 'string', 'max:40',
                                      Rule::unique('smkp_findings', 'nomor')->where('audit_id', $smkp->id)->ignore($temuan->id)],
            'jenis'               => ['sometimes', 'required', Rule::in(array_keys(SmkpFinding::KATEGORI))],
            'uraian'              => ['sometimes', 'required', 'string', 'max:3000'],
            'lokasi'              => ['nullable', 'string', 'max:200'],
            'divisi'              => ['nullable', 'string', 'max:190'],
            'departemen'          => ['nullable', 'string', 'max:190'],
            'bukti'               => ['nullable', 'string', 'max:3000'],
            'deskripsi'           => ['nullable', 'string', 'max:3000'],
            'akar_masalah'        => ['nullable', 'string', 'max:3000'],
            'tindakan'            => ['nullable', 'string', 'max:3000'],
            'bukti_perbaikan'     => ['nullable', 'string', 'max:3000'],
            'penanggung_jawab'    => ['nullable', 'string', 'max:150'],
            'target_selesai'      => ['nullable', 'date'],
            'tanggal_selesai'     => $tutup,
            'status'              => ['required', Rule::in(SmkpFinding::STATUS)],
            'verifikasi'          => ['nullable', 'string', 'max:3000'],
        ], [
            'nomor.unique'                   => 'Nomor ini sudah dipakai temuan lain pada audit ini.',
            'tanggal_selesai.before_or_equal'=> 'Tanggal selesai tidak boleh melewati hari ini.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum audit dimulai ('.$smkp->tanggal_mulai?->translatedFormat('d F Y').').',
        ]);

        // Tanggal perbaikan hanya bermakna untuk temuan yang ditutup; bila
        // auditor tidak mengisinya, tanggal penutupan dipakai.
        if ($d['status'] === 'Closed') {
            $d['tanggal_selesai'] = ($d['tanggal_selesai'] ?? null)
                ?: ($temuan->status === 'Closed' ? $temuan->tanggal_selesai?->toDateString() : null)
                ?: now()->toDateString();
        } else {
            $d['tanggal_selesai'] = null;
        }

        // Nomor yang sudah ditetapkan tidak dapat dikosongkan: formulir yang
        // tercetak merujuk nomor itu.
        $d['nomor'] = trim((string) ($d['nomor'] ?? '')) ?: $temuan->nomor;

        $statusLama = $temuan->status;
        $temuan->update($d);

        ActivityLog::write('Ubah temuan SMKP', trim($temuan->nomor.' '.$temuan->kode_kriteria)
            .($statusLama !== $temuan->status ? " · {$statusLama} → {$temuan->status}" : '').' · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Tindakan perbaikan '.$temuan->kode_kriteria.' tersimpan.');
    }

    public function hapusTemuan(SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $temuan->delete();
        Storage::disk('local')->deleteDirectory(SmkpTindakLanjut::folder($smkp->id, $temuan->id));
        ActivityLog::write('Hapus temuan SMKP', trim($temuan->nomor.' '.$temuan->kode_kriteria).' · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Temuan dihapus.');
    }

    /* ---------- Laporan Audit (format Kepdirjen 185.K/37.04/DJB/2019) ---------- */

    /** Isian bagian laporan yang tidak dapat diturunkan dari data audit. */
    public function laporanIsian(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $audit = $this->ringkasAudit($smkp);
        $rekap = $smkp->rekap();

        return Inertia::render('Smkp/Laporan', [
            'audit'  => $audit,
            'isian'  => $this->isiLaporan((array) ($smkp->laporan ?? [])),
            'bawaan' => [
                'penerapan'  => $this->pengantarPenerapan($audit['perusahaan']['nama']),
                'distribusi' => implode("\n", SmkpTahap::DISTRIBUSI_LAPORAN),
            ],
            // Bagian laporan yang terisi otomatis dari data audit.
            'otomatis' => [
                'periode'    => trim((string) ($smkp->permulaan['periode'] ?? '')),
                'alamatSite' => trim((string) ($smkp->permulaan['alamat_site'] ?? '')) ?: $this->alamatSiteBawaan($smkp),
                'jumlahPekerja' => (string) ($smkp->permulaan['jumlah_pekerja'] ?? ''),
                'tim'        => count($this->timLaporan($smkp)),
                'praktik'    => count((array) ($smkp->kesesuaian ?? [])),
                'temuan'     => $smkp->findings()->count(),
                'dinilai'    => $rekap['dinilai'],
                'berlaku'    => $rekap['berlaku'],
            ],
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    public function simpanLaporan(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'status_perusahaan'        => ['nullable', 'string', 'max:150'],
            'sk_izin'                  => ['nullable', 'string', 'max:200'],
            'wiup'                     => ['nullable', 'string', 'max:100'],
            'legalitas'                => ['nullable', 'string', 'max:10000'],
            'kegiatan'                 => ['nullable', 'string', 'max:10000'],
            'penerapan'                => ['nullable', 'string', 'max:5000'],
            'satuan_produksi'          => ['nullable', 'string', 'max:60'],
            'produksi'                 => ['nullable', 'array', 'max:24'],
            'produksi.*.periode'       => ['nullable', 'string', 'max:60'],
            'produksi.*.jumlah'        => ['nullable', 'string', 'max:60'],
            'pekerja'                  => ['nullable', 'array', 'max:40'],
            'pekerja.*.jabatan'        => ['nullable', 'string', 'max:150'],
            'pekerja.*.jumlah'         => ['nullable', 'string', 'max:30'],
            'peralatan'                => ['nullable', 'array', 'max:80'],
            'peralatan.*.jenis'        => ['nullable', 'string', 'max:150'],
            'peralatan.*.tipe'         => ['nullable', 'string', 'max:150'],
            'peralatan.*.produktivitas'=> ['nullable', 'string', 'max:100'],
            'peralatan.*.jumlah'       => ['nullable', 'string', 'max:30'],
            'area'                     => ['nullable', 'array', 'max:40'],
            'area.*'                   => ['nullable', 'string', 'max:300'],
            'distribusi'               => ['nullable', 'string', 'max:3000'],
            'tempat'                   => ['nullable', 'string', 'max:100'],
            'tanggal'                  => ['nullable', 'date'],
        ]);

        $smkp->update(['laporan' => $this->isiLaporan($d)]);
        if ($smkp->status === 'draft') $smkp->update(['status' => 'berjalan']);

        return redirect()->route('smkp.laporan.isian', $smkp)->with('ok', 'Isian Laporan Audit tersimpan.');
    }

    /** Laporan Audit siap cetak — sistematika Kepdirjen 185.K/37.04/DJB/2019. */
    public function laporan(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $audit = $this->ringkasAudit($smkp);
        $pt = $audit['perusahaan']['nama'];
        $jasa = $smkp->company?->kategori === 'pjp';
        $p = (array) ($smkp->permulaan ?? []);
        $rekap = $smkp->rekap();
        $temuan = $smkp->findings()->get()->sort([SmkpFinding::class, 'banding'])->values();

        $elemen = [];
        foreach (Smkp::elemen() as $e) {
            $r = $rekap['elemen'][$e['kode']];
            $elemen[] = ['kode' => $e['kode'], 'nama' => $e['nama'], 'maks' => $r['maks'], 'nilai' => $r['nilai'], 'skor' => $r['skor']];
        }

        $pjo = trim((string) ($smkp->rencana['pengesahan']['pjo']['nama'] ?? '')) ?: (string) $smkp->company?->pjo;

        return Inertia::render('Print/Smkp/LaporanAudit', [
            'audit'     => $audit,
            'isian'     => $this->isiLaporan((array) ($smkp->laporan ?? [])),
            // Periode yang dinilai (Tahap I), bukan tanggal pelaksanaan audit.
            'periode'   => trim((string) ($p['periode'] ?? '')) ?: $audit['periode'],
            'alamatSite'=> trim((string) ($p['alamat_site'] ?? '')) ?: $this->alamatSiteBawaan($smkp),
            'jumlahPekerja' => (string) ($p['jumlah_pekerja'] ?? ''),
            'pengantar' => $this->pengantarPenerapan($pt),
            'distribusi'=> SmkpTahap::DISTRIBUSI_LAPORAN,
            'penerapan' => $this->penerapanElemen($smkp, $pt, $jasa),
            'praktik'   => $this->praktikTerbaik($smkp),
            'rekap'     => ['skor' => $rekap['skor'], 'maks' => $rekap['maks'], 'nilai' => $rekap['nilai'], 'dinilai' => $rekap['dinilai'], 'tingkat' => $rekap['tingkat']],
            'elemen'    => $elemen,
            // Kritikal, Mayor, lalu Minor — masing-masing urut sub-elemen/sub-sub-elemen acuan.
            'temuan'    => $temuan->map(fn (SmkpFinding $t, int $i) => [
                'nomor' => $t->nomorTampil($i + 1), 'kode' => (string) $t->kode_kriteria, 'jenis' => $t->jenis, 'label' => $t->labelKategori(),
                // Sama dengan Formulir Rekapitulasi Ketidaksesuaian: deskripsi lalu "Hal ini tidak sesuai dengan …".
                'uraian' => Smkp::uraianTemuan((string) $t->kode_kriteria, $t->deskripsi, $t->uraian),
            ]),
            'kategori'  => SmkpFinding::KATEGORI,
            // Dihitung dari temuan yang tercantum di laporan, bukan dari nilai —
            // keduanya bisa berbeda setelah auditor menetapkan kategori.
            'hitung'    => collect(array_keys(SmkpFinding::KATEGORI))->mapWithKeys(fn ($k) => [$k => $temuan->where('jenis', $k)->count()])->all(),
            'tim'       => $this->timLaporan($smkp),
            // Perusahaan jasa pertambangan: laporan turut ditandatangani PJO.
            'pihakPjo'  => $jasa ? [['peran' => 'Penanggung Jawab Operasional', 'kunci' => 'pjo', 'nama' => $pjo]] : [],
            'jasa'      => $jasa,
            'ttd'       => $this->isianTtd($request, $smkp),
            'dok'       => KopDokumen::cetak('laporan-audit', $smkp->company),
            'kembali'   => route('smkp.laporan.isian', $smkp),
        ]);
    }

    /* ---------- bantu ---------- */

    /** Perusahaan yang boleh dipilih pengguna ini untuk audit. */
    private function perusahaan(Request $request)
    {
        $j = $request->user()->jangkauan();

        return Company::query()
            ->when($j !== null, fn ($q) => $q->whereIn('id', $j ?: [0]))
            ->orderByRaw("CASE kategori WHEN 'induk' THEN 1 WHEN 'sister' THEN 2 WHEN 'pjp' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get();
    }

    /** Ringkasan audit yang dipakai seluruh halaman. */
    protected function ringkasAudit(SmkpAudit $a): array
    {
        $c = $a->company;

        return [
            'id'              => $a->id,
            'judul'           => $a->namaTampil(),
            'tahun'           => $a->tahun,
            'status'          => $a->status,
            'labelStatus'     => SmkpAudit::STATUS[$a->status] ?? $a->status,
            'tahap'           => (int) $a->tahap,
            'labelTahap'      => SmkpTahap::labelTahap((int) $a->tahap),
            'ketua'           => $a->ketua_auditor,
            'mulai'           => $a->tanggal_mulai?->translatedFormat('d F Y'),
            'selesai'         => $a->tanggal_selesai?->translatedFormat('d F Y'),
            'periode'         => $a->tanggal_mulai
                ? $a->tanggal_mulai->translatedFormat('d F Y').($a->tanggal_selesai ? ' – '.$a->tanggal_selesai->translatedFormat('d F Y') : '')
                : 'Tahun '.$a->tahun,
            'perusahaan'      => [
                'id'        => $c?->id,
                'nama'      => $c?->name ?? '—',
                'kategori'  => $c?->labelKategori(),
                'kodeKategori' => $c?->kategori,
                // Tahap I dapat menetapkan jenis perizinan & alamat untuk audit ini.
                'izin'      => trim((string) ($a->permulaan['izin'] ?? '')) ?: $c?->izin_type,
                'komoditas' => $c?->commodity,
                'alamat'    => trim((string) ($a->permulaan['alamat'] ?? '')) ?: ($c?->address ?: $c?->location),
                'ktt'       => $c?->ktt,
                'logo'      => $c?->logoUrl(),
            ],
        ];
    }

    /** Elemen, sub-elemen, dan sub-sub-elemen SMKP untuk pilihan formulir (kode + nama). */
    protected function daftarSubElemen(): array
    {
        return array_map(fn ($e) => [
            'kode' => $e['kode'], 'nama' => $e['nama'],
            'sub'  => array_map(fn ($s) => [
                'kode' => $s['kode'], 'nama' => $s['nama'],
                'subsub' => array_map(fn ($x) => ['kode' => $x['kode'], 'nama' => $x['nama']], $s['subsub'] ?? []),
            ], $e['sub']),
        ], Smkp::elemen());
    }

    /** Nama penanda tangan berkas: ketua tim dan KTT. */
    protected function penandaTangan(SmkpAudit $a): array
    {
        return [
            'ketua' => $a->ketua_auditor ?: ($a->rencana['pengesahan']['ketua']['nama'] ?? ''),
            'ktt'   => ($a->rencana['pengesahan']['ktt']['nama'] ?? '') ?: ($a->company?->ktt ?? ''),
        ];
    }

    /**
     * Penanda tangan Berita Acara Tahap I sesuai urutan formulir resmi: Ketua
     * Tim Audit, PJO (khusus audit perusahaan jasa pertambangan), lalu KTT.
     */
    protected function pihakBeritaAcara(SmkpAudit $a): array
    {
        $n = $this->penandaTangan($a);
        $pjo = trim((string) ($a->rencana['pengesahan']['pjo']['nama'] ?? ''));
        $jasa = $a->company?->kategori === 'pjp';

        return array_values(array_filter([
            ['peran' => 'Ketua Tim Audit', 'kunci' => 'ketua', 'nama' => $n['ketua']],
            $jasa || $pjo !== '' ? ['peran' => 'Penanggung Jawab Operasional dari Auditi', 'ket' => 'khusus untuk audit perusahaan jasa pertambangan', 'kunci' => 'pjo', 'nama' => $pjo ?: (string) $a->company?->pjo] : null,
            ['peran' => 'Kepala Teknik Tambang', 'kunci' => 'ktt', 'nama' => $n['ktt']],
        ]));
    }

    /**
     * Data persiapan audit lapangan (Berita Acara Tahap I bagian III.B):
     * data trend, unjuk kerja peralatan, key leading indicator, audit internal
     * tahun sebelumnya, dan masukan pemangku kepentingan — hanya kunci yang
     * dikenal, baris kosong dibuang.
     */
    protected function persiapan(array $p): array
    {
        $x = (array) ($p['persiapan'] ?? []);
        $daftar = fn ($v) => array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) $v), fn ($t) => $t !== ''));
        $kolom = fn ($rows, array $kunci) => $this->baris(array_map(
            fn ($b) => array_combine($kunci, array_map(fn ($k) => trim((string) (((array) $b)[$k] ?? '')), $kunci)),
            array_values((array) $rows)
        ));

        $tren = [];
        foreach (array_keys(SmkpTahap::trenData()) as $k) $tren[$k] = $daftar($x['tren'][$k] ?? []);
        $lalu = [];
        foreach (array_keys(SmkpTahap::auditLalu()) as $k) $lalu[$k] = trim((string) ($x['audit_lalu'][$k] ?? ''));

        return [
            'tren'       => $tren,
            'peralatan'  => $kolom($x['peralatan'] ?? [], ['jenis', 'pa', 'ma']),
            'kli'        => $kolom($x['kli'] ?? [], ['indikator', 'status']),
            'audit_lalu' => $lalu,
            // Masukan dikelompokkan per sumber (mis. "Masukan Audit Internal SMKP Periode 2025").
            'masukan'    => array_values(array_filter(array_map(fn ($m) => is_array($m)
                ? ['sumber' => trim((string) ($m['sumber'] ?? '')), 'isi' => trim((string) ($m['isi'] ?? ''))]
                : ['sumber' => '', 'isi' => trim((string) $m)], (array) ($x['masukan'] ?? [])), fn ($m) => $m['isi'] !== '')),
        ];
    }

    /** Alamat site office bawaan: lokasi perusahaan bila berbeda dari alamat kantornya. */
    protected function alamatSiteBawaan(SmkpAudit $a): string
    {
        $c = $a->company;

        return $c?->address && $c?->location && trim($c->location) !== trim($c->address) ? trim((string) $c->location) : '';
    }

    /**
     * Bukti komitmen integritas auditor bawaan: sertifikat teregistrasi
     * tiap anggota tim — "Nomor Register: … (Ketua Tim Audit), … (Auditor)".
     */
    protected function buktiIntegritasBawaan(SmkpAudit $a): string
    {
        $reg = array_values(array_filter(array_map(
            fn ($t) => trim((string) ($t['registrasi'] ?? '')) === '' ? null : trim((string) $t['registrasi']).' ('.(trim((string) ($t['peran'] ?? '')) ?: 'Auditor').')',
            $a->tim()
        )));
        if (!$reg) return 'Sertifikat yang teregistrasi Nomor Register: …… (Lead Auditor)';
        $akhir = array_pop($reg);

        return 'Sertifikat yang teregistrasi Nomor Register: '.($reg ? implode(', ', $reg).' dan ' : '').$akhir;
    }

    /** Usulan "Data Audit Internal SMKP" dari audit tahun sebelumnya yang tercatat di aplikasi ini. */
    protected function saranAuditLalu(SmkpAudit $a): ?array
    {
        $lalu = SmkpAudit::where('company_id', $a->company_id)->where('tahun', (int) $a->tahun - 1)->first();
        if (!$lalu) return null;

        $r = $lalu->rekap();
        $temuan = $lalu->findings()->get(['jenis', 'status']);
        $isi = ['nilai' => $r['dinilai'] > 0 ? number_format((float) $r['skor'], 2, ',', '.').' ('.($r['tingkat']['label'] ?? '').')' : ''];
        foreach (array_keys(SmkpFinding::KATEGORI) as $k) {
            $t = $temuan->where('jenis', $k);
            $isi[$k] = $t->isEmpty() ? 'Tidak ada ketidaksesuaian' : $t->where('status', 'Closed')->count().' dari '.$t->count().' ketidaksesuaian selesai (Closed)';
        }

        return ['tahun' => (int) $lalu->tahun, 'isi' => $isi];
    }

    /** Isian Laporan Audit, dirapikan: hanya kunci yang dikenal, baris kosong dibuang. */
    protected function isiLaporan(array $l): array
    {
        $teks = fn (string $k) => trim((string) ($l[$k] ?? ''));
        $kolom = fn ($rows, array $kunci) => $this->baris(array_map(
            fn ($b) => array_combine($kunci, array_map(fn ($k) => trim((string) (((array) $b)[$k] ?? '')), $kunci)),
            array_values((array) $rows)
        ));
        $tanggal = $teks('tanggal');

        return [
            'status_perusahaan' => $teks('status_perusahaan'),
            'sk_izin'           => $teks('sk_izin'),
            'wiup'              => $teks('wiup'),
            'legalitas'         => $teks('legalitas'),
            'kegiatan'          => $teks('kegiatan'),
            'penerapan'         => $teks('penerapan'),
            'satuan_produksi'   => $teks('satuan_produksi'),
            'produksi'          => $kolom($l['produksi'] ?? [], ['periode', 'jumlah']),
            'pekerja'           => $kolom($l['pekerja'] ?? [], ['jabatan', 'jumlah']),
            'peralatan'         => $kolom($l['peralatan'] ?? [], ['jenis', 'tipe', 'produktivitas', 'jumlah']),
            'area'              => array_values(array_filter(array_map(fn ($a) => trim((string) $a), (array) ($l['area'] ?? [])), fn ($a) => $a !== '')),
            'distribusi'        => $teks('distribusi'),
            'tempat'            => $teks('tempat'),
            'tanggal'           => preg_match('/^\d{4}-\d{2}-\d{2}/', $tanggal) ? substr($tanggal, 0, 10) : '',
        ];
    }

    /** Pengantar bawaan Gambaran Umum Penerapan SMKP Minerba (laporan bagian 2.3). */
    protected function pengantarPenerapan(string $pt): string
    {
        return "{$pt} telah menerapkan Sistem Manajemen Keselamatan Pertambangan Minerba, dalam rangka pengendalian risiko Keselamatan Pertambangan yang terdiri atas Keselamatan dan Kesehatan Kerja Pertambangan dan Keselamatan Operasi Pertambangan.\n"
            .'Penerapan SMKP Minerba dilakukan sesuai Pasal 18 Peraturan Menteri Energi dan Sumber Daya Mineral nomor 26 Tahun 2018 tentang Pelaksanaan Kaidah Pertambangan yang Baik dan Pengawasan Pertambangan Mineral dan Batubara, Lampiran IV Keputusan Menteri Energi dan Sumber Daya Mineral nomor 1827.K/30/MEM/2018, serta Lampiran II Keputusan Direktur Jenderal Mineral dan Batubara nomor 185.K/37.04/DJB/2019.';
    }

    /**
     * Tim auditor untuk Laporan Audit: fungsi, nama, nomor registrasi, dan
     * kunci tanda tangan digital — ketua tim memakai 'ketua', anggota lain
     * 'auditor2' dst. sesuai urutan tabel tim pada Rencana Audit. Pemandu,
     * pengamat, dan tenaga ahli tercantum tetapi tidak menandatangani.
     */
    protected function timLaporan(SmkpAudit $a): array
    {
        $tim = $a->tim();
        if (!$tim && trim((string) $a->ketua_auditor) !== '') $tim = [['nama' => $a->ketua_auditor, 'peran' => 'Lead Auditor']];

        $iKetua = 0;
        foreach ($tim as $i => $t) {
            if (preg_match('/ketua|lead/i', (string) ($t['peran'] ?? ''))) { $iKetua = $i; break; }
        }

        $slot = self::PIHAK_AUDITOR;
        $out = [];
        foreach ($tim as $i => $t) {
            $fungsi = trim((string) ($t['peran'] ?? '')) ?: ($i === $iKetua ? 'Lead Auditor' : 'Auditor');
            $tandaTangan = !preg_match('/pemandu|pengamat|tenaga ahli/i', $fungsi);
            $out[] = [
                'fungsi'     => $fungsi,
                'nama'       => trim((string) ($t['nama'] ?? '')),
                'registrasi' => trim((string) ($t['registrasi'] ?? '')),
                'tandaTangan'=> $tandaTangan,
                'kunci'      => !$tandaTangan ? null : ($i === $iKetua ? 'ketua' : array_shift($slot)),
            ];
        }

        return $out;
    }

    /**
     * Gambaran penerapan SMKP per elemen (laporan bagian 2.3): uraian tingkat
     * nilai resmi tiap butir yang sudah dinilai; butir N/A dinyatakan tidak
     * dapat diaplikasikan.
     */
    protected function penerapanElemen(SmkpAudit $a, string $pt, bool $jasa): array
    {
        $hasil = (array) ($a->hasil ?? []);
        $uraian = function (array $b) use ($hasil, $pt, $jasa): ?string {
            $v = Smkp::nilaiButir($hasil, $b['kode']);
            if ($v === null) return null;
            if ($v === Smkp::NA) return "{$b['nama']} tidak dapat diaplikasikan di {$pt}.";

            return Smkp::uraianPenerapan($b['kode'], (float) Smkp::nilaiSetara($hasil, $b), $pt, $jasa);
        };

        $out = [];
        foreach (Smkp::elemen() as $e) {
            $sub = [];
            foreach ($e['sub'] as $s) {
                if (!empty($s['subsub'])) {
                    $rinci = [];
                    foreach (Smkp::butirSub($s) as $b) {
                        if (($t = $uraian($b)) !== null) $rinci[] = ['kode' => $b['kode'], 'nama' => $b['nama'], 'teks' => $t];
                    }
                    if ($rinci) $sub[] = ['kode' => $s['kode'], 'nama' => $s['nama'], 'teks' => null, 'rinci' => $rinci];
                } elseif (($b = Smkp::butirSub($s)[0] ?? null) && ($t = $uraian($b)) !== null) {
                    $sub[] = ['kode' => $s['kode'], 'nama' => $s['nama'], 'teks' => $t, 'rinci' => []];
                }
            }
            if ($sub) $out[] = ['kode' => $e['kode'], 'nama' => $e['nama'], 'sub' => $sub];
        }

        return $out;
    }

    /** Praktik terbaik (laporan bagian 5.1.1) dari Formulir Kesesuaian, dengan nama kriterianya. */
    protected function praktikTerbaik(SmkpAudit $a): array
    {
        $nama = [];
        foreach (Smkp::elemen() as $e) {
            $nama[$e['kode']] = $e['nama'];
            foreach ($e['sub'] as $s) {
                $nama[$s['kode']] = $s['nama'];
                foreach ((array) ($s['subsub'] ?? []) as $x) $nama[$x['kode']] = $x['nama'];
            }
        }

        $out = [];
        foreach ((array) ($a->kesesuaian ?? []) as $r) {
            $r = (array) $r;
            $ket = trim((string) ($r['keterangan'] ?? ''));
            $el = (string) ($r['elemen'] ?? '');
            if ($ket === '' || !isset($nama[$el])) continue;
            $kode = ($r['subsub'] ?? '') ?: (($r['sub'] ?? '') ?: '');
            $out[] = [
                'elemen'   => $el,
                'namaElemen' => $nama[$el],
                'kriteria' => $kode === '' ? '' : (($r['subsub'] ?? '') ? 'Sub Sub Elemen ' : 'Sub Elemen ').$kode.' – '.($nama[$kode] ?? ''),
                'uraian'   => $ket,
            ];
        }

        return $out;
    }


    /* ================= Tanda tangan digital formulir ================= */

    /**
     * Anggota tim audit selain ketua, untuk tanda tangan Laporan Audit —
     * urut sesuai tabel tim pada Rencana Audit (lihat timLaporan()).
     */
    public const PIHAK_AUDITOR = ['auditor2', 'auditor3', 'auditor4', 'auditor5', 'auditor6', 'auditor7', 'auditor8'];

    /**
     * Penanda tangan formulir: ketua tim auditor, KTT (auditi), PJO
     * (Penanggung Jawab Operasional — perusahaan jasa pertambangan, lihat
     * SmkpTahap::pengesah()), dan anggota tim audit lainnya.
     */
    public const PIHAK = ['ketua', 'ktt', 'pjo', ...self::PIHAK_AUDITOR];

    /**
     * Bagian tindak lanjut Formulir Ketidaksesuaian: tanda tangannya tidak
     * tampil otomatis, tetapi dibubuhkan sendiri oleh pihaknya setelah
     * syaratnya terpenuhi — perbaikan oleh auditi setelah tindak lanjut
     * disampaikan, verifikasi oleh auditor setelah buktinya diverifikasi.
     */
    protected const BAGIAN_MANUAL = '/^ketidaksesuaian\.(\d{1,10})\.(perbaikan|verifikasi)$/';
    protected const PIHAK_MANUAL = ['perbaikan' => 'ktt', 'verifikasi' => 'ketua'];

    /**
     * Isian tanda tangan untuk halaman cetak: tanggal per bagian & pihak,
     * dan tautan gambar tanda tangan digital per pihak (dipakai di seluruh
     * formulir audit ini — bukan hanya paket SmkpFormulirController).
     */
    protected function isianTtd(Request $request, SmkpAudit $smkp): array
    {
        $t = (array) ($smkp->ttd_formulir ?? []);

        $tanggal = [];
        foreach ((array) ($t['tanggal'] ?? []) as $bagian => $per) {
            foreach ((array) $per as $pihak => $v) {
                if (!in_array($pihak, self::PIHAK, true) || !is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) continue;
                $tanggal[$bagian][$pihak] = ['nilai' => $v, 'teks' => Carbon::createFromFormat('Y-m-d', $v)->translatedFormat('d F Y')];
            }
        }

        $gambar = [];
        foreach (self::PIHAK as $p) {
            $jalur = $t['gambar'][$p] ?? null;
            // ?v= berganti setiap unggah ulang agar peramban tidak menampilkan gambar lama.
            $gambar[$p] = is_string($jalur) && Storage::disk('local')->exists($jalur)
                ? route('smkp.formulir.ttd', [$smkp, $p], false).'?v='.substr(md5($jalur), 0, 10) : null;
        }

        $bubuh = [];
        foreach ((array) ($t['bubuh'] ?? []) as $bagian => $per) {
            if (!preg_match(self::BAGIAN_MANUAL, (string) $bagian, $m)) continue;
            $pihak = self::PIHAK_MANUAL[$m[2]];
            if (!empty(((array) $per)[$pihak])) $bubuh[$bagian] = [$pihak => true];
        }

        $u = $request->user();

        return [
            'tanggal'     => (object) $tanggal,
            'gambar'      => $gambar,
            'bubuh'       => (object) $bubuh,
            'bisaSunting' => $u->bisaAudit(),
            'bisaGambar'  => array_combine(self::PIHAK, array_map(fn ($p) => self::bolehGambar($u, $p), self::PIHAK)),
            'bisaBubuh'   => array_combine(self::PIHAK, array_map(fn ($p) => self::bolehBubuh($u, $p), self::PIHAK)),
            'url'         => route('smkp.formulir', $smkp, false),
        ];
    }

    /** Pihak di sisi tim audit (ketua dan anggota), bukan auditi. */
    protected static function pihakAuditor(string $pihak): bool
    {
        return $pihak === 'ketua' || in_array($pihak, self::PIHAK_AUDITOR, true);
    }

    /** Gambar tanda tangan: auditor mengelola semuanya, auditi hanya tanda tangannya sendiri (KTT/PJO). */
    protected static function bolehGambar(User $u, string $pihak): bool
    {
        return $u->bisaAudit() || (!self::pihakAuditor($pihak) && $u->isAuditi());
    }

    /** Tanda tangan bagian tindak lanjut hanya dibubuhkan pihak itu sendiri. */
    protected static function bolehBubuh(User $u, string $pihak): bool
    {
        return self::pihakAuditor($pihak) ? $u->bisaAudit() : $u->isAuditi();
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
        $j = $r->user()->jangkauan();
        $cid = is_scalar($r->input('company_id')) ? (int) $r->input('company_id') : 0;

        // Perusahaan di luar jangkauan ditolak (403) sebelum validasi: pesan
        // "sudah ada" / "tidak ditemukan" akan membocorkan data perusahaan lain.
        $terjangkau = $j === null || in_array($cid, $j, true);
        abort_if($r->filled('company_id') && !$terjangkau, 403, 'Perusahaan ini di luar jangkauan akun Anda.');

        // Satu periode audit per perusahaan per tahun.
        $unik = Rule::unique('smkp_audits', 'tahun')->where('company_id', $cid)->ignore($abaikan?->id);

        $d = $r->validate([
            'company_id'      => ['required', 'integer', Rule::exists('companies', 'id')],
            'tahun'           => ['required', 'integer', 'min:2000', 'max:2100', $unik],
            'judul'           => ['nullable', 'string', 'max:200'],
            'status'          => ['required', Rule::in(array_keys(SmkpAudit::STATUS))],
            'tanggal_mulai'   => ['nullable', 'date', function ($attr, $v, $fail) use ($r) {
                // Pelaksanaan boleh mendahului atau melewati tahun periode paling jauh setahun.
                $th = (int) $r->input('tahun');
                $ts = strtotime((string) $v);
                if ($ts === false) return;                 // ditangani aturan 'date'
                $y  = (int) date('Y', $ts);
                if ($th && ($y < $th - 1 || $y > $th + 1)) {
                    $fail("Tanggal mulai harus di sekitar periode audit {$th} (paling jauh setahun sebelum atau sesudahnya).");
                }
            }],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'ketua_auditor'   => ['nullable', 'string', 'max:150'],
        ], [
            'company_id.required' => 'Pilih perusahaan yang diaudit.',
            'tahun.unique'        => 'Periode audit tahun ini sudah ada untuk perusahaan tersebut.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        if ($j !== null && !in_array((int) $d['company_id'], $j, true)) {
            abort(403, 'Perusahaan ini di luar jangkauan akun Anda.');
        }

        // Status Selesai mengikuti tahap Pelaporan (lihat ubahTahap), bukan dipilih bebas.
        $pelaporan = $abaikan && (int) $abaikan->tahap === SmkpTahap::PELAPORAN;
        if ($d['status'] === 'selesai' && !$pelaporan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Audit selesai lewat tahap Pelaporan — pindahkan tahap audit ke Pelaporan dari halaman ringkasan.']);
        }
        if ($d['status'] !== 'selesai' && $pelaporan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Audit pada tahap Pelaporan berstatus Selesai — pindahkan ke tahap sebelumnya untuk membukanya kembali.']);
        }

        return $d;
    }

    /** Status yang boleh dipilih di formulir: Selesai hanya (dan selalu) pada tahap Pelaporan. */
    private function statusPilihan(?SmkpAudit $a): array
    {
        return $a && (int) $a->tahap === SmkpTahap::PELAPORAN
            ? array_intersect_key(SmkpAudit::STATUS, ['selesai' => 1])
            : array_diff_key(SmkpAudit::STATUS, ['selesai' => 1]);
    }

    /**
     * Isian penyesuaian untuk formulir Tahap I. Isian lama (sebelum batas 10%)
     * yang melebihi batas ditampilkan sudah dipangkas — sama dengan yang
     * dicetak — agar menyimpan Tahap I tanpa menyentuhnya tidak ditolak.
     */
    private function penyesuaianTampil(SmkpAudit $smkp, array $p): string
    {
        $isi = trim((string) ($p['penyesuaian'] ?? ''));
        if ($isi === '') return '';

        $md = SmkpTahap::mandays($p);
        if (!$md['kondisi']) return '';

        return (float) $isi > $md['maks_penyesuaian'] ? rtrim(rtrim(number_format($md['maks_penyesuaian'], 2, '.', ''), '0'), '.') : $isi;
    }

    /** "12,5" → "12.5" pada kolom angka; teks lain dibiarkan untuk divalidasi apa adanya. */
    private function desimalKoma(Request $r, array $kunci): void
    {
        foreach ($kunci as $k) {
            $v = $r->input($k);
            if (!is_string($v) || !preg_match('/^\s*-?\d+,\d+\s*$/', $v)) continue;

            $atas = strtok($k, '.');
            $isi = [$atas => $r->input($atas)];
            data_set($isi, $k, str_replace(',', '.', trim($v)));
            $r->merge($isi);
        }
    }
}
