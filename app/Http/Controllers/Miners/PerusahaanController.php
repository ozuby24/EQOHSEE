<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Master Data → Perusahaan — `master_company` Safe Track: daftar IUP
 * beserta mitra (IUJP)-nya, dan identitas yang dicetak pada kartu serta
 * judul kolom persetujuan (nama pendek, KTT, channel darurat).
 *
 * HUBUNGAN IUP–IUJP SATU INDUK. Safe Track menyimpannya banyak-ke-banyak
 * (`iup_iujp`); EQOHSEE menyimpan satu `parent_id`, dan seluruh batas
 * data aplikasi bersandar padanya. Layar ini mengubah induk itu — karena
 * itu hanya administrator yang boleh, sama seperti Kelola Perusahaan.
 */
class PerusahaanController extends Controller
{
    public function index()
    {
        $semua = Company::query()->orderBy('name')->get();

        $iup = $semua->whereNull('parent_id')->reject(fn (Company $c) => $c->isJasa() && $semua->where('parent_id', $c->id)->isEmpty());

        $baris = collect();
        foreach ($iup as $c) {
            $baris->push($this->baris($c, 'IUP', null));

            foreach ($semua->where('parent_id', $c->id) as $anak) {
                $baris->push($this->baris($anak, 'IUJP', $c));
            }
        }

        /* Mitra tanpa induk tetap tampil: justru itu yang perlu dipasangkan. */
        foreach ($semua->whereNull('parent_id')->filter(fn (Company $c) => $c->isJasa() && ! $iup->contains('id', $c->id)) as $lepas) {
            $baris->push($this->baris($lepas, 'IUJP', null));
        }

        return Inertia::render('Miners/Perusahaan', [
            'judul' => 'Data Perusahaan',
            'kop'   => false,
            'baris' => $baris->values(),
            'iup'   => $iup->map(fn (Company $c) => ['nilai' => $c->id, 'label' => $c->name])->values(),
        ]);
    }

    public function update(Request $r, Company $company)
    {
        $data = $r->validate([
            'nama_pendek'     => ['nullable', 'string', 'max:60'],
            'code'            => ['nullable', 'string', 'max:20'],
            'parent_id'       => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('parent_id'),
                                  Rule::notIn([$company->id])],
            'ktt'             => ['nullable', 'string', 'max:150'],
            'jabatan_ktt'     => ['nullable', 'string', 'max:120'],
            'no_izin'         => ['nullable', 'string', 'max:120'],
            'channel_darurat' => ['nullable', 'string', 'max:120'],
            'theme_color'     => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'parent_id.exists' => 'Induk harus sebuah IUP (perusahaan tanpa induk).',
            'parent_id.not_in' => 'Perusahaan tidak dapat menjadi induk dirinya sendiri.',
        ]);

        /* IUP yang masih punya mitra tidak dapat dijadikan mitra IUP lain
           — batas data hanya mengenal satu tingkat induk. */
        if (! empty($data['parent_id']) && Company::query()->where('parent_id', $company->id)->exists()) {
            return back()->withErrors(['parent_id' => $company->name.' masih menjadi induk mitra lain.']);
        }

        $company->update($data);

        return back()->with('sukses', 'Data '.$company->name.' diperbarui.');
    }

    /** @return array<string,mixed> */
    private function baris(Company $c, string $jenis, ?Company $induk): array
    {
        return [
            'id'          => $c->id,
            'nama'        => $c->name,
            'kode'        => $c->code,
            'jenis'       => $jenis,
            'induk'       => $induk?->name,
            'izin'        => $c->izin_type,
            'nilai'       => $c->only(['nama_pendek', 'code', 'parent_id', 'ktt', 'jabatan_ktt',
                                       'no_izin', 'channel_darurat', 'theme_color']),
            'ktt'         => $c->ktt,
            'nama_pendek' => $c->nama_pendek,
        ];
    }
}
