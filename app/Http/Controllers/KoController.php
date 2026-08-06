<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, KoAction, KoInspection, KoObject, KoPersonnel, KoReview, KoSafeguard, User};
use App\Support\Ko;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class KoController extends Controller
{
    /* ================= dasar ================= */

    /** superadmin/admin EQOHSEE melihat semua; selain itu terbatas perusahaannya. */
    private function lingkup()
    {
        $u = auth()->user();
        $q = KoObject::query()->with(['company', 'safeguards', 'reviews']);

        if (!$u->isAdmin() && $u->company_id) $q->where('company_id', $u->company_id);

        if ($u->isAdmin()) {
            $co = request('perusahaan');
            if ($co && $co !== 'ALL') $q->where('company_id', (int) $co);
        }

        return $q;
    }

    private function tenagaLingkup()
    {
        $u = auth()->user();
        $q = KoPersonnel::query()->with('company');

        if (!$u->isAdmin() && $u->company_id) $q->where('company_id', $u->company_id);

        if ($u->isAdmin()) {
            $co = request('perusahaan');
            if ($co && $co !== 'ALL') $q->where('company_id', (int) $co);
        }

        return $q;
    }

    /** Boleh menambah/mengubah data KO. */
    private function bolehUbah(): bool
    {
        $u = auth()->user();
        return $u->isAdmin() || $u->ko_role === 'pengawas';
    }

    private function pastikanUbah(): void
    {
        abort_unless($this->bolehUbah(), 403, 'Peran Anda hanya boleh membaca data KO.');
    }

    private function pastikanHapus(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh menghapus.');
    }

    private function bersama(): array
    {
        return [
            'perusahaan' => Company::orderBy('name')->get(),
            'bolehUbah'  => $this->bolehUbah(),
            'set'        => Ko::settings(),
        ];
    }

    private function catat(string $aksi, string $ket = ''): void
    {
        try { ActivityLog::write($aksi, $ket, 'ko'); } catch (\Throwable $e) {}
    }

    /* ================= 1. Dashboard ================= */

    public function index()
    {
        $objek  = $this->lingkup()->get();
        $tenaga = $this->tenagaLingkup()->get();
        $c      = Ko::hitung($objek, $tenaga);

        return view('ko.dashboard', $this->bersama() + [
            'c'          => $c,
            'sub'        => Ko::subElemen($c),
            'peringatan' => array_slice(Ko::peringatan($objek), 0, 12),
            'objek'      => $objek,
            'aksiTerbuka'=> KoAction::whereIn('status', ['Terbuka', 'Berjalan'])->count(),
        ]);
    }

    /* ================= 2. Register objek ================= */

    public function register(Request $r)
    {
        $q = $this->lingkup();

        if ($cari = trim((string) $r->get('q'))) {
            $q->where(function ($w) use ($cari) {
                foreach (['kode', 'nama', 'jenis', 'merk', 'serial_number', 'lokasi'] as $k) {
                    $w->orWhere($k, 'like', "%{$cari}%");
                }
            });
        }
        if ($kat = $r->get('kat'))  $q->where('kategori', $kat);
        if ($ops = $r->get('ops'))  $q->where('status_operasi', $ops);

        $objek = $q->orderBy('kode')->get();

        if ($st = $r->get('st')) {
            $objek = $objek->filter(fn ($o) => Ko::status($o) === $st)->values();
        }

        return view('ko.register', $this->bersama() + [
            'objek' => $objek,
            'q'     => $cari ?? '',
            'kat'   => $kat, 'ops' => $ops, 'st' => $st,
        ]);
    }

    public function create()
    {
        $this->pastikanUbah();

        return view('ko.form', $this->bersama() + ['o' => new KoObject(['interval_tahun' => 3])]);
    }

    public function edit(KoObject $objek)
    {
        $this->pastikanUbah();

        return view('ko.form', $this->bersama() + ['o' => $objek]);
    }

    public function store(Request $r)
    {
        $this->pastikanUbah();
        $d = $this->validasiObjek($r);

        $o = KoObject::create($d);
        $this->catat('Tambah objek KO', $o->kode . ' — ' . $o->nama);

        return redirect()->route('ko.show', $o)->with('ok', 'Objek ' . $o->kode . ' ditambahkan.');
    }

    public function update(Request $r, KoObject $objek)
    {
        $this->pastikanUbah();
        $d = $this->validasiObjek($r, $objek->id);

        $objek->update($d);
        $this->catat('Ubah objek KO', $objek->kode . ' — ' . $objek->nama);

        return redirect()->route('ko.show', $objek)->with('ok', 'Objek ' . $objek->kode . ' diperbarui.');
    }

    public function destroy(KoObject $objek)
    {
        $this->pastikanHapus();

        $kode = $objek->kode;
        $objek->delete();
        $this->catat('Hapus objek KO', $kode);

        return redirect()->route('ko.register')->with('ok', 'Objek ' . $kode . ' dihapus.');
    }

    private function validasiObjek(Request $r, ?int $abaikan = null): array
    {
        $unik = 'unique:ko_objects,kode' . ($abaikan ? ',' . $abaikan : '');

        return $r->validate([
            'kode'            => ['required', 'string', 'max:50', $unik],
            'nama'            => ['required', 'string', 'max:200'],
            'kategori'        => ['required', 'in:' . implode(',', Ko::KATEGORI)],
            'jenis'           => ['nullable', 'string', 'max:100'],
            'merk'            => ['nullable', 'string', 'max:150'],
            'serial_number'   => ['nullable', 'string', 'max:100'],
            'lokasi'          => ['nullable', 'string', 'max:150'],
            'company_id'      => ['nullable', 'exists:companies,id'],
            'kritikalitas'    => ['required', 'in:' . implode(',', Ko::KRITIS)],
            'status_operasi'  => ['required', 'in:' . implode(',', Ko::OPERASI)],
            'tgl_sertifikasi' => ['nullable', 'date'],
            'interval_tahun'  => ['required', 'integer', 'in:' . implode(',', Ko::INTERVAL)],
            'no_sertifikat'   => ['nullable', 'string', 'max:100'],
            'lembaga_uji'     => ['nullable', 'string', 'max:150'],
            'lapor_kait'      => ['nullable', 'boolean'],
            'pm_jenis'        => ['nullable', 'string', 'max:150'],
            'pm_terakhir'     => ['nullable', 'date'],
            'pm_berikutnya'   => ['nullable', 'date'],
            'keterangan'      => ['nullable', 'string', 'max:2000'],
        ]) + ['lapor_kait' => (bool) $r->boolean('lapor_kait')];
    }

    /* ================= 3. Rincian objek ================= */

    public function show(KoObject $objek)
    {
        $objek->load(['company', 'safeguards', 'reviews.personnel', 'inspections.personnel', 'actions.pic']);

        return view('ko.rincian', $this->bersama() + [
            'o'      => $objek,
            'tenaga' => $this->tenagaLingkup()->orderBy('nama')->get(),
            'user'   => User::orderBy('name')->get(),
            'sigapAda' => Schema::hasTable('sigap_assets'),
        ]);
    }

    /* ================= 4. Kelayakan ================= */

    public function kelayakan()
    {
        $objek = $this->lingkup()->orderBy('kode')->get()
            ->sortBy(fn ($o) => Ko::sisaHari($o) ?? -99999)->values();

        return view('ko.kelayakan', $this->bersama() + [
            'objek' => $objek,
            'c'     => Ko::hitung($objek, $this->tenagaLingkup()->get()),
        ]);
    }

    /* ================= 5. Perawatan ================= */

    public function perawatan()
    {
        $objek = $this->lingkup()->orderBy('kode')->get()
            ->sortBy(fn ($o) => Ko::sisaPm($o) ?? 99999)->values();

        return view('ko.perawatan', $this->bersama() + [
            'objek' => $objek,
            'c'     => Ko::hitung($objek, $this->tenagaLingkup()->get()),
        ]);
    }

    public function catatPm(Request $r, KoObject $objek)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'tanggal'    => ['required', 'date'],
            'berikutnya' => ['nullable', 'date'],
            'hasil'      => ['nullable', 'string', 'max:100'],
            'catatan'    => ['nullable', 'string', 'max:1000'],
            'ko_personnel_id' => ['nullable', 'exists:ko_personnel,id'],
        ]);

        KoInspection::create($d + [
            'ko_object_id' => $objek->id,
            'jenis'        => 'PM',
            'user_id'      => auth()->id(),
        ]);

        $objek->update([
            'pm_terakhir'   => $d['tanggal'],
            'pm_berikutnya' => $d['berikutnya'] ?? $objek->pm_berikutnya,
        ]);

        $this->catat('Catat perawatan', $objek->kode);

        return back()->with('ok', 'Perawatan ' . $objek->kode . ' dicatat.');
    }

    /* ================= 6. Pengaman ================= */

    public function pengaman()
    {
        $objek = $this->lingkup()->orderBy('kode')->get()
            ->filter(fn ($o) => $o->safeguards->count())->values();

        return view('ko.pengaman', $this->bersama() + [
            'objek' => $objek,
            'c'     => Ko::hitung($this->lingkup()->get(), $this->tenagaLingkup()->get()),
            'sigapAda' => Schema::hasTable('sigap_assets'),
        ]);
    }

    public function simpanPengaman(Request $r, KoObject $objek)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'          => ['nullable', 'exists:ko_safeguards,id'],
            'nama'        => ['required', 'string', 'max:150'],
            'spesifikasi' => ['nullable', 'string', 'max:150'],
            'status'      => ['required', 'in:' . implode(',', Ko::PENGAMAN_STATUS)],
            'tgl_periksa' => ['nullable', 'date'],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $p = $d['id']
            ? KoSafeguard::where('ko_object_id', $objek->id)->findOrFail($d['id'])
            : new KoSafeguard(['ko_object_id' => $objek->id]);

        $p->fill(collect($d)->except('id')->all());
        $p->save();

        if ($p->tgl_periksa) {
            KoInspection::create([
                'ko_object_id'    => $objek->id,
                'ko_safeguard_id' => $p->id,
                'jenis'           => 'Pengaman',
                'tanggal'         => $p->tgl_periksa,
                'hasil'           => $p->status,
                'nilai_ukur'      => $p->spesifikasi,
                'catatan'         => $p->catatan,
                'user_id'         => auth()->id(),
            ]);
        }

        $this->catat('Simpan pengaman', $objek->kode . ' — ' . $p->nama);

        return back()->with('ok', 'Perangkat pengaman tersimpan.');
    }

    public function hapusPengaman(KoObject $objek, KoSafeguard $pengaman)
    {
        $this->pastikanHapus();
        abort_unless($pengaman->ko_object_id === $objek->id, 404);

        $nama = $pengaman->nama;
        $pengaman->delete();
        $this->catat('Hapus pengaman', $objek->kode . ' — ' . $nama);

        return back()->with('ok', 'Perangkat pengaman dihapus.');
    }

    /* ================= 7. Kajian teknis ================= */

    public function kajian()
    {
        $kajian = KoReview::with(['object.company', 'personnel'])
            ->whereIn('ko_object_id', $this->lingkup()->select('ko_objects.id'))
            ->latest('tanggal')->get();

        return view('ko.kajian', $this->bersama() + [
            'kajian' => $kajian,
            'objek'  => $this->lingkup()->orderBy('kode')->get(),
            'tenaga' => $this->tenagaLingkup()->orderBy('nama')->get(),
        ]);
    }

    public function simpanKajian(Request $r)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'              => ['nullable', 'exists:ko_reviews,id'],
            'ko_object_id'    => ['required', 'exists:ko_objects,id'],
            'judul'           => ['required', 'string', 'max:200'],
            'pemicu'          => ['nullable', 'string', 'max:100'],
            'tanggal'         => ['nullable', 'date'],
            'ko_personnel_id' => ['nullable', 'exists:ko_personnel,id'],
            'status'          => ['required', 'in:' . implode(',', Ko::KAJIAN_STATUS)],
            'tgl_lapor'       => ['nullable', 'date'],
            'ringkasan'       => ['nullable', 'string', 'max:2000'],
        ]);

        $k = $d['id'] ? KoReview::findOrFail($d['id']) : new KoReview();
        $k->fill(collect($d)->except('id')->all());

        if ($k->ko_personnel_id) {
            $k->oleh = optional(KoPersonnel::find($k->ko_personnel_id))->nama;
        }
        $k->save();

        $this->catat('Simpan kajian teknis', $k->judul);

        return back()->with('ok', 'Kajian teknis tersimpan.');
    }

    public function hapusKajian(KoReview $kajian)
    {
        $this->pastikanHapus();

        $j = $kajian->judul;
        $kajian->delete();
        $this->catat('Hapus kajian teknis', $j);

        return back()->with('ok', 'Kajian dihapus.');
    }

    /* ================= 8. Tenaga teknis ================= */

    public function tenaga()
    {
        return view('ko.tenaga', $this->bersama() + [
            'tenaga' => $this->tenagaLingkup()->orderBy('nama')->get(),
            'user'   => User::orderBy('name')->get(),
        ]);
    }

    public function simpanTenaga(Request $r)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'             => ['nullable', 'exists:ko_personnel,id'],
            'nama'           => ['required', 'string', 'max:150'],
            'jabatan'        => ['nullable', 'string', 'max:150'],
            'company_id'     => ['nullable', 'exists:companies,id'],
            'sertifikasi'    => ['nullable', 'string', 'max:150'],
            'no_sertifikat'  => ['nullable', 'string', 'max:100'],
            'tgl_kadaluarsa' => ['nullable', 'date'],
            'user_id'        => ['nullable', 'exists:users,id'],
        ]);

        $t = $d['id'] ? KoPersonnel::findOrFail($d['id']) : new KoPersonnel();
        $t->fill(collect($d)->except('id')->all());
        $t->save();

        $this->catat('Simpan tenaga teknis', $t->nama);

        return back()->with('ok', 'Data tenaga teknis tersimpan.');
    }

    public function hapusTenaga(KoPersonnel $tenaga)
    {
        $this->pastikanHapus();

        $n = $tenaga->nama;
        $tenaga->delete();
        $this->catat('Hapus tenaga teknis', $n);

        return back()->with('ok', 'Tenaga teknis dihapus.');
    }

    /* ================= 9. Tindak lanjut ================= */

    public function tindak(Request $r)
    {
        $q = KoAction::with(['object.company', 'safeguard', 'pic'])
            ->whereIn('ko_object_id', $this->lingkup()->select('ko_objects.id'));

        if ($st = $r->get('st')) $q->where('status', $st);

        return view('ko.tindak', $this->bersama() + [
            'aksi'  => $q->orderByRaw("CASE status WHEN 'Terbuka' THEN 0 WHEN 'Berjalan' THEN 1 ELSE 2 END")
                         ->orderBy('target_tgl')->get(),
            'st'    => $st,
            'objek' => $this->lingkup()->orderBy('kode')->get(),
            'user'  => User::orderBy('name')->get(),
        ]);
    }

    /** Membuat tindak lanjut dari peringatan yang belum punya tindak lanjut terbuka. */
    public function tarikPeringatan()
    {
        $this->pastikanUbah();

        $objek = $this->lingkup()->get();
        $baru  = 0;

        foreach (Ko::peringatan($objek) as $p) {
            $o  = $p['objek'];
            $pg = $p['pengaman'] ?? null;

            $ada = KoAction::where('ko_object_id', $o->id)
                ->where('sumber', $p['sumber'])
                ->where('ko_safeguard_id', $pg?->id)
                ->whereIn('status', ['Terbuka', 'Berjalan'])
                ->exists();

            if ($ada) continue;

            KoAction::create([
                'ko_object_id'    => $o->id,
                'ko_safeguard_id' => $pg?->id,
                'sumber'          => $p['sumber'],
                'uraian'          => $p['jenis'] . ' — ' . $p['ket'],
                'prioritas'       => $p['pr'] === 0 ? 'Tinggi' : ($p['pr'] === 1 ? 'Sedang' : 'Rendah'),
                'target_tgl'      => now()->addDays($p['pr'] === 0 ? 7 : 30)->toDateString(),
                'status'          => 'Terbuka',
            ]);
            $baru++;
        }

        $this->catat('Tarik peringatan jadi tindak lanjut', $baru . ' item');

        return back()->with('ok', $baru
            ? $baru . ' tindak lanjut dibuat dari peringatan.'
            : 'Semua peringatan sudah punya tindak lanjut terbuka.');
    }

    public function simpanTindak(Request $r)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'           => ['nullable', 'exists:ko_actions,id'],
            'ko_object_id' => ['required', 'exists:ko_objects,id'],
            'sumber'       => ['required', 'string', 'max:50'],
            'uraian'       => ['required', 'string', 'max:2000'],
            'prioritas'    => ['required', 'in:Tinggi,Sedang,Rendah'],
            'pic_user_id'  => ['nullable', 'exists:users,id'],
            'pic_nama'     => ['nullable', 'string', 'max:150'],
            'target_tgl'   => ['nullable', 'date'],
            'status'       => ['required', 'in:' . implode(',', Ko::AKSI_STATUS)],
            'tgl_selesai'  => ['nullable', 'date'],
            'tindakan'     => ['nullable', 'string', 'max:2000'],
        ]);

        $a = $d['id'] ? KoAction::findOrFail($d['id']) : new KoAction();
        $a->fill(collect($d)->except('id')->all());

        if ($a->status === 'Selesai' && !$a->tgl_selesai) $a->tgl_selesai = now()->toDateString();
        $a->save();

        $this->catat('Simpan tindak lanjut KO', $a->uraian);

        return back()->with('ok', 'Tindak lanjut tersimpan.');
    }

    public function hapusTindak(KoAction $tindak)
    {
        $this->pastikanHapus();

        $tindak->delete();
        $this->catat('Hapus tindak lanjut KO');

        return back()->with('ok', 'Tindak lanjut dihapus.');
    }

    /* ================= 10. Pengaturan ================= */

    public function pengaturan()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('ko.pengaturan', $this->bersama());
    }

    public function simpanPengaturan(Request $r)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $d = $r->validate([
            'ko_warn_days'    => ['required', 'integer', 'min:1', 'max:365'],
            'ko_target_layak' => ['required', 'integer', 'min:1', 'max:100'],
            'ko_target_pmc'   => ['required', 'integer', 'min:1', 'max:100'],
            'ko_iv_peralatan' => ['required', 'integer', 'in:' . implode(',', Ko::INTERVAL)],
            'ko_iv_instalasi' => ['required', 'integer', 'in:' . implode(',', Ko::INTERVAL)],
        ]);

        Ko::simpanSettings($d);
        $this->catat('Ubah pengaturan KO', 'Ambang jatuh tempo: ' . $d['ko_warn_days'] . ' hari');

        return back()->with('ok', 'Pengaturan KO tersimpan.');
    }
}
