<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, AksesAudit, Company, User};
use App\Support\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pusat Kendali → Aturan Akses Audit (port DBEST #16, khusus administrator).
 *
 * Per perusahaan: AUDITOR (akun yang boleh mengaudit perusahaan itu di Audit
 * SMKP dan Audit Lingkungan) dan PENGUNGGAH DATA (akun perusahaan yang
 * mengunggah bukti dan menjawab temuan/RTP). Administrator tetap mengaudit
 * semua perusahaan. Aturan independensi: satu akun tidak boleh menjadi
 * auditor sekaligus pengunggah perusahaan yang sama.
 */
class AksesAuditController extends Controller
{
    public function index(Request $request)
    {
        $lihat = Perusahaan::terpilih($request->user());
        $perusahaan = Company::query()
            ->when($lihat, fn ($q) => $q->where(fn ($w) => $w->whereKey($lihat)->orWhere('parent_id', $lihat)))
            ->orderBy('name')->get(['id', 'name', 'code', 'parent_id', 'kategori']);

        $tugas = AksesAudit::with('user:id,name,email,company_id,audit_role,is_admin')->whereIn('company_id', $perusahaan->pluck('id'))
            ->orderBy('id')->get()->groupBy('company_id');

        $baris = $perusahaan->map(function (Company $c) use ($tugas) {
            $t = $tugas->get($c->id, collect());
            $orang = fn (string $peran) => $t->where('peran', $peran)->filter->user->map(fn (AksesAudit $a) => [
                'id' => $a->id, 'nama' => $a->user->name, 'email' => $a->user->email,
            ])->values()->all();

            return [
                'id' => $c->id, 'nama' => $c->name, 'kode' => (string) $c->code, 'induk' => !$c->parent_id,
                'auditor' => $orang(AksesAudit::AUDITOR), 'pengunggah' => $orang(AksesAudit::PENGUNGGAH),
            ];
        })->sortBy(fn ($b) => [($b['auditor'] || $b['pengunggah']) ? 0 : 1, mb_strtolower($b['nama'])])->values();

        return Inertia::render('Admin/AksesAudit', [
            'judul'  => 'Aturan Akses Audit',
            'baris'  => $baris,
            'peran'  => AksesAudit::PERAN,
            'akun'   => User::query()->where('is_admin', false)->where(fn ($w) => $w->where('active', true)->orWhereNull('active'))->orderBy('name')
                ->get(['id', 'name', 'email', 'company_id', 'audit_role'])
                ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name, 'email' => $u->email, 'auditor' => $u->bisaAudit()])->values(),
        ]);
    }

    public function simpan(Request $request)
    {
        $d = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'user_id'    => ['required', 'integer', Rule::exists('users', 'id')],
            'peran'      => ['required', Rule::in(array_keys(AksesAudit::PERAN))],
        ]);

        $u = User::findOrFail($d['user_id']);
        $lain = AksesAudit::where('company_id', $d['company_id'])->where('user_id', $u->id)->first();
        if ($lain && $lain->peran !== $d['peran']) {
            return back()->withErrors(['user_id' => 'Aturan independensi: '.$u->name.' sudah menjadi '
                .mb_strtolower(AksesAudit::PERAN[$lain->peran]).' perusahaan ini — satu akun tidak boleh menjadi auditor sekaligus pengunggah perusahaan yang sama.']);
        }
        if ($d['peran'] === AksesAudit::AUDITOR && !$u->bisaAudit()) {
            return back()->withErrors(['user_id' => $u->name.' belum berperan auditor. Ubah Peran Audit-nya di Kelola Pengguna lebih dulu.']);
        }

        AksesAudit::updateOrCreate(['company_id' => $d['company_id'], 'user_id' => $u->id], ['peran' => $d['peran'], 'dibuat_oleh' => $request->user()->id]);
        $pt = Company::find($d['company_id'])?->name;
        ActivityLog::write('Tetapkan akses audit', $u->name.' · '.AksesAudit::PERAN[$d['peran']].' · '.$pt, 'admin');

        return back()->with('ok', $u->name.' ditetapkan sebagai '.mb_strtolower(AksesAudit::PERAN[$d['peran']]).' '.$pt.'.');
    }

    public function hapus(Request $request, AksesAudit $akses)
    {
        $akses->load('user', 'company');
        ActivityLog::write('Cabut akses audit', ($akses->user?->name ?? '').' · '.($akses->company?->name ?? ''), 'admin');
        $akses->delete();

        return back()->with('ok', 'Penugasan dicabut.');
    }
}
