<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Company};
use App\Models\Energi\Laporan;
use App\Support\Energi\{EksporLaporan, Kewajiban, Pelaporan};
use App\Support\KopDokumen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pusat Pelaporan Energi & Karbon (PRD E6, FSD §7.3, §10).
 *
 *   draf → diajukan → disetujui → terbit → (perlu_revisi → versi baru; yang lama: diganti)
 *
 * Penyusun tidak menyetujui laporannya sendiri. Saat terbit, snapshot
 * disusun ulang dari periode yang sudah terkunci, dibekukan, di-hash, dan
 * diberi nomor dokumen terkendali — sesudah itu angka laporan tidak
 * dihitung ulang lagi.
 */
class LaporanController extends Controller
{
    use BantuEnergi;

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);

        return Inertia::render('Energi/Pelaporan', [
            'templat' => collect(Pelaporan::TEMPLAT)->map(fn ($t, $k) => ['kode' => $k] + $t)->values(),
            'laporan' => Laporan::with(['penyusun:id,name', 'penyetuju:id,name'])->orderByDesc('tahun')->orderByDesc('id')->limit(100)->get()
                ->map(fn (Laporan $l) => $this->baris($l))->values(),
            'kewajiban' => Kewajiban::daftar($c),
            'boleh' => ['susun' => $request->user()->can('energi.lapor') || $request->user()->can('energi.bod')],
            'tahunBawaan' => now()->year - 1,
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function kalender(Request $request)
    {
        $c = $this->perusahaanAktif($request);

        return Inertia::render('Energi/Kalender', [
            'kewajiban' => Kewajiban::daftar($c),
            'status' => Kewajiban::STATUS,
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function susun(Request $request, string $templat)
    {
        abort_unless(isset(Pelaporan::TEMPLAT[$templat]), 404);
        $this->bolehSusun($request, $templat);

        $d = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $c = $this->perusahaanAktif($request);
        [$mulai, $sampai] = Pelaporan::rentang($templat, $d['tahun'], $d['bulan'] ?? null);

        $l = Laporan::create([
            'company_id' => $c, 'templat' => $templat, 'tahun' => $d['tahun'],
            'periode_mulai' => $mulai->toDateString(), 'periode_sampai' => $sampai->toDateString(),
            'versi' => 1, 'status' => 'draf', 'judul' => Pelaporan::TEMPLAT[$templat]['judul'].' '.$d['tahun']
                .(isset($d['bulan']) && Pelaporan::TEMPLAT[$templat]['periode'] === 'bulanan' ? '-'.str_pad((string) $d['bulan'], 2, '0', STR_PAD_LEFT) : ''),
            'snapshot' => Pelaporan::susun($templat, $c, $mulai, $sampai),
            'disusun_oleh' => $request->user()->id, 'catatan' => $d['catatan'] ?? null,
        ]);
        ActivityLog::write('Susun laporan energi', $l->judul, 'energi');

        return redirect()->route('energi.lapor.show', $l)->with('ok', 'Draf laporan disusun. Periksa angkanya, lalu ajukan.');
    }

    public function show(Request $request, Laporan $laporan)
    {
        $snap = $laporan->snapshot ?? [];
        $v = Pelaporan::validasi($laporan->templat, $snap, $laporan->company_id);
        $c = $laporan->company_id ? Company::withoutGlobalScopes()->find($laporan->company_id) : null;
        $u = $request->user();

        $dok = KopDokumen::untuk(Pelaporan::TEMPLAT[$laporan->templat]['kop'] ?? 'laporan-energi', $c);
        if ($laporan->nomor_dokumen) $dok['nomor'] = $laporan->nomor_dokumen;

        return Inertia::render('Print/EnergiLaporan', [
            'dok' => $dok,
            'laporan' => $this->baris($laporan) + ['utuh' => $laporan->terbit() ? $laporan->utuh() : null,
                                                    'catatan' => $laporan->catatan, 'disampaikan_pada' => $laporan->disampaikan_pada?->toDateString(),
                                                    'bukti_penyampaian' => $laporan->bukti_penyampaian],
            'snap' => $snap,
            'validasi' => $v,
            'boleh' => [
                'ajukan'   => $laporan->status === 'draf' && ($u->can('energi.lapor') || $u->can('energi.bod')),
                'setujui'  => $laporan->status === 'diajukan' && $u->can('energi.setujui') && ($u->isAdmin() || (int) $laporan->disusun_oleh !== (int) $u->id),
                'terbitkan'=> $laporan->status === 'disetujui' && ($u->can('energi.setujui') || $u->isKtt()) && $v['blokir'] === [],
                'revisi'   => in_array($laporan->status, ['terbit', 'perlu_revisi'], true) && $u->can('energi.lapor'),
                'sampaikan'=> $laporan->status === 'terbit' && $u->can('energi.lapor'),
                'susunUlang' => $laporan->status === 'draf' && $u->can('energi.lapor'),
            ],
            'unduh' => collect(['pdf', 'xlsx', 'csv', 'json'])->mapWithKeys(fn ($f) => [$f => $f === 'pdf' ? null : route('energi.lapor.unduh', [$laporan, $f])]),
            'kembali' => route('energi.lapor.index'),
        ]);
    }

    /** Susun ulang draf dari data terbaru (hanya draf). */
    public function susunUlang(Request $request, Laporan $laporan)
    {
        abort_unless($laporan->status === 'draf', 422, 'Hanya draf yang dapat disusun ulang.');
        $this->bolehSusun($request, $laporan->templat);

        $laporan->update(['snapshot' => Pelaporan::susun($laporan->templat, $laporan->company_id, $laporan->periode_mulai, $laporan->periode_sampai)]);

        return back()->with('ok', 'Draf disusun ulang dari data terbaru.');
    }

    public function ajukan(Request $request, Laporan $laporan)
    {
        abort_unless($laporan->status === 'draf', 422);
        $this->bolehSusun($request, $laporan->templat);
        $laporan->update(['status' => 'diajukan', 'diajukan_oleh' => $request->user()->id, 'diajukan_pada' => now()]);

        return back()->with('ok', 'Laporan diajukan untuk persetujuan.');
    }

    public function setujui(Request $request, Laporan $laporan)
    {
        abort_unless($laporan->status === 'diajukan', 422);
        abort_unless($request->user()->can('energi.setujui'), 403);
        abort_if(!$request->user()->isAdmin() && (int) $laporan->disusun_oleh === (int) $request->user()->id, 403,
            'Laporan disetujui oleh orang lain daripada penyusunnya.');

        $laporan->update(['status' => 'disetujui', 'disetujui_oleh' => $request->user()->id, 'disetujui_pada' => now()]);

        return back()->with('ok', 'Laporan disetujui. Pengesah dapat menerbitkannya.');
    }

    public function terbitkan(Request $request, Laporan $laporan)
    {
        abort_unless($laporan->status === 'disetujui', 422);
        abort_unless($request->user()->can('energi.setujui') || $request->user()->isKtt(), 403);

        // Snapshot akhir disusun ulang dari periode terkunci, lalu dibekukan.
        $snap = Pelaporan::susun($laporan->templat, $laporan->company_id, $laporan->periode_mulai, $laporan->periode_sampai);
        $v = Pelaporan::validasi($laporan->templat, $snap, $laporan->company_id);
        if ($v['blokir']) {
            return back()->withErrors(['laporan' => 'Laporan belum dapat terbit: '.implode(' ', $v['blokir'])]);
        }

        $snap['validasi'] = $v;
        $snap = json_decode(json_encode($snap), true);
        $c = $laporan->company_id ? Company::withoutGlobalScopes()->find($laporan->company_id) : null;
        $nomor = KopDokumen::untuk(Pelaporan::TEMPLAT[$laporan->templat]['kop'] ?? 'laporan-energi', $c)['nomor'];

        $laporan->update([
            'status' => 'terbit', 'snapshot' => $snap, 'hash_snapshot' => Laporan::hash($snap),
            'nomor_dokumen' => $nomor ? $nomor.' Rev. '.str_pad((string) $laporan->versi, 2, '0', STR_PAD_LEFT) : null,
            'diterbitkan_oleh' => $request->user()->id, 'diterbitkan_pada' => now(),
        ]);

        // Versi yang digantikan berhenti berlaku.
        if ($laporan->pengganti_id) Laporan::whereKey($laporan->pengganti_id)->update(['status' => 'diganti']);

        ActivityLog::write('Terbitkan laporan energi', $laporan->judul.' v'.$laporan->versi, 'energi');

        return back()->with('ok', 'Laporan terbit — snapshot dibekukan (SHA-256 '.substr($laporan->hash_snapshot, 0, 12).'…).');
    }

    /** Versi baru dari laporan terbit/perlu revisi. */
    public function revisi(Request $request, Laporan $laporan)
    {
        abort_unless(in_array($laporan->status, ['terbit', 'perlu_revisi'], true), 422);
        $this->bolehSusun($request, $laporan->templat);

        $baru = Laporan::create([
            'company_id' => $laporan->company_id, 'templat' => $laporan->templat, 'tahun' => $laporan->tahun,
            'periode_mulai' => $laporan->periode_mulai->toDateString(), 'periode_sampai' => $laporan->periode_sampai->toDateString(),
            'versi' => $laporan->versi + 1, 'status' => 'draf', 'judul' => $laporan->judul,
            'snapshot' => Pelaporan::susun($laporan->templat, $laporan->company_id, $laporan->periode_mulai, $laporan->periode_sampai),
            'disusun_oleh' => $request->user()->id, 'pengganti_id' => $laporan->id,
        ]);

        return redirect()->route('energi.lapor.show', $baru)->with('ok', 'Versi '.$baru->versi.' disusun sebagai draf.');
    }

    /** Catat penyampaian ke regulator (tanda terima POME, SRN, dsb.). */
    public function sampaikan(Request $request, Laporan $laporan)
    {
        abort_unless($laporan->status === 'terbit', 422);
        abort_unless($request->user()->can('energi.lapor'), 403);
        $d = $request->validate([
            'disampaikan_pada'  => ['required', 'date', 'before_or_equal:today'],
            'bukti_penyampaian' => ['required', 'string', 'max:255'],
        ]);
        $laporan->update($d);
        ActivityLog::write('Catat penyampaian laporan energi', $laporan->judul, 'energi');

        return back()->with('ok', 'Penyampaian dicatat — kewajiban di kalender ditandai patuh.');
    }

    public function unduh(Request $request, Laporan $laporan, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'json'], true), 404);

        return EksporLaporan::unduh($laporan, $format);
    }

    /* ── bantu ── */

    private function bolehSusun(Request $request, string $templat): void
    {
        $u = $request->user();
        $peran = Pelaporan::TEMPLAT[$templat]['peran'] ?? 'energi';
        $ok = $u->can('energi.lapor') || ($peran === 'bod' && $u->can('energi.bod'));

        abort_unless($ok, 403, 'Laporan disusun Manajer Energi, Petugas GRK, atau Keuangan.');
    }

    private function baris(Laporan $l): array
    {
        return [
            'id' => $l->id, 'templat' => $l->templat, 'judul' => $l->judul, 'tahun' => $l->tahun, 'versi' => $l->versi,
            'status' => $l->status, 'nomor_dokumen' => $l->nomor_dokumen, 'hash' => $l->hash_snapshot,
            'periode' => [$l->periode_mulai?->toDateString(), $l->periode_sampai?->toDateString()],
            'penyusun' => $l->penyusun?->name, 'penyetuju' => $l->penyetuju?->name,
            'diterbitkan_pada' => $l->diterbitkan_pada?->format('d/m/Y'), 'disampaikan' => (bool) $l->disampaikan_pada,
            'tautan' => route('energi.lapor.show', $l),
        ];
    }
}
