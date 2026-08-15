<?php

namespace App\Http\Controllers;

use App\Support\Engineering as E;
use Illuminate\Http\Request;

/**
 * Mining Engineering Hub.
 *
 * Halaman acuan rekayasa: indikator armada, energi, pemeliharaan, dan
 * keselamatan beserta rumusnya, ditambah alat hitung yang dipakai
 * sehari-hari. Angkanya data contoh dari App\Support\Engineering — modul
 * Energy Performance yang menyimpan catatan lapangan sungguhan.
 *
 * Seluruh angka turunan dihitung di kelas pendukung itu, bukan di sini
 * maupun di view: satu rumus, satu tempat, dan tidak mungkin dua halaman
 * menampilkan angka berbeda untuk hal yang sama.
 */
class EngineeringController extends Controller
{
    public function index()
    {
        return view('meh.index', [
            'p' => E::ringkasProduksi(),
            'a' => E::ringkasArmada(),
            'e' => E::ringkasEnergi(),
            'm' => E::ringkasPemeliharaan(),
            'tren' => E::trenProduksi(),
        ]);
    }

    public function energy()
    {
        $e = E::ringkasEnergi();

        $program = array_map(fn ($x) => $x + E::nilaiProgram($x), E::programHemat());

        return view('meh.energy', [
            'e' => $e,
            'program' => $program,
            'terwujud' => array_reduce(
                array_filter($program, fn ($x) => in_array($x['status'], ['Berjalan', 'Selesai'], true)),
                fn ($t, $x) => [
                    'gj' => $t['gj'] + $x['gj'],
                    'tco2e' => $t['tco2e'] + $x['tco2e'],
                    'rupiah' => $t['rupiah'] + $x['rupiah'],
                    'jumlah' => $t['jumlah'] + 1,
                ],
                ['gj' => 0, 'tco2e' => 0, 'rupiah' => 0, 'jumlah' => 0]
            ),
        ]);
    }

    public function fleet(Request $request)
    {
        $status = $request->get('status');
        $unit = E::armada();

        if ($status && $status !== 'semua') {
            $unit = array_values(array_filter($unit, fn ($u) => $u['status'] === $status));
        }

        // Diurutkan menurut keborosan; unit paling haus yang paling perlu dilihat.
        usort($unit, fn ($x, $y) => E::fuelRate($y) <=> E::fuelRate($x));

        return view('meh.fleet', [
            'unit' => $unit,
            'status' => $status ?: 'semua',
            'a' => E::ringkasArmada(),
            'p' => E::ringkasProduksi(),
            'acuan' => E::acuanKelas(),
        ]);
    }

    public function equipment()
    {
        return view('meh.equipment', [
            'unit' => E::armada(),
            'a' => E::ringkasArmada(),
            'acuan' => E::acuanKelas(),
        ]);
    }

    public function maintenance(Request $request)
    {
        $prioritas = $request->get('prioritas');
        $kerja = E::pekerjaan();

        if ($prioritas && $prioritas !== 'semua') {
            $kerja = array_values(array_filter($kerja, fn ($p) => $p['prioritas'] === $prioritas));
        }

        $urutan = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
        usort($kerja, fn ($a, $b) => $urutan[$a['prioritas']] <=> $urutan[$b['prioritas']]);

        return view('meh.maintenance', [
            'm' => E::ringkasPemeliharaan(),
            'kerja' => $kerja,
            'prioritas' => $prioritas ?: 'semua',
        ]);
    }

    public function hse()
    {
        return view('meh.hse', [
            'h' => E::ringkasHse(),
            'smkp' => E::smkp(),
        ]);
    }

    public function kpi()
    {
        return view('meh.kpi', [
            'a' => E::ringkasArmada(),
            'p' => E::ringkasProduksi(),
            'e' => E::ringkasEnergi(),
        ]);
    }

    public function tools()
    {
        return view('meh.tools');
    }

    public function regulations(Request $request)
    {
        $kategori = $request->get('kategori');
        $daftar = E::regulasi();

        if ($kategori && $kategori !== 'Semua') {
            $daftar = array_values(array_filter($daftar, fn ($r) => $r['kategori'] === $kategori));
        }

        return view('meh.regulations', [
            'daftar' => $daftar,
            'kategori' => $kategori ?: 'Semua',
            'semuaKategori' => array_merge(['Semua'], array_values(array_unique(array_column(E::regulasi(), 'kategori')))),
        ]);
    }
}
