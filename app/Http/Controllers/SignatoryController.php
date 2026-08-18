<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, Signatory};
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Support\Berkas;

class SignatoryController extends Controller
{
    public function index()
    {
        return Inertia::render('PenandaTangan/Daftar', [
            'judul'    => 'Penanda Tangan Sertifikat',
            'subjudul' => 'Nama yang tercetak pada sertifikat yang diterbitkan',

            'penandaTangan' => Signatory::with('company')
                ->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (Signatory $s) => [
                    'id'        => $s->id,
                    'nama'      => $s->name,
                    'jabatan'   => (string) ($s->title ?? ''),
                    'aktif'     => (bool) $s->is_active,
                    'perusahaanId' => $s->company_id,
                    'perusahaan'   => $s->company?->name,
                    'tandaTangan' => Berkas::url($s, 'ttd'),
                    'urlSimpan' => route('signatories.update', $s),
                    'urlHapus'  => route('signatories.destroy', $s),
                ])->all(),

            /* Perusahaan pemilik dipilih tegas, bukan disimpulkan dari
               siapa yang menambahkan. Tanda tangan adalah pernyataan
               pertanggungjawaban seseorang; menebak pemiliknya berarti
               menempelkan namanya pada dokumen perusahaan yang belum
               tentu benar. Kosong berarti penanda tangan pusat yang
               boleh dipakai seluruh perusahaan — dan itu pilihan yang
               harus disengaja. */
            'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                ->map(fn (Company $c) => ['id' => $c->id, 'nama' => $c->name])->all(),

            'tautan' => ['tambah' => route('signatories.store')],
        ]);
    }

    public function store(Request $r)
    {
        Signatory::create($this->v($r));
        ActivityLog::write('Tambah penanda tangan', $r->input('name'));
        return back()->with('ok', 'Penanda tangan ditambahkan.');
    }

    public function update(Request $r, Signatory $signatory)
    {
        $signatory->update($this->v($r));
        return back()->with('ok', 'Penanda tangan diperbarui.');
    }

    public function destroy(Signatory $signatory)
    {
        $signatory->delete();
        return back()->with('ok', 'Penanda tangan dihapus.');
    }

    private function v(Request $r): array
    {
        $d = $this->pemilik($r->validate([
            'name'       => ['required','string','max:150'],
            'title'      => ['nullable','string','max:150'],
            'company_id' => ['nullable','exists:companies,id'],
            'is_active'  => ['nullable','boolean'],
            'signature'  => array_merge(['nullable'], Berkas::ATURAN_GAMBAR),
        ]));

        // Pengguna biasa tidak dapat menitipkan tanda tangan ke
        // perusahaan lain lewat isian; hanya administrator yang memilih
        // pemiliknya secara bebas.
        if (!$r->user()?->isAdmin()) {
            $d['company_id'] = $r->user()?->company_id;
        } else {
            $d['company_id'] = $d['company_id'] ?? null;
        }
        $d['is_active'] = (bool) ($d['is_active'] ?? false);   // kolom NOT NULL

        if ($r->hasFile('signature')) {
            $d['signature'] = Berkas::simpan($r->file('signature'), 'signatures');
        } else {
            unset($d['signature']);
        }
        return $d;
    }
}
