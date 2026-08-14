<?php

namespace App\Http\Controllers;

use App\Models\{Document, DocumentIso};
use App\Support\{Iso, KopDokumen, Pillars};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * ISO — pemenuhan standar oleh dokumen terkendali.
 *
 * Register dokumen menjawab "dokumen apa saja yang kita punya". Halaman ini
 * menjawab pertanyaan sebaliknya, yang justru ditanyakan auditor: klausul
 * mana yang belum punya dokumen. Karena itu celah ditampilkan sejelas
 * cakupannya, bukan disembunyikan di balik persentase.
 */
class IsoController extends Controller
{
    /** Ringkasan seluruh standar beserta cakupannya. */
    public function index()
    {
        $hitung = $this->hitungPerKlausul();

        $standar = [];
        foreach (Iso::semua() as $kode => $s) {
            $standar[$kode] = $s + [
                'cakupan' => Iso::cakupan($kode, $hitung[$kode] ?? []),
                'warna'   => Iso::warna($kode),
                'aspek'   => Pillars::get($s['pilar'] ?? '')['nama'] ?? null,
            ];
        }

        return Inertia::render('Iso/Index', [
            'judul'    => 'Pemenuhan Klausul ISO',
            'subjudul' => 'Klausul mana yang sudah — dan belum — punya dokumen',

            'standar' => array_map(fn ($kode) => [
                'kode'     => $kode,
                'nama'     => $standar[$kode]['nama'],
                'judul'    => $standar[$kode]['judul'],
                'ket'      => $standar[$kode]['ket'] ?? '',
                'aspek'    => $standar[$kode]['aspek'],
                'warna'    => $standar[$kode]['warna'],
                'butir'    => $standar[$kode]['cakupan']['butir'],
                'tercakup' => $standar[$kode]['cakupan']['tercakup'],
                'celah'    => count($standar[$kode]['cakupan']['celah']),
                'rasio'    => $standar[$kode]['cakupan']['rasio'],
                'url'      => route('iso.show', $kode),
            ], array_keys($standar)),

            'catatan'  => Iso::catatan(),
            'dokumen'  => Document::count(),
            'dipetakan'=> DocumentIso::distinct('document_id')->count('document_id'),
        ]);
    }

    /** Satu standar: klausul per bab, dokumen yang memenuhinya, dan celahnya. */
    public function show(string $standar)
    {
        $s = Iso::get($standar);
        abort_if(!$s, 404, 'Standar tidak dikenal.');

        $peta    = $this->dokumenPerKlausul($standar);
        $cakupan = Iso::cakupan($standar, $this->hitungPerKlausul()[$standar] ?? []);
        $perBab  = Iso::perBab($standar);

        return Inertia::render('Iso/Detail', [
            'judul'    => $s['nama'],
            'subjudul' => $s['judul'],

            'kode'  => $standar,
            'warna' => Iso::warna($standar),

            'cakupan' => [
                'butir'    => $cakupan['butir'],
                'tercakup' => $cakupan['tercakup'],
                'celah'    => count($cakupan['celah']),
                'rasio'    => $cakupan['rasio'],
            ],

            // Dokumen yang memenuhi tiap klausul disisipkan ke butirnya
            // sendiri. Mengirim petanya terpisah memaksa sisi Vue mencari
            // ulang per butir, dan klausul tanpa pasangan — justru yang
            // paling perlu terbaca — jadi bergantung pada pencarian itu.
            'bab' => array_map(fn ($nomor) => [
                'nomor'   => $nomor,
                'judul'   => $perBab[$nomor]['judul'],
                'klausul' => array_map(fn ($k) => [
                    'no'    => $k['no'],
                    'judul' => $k['judul'],
                    'dokumen' => ($peta[$k['no']] ?? collect())->map(fn ($d) => [
                        'kode'  => $d->kode,
                        'judul' => \Illuminate\Support\Str::limit($d->judul, 44),
                        'url'   => route('dokumen.show', $d),
                    ])->all(),
                ], $perBab[$nomor]['klausul']),
            ], array_keys($perBab)),

            'tautan' => [
                'cetak'  => route('iso.cetak', $standar),
                'daftar' => route('iso.index'),
            ],
        ]);
    }

    /** Matriks pemenuhan siap cetak, berkop dokumen terkendali. */
    public function cetak(string $standar)
    {
        $s = Iso::get($standar);
        abort_if(!$s, 404, 'Standar tidak dikenal.');

        return Inertia::render('Print/Iso', [
            'standar' => $s,
            'kode'    => $standar,
            'perBab'  => Iso::perBab($standar),
            'peta'    => $this->dokumenPerKlausul($standar),
            'cakupan' => Iso::cakupan($standar, $this->hitungPerKlausul()[$standar] ?? []),
            'dok'     => KopDokumen::untuk('iso-matriks', \App\Models\Company::first()),
            'kembali' => route('iso.show', $standar),
        ]);
    }

    /**
     * Banyak dokumen per klausul, untuk seluruh standar sekaligus.
     *
     * Dihitung satu kueri agar halaman ringkasan tidak menembak basis data
     * sekali per klausul — empat standar berisi ratusan butir.
     *
     * @return array<string,array<string,int>>
     */
    private function hitungPerKlausul(): array
    {
        $baris = DocumentIso::query()
            ->select('standar', 'klausul', DB::raw('COUNT(DISTINCT document_id) as jumlah'))
            ->groupBy('standar', 'klausul')
            ->get();

        $out = [];
        foreach ($baris as $b) {
            $out[$b->standar][$b->klausul] = (int) $b->jumlah;
        }
        return $out;
    }

    /**
     * Dokumen yang memenuhi tiap klausul pada satu standar.
     * @return array<string,\Illuminate\Support\Collection>
     */
    private function dokumenPerKlausul(string $standar)
    {
        return DocumentIso::with('document')
            ->where('standar', $standar)
            ->get()
            ->filter(fn ($m) => $m->document !== null)
            ->groupBy('klausul')
            ->map(fn ($g) => $g->pluck('document')->sortBy('kode')->values());
    }
}
