<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Models\Miners\Permit;
use App\Models\User;
use App\Support\Miners\AksesMiners;
use App\Support\Miners\Kartu\{DataKartu, SkemaCetak};
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Cetak Kartu — Kartu\KartuController Safe Track.
 *
 * Daftar Mine Permit TERBIT dan AKTIF dalam jangkauan (pengelola: semua;
 * pengaju: miliknya), dipilih sungguhan lalu dicetak: satu kartu CR80
 * depan-belakang, atau banyak kartu pada lembar A4 menurut tata cetak yang
 * disimpan per perusahaan. Kartu tanpa foto dilewati dengan alasannya.
 * Halaman cetak tidak disimpan di tembolok peramban (TanpaSimpan).
 */
class KartuController extends Controller
{
    public function index(Request $r)
    {
        $u    = $r->user();
        $cari = mb_strtolower(trim((string) $r->query('cari', '')));

        $baris = $this->permit($u)->with(['pekerja.company:id,name', 'mcuOrang:id,jabatan,nama', 'iup:id,name'])
            ->orderByDesc('tanggal')->orderByDesc('id')->get()
            ->map(fn (Permit $p) => [
                'id'         => $p->id,
                'nomor'      => $p->no_registrasi,
                'nama'       => $p->pekerja?->nama ?? $p->mcuOrang?->nama,
                'jabatan'    => $p->mcuOrang?->jabatan,
                'perusahaan' => $p->pekerja?->company?->name,
                'jenis'      => $p->jenis_kartu ?: 'Mine Permit',
                'berlaku'    => $p->habisEfektif()?->toDateString(),
                'foto'       => (bool) $p->foto,
            ])
            ->when($cari !== '', fn (Collection $c) => $c->filter(fn ($b) => str_contains(mb_strtolower($b['nama'].' '.$b['nomor'].' '.$b['perusahaan']), $cari)))
            ->values();

        return Inertia::render('Miners/CetakKartu', [
            'judul'  => 'Cetak Kartu',
            'kop'    => false,
            'cari'   => $r->query('cari', ''),
            'baris'  => $baris,
            'skema'  => SkemaCetak::tersimpan($u),
            'pilihanSkema' => SkemaCetak::SKEMA,
            'kelola' => AksesMiners::kelola($u),
            /* Penjelasan daftar kosong (Safe Track): kartu muncul sesudah
               Mine Permit disetujui KTT dan masih aktif. */
            'adaPermit' => Permit::query()->exists(),
        ]);
    }

    /** Satu kartu, depan dan belakang. */
    public function cetak(Request $r, string $permit)
    {
        $p = $this->permit($r->user())->findOrFail((int) $permit);

        return $this->tanpaSimpan(Inertia::render('Print/KartuCetak', [
            'k'       => DataKartu::dariPermit($p),
            'kembali' => route('miners.daftar.cetak-kartu', [], false),
        ])->toResponse($r));
    }

    /** Lembar A4 banyak kartu (`kartu/lembar`). */
    public function lembar(Request $r)
    {
        $r->validate(['id' => ['required', 'array', 'min:1', 'max:500'], 'id.*' => ['integer']], [
            'id.required' => 'Pilih sedikitnya satu kartu.',
        ]);

        $s = SkemaCetak::rapikan(array_merge(SkemaCetak::tersimpan($r->user()), $r->only(array_keys(SkemaCetak::bawaan()))));

        $permit = $this->permit($r->user())->whereIn('id', $r->input('id'))->orderBy('id')->get();

        [$ada, $tanpa] = $permit->partition(fn (Permit $p) => (bool) $p->foto);

        $petak  = SkemaCetak::petak($s);
        $jumlah = $ada->count();

        $ringkas = sprintf('%s · %s %s · %d kolom × %d baris = %d kartu per lembar · %d kartu = %d lembar%s',
            SkemaCetak::SKEMA[$s['skema']], strtoupper($s['kertas']), $s['orientasi'] === 'landscape' ? 'mendatar' : 'tegak',
            $petak['kolom'], $petak['baris'], $petak['per_lembar'], $jumlah, (int) ceil($jumlah / max(1, $petak['per_lembar'])),
            $s['skema'] === 'bolak-balik' ? ' (bolak-balik)' : '');

        return $this->tanpaSimpan(Inertia::render('Print/KartuLembar', [
            'kartu'    => $ada->map(fn (Permit $p) => DataKartu::dariPermit($p))->values(),
            'dilewati' => $tanpa->map(fn (Permit $p) => $p->pekerja?->nama ?? $p->no_registrasi)->values(),
            'skema'    => $s,
            'petak'    => $petak,
            'ringkas'  => e($ringkas),
            'kembali'  => route('miners.daftar.cetak-kartu', [], false),
        ])->toResponse($r));
    }

    /** Simpan tata cetak sebagai preset perusahaan (`POST kartu/skema`). */
    public function simpanTata(Request $r)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Tata cetak hanya diubah admin atau HSE IUP.');

        $data = $r->validate([
            'nama'         => ['nullable', 'string', 'max:60'],
            'skema'        => ['required', 'in:'.implode(',', array_keys(SkemaCetak::SKEMA))],
            'kertas'       => ['required', 'in:'.implode(',', array_keys(SkemaCetak::KERTAS))],
            'orientasi'    => ['required', 'in:portrait,landscape'],
            'sela'         => ['required', 'numeric'],
            'sela_lipat'   => ['required', 'numeric'],
            'margin'       => ['required', 'numeric'],
            'garis_potong' => ['required', 'boolean'],
        ]);

        SkemaCetak::simpan($r->user(), $data['nama'] ?: 'Bawaan', $data);

        return back()->with('sukses', 'Tata cetak kartu disimpan.');
    }

    /** Mine Permit yang boleh dicetak pengguna ini: terbit dan aktif, dalam jangkauannya. */
    private function permit(User $u)
    {
        return Permit::query()->where('status', 'terbit')->where('aktif', true)
            ->when(! AksesMiners::kelola($u), fn ($q) => $q->where('user_id', $u->id));
    }

    /** `TanpaSimpan` Safe Track — kartu berisi data diri, jangan tertinggal di tembolok. */
    private function tanpaSimpan($res)
    {
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $res->headers->set('Pragma', 'no-cache');

        return $res;
    }
}
