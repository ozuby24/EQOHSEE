<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, EnergyOpportunity, TindakLanjut, User};
use App\Models\Energi\{Audit, Pengaturan, Personel, Program, Rekomendasi, Site, Tinjauan};
use App\Rules\DalamPerusahaan;
use App\Support\Energi\{Ambang, Kewajiban, Seu};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Manajemen energi menurut PP 33/2023 & Permen ESDM 8/2025, kerangka
 * ISO 50001 (PRD E4, FSD §8.5).
 */
class ManajemenEnergiController extends Controller
{
    use BantuEnergi;

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $t = now()->year;
        $ambang = Ambang::status($c, $t);
        $kew = Kewajiban::daftar($c);
        $k = collect($kew)->keyBy('kode');

        $cek = fn (string $kode) => in_array($k[$kode]['status'] ?? null, ['selesai', 'tidak_berlaku'], true);

        return Inertia::render('Energi/Manajemen', [
            'ambang' => $ambang,
            'checklist' => [
                ['label' => 'Manajer Energi bersertifikat berlaku', 'ok' => $cek('SERT_MANAJER'), 'rincian' => $k['SERT_MANAJER']['keterangan'] ?? null, 'dasar' => 'Permen ESDM 8/2025 Ps. 4'],
                ['label' => 'Program konservasi energi tahun '.$t, 'ok' => Program::where('company_id', $c)->where('tahun', $t)->exists(), 'rincian' => null, 'dasar' => 'PP 33/2023'],
                ['label' => 'Audit energi ≤3 tahun terakhir mencakup ≥1 SEU', 'ok' => $cek('AUDIT_ENERGI'), 'rincian' => $k['AUDIT_ENERGI']['keterangan'] ?? null, 'dasar' => 'Permen ESDM 8/2025 Ps. 13'],
                ['label' => 'Tidak ada rekomendasi audit lewat tenggat', 'ok' => $cek('REKOMENDASI'), 'rincian' => $k['REKOMENDASI']['keterangan'] ?? null, 'dasar' => 'Permen ESDM 8/2025 Ps. 16'],
                ['label' => 'Monev tahunan ke manajemen puncak '.$t, 'ok' => $cek('ESDM_MONEV'), 'rincian' => null, 'dasar' => 'Permen ESDM 8/2025 Ps. 10'],
                ['label' => 'Laporan tahunan '.($t - 1).' ke Menteri ESDM (≤ 30 Juni)', 'ok' => $cek('ESDM_ME'), 'rincian' => $k['ESDM_ME']['keterangan'] ?? null, 'dasar' => 'Permen ESDM 8/2025 Ps. 17'],
            ],
            'kewajiban' => $kew,
            'atur' => Pengaturan::untuk($c)->only(['kategori_pengguna', 'ambang_toe', 'sistem_grid', 'emiten',
                'ambang_kelengkapan_persen', 'ambang_rekonsiliasi_persen', 'ambang_rag_persen', 'target_tahun',
                'target_penurunan_persen', 'tanggal_proper', 'tanggal_laporan_tahunan']),
            'pilihan' => ['kategori' => array_map(fn ($k) => $k['label'].' — ambang '.number_format($k['ambang'], 0, ',', '.').' TOE', Pengaturan::KATEGORI),
                          'grid' => Site::SISTEM_GRID],
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function simpanPengaturan(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $request->validate([
            'kategori_pengguna' => ['required', Rule::in(array_keys(Pengaturan::KATEGORI))],
            'ambang_toe'        => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'sistem_grid'       => ['nullable', Rule::in(array_keys(Site::SISTEM_GRID))],
            'emiten'            => ['boolean'],
            'ambang_kelengkapan_persen'  => ['required', 'numeric', 'min:0', 'max:100'],
            'ambang_rekonsiliasi_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'ambang_rag_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'target_tahun'      => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'target_penurunan_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tanggal_proper'    => ['nullable', 'date'],
            'tanggal_laporan_tahunan' => ['nullable', 'date'],
        ]);

        $c = $this->perusahaanAktif($request);
        Pengaturan::withoutGlobalScopes()->updateOrCreate(['company_id' => $c], $d);
        ActivityLog::write('Ubah pengaturan energi', $this->namaPerusahaan($c), 'energi');

        return back()->with('ok', 'Pengaturan manajemen energi tersimpan.');
    }

    /* ─────────── personel ─────────── */

    public function personel(Request $request)
    {
        return Inertia::render('Energi/Personel', [
            'daftar' => Personel::with('user:id,name')->orderBy('peran')->orderBy('nama')->get()->map(fn (Personel $p) => [
                'id' => $p->id, 'nama' => $p->nama, 'peran' => $p->peran, 'internal' => $p->internal, 'lembaga' => $p->lembaga,
                'nomor_sertifikat' => $p->nomor_sertifikat, 'lembaga_sertifikasi' => $p->lembaga_sertifikasi,
                'berlaku_sampai' => $p->berlaku_sampai?->toDateString(), 'nomor_sk' => $p->nomor_sk, 'aktif' => $p->aktif,
                'sah' => $p->sah(), 'user_id' => $p->user_id,
                'sisa_hari' => $p->berlaku_sampai ? (int) now()->startOfDay()->diffInDays($p->berlaku_sampai, false) : null,
            ])->values(),
            'peran' => Personel::PERAN,
            'akun' => User::orderBy('name')->get(['id', 'name']),
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function simpanPersonel(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        Personel::create($this->pemilik($this->validasiPersonel($request)));

        return back()->with('ok', 'Personel manajemen energi dicatat.');
    }

    public function ubahPersonel(Request $request, Personel $personel)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $personel->update($this->validasiPersonel($request));

        return back()->with('ok', 'Data personel diperbarui.');
    }

    private function validasiPersonel(Request $request): array
    {
        return $request->validate([
            'user_id'  => ['nullable', 'exists:users,id'],
            'nama'     => ['required', 'string', 'max:150'],
            'peran'    => ['required', Rule::in(array_keys(Personel::PERAN))],
            'internal' => ['boolean'],
            'lembaga'  => ['nullable', 'string', 'max:150'],
            'nomor_sertifikat'    => ['nullable', 'string', 'max:100'],
            'lembaga_sertifikasi' => ['nullable', 'string', 'max:150'],
            'berlaku_sampai'      => ['nullable', 'date'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'aktif'    => ['boolean'],
        ]);
    }

    /* ─────────── program konservasi ─────────── */

    public function program(Request $request)
    {
        $tahun = $request->integer('tahun') ?: now()->year;

        return Inertia::render('Energi/Program', [
            'tahun' => $tahun,
            'daftar' => Program::where('tahun', $tahun)->orderBy('id')->get()->map(fn (Program $p) => $p->only([
                'id', 'tahun', 'judul', 'sasaran', 'target_hemat_gj', 'target_hemat_tco2e', 'anggaran_rp', 'pic', 'status', 'peluang_ids',
            ]))->values(),
            'peluang' => EnergyOpportunity::orderBy('judul')->get(['id', 'judul', 'status']),
            'status' => Program::STATUS,
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function simpanProgram(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        Program::create($this->pemilik($this->validasiProgram($request)));

        return back()->with('ok', 'Program konservasi energi dicatat.');
    }

    public function ubahProgram(Request $request, Program $program)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $program->update($this->validasiProgram($request));

        return back()->with('ok', 'Program konservasi diperbarui.');
    }

    private function validasiProgram(Request $request): array
    {
        return $request->validate([
            'tahun'   => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul'   => ['required', 'string', 'max:200'],
            'sasaran' => ['nullable', 'string', 'max:5000'],
            'target_hemat_gj'    => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'target_hemat_tco2e' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'anggaran_rp' => ['nullable', 'numeric', 'min:0', 'max:100000000000000'],
            'pic'     => ['nullable', 'string', 'max:150'],
            'status'  => ['required', Rule::in(Program::STATUS)],
            'peluang_ids'   => ['nullable', 'array'],
            'peluang_ids.*' => ['integer', new DalamPerusahaan('energy_opportunities')],
        ]);
    }

    /* ─────────── audit energi & rekomendasi ─────────── */

    public function audit(Request $request)
    {
        $c = $this->perusahaanAktif($request);

        return Inertia::render('Energi/Audit', [
            'daftar' => Audit::withCount('rekomendasi')->orderByDesc('mulai')->get()->map(fn (Audit $a) => [
                'id' => $a->id, 'judul' => $a->judul, 'jenis' => $a->jenis, 'mulai' => $a->mulai?->toDateString(),
                'selesai' => $a->selesai?->toDateString(), 'status' => $a->status, 'standar' => $a->standar,
                'seu' => $a->seu ?? [], 'rekomendasi' => $a->rekomendasi_count,
                'tautan' => route('energi.me.audit.show', $a),
            ])->values(),
            'auditor' => Personel::where('peran', 'auditor_energi')->where('aktif', true)->get(['id', 'nama', 'nomor_sertifikat']),
            'seu' => Seu::pareto(now()->subYear()->startOfDay(), now()->startOfDay(), $c ? ['company_id' => $c] : []),
            'status' => Audit::STATUS,
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function simpanAudit(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $a = Audit::create($this->pemilik($this->validasiAudit($request)));
        ActivityLog::write('Catat audit energi', $a->judul, 'energi');

        return redirect()->route('energi.me.audit.show', $a)->with('ok', 'Audit energi dicatat. Tambahkan rekomendasinya di bawah.');
    }

    public function auditShow(Request $request, Audit $audit)
    {
        $audit->load('rekomendasi.peluang:id,judul');

        return Inertia::render('Energi/AuditRinci', [
            'audit' => [
                'id' => $audit->id, 'judul' => $audit->judul, 'jenis' => $audit->jenis, 'ruang_lingkup' => $audit->ruang_lingkup,
                'seu' => $audit->seu ?? [], 'auditor' => Personel::whereIn('id', $audit->auditor_ids ?? [])->pluck('nama'),
                'mulai' => $audit->mulai?->toDateString(), 'selesai' => $audit->selesai?->toDateString(),
                'standar' => $audit->standar, 'status' => $audit->status, 'ringkasan' => $audit->ringkasan,
                'auditor_ids' => $audit->auditor_ids ?? [],
            ],
            'rekomendasi' => $audit->rekomendasi->map(fn (Rekomendasi $r) => [
                'id' => $r->id, 'uraian' => $r->uraian, 'potensi_hemat_gj' => $r->potensi_hemat_gj,
                'potensi_hemat_rp' => $r->potensi_hemat_rp, 'biaya_rp' => $r->biaya_rp, 'prioritas' => $r->prioritas,
                'tenggat' => $r->tenggat?->toDateString(), 'status' => $r->status, 'alasan_tidak_layak' => $r->alasan_tidak_layak,
                'lewat' => $r->lewatTenggat(), 'peluang' => $r->peluang?->judul, 'peluang_id' => $r->peluang_id,
            ])->values(),
            'auditorPilihan' => Personel::where('peran', 'auditor_energi')->get(['id', 'nama']),
            'peluang' => EnergyOpportunity::orderBy('judul')->get(['id', 'judul']),
            'status' => Audit::STATUS, 'statusRek' => Rekomendasi::STATUS, 'prioritas' => Rekomendasi::PRIORITAS,
            'tenggatBawaan' => ($audit->selesai ?? $audit->mulai)?->copy()->addYears(3)->toDateString(),
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function ubahAudit(Request $request, Audit $audit)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $audit->update($this->validasiAudit($request));

        return back()->with('ok', 'Audit energi diperbarui.');
    }

    private function validasiAudit(Request $request): array
    {
        return $request->validate([
            'jenis'   => ['required', Rule::in(['internal', 'eksternal'])],
            'judul'   => ['required', 'string', 'max:200'],
            'ruang_lingkup' => ['nullable', 'string', 'max:5000'],
            'seu'     => ['nullable', 'array'],
            'seu.*'   => ['string', 'max:100'],
            'auditor_ids'   => ['nullable', 'array'],
            'auditor_ids.*' => ['integer', new DalamPerusahaan('energi_personel')],
            'mulai'   => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'standar' => ['nullable', 'string', 'max:100'],
            'status'  => ['required', Rule::in(Audit::STATUS)],
            'ringkasan' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * Rekomendasi audit. Tenggat bawaan = selesai audit + 3 tahun (Ps. 16),
     * dan setiap rekomendasi membuka TindakLanjut supaya masuk pengingat
     * yang sudah dipakai seluruh modul.
     */
    public function simpanRekomendasi(Request $request, Audit $audit)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $this->validasiRekomendasi($request);
        $d['tenggat'] ??= ($audit->selesai ?? $audit->mulai)->copy()->addYears(3)->toDateString();

        $r = $audit->rekomendasi()->create($d + ['company_id' => $audit->company_id]);

        TindakLanjut::create([
            'company_id' => $audit->company_id, 'user_id' => $request->user()->id,
            'sumber_type' => Rekomendasi::class, 'sumber_id' => $r->id, 'modul' => 'energi',
            'kode_pemicu' => 'AUDIT-ENERGI-'.$audit->id, 'judul' => mb_substr('Rekomendasi audit energi: '.$r->uraian, 0, 190),
            'kategori' => 'rekomendasi_audit', 'prioritas' => $r->prioritas === 'tinggi' ? 'tinggi' : ($r->prioritas === 'rendah' ? 'rendah' : 'sedang'),
            'status' => 'rencana', 'target_selesai' => $r->tenggat->toDateString(), 'uraian' => $r->uraian,
        ]);

        return back()->with('ok', 'Rekomendasi dicatat, tenggat '.$r->tenggat->translatedFormat('d F Y').'.');
    }

    public function ubahRekomendasi(Request $request, Rekomendasi $rek)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $this->validasiRekomendasi($request);

        if (($d['status'] ?? null) === 'tidak_layak' && blank($d['alasan_tidak_layak'] ?? null)) {
            return back()->withErrors(['alasan_tidak_layak' => 'Rekomendasi yang dinyatakan tidak layak wajib disertai alasannya.']);
        }

        $rek->update($d);

        TindakLanjut::where('sumber_type', Rekomendasi::class)->where('sumber_id', $rek->id)->update([
            'status' => match ($rek->status) { 'selesai' => 'selesai', 'tidak_layak' => 'batal', 'ditindaklanjuti' => 'berjalan', default => 'rencana' },
            'target_selesai' => $rek->tenggat?->toDateString(),
            'selesai_pada' => $rek->status === 'selesai' ? now()->toDateString() : null,
        ]);

        return back()->with('ok', 'Rekomendasi diperbarui.');
    }

    private function validasiRekomendasi(Request $request): array
    {
        return $request->validate([
            'uraian' => ['required', 'string', 'max:5000'],
            'potensi_hemat_gj' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'potensi_hemat_rp' => ['nullable', 'numeric', 'min:0', 'max:100000000000000'],
            'biaya_rp' => ['nullable', 'numeric', 'min:0', 'max:100000000000000'],
            'prioritas' => ['required', Rule::in(Rekomendasi::PRIORITAS)],
            'tenggat' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(Rekomendasi::STATUS)],
            'alasan_tidak_layak' => ['nullable', 'string', 'max:500'],
            'peluang_id' => ['nullable', new DalamPerusahaan('energy_opportunities')],
        ]) + ['status' => $request->input('status', 'terbuka')];
    }

    /* ─────────── monev & tinjauan manajemen ─────────── */

    public function tinjauan(Request $request)
    {
        return Inertia::render('Energi/Tinjauan', [
            'daftar' => Tinjauan::orderByDesc('tanggal')->get()->map(fn (Tinjauan $t) => [
                'id' => $t->id, 'tahun' => $t->tahun, 'jenis' => $t->jenis, 'tanggal' => $t->tanggal?->toDateString(),
                'peserta' => $t->peserta, 'masukan' => $t->masukan, 'keluaran' => $t->keluaran,
            ])->values(),
            'jenis' => Tinjauan::JENIS,
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function simpanTinjauan(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $request->validate([
            'jenis'    => ['required', Rule::in(array_keys(Tinjauan::JENIS))],
            'tanggal'  => ['required', 'date'],
            'peserta'  => ['nullable', 'string', 'max:500'],
            'masukan'  => ['nullable', 'string', 'max:10000'],
            'keluaran' => ['nullable', 'string', 'max:10000'],
        ]);

        Tinjauan::create($this->pemilik($d) + ['tahun' => \Illuminate\Support\Carbon::parse($d['tanggal'])->year]);

        return back()->with('ok', Tinjauan::JENIS[$d['jenis']].' dicatat.');
    }
}
