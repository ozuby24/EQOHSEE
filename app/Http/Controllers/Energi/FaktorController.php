<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Energi\{Faktor, Site};
use App\Support\Energi\{Inventaris, Katalog, RegistriFaktor};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Registri faktor emisi & konversi berversi (PRD E1, FSD §8.3).
 *
 * Aturan yang dijaga di sini:
 *  - faktor AKTIF tidak pernah disunting di tempat; perubahan berarti versi
 *    baru (draf) yang berlaku sejak tanggal tertentu;
 *  - versi baru disetujui oleh orang lain daripada pengajunya;
 *  - dua versi aktif berkunci sama tidak boleh berlaku pada tanggal yang
 *    sama — menyetujui versi baru menutup versi lama yang masih terbuka;
 *  - sumber dokumen wajib diisi: faktor tanpa sumber tidak dapat diperiksa
 *    verifikator mana pun.
 */
class FaktorController extends Controller
{
    use BantuEnergi;

    public function index(Request $request)
    {
        $q = Faktor::query()->with(['pengaju:id,name', 'penyetuju:id,name', 'company:id,name'])
            ->when($request->query('kode'), fn ($q, $k) => $q->where('kode_energi', $k))
            ->when($request->query('parameter'), fn ($q, $p) => $q->where('parameter', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('wilayah'), fn ($q, $w) => $q->where('wilayah', $w))
            ->orderBy('kode_energi')->orderBy('parameter')->orderBy('kategori_ipcc')->orderByDesc('berlaku_mulai');

        $u = $request->user();

        return Inertia::render('Energi/Faktor', [
            'faktor' => $q->get()->map(fn (Faktor $f) => $f->ringkas() + [
                'status' => $f->status, 'pemasok' => $f->pemasok, 'sumber_url' => $f->sumber_url,
                'ketidakpastian_persen' => $f->ketidakpastian_persen, 'catatan' => $f->catatan,
                'milik' => $f->company?->name ?? 'Bersama (nasional/IPCC)',
                'pengaju' => $f->pengaju?->name, 'penyetuju' => $f->penyetuju?->name,
                'disetujui_pada' => $f->disetujui_pada?->format('d/m/Y'),
                'boleh_setujui' => $f->status === 'draf' && $this->bolehSetujui($request, $f),
            ])->values(),
            'saring' => $request->only(['kode', 'parameter', 'status', 'wilayah']),
            'pilihan' => [
                'kode' => Katalog::semuaKode(), 'parameter' => Katalog::PARAMETER,
                'kategori' => Katalog::KATEGORI_IPCC, 'wilayah' => Site::SISTEM_GRID, 'status' => Faktor::STATUS,
            ],
            'terpasang' => RegistriFaktor::terpasang(),
            'boleh' => [
                'kelola' => $u->can('energi.faktor') || $u->can('energi.harga'),
                'faktor' => $u->can('energi.faktor'),
            ],
            'perusahaan' => $this->perusahaanAktif($request),
            'tautan' => ['simpan' => route('energi.faktor.simpan'), 'uji' => route('energi.faktor.uji')],
        ]);
    }

    public function simpan(Request $request)
    {
        $d = $this->validasi($request);
        $this->bolehMengelola($request, $d['parameter']);

        $d['company_id'] = $request->user()->isAdmin() && $request->boolean('bersama') ? null : $request->user()->company_id;
        $d['status'] = 'draf';
        $d['diajukan_oleh'] = $request->user()->id;

        $f = Faktor::withoutGlobalScopes()->create($d);
        ActivityLog::write('Ajukan faktor energi', $f->kode_energi.'/'.$f->parameter.' = '.$f->nilai, 'energi');

        return back()->with('ok', 'Versi faktor diajukan sebagai draf — menunggu persetujuan petugas lain.');
    }

    public function ubah(Request $request, Faktor $faktor)
    {
        if ($faktor->status !== 'draf') {
            return back()->withErrors(['nilai' => 'Faktor yang sudah aktif atau ditarik tidak diubah di tempat — ajukan versi baru dengan tanggal berlaku baru.']);
        }

        $d = $this->validasi($request);
        $this->bolehMengelola($request, $d['parameter']);
        $faktor->update($d);

        return back()->with('ok', 'Draf faktor diperbarui.');
    }

    public function setujui(Request $request, Faktor $faktor)
    {
        abort_unless($faktor->status === 'draf', 422, 'Hanya draf yang dapat disetujui.');
        abort_unless($this->bolehSetujui($request, $faktor), 403, 'Faktor disetujui oleh petugas lain daripada pengajunya.');

        // Versi aktif yang masih terbuka dan berkunci sama ditutup sehari sebelum versi baru berlaku.
        $lama = Faktor::withoutGlobalScopes()->where('status', 'aktif')
            ->where('kode_energi', $faktor->kode_energi)->where('parameter', $faktor->parameter)
            ->where(fn ($q) => $faktor->company_id === null ? $q->whereNull('company_id') : $q->where('company_id', $faktor->company_id))
            ->where(fn ($q) => $faktor->kategori_ipcc === null ? $q->whereNull('kategori_ipcc') : $q->where('kategori_ipcc', $faktor->kategori_ipcc))
            ->where(fn ($q) => $faktor->wilayah === null ? $q->whereNull('wilayah') : $q->where('wilayah', $faktor->wilayah))
            ->where(fn ($q) => $faktor->pemasok === null ? $q->whereNull('pemasok') : $q->where('pemasok', $faktor->pemasok))
            ->whereNull('berlaku_sampai')->whereDate('berlaku_mulai', '<', $faktor->berlaku_mulai->toDateString())
            ->get();

        /* Satu transaksi: bila versi baru ternyata bertumpang-tindih, versi
           lama yang sempat ditutup dikembalikan seperti semula. */
        \Illuminate\Support\Facades\DB::transaction(function () use ($lama, $faktor, $request) {
            foreach ($lama as $l) $l->update(['berlaku_sampai' => $faktor->berlaku_mulai->copy()->subDay()->toDateString()]);

            if ($bentrok = RegistriFaktor::tumpangTindih($faktor)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['berlaku_mulai' =>
                    'Rentang berlaku bertumpang-tindih dengan versi aktif #'.$bentrok->id
                    .' ('.$bentrok->berlaku_mulai->format('d/m/Y').' – '.($bentrok->berlaku_sampai?->format('d/m/Y') ?? 'terbuka').').']);
            }

            $faktor->update(['status' => 'aktif', 'disetujui_oleh' => $request->user()->id, 'disetujui_pada' => now()]);
        });

        ActivityLog::write('Setujui faktor energi', $faktor->kode_energi.'/'.$faktor->parameter.' = '.$faktor->nilai, 'energi');

        return back()->with('ok', 'Faktor disetujui dan berlaku sejak '.$faktor->berlaku_mulai->translatedFormat('d F Y').'.');
    }

