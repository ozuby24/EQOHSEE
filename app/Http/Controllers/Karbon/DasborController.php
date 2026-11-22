<?php

namespace App\Http\Controllers\Karbon;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Energi\BantuEnergi;
use App\Models\Energi\{GrkBatas, Mitigasi, Pengaturan};
use App\Support\Energi\{Inventaris, KendaliDireksi, Kewajiban, Pelaporan};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Dasbor aplikasi Carbon Management.
 *
 * Seluruh angka dibaca dari mesin yang sama dengan laporan GRK
 * (Inventaris + Pelaporan), sehingga dasbor dan laporan resmi tidak
 * pernah berselisih. Data aktivitasnya tetap dicatat di aplikasi
 * Energy Performance; aplikasi ini membaca, menghitung, dan melaporkan.
 */
class DasborController extends Controller
{
    use BantuEnergi;

    /** Kewajiban yang menyangkut emisi, untuk panel kepatuhan. */
    private const KEWAJIBAN = ['GRK_PU', 'PROPER', 'OJK_LK', 'PSPK2'];

    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentangTanggal($request, now()->startOfYear(), now()->startOfDay());
        $c = $this->perusahaanAktif($request);
        $saring = $c ? ['company_id' => $c] : [];

        $inv = Inventaris::hitung($dari, $sampai, $saring);
        $r = Inventaris::ringkas($inv['baris']);
        $p = Inventaris::produksi($dari, $sampai, $saring);

        // Periode yang sama tahun lalu, untuk arah perubahan.
        $lalu = Inventaris::ringkas(Inventaris::hitung($dari->copy()->subYear(), $sampai->copy()->subYear(), $saring)['baris']);
        $pLalu = Inventaris::produksi($dari->copy()->subYear(), $sampai->copy()->subYear(), $saring);

        $snap = Pelaporan::susun('GRK_PU', $c, $dari, $sampai);

        // Dua belas bulan terakhir per scope, lepas dari rentang saringan.
        $awal12 = $sampai->copy()->subMonths(11)->startOfMonth();
        $bulanan = [];
        $per = Inventaris::kelompok(Inventaris::hitung($awal12, $sampai, $saring)['baris'], fn ($b) => $b['bulan']);
        for ($m = $awal12->copy(); $m->lte($sampai); $m->addMonth()) {
            $x = $per[$m->format('Y-m')] ?? [];
            $bulanan[] = ['bulan' => $m->format('Y-m'), 'label' => $m->translatedFormat('M y'),
                          's1' => $x['s1'] ?? 0.0, 's2' => $x['s2_lb'] ?? 0.0, 's3' => $x['s3'] ?? 0.0];
        }

        $sumber = collect($snap['per_sumber'])->where('scope', '!=', 3)->sortByDesc('co2e_t')->take(8)->values()
            ->map(fn ($b) => ['label' => $b['label'], 'kelompok' => $b['kelompok'] ?? null, 'energi' => $b['energi'] ?? null,
                              'scope' => $b['scope'], 'co2e_t' => $b['co2e_t']])->all();

        $atur = Pengaturan::untuk($c);
        $dasar = null;
        if ($atur->tahun_dasar) {
            $d = $atur->dasar_snapshot ?: Inventaris::ringkas(Inventaris::hitung($atur->tahun_dasar.'-01-01',
                $atur->tahun_dasar.'-12-31', $saring)['baris']);
            $dasar = ['tahun' => $atur->tahun_dasar, 'total' => ($d['s1'] ?? 0) + ($d['s2_lb'] ?? 0)];
        }

        $mitigasi = Mitigasi::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderByDesc('target_penurunan_tco2e_tahun')->get()
            ->map(fn (Mitigasi $m) => ['id' => $m->id, 'judul' => $m->judul, 'jenis' => $m->jenis,
                'target' => (float) $m->target_penurunan_tco2e_tahun, 'capaian' => $m->capaianTahun(now()->year),
                'status_srn' => $m->status_srn])->values()->all();

        $kewajiban = array_values(array_filter(Kewajiban::daftar($c), fn ($k) => in_array($k['kode'], self::KEWAJIBAN, true)));

        $batas = GrkBatas::query()->when($c, fn ($q) => $q->where('company_id', $c))->where('tahun', $sampai->year)->first();

        return Inertia::render('Karbon/Dasbor', [
            'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(),
            'r' => Pelaporan::potong($r), 'lalu' => Pelaporan::potong($lalu),
            'produksi' => $p, 'produksiLalu' => $pLalu,
            'intensitas' => $p['ton'] > 0 ? $r['total'] / $p['ton'] : null,
            'intensitasLalu' => $pLalu['ton'] > 0 ? $lalu['total'] / $pLalu['ton'] : null,
            'bulanan' => $bulanan,
            'perGas' => $snap['per_gas'],
            'perKategori' => $snap['per_kategori_s1'],
            'scope3' => $snap['scope3'],
            'sumber' => $sumber,
            'dasar' => $dasar,
            'jalur' => KendaliDireksi::jalur($c),
            'eksposur' => KendaliDireksi::eksposur((float) $r['total'], Carbon::parse($sampai), $c),
            'mitigasi' => $mitigasi,
            'kewajiban' => $kewajiban,
            'batasDisetujui' => (bool) $batas?->disetujui(),
            'faktorCocok' => $snap['faktor_perlu_cocok'],
            'gwp' => $atur->gwp_set,
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }
}
