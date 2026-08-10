<?php

namespace App\Http\Controllers;

use App\Models\{Document, DocumentIso};
use App\Support\{Iso, KopDokumen, Pillars};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('iso.index', [
            'standar'  => $standar,
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

        return view('iso.show', [
            'standar' => $s,
            'kode'    => $standar,
            'warna'   => Iso::warna($standar),
            'perBab'  => Iso::perBab($standar),
            'peta'    => $this->dokumenPerKlausul($standar),
            'cakupan' => Iso::cakupan($standar, $this->hitungPerKlausul()[$standar] ?? []),
        ]);
    }

    /** Matriks pemenuhan siap cetak, berkop dokumen terkendali. */
    public function cetak(string $standar)
    {
        $s = Iso::get($standar);
        abort_if(!$s, 404, 'Standar tidak dikenal.');

        return view('iso.cetak', [
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
