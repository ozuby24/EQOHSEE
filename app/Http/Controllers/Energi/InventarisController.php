<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, EnergyOpportunity};
use App\Models\Energi\{GrkBatas, Mitigasi, Pengaturan, Scope3, Site};
use App\Rules\DalamPerusahaan;
use App\Support\Energi\{Inventaris, Katalog, Pelaporan};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Inventarisasi GRK perusahaan — Scope 1, 2, 3 (PRD E3, FSD §8.4).
 */
class InventarisController extends Controller
{
    use BantuEnergi;

    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentangTanggal($request, now()->startOfYear(), now()->startOfDay());
        $c = $this->perusahaanAktif($request);
        $saring = $c ? ['company_id' => $c] : [];

        $inv = Inventaris::hitung($dari, $sampai, $saring);
        $r = Inventaris::ringkas($inv['baris']);
        $p = Inventaris::produksi($dari, $sampai, $saring);

        $atur = Pengaturan::untuk($c);
        $dasar = null;
        if ($atur->tahun_dasar) {
            $d = $atur->dasar_snapshot ?: Inventaris::ringkas(Inventaris::hitung($atur->tahun_dasar.'-01-01',
                $atur->tahun_dasar.'-12-31', array_filter(['company_id' => $c]))['baris']);
            $dasar = ['tahun' => $atur->tahun_dasar, 'total' => ($d['s1'] ?? 0) + ($d['s2_lb'] ?? 0)];
        }

        $perBulan = [];
        foreach (Inventaris::kelompok($inv['baris'], fn ($b) => $b['bulan']) as $bulan => $x) {
            $perBulan[] = ['bulan' => $bulan, 'label' => Carbon::parse($bulan.'-01')->translatedFormat('M y'),
                           's1' => $x['s1'], 's2' => $x['s2_lb'], 's3' => $x['s3']];
        }

        $snap = Pelaporan::susun('GRK_PU', $c, $dari, $sampai);