    public function tarik(Request $request, Faktor $faktor)
    {
        $this->bolehMengelola($request, $faktor->parameter);
        $faktor->update(['status' => 'ditarik']);
        ActivityLog::write('Tarik faktor energi', $faktor->kode_energi.'/'.$faktor->parameter, 'energi');

        return back()->with('ok', 'Faktor ditarik. Perhitungan yang memakai tanggal itu kembali ke versi lain atau ditandai "faktor tidak tersedia".');
    }

    /** Tandai nilai acuan sudah dicocokkan dengan dokumen sumbernya. */
    public function cocokkan(Request $request, Faktor $faktor)
    {
        abort_unless($request->user()->can('energi.faktor'), 403);
        $faktor->update(['perlu_cocok' => false, 'catatan' => trim(($faktor->catatan ? $faktor->catatan."\n" : '')
            .'Dicocokkan '.now()->format('d/m/Y').' oleh '.$request->user()->name.'.')]);

        return back()->with('ok', 'Faktor ditandai sudah dicocokkan dengan dokumen sumbernya.');
    }

    /**
     * Uji dampak (FSD §8.3): tCO₂e 12 bulan terakhir dengan faktor draf
     * dibandingkan dengan faktor aktif — sebelum disetujui, bukan sesudah.
     */
    public function ujiDampak(Request $request)
    {
        $request->validate(['faktor' => ['required', 'integer']]);
        $f = Faktor::findOrFail($request->integer('faktor'));
        $dari = now()->subMonths(12)->startOfMonth();
        $sampai = now()->startOfDay();
        $saring = ($c = $this->perusahaanAktif($request)) ? ['company_id' => $c] : [];

        $aktif = Inventaris::ringkas(Inventaris::hitung($dari, $sampai, $saring)['baris']);

        RegistriFaktor::sisipkan($f);
        $draf = Inventaris::ringkas(Inventaris::hitung($dari, $sampai, $saring)['baris']);
        RegistriFaktor::lupakan();

        return response()->json([
            'aktif' => ['total' => $aktif['total'], 'gj' => $aktif['gj'], 's1' => $aktif['s1'], 's2' => $aktif['s2_lb']],
            'draf'  => ['total' => $draf['total'], 'gj' => $draf['gj'], 's1' => $draf['s1'], 's2' => $draf['s2_lb']],
            'selisih_persen' => $aktif['total'] > 0 ? ($draf['total'] - $aktif['total']) / $aktif['total'] * 100 : null,
            'rentang' => [$dari->toDateString(), $sampai->toDateString()],
        ]);
    }

