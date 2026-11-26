<?php

namespace App\Http\Controllers\Meranti;

use App\Http\Controllers\Controller;
use App\Models\Meranti as M;
use App\Models\User;
use App\Support\Meranti\{Impor, Katalog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

/**
 * MERANTI — halaman Data Master: log aktivitas, pengguna & peran, impor
 * lembar Google Sheets, dan setelan folder Drive arsip capaian.
 *
 * Seluruhnya dijaga gerbang meranti.master (peran Data Master atau
 * administrator), sama dengan menu yang hanya tampil bagi peran itu di
 * MERANTI.
 */
class KelolaController extends Controller
{
    use BantuMeranti;

    /* ═══════════ log aktivitas ═══════════ */

    public function log(Request $request)
    {
        Gate::authorize('meranti.master');
        $c = $this->perusahaanAktif($request);
        $q = $this->kueri(M\Log::class, $c)
            ->when($request->query('aksi'), fn ($q, $a) => $q->where('aksi', $a))
            ->when($request->query('cari'), fn ($q, $s) => $q->where(fn ($w) => $w->where('modul', 'like', "%{$s}%")
                ->orWhere('detail', 'like', "%{$s}%")->orWhere('pengguna', 'like', "%{$s}%")))
            ->orderByDesc('waktu')->orderByDesc('id');

        return Inertia::render('Meranti/Log', [
            'subjudul' => 'Riwayat tambah, ubah, hapus, dan impor — termasuk riwayat aplikasi MERANTI lama.',
            'log' => $q->paginate(50)->withQueryString()->through(fn (M\Log $l) => [
                'id' => $l->id, 'waktu' => $l->waktu?->format('Y-m-d H:i'), 'pengguna' => $l->pengguna, 'aksi' => $l->aksi,
                'modul' => $l->modul, 'detail' => $l->detail, 'impor' => $l->asal_ref !== null,
            ]),
            'aksi' => $this->kueri(M\Log::class, $c)->distinct()->orderBy('aksi')->pluck('aksi'),
            'saring' => ['aksi' => $request->query('aksi'), 'cari' => $request->query('cari')],
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ pengguna & peran ═══════════ */

    public function pengguna(Request $request)
    {
        Gate::authorize('meranti.master');
        $c = $this->perusahaanAktif($request);

        $akun = User::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('name')->limit(500)
            ->get(['id', 'name', 'email', 'position', 'is_admin', 'meranti_role', 'active'])
            ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name, 'email' => $u->email, 'jabatan' => $u->position, 'admin' => (bool) $u->is_admin,
                'aktif' => (bool) $u->active, 'peran' => $u->meranti_role, 'efektif' => $this->peranEfektif($u)]);

        return Inertia::render('Meranti/Pengguna', [
            'subjudul' => 'Empat peran MERANTI: Data Master, EMC, Crew, Manager.',
            // `akun`, bukan `pengguna`: nama itu prop bersama untuk pengguna yang sedang masuk.
            'akun' => $akun,
            'peran' => collect(User::PERAN_MERANTI)->map(fn ($l, $k) => ['nilai' => $k, 'label' => $l])->values()->all(),
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    private function peranEfektif(User $u): string
    {
        if ($u->is_admin) return 'Administrator — akses penuh';

        return User::PERAN_MERANTI[$u->meranti_role ?? ''] ?? 'Bawaan — lihat & tambah data (setara Crew)';
    }

    public function aturPeran(Request $request, int $user)
    {
        Gate::authorize('meranti.master');
        $data = $request->validate(['peran' => ['nullable', Rule::in(array_keys(User::PERAN_MERANTI))]]);

        // Lingkup User membatasi pengguna biasa ke perusahaannya sendiri.
        $u = User::query()->whereKey($user)->firstOrFail();
        if (!$request->user()->isAdmin() && $u->company_id !== $request->user()->company_id) abort(404);

        $u->forceFill(['meranti_role' => $data['peran'] ?: null])->save();
        $this->catat($request, $u->company_id, 'Ubah', 'Pengguna & Peran', $u->name.' → '.(User::PERAN_MERANTI[$u->meranti_role ?? ''] ?? 'bawaan'));

        return back()->with('ok', 'Peran MERANTI '.$u->name.' diperbarui.');
    }

    /* ═══════════ impor lembar Google Sheets ═══════════ */

    public function impor(Request $request)
    {
        Gate::authorize('meranti.master');
        $c = $this->perusahaanSimpan($request);

        return Inertia::render('Meranti/Impor', [
            'subjudul' => 'Pindahkan data lembar Google Sheets MERANTI ke EQOHSEE — aman diulang, tidak menggandakan.',
            'hasil' => $request->session()->get('hasilImpor'),
            'isi' => collect(Katalog::semua())->map(fn ($m, $k) => ['kunci' => $k, 'judul' => $m['judul'], 'grup' => $m['grup'], 'slug' => $m['slug'],
                'n' => $this->kueri($m['kelas'], $c)->count(), 'impor' => $this->kueri($m['kelas'], $c)->whereNotNull('asal_ref')->count()])->values()->all(),
            'tujuan' => ['id' => $c, 'nama' => $this->namaPerusahaan($c)],
            'perusahaan' => $this->perusahaanAktif($request), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function jalankanImpor(Request $request)
    {
        Gate::authorize('meranti.master');
        $data = $request->validate([
            'sumber' => ['required', Rule::in(['berkas', 'tautan'])],
            'berkas' => ['required_if:sumber,berkas', 'nullable', 'file', 'max:30720'],
            'tautan' => ['required_if:sumber,tautan', 'nullable', 'string', 'max:500'],
        ], [], ['berkas' => 'berkas .xlsx/.csv', 'tautan' => 'tautan Google Sheets']);

        $c = $this->perusahaanSimpan($request);
        if (!$c) return back()->withErrors(['sumber' => 'Pilih perusahaan tujuan lebih dulu — data MERANTI selalu milik satu perusahaan.']);

        $sementara = null;
        try {
            [$buku, $label] = match ($data['sumber']) {
                'berkas' => (function () use ($request) {
                    $f = $request->file('berkas');
                    $nama = $f->getClientOriginalName();
                    if (preg_match('/\.csv$/i', $nama)) return [Impor::bacaCsv($f->getRealPath(), $nama), $nama];
                    if (!preg_match('/\.xlsx$/i', $nama)) throw new RuntimeException('Unggah berkas .xlsx (Google Sheets: File → Download → Microsoft Excel) atau .csv ekspor MERANTI.');

                    return [Impor::bacaXlsx($f->getRealPath()), $nama];
                })(),
                'tautan' => (function () use ($data, &$sementara) {
                    $sementara = Impor::unduhTautan($data['tautan']);

                    return [Impor::bacaXlsx($sementara), 'Google Sheets '.Impor::idLembar($data['tautan'])];
                })(),
            };
            $hasil = Impor::jalankan($buku, $c, $request->user(), $label);
        } catch (RuntimeException $e) {
            return back()->withErrors([$data['sumber'] => $e->getMessage()]);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception) {
            return back()->withErrors(['berkas' => 'Berkas .xlsx tidak dapat dibaca.']);
        } finally {
            if ($sementara && is_file($sementara)) @unlink($sementara);
        }

        return back()->with('hasilImpor', $hasil + ['sumber' => $label, 'perusahaan' => $this->namaPerusahaan($c)])
            ->with('ok', "Impor selesai: {$hasil['baru']} baris baru, {$hasil['diperbarui']} diperbarui, {$hasil['dilewati']} dilewati.");
    }

    /* ═══════════ setelan ═══════════ */

    public function setelan(Request $request)
    {
        Gate::authorize('meranti.master');
        $data = $request->validate([
            'folder_drive' => ['nullable', 'url:https', 'max:500', 'regex:~^https://(drive|docs)\.google\.com/~'],
        ], ['folder_drive.regex' => 'Gunakan tautan folder Google Drive (https://drive.google.com/…).'], ['folder_drive' => 'folder Google Drive']);

        $c = $this->perusahaanSimpan($request);
        M\Setelan::withoutGlobalScopes()->updateOrCreate(['company_id' => $c], ['folder_drive' => $data['folder_drive'] ?? null]);
        $this->catat($request, $c, 'Ubah', 'Setelan', 'Folder Drive arsip capaian');

        return back()->with('ok', 'Folder Google Drive arsip capaian disimpan.');
    }
}