        return Inertia::render('Energi/Grk', [
            'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(),
            'r' => Pelaporan::potong($r), 'produksi' => $p,
            'intensitas' => $p['ton'] > 0 ? $r['total'] / $p['ton'] : null,
            'dasar' => $dasar,
            'perBulan' => $perBulan,
            'perSumber' => $snap['per_sumber'],
            'perGas' => $snap['per_gas'],
            'perKategori' => $snap['per_kategori_s1'],
            'scope3' => $snap['scope3'],
            'faktorCocok' => $snap['faktor_perlu_cocok'],
            'gwp' => $atur->gwp_set,
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ─────────── batas organisasi ─────────── */

    public function batas(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $tahun = $request->integer('tahun') ?: now()->year;
        $b = GrkBatas::where('company_id', $c)->where('tahun', $tahun)->with('penyetuju:id,name')->first();

        return Inertia::render('Energi/GrkBatas', [
            'tahun' => $tahun,
            'batas' => $b ? [
                'id' => $b->id, 'pendekatan' => $b->pendekatan, 'persen_ekuitas' => $b->persen_ekuitas,
                'uraian_batas' => $b->uraian_batas, 'pengecualian' => $b->pengecualian ?? [],
                'relevansi_scope3' => $b->relevansi_scope3 ?? [], 'disetujui' => $b->disetujui(),
                'penyetuju' => $b->penyetuju?->name, 'disetujui_pada' => $b->disetujui_pada?->format('d/m/Y'),
            ] : null,
            'pendekatan' => GrkBatas::PENDEKATAN,
            'kategoriScope3' => Scope3::KATEGORI,
            'atur' => ['tahun_dasar' => Pengaturan::untuk($c)->tahun_dasar,
                       'ambang_rekalkulasi_persen' => Pengaturan::untuk($c)->ambang_rekalkulasi_persen,
                       'gwp_set' => Pengaturan::untuk($c)->gwp_set],
            'gwp' => Pengaturan::GWP,
            'boleh' => ['kelola' => $request->user()->can('energi.kelola'), 'setujui' => $request->user()->can('energi.setujui')],
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function simpanBatas(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);

        $d = $request->validate([
            'tahun'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'pendekatan'     => ['required', Rule::in(array_keys(GrkBatas::PENDEKATAN))],
            'persen_ekuitas' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'uraian_batas'   => ['nullable', 'string', 'max:5000'],
            'pengecualian'   => ['nullable', 'array', 'max:50'],
            'pengecualian.*.sumber' => ['required', 'string', 'max:200'],
            'pengecualian.*.alasan' => ['required', 'string', 'max:500'],
            'pengecualian.*.estimasi_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'relevansi_scope3' => ['nullable', 'array'],
            'relevansi_scope3.*.relevan' => ['nullable', 'boolean'],
            'relevansi_scope3.*.alasan'  => ['nullable', 'string', 'max:500'],
        ]);

        $c = $this->perusahaanAktif($request);
        $b = GrkBatas::firstOrNew(['company_id' => $c, 'tahun' => $d['tahun']]);
        $b->fill($d + ['company_id' => $c]);

        // Batas yang diubah sesudah disetujui harus disetujui ulang.
        if ($b->isDirty(['pendekatan', 'persen_ekuitas', 'uraian_batas', 'pengecualian'])) {
            $b->disetujui_oleh = null;
            $b->disetujui_pada = null;
        }
        $b->save();

        return back()->with('ok', 'Batas organisasi '.$d['tahun'].' tersimpan'.($b->disetujui() ? '.' : ' — menunggu persetujuan.'));
    }

    public function setujuiBatas(Request $request, GrkBatas $batas)
    {
        abort_unless($request->user()->can('energi.setujui'), 403);
        $batas->update(['disetujui_oleh' => $request->user()->id, 'disetujui_pada' => now()]);
        ActivityLog::write('Setujui batas organisasi GRK', 'Tahun '.$batas->tahun, 'energi');

        return back()->with('ok', 'Batas organisasi tahun '.$batas->tahun.' disetujui.');
    }

    /** Tahun dasar, set GWP & ambang rekalkulasi (FSD §8.4). */
    public function simpanDasar(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $request->validate([
            'tahun_dasar' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'ambang_rekalkulasi_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'gwp_set' => ['required', Rule::in(array_keys(Pengaturan::GWP))],
        ]);

        $c = $this->perusahaanAktif($request);
        $atur = Pengaturan::withoutGlobalScopes()->firstOrNew(['company_id' => $c]);
        $ganti = (int) $atur->tahun_dasar !== (int) ($d['tahun_dasar'] ?? 0);
        $atur->fill($d);

        if ($ganti && !empty($d['tahun_dasar'])) {
            $r = Inventaris::ringkas(Inventaris::hitung($d['tahun_dasar'].'-01-01', $d['tahun_dasar'].'-12-31',
                array_filter(['company_id' => $c]))['baris']);
            $atur->dasar_snapshot = ['s1' => $r['s1'], 's2_lb' => $r['s2_lb'], 's2_mb' => $r['s2_mb'], 's3' => $r['s3'],
                                     'gj' => $r['gj'], 'dibekukan' => now()->toDateString()];
        }
        $atur->save();

        return back()->with('ok', 'Tahun dasar dan set GWP tersimpan.');
    }

    /* ─────────── Scope 3 ─────────── */

    public function scope3(Request $request)
    {
        $tahun = $request->integer('tahun') ?: now()->year;
        $c = $this->perusahaanAktif($request);
        $b = GrkBatas::where('company_id', $c)->where('tahun', $tahun)->first();

        return Inertia::render('Energi/GrkScope3', [
            'tahun' => $tahun,
            'daftar' => Scope3::where('tahun', $tahun)->orderBy('kategori')->get()->map(fn (Scope3 $s) => [
                'id' => $s->id, 'bulan' => $s->bulan, 'kategori' => $s->kategori, 'subkategori' => $s->subkategori,
                'metode' => $s->metode, 'kuantitas' => $s->kuantitas, 'satuan' => $s->satuan,
                'faktor_kgco2e' => $s->faktor_kgco2e, 'sumber_faktor' => $s->sumber_faktor, 'mitra' => $s->mitra,
                'kualitas_data' => $s->kualitas_data, 'status' => $s->status, 'tco2e' => $s->tco2e(),
            ])->values(),
            'relevansi' => $b?->relevansi_scope3 ?? [],
            'kategori' => Scope3::KATEGORI, 'metode' => Scope3::METODE,
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function simpanScope3(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $request->validate([
            'tahun'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'bulan'         => ['nullable', 'integer', 'between:1,12'],
            'kategori'      => ['required', 'integer', 'between:1,15'],
            'subkategori'   => ['required', 'string', 'max:150'],
            'metode'        => ['required', Rule::in(array_keys(Scope3::METODE))],
            'kuantitas'     => ['required', 'numeric', 'min:0', 'max:1000000000000'],
            'satuan'        => ['required', 'string', 'max:20'],
            'faktor_kgco2e' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'sumber_faktor' => ['required', 'string', 'max:255'],
            'mitra'         => ['nullable', 'string', 'max:150'],
            'kualitas_data' => ['required', 'integer', 'between:1,5'],
            'catatan'       => ['nullable', 'string', 'max:500'],
        ]);

        Scope3::create($this->pemilik($d) + ['status' => 'diajukan', 'diinput_oleh' => $request->user()->id]);

        return back()->with('ok', 'Aktivitas Scope 3 kategori '.$d['kategori'].' dicatat.');
    }

    public function verifikasiScope3(Request $request, Scope3 $scope3)
    {
        abort_unless($request->user()->can('energi.verifikasi'), 403);
        $scope3->update(['status' => 'terverifikasi']);

        return back()->with('ok', 'Aktivitas Scope 3 terverifikasi.');
    }

    public function hapusScope3(Scope3 $scope3)
    {
        $scope3->delete();

        return back()->with('ok', 'Aktivitas Scope 3 dihapus.');
    }

    /* ─────────── aksi mitigasi ─────────── */

    public function mitigasi(Request $request)
    {
        return Inertia::render('Energi/GrkMitigasi', [
            'daftar' => Mitigasi::with(['site:id,nama', 'peluang:id,judul'])->orderByDesc('mulai')->get()->map(fn (Mitigasi $m) => [
                'id' => $m->id, 'judul' => $m->judul, 'jenis' => $m->jenis, 'mulai' => $m->mulai?->toDateString(),
                'selesai' => $m->selesai?->toDateString(), 'metodologi' => $m->metodologi,
                'baseline_tco2e_tahun' => $m->baseline_tco2e_tahun, 'target_penurunan_tco2e_tahun' => $m->target_penurunan_tco2e_tahun,
                'capaian' => $m->capaian ?? [], 'status_srn' => $m->status_srn, 'nomor_srn' => $m->nomor_srn,
                'site' => $m->site?->nama, 'peluang' => $m->peluang?->judul, 'catatan' => $m->catatan,
            ])->values(),
            'jenis' => Mitigasi::JENIS, 'statusSrn' => Mitigasi::STATUS_SRN,
            'sites' => Site::orderBy('nama')->get(['id', 'nama']),
            'peluang' => EnergyOpportunity::orderBy('judul')->get(['id', 'judul']),
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function simpanMitigasi(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        Mitigasi::create($this->pemilik($this->validasiMitigasi($request)));

        return back()->with('ok', 'Aksi mitigasi dicatat.');
    }

    public function ubahMitigasi(Request $request, Mitigasi $mitigasi)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $mitigasi->update($this->validasiMitigasi($request));

        return back()->with('ok', 'Aksi mitigasi diperbarui.');
    }

    private function validasiMitigasi(Request $request): array
    {
        return $request->validate([
            'judul'      => ['required', 'string', 'max:200'],
            'jenis'      => ['required', Rule::in(array_keys(Mitigasi::JENIS))],
            'site_id'    => ['nullable', new DalamPerusahaan('energi_sites')],
            'peluang_id' => ['nullable', new DalamPerusahaan('energy_opportunities')],
            'mulai'      => ['required', 'date'],
            'selesai'    => ['nullable', 'date', 'after_or_equal:mulai'],
            'metodologi' => ['nullable', 'string', 'max:255'],
            'baseline_tco2e_tahun'         => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'target_penurunan_tco2e_tahun' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'capaian'    => ['nullable', 'array'],
            'capaian.*'  => ['nullable', 'numeric', 'min:0'],
            'status_srn' => ['required', Rule::in(array_keys(Mitigasi::STATUS_SRN))],
            'nomor_srn'  => ['nullable', 'string', 'max:60'],
            'catatan'    => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