    /* ── bantu ── */

    private function validasi(Request $request): array
    {
        return $request->validate([
            'kode_energi'   => ['required', Rule::in(array_keys(Katalog::semuaKode()))],
            'parameter'     => ['required', Rule::in(array_keys(Katalog::PARAMETER))],
            'kategori_ipcc' => ['nullable', Rule::in(array_keys(Katalog::KATEGORI_IPCC))],
            'wilayah'       => ['nullable', 'string', 'max:30'],
            'pemasok'       => ['nullable', 'string', 'max:100'],
            'nilai'         => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'satuan'        => ['required', 'string', 'max:30'],
            'berlaku_mulai' => ['required', 'date'],
            'berlaku_sampai'=> ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
            'tier'          => ['required', 'integer', 'between:1,3'],
            'sumber_dokumen'=> ['required', 'string', 'max:255'],
            'sumber_rujukan'=> ['nullable', 'string', 'max:255'],
            'sumber_url'    => ['nullable', 'url', 'max:500'],
            'ketidakpastian_persen' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'catatan'       => ['nullable', 'string', 'max:2000'],
        ], ['sumber_dokumen.required' => 'Sumber dokumen wajib diisi — faktor tanpa sumber tidak dapat diperiksa verifikator.']);
    }

    /** Keuangan hanya boleh mengelola harga; selebihnya Petugas GRK. */
    private function bolehMengelola(Request $request, string $parameter): void
    {
        $u = $request->user();
        $harga = in_array($parameter, ['harga', 'harga_dasar', 'harga_rendah', 'harga_tinggi'], true);

        abort_unless($u->can('energi.faktor') || ($harga && $u->can('energi.harga')), 403,
            'Registri faktor dikelola Petugas GRK; harga dapat dikelola Keuangan.');
    }

    private function bolehSetujui(Request $request, Faktor $f): bool
    {
        $u = $request->user();
        $harga = in_array($f->parameter, ['harga', 'harga_dasar', 'harga_rendah', 'harga_tinggi'], true);
        $berwenang = $u->can('energi.faktor') || ($harga && $u->can('energi.harga'));

        return $berwenang && ($u->isAdmin() || (int) $f->diajukan_oleh !== (int) $u->id);
    }
}
