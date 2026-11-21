<?php

namespace App\Support\Energi;

use App\Models\{Company, EnergyBaseline, EnergyOpportunity, TindakLanjut};
use App\Models\Energi\{Audit, GrkBatas, Mitigasi, Pengaturan, Periode, Personel, Program, Rekomendasi, Scope3, Tinjauan};
use Illuminate\Support\Carbon;

/**
 * Pusat Pelaporan — templat laporan regulator & standar (FSD §10).
 *
 * Setiap templat menyusun SNAPSHOT: seluruh angka, rincian, dan faktor
 * yang dipakai, dibekukan sebagai satu larik. Halaman laporan, PDF, XLSX,
 * dan CSV seluruhnya digambar dari snapshot itu — tidak satu pun yang
 * menghitung ulang — sehingga laporan yang sudah disampaikan ke ESDM tetap
 * persis sama meski faktor diperbarui tahun berikutnya.
 *
 * Struktur kolom mengikuti rancangan FSD; format resmi (mis. Lampiran I
 * Permen ESDM 8/2025) WAJIB dicocokkan dengan naskah resminya sebelum
 * dipakai untuk penyampaian [A-05][A-07].
 */
final class Pelaporan
{
    public const TEMPLAT = [
        'ESDM_ME'     => ['judul' => 'Laporan Manajemen Energi ke Menteri ESDM', 'kop' => 'laporan-energi-esdm',
                          'dasar' => 'PP 33/2023; Permen ESDM 8/2025 Ps. 17 & Lampiran I', 'periode' => 'tahunan',
                          'tenggat' => '30 Juni tahun berikutnya (elektronik/POME)', 'resmi' => true, 'peran' => 'energi'],
        'ESDM_MONEV'  => ['judul' => 'Laporan Monev Manajer Energi ke Manajemen Puncak', 'kop' => 'laporan-energi-monev',
                          'dasar' => 'Permen ESDM 8/2025 Ps. 10', 'periode' => 'tahunan', 'tenggat' => '≥1× per tahun', 'resmi' => true, 'peran' => 'energi'],
        'GRK_PU'      => ['judul' => 'Laporan Inventarisasi Emisi GRK Perusahaan', 'kop' => 'laporan-grk',
                          'dasar' => 'Perpres 110/2025 Ps. 11–14; Permen ESDM 22/2019', 'periode' => 'tahunan',
                          'tenggat' => 'Paling lambat Maret tahun berikutnya', 'resmi' => true, 'peran' => 'grk'],
        'ISO14064'    => ['judul' => 'Laporan GRK Organisasi (ISO 14064-1:2018)', 'kop' => 'laporan-grk-iso',
                          'dasar' => 'ISO 14064-1:2018 klausul 9', 'periode' => 'tahunan', 'tenggat' => 'Tahunan', 'resmi' => true, 'peran' => 'grk'],
        'PROPER'      => ['judul' => 'Data Efisiensi Energi & Penurunan Emisi untuk PROPER', 'kop' => 'laporan-proper-energi',
                          'dasar' => 'Permen LHK 1/2021; Permen LH/BPLH 7/2025', 'periode' => 'tahunan', 'tenggat' => 'Siklus penilaian PROPER', 'resmi' => true, 'peran' => 'grk'],
        'OJK_LK'      => ['judul' => 'Kinerja Energi & Emisi — Laporan Keberlanjutan', 'kop' => 'laporan-keberlanjutan-energi',
                          'dasar' => 'POJK 51/POJK.03/2017; SEOJK 16/2021', 'periode' => 'tahunan', 'tenggat' => 'Bersama laporan tahunan', 'resmi' => true, 'peran' => 'keuangan'],
        'PSPK2'       => ['judul' => 'Metrik Iklim PSPK 2 (IFRS S2)', 'kop' => 'laporan-pspk2',
                          'dasar' => 'PSPK 2 — berlaku periode mulai ≥ 1 Januari 2027', 'periode' => 'tahunan', 'tenggat' => 'Bersama laporan keuangan', 'resmi' => true, 'peran' => 'keuangan'],
        'SRN_AKSI'    => ['judul' => 'Data Aksi Mitigasi untuk SRN PPI', 'kop' => 'laporan-srn-aksi',
                          'dasar' => 'Perpres 110/2025; Permen LHK 21/2022', 'periode' => 'tahunan', 'tenggat' => 'Sesuai kebutuhan', 'resmi' => true, 'peran' => 'grk'],
        'KINERJA_BLN' => ['judul' => 'Laporan Kinerja Energi Bulanan', 'kop' => 'laporan-energi',
                          'dasar' => 'Internal', 'periode' => 'bulanan', 'tenggat' => 'Bulanan', 'resmi' => false, 'peran' => 'energi'],
        'BOD_PACK'    => ['judul' => 'Board Pack Kinerja Energi & Karbon', 'kop' => 'laporan-bod-energi',
                          'dasar' => 'Internal — rapat direksi/komisaris', 'periode' => 'kustom', 'tenggat' => 'Sesuai rapat', 'resmi' => false, 'peran' => 'bod'],
    ];

    /** Rentang periode sebuah templat. */
    public static function rentang(string $templat, int $tahun, ?int $bulan = null): array
    {
        if ((self::TEMPLAT[$templat]['periode'] ?? 'tahunan') === 'bulanan') {
            $m = Carbon::create($tahun, $bulan ?: 1, 1);

            return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()->startOfDay()];
        }

        return [Carbon::create($tahun, 1, 1), Carbon::create($tahun, 12, 31)];
    }

    /**
     * Susun snapshot satu templat untuk satu perusahaan & periode.
     *
     * Dibaca dengan batas perusahaan disebut tegas: laporan selalu laporan
     * SATU perusahaan, bukan gabungan yang kebetulan terlihat oleh penyusunnya.
     */
    public static function susun(string $templat, ?int $companyId, Carbon $mulai, Carbon $sampai): array
    {
        $saring = $companyId !== null ? ['company_id' => $companyId] : [];
        $atur = Pengaturan::untuk($companyId);
        $tahun = $mulai->year;

        $inv = Inventaris::hitung($mulai, $sampai, $saring);
        $ring = Inventaris::ringkas($inv['baris']);
        $prod = Inventaris::produksi($mulai, $sampai, $saring);
        $c = $companyId ? Company::withoutGlobalScopes()->find($companyId) : null;

        $s = [
            'templat' => $templat,
            'judul'   => self::TEMPLAT[$templat]['judul'],
            'dasar'   => self::TEMPLAT[$templat]['dasar'],
            'dibuat_pada' => now()->toIso8601String(),
            'periode' => [$mulai->toDateString(), $sampai->toDateString()],
            'perusahaan' => ['id' => $c?->id, 'nama' => $c?->name ?? 'Semua perusahaan'],
            'gwp_set' => $atur->gwp_set,
            'ringkas' => self::potong($ring),
            'produksi' => $prod,
            'intensitas' => [
                'gj_ton'     => $prod['ton'] > 0 ? $ring['gj'] / $prod['ton'] : null,
                'l_bcm'      => $prod['bcm'] > 0 ? $ring['liter_solar'] / $prod['bcm'] : null,
                'tco2e_ton'  => $prod['ton'] > 0 ? $ring['total'] / $prod['ton'] : null,
                'rp_ton'     => $prod['ton'] > 0 ? $ring['rupiah'] / $prod['ton'] : null,
            ],
            'sbm' => Kalkulator::sbm($ring['gj'], $sampai),
            'per_bulan' => array_map(fn ($r) => self::potong($r, true),
                Inventaris::kelompok($inv['baris'], fn ($b) => $b['bulan'])),
            'per_sumber' => self::perSumber($inv['baris']),
            'faktor' => array_values($inv['faktor']),
            'periode_kunci' => self::statusPeriode($companyId, $mulai, $sampai),
            'versi_aplikasi' => self::versiAplikasi(),
        ];

        $s['faktor_perlu_cocok'] = collect($s['faktor'])->where('perlu_cocok', true)
            ->map(fn ($f) => $f['kode_energi'].'/'.$f['parameter'])->unique()->values()->all();

        return $s + match ($templat) {
            'ESDM_ME'     => self::esdm($companyId, $tahun, $mulai, $sampai, $ring),
            'ESDM_MONEV'  => self::monev($companyId, $tahun, $mulai, $sampai, $ring, $prod),
            'GRK_PU', 'ISO14064', 'PSPK2', 'OJK_LK' => self::grk($companyId, $tahun, $templat, $ring, $inv['baris'], $atur),
            'PROPER'      => self::proper($companyId, $tahun),
            'SRN_AKSI'    => ['mitigasi' => self::mitigasi($companyId, $tahun)],
            'KINERJA_BLN', 'BOD_PACK' => self::internal($companyId, $mulai, $sampai),
            default       => [],
        };
    }

    /** @return array{blokir:list<string>,peringatan:list<string>} */
    public static function validasi(string $templat, array $snap, ?int $companyId): array
    {
        $blokir = $peringatan = [];
        $resmi = self::TEMPLAT[$templat]['resmi'] ?? false;

        $terbuka = collect($snap['periode_kunci'] ?? [])->where('terkunci', false)->pluck('label')->all();
        if ($terbuka) {
            $pesan = 'Periode belum terkunci: '.implode(', ', $terbuka).'.';
            if ($resmi) $blokir[] = $pesan; else $peringatan[] = $pesan;
        }

        if (!empty($snap['ringkas']['kurang'])) {
            $pesan = 'Faktor tidak tersedia: '.implode(', ', $snap['ringkas']['kurang']).'.';
            if ($resmi) $blokir[] = $pesan; else $peringatan[] = $pesan;
        }

        if ($templat === 'ESDM_ME' && empty($snap['manajer_sah'])) {
            $blokir[] = 'Belum ada Manajer Energi bersertifikat yang berlaku pada akhir periode (Permen ESDM 8/2025 Ps. 4).';
        }

        if (in_array($templat, ['GRK_PU', 'ISO14064', 'PSPK2'], true) && empty($snap['batas']['disetujui'])) {
            $blokir[] = 'Batas organisasi tahun '.substr($snap['periode'][0], 0, 4).' belum disetujui.';
        }

        if ($templat === 'PSPK2' && (int) substr($snap['periode'][0], 0, 4) < 2027) {
            $peringatan[] = 'PSPK 2 baru berlaku untuk periode mulai 1 Januari 2027; laporan ini bersifat uji coba.';
        }

        if (!empty($snap['faktor_perlu_cocok'])) {
            $peringatan[] = 'Faktor acuan belum dicocokkan dengan dokumen sumber: '.implode(', ', $snap['faktor_perlu_cocok']).'.';
        }

        if (($snap['ringkas']['persen_estimasi'] ?? 0) > 10) {
            $peringatan[] = 'Lebih dari 10% baris data aktivitas berupa estimasi.';
        }

        if ($resmi) {
            $peringatan[] = 'Struktur kolom mengikuti rancangan FSD; cocokkan dengan format resmi sebelum disampaikan.';
        }

        return ['blokir' => $blokir, 'peringatan' => $peringatan];
    }

    /* ═══════════════ bagian per templat ═══════════════ */

    private static function esdm(?int $c, int $tahun, Carbon $mulai, Carbon $sampai, array $ring): array
    {
        $manajer = Personel::withoutGlobalScopes()->where('company_id', $c)->where('aktif', true)->orderBy('peran')->get();
        $sah = $manajer->where('peran', 'manajer_energi')->filter(fn ($m) => $m->sah($sampai));
        $audit = Audit::withoutGlobalScopes()->with('rekomendasi')->where('company_id', $c)
            ->whereDate('mulai', '<=', $sampai->toDateString())->orderByDesc('mulai')->first();

        return [
            'manajer_sah' => $sah->isNotEmpty(),
            'personel' => $manajer->map(fn ($m) => [
                'nama' => $m->nama, 'peran' => Personel::PERAN[$m->peran] ?? $m->peran, 'internal' => $m->internal,
                'nomor_sertifikat' => $m->nomor_sertifikat, 'lembaga_sertifikasi' => $m->lembaga_sertifikasi,
                'berlaku_sampai' => $m->berlaku_sampai?->toDateString(), 'sah' => $m->sah($sampai),
            ])->values()->all(),
            'ambang' => Ambang::status($c, $tahun + 1, Carbon::create($tahun + 1, 1, 1)),
            'seu' => Seu::pareto($mulai, $sampai, $c !== null ? ['company_id' => $c] : []),
            'program' => self::program($c, $tahun),
            'program_berikut' => self::program($c, $tahun + 1),
            'audit' => $audit ? [
                'judul' => $audit->judul, 'jenis' => $audit->jenis, 'mulai' => $audit->mulai?->toDateString(),
                'selesai' => $audit->selesai?->toDateString(), 'standar' => $audit->standar, 'seu' => $audit->seu ?? [],
                'rekomendasi' => $audit->rekomendasi->map(fn (Rekomendasi $r) => [
                    'uraian' => $r->uraian, 'tenggat' => $r->tenggat?->toDateString(), 'status' => $r->status,
                    'potensi_hemat_gj' => $r->potensi_hemat_gj, 'lewat' => $r->lewatTenggat(),
                ])->values()->all(),
            ] : null,
            'penghematan' => self::penghematan($c),
        ];
    }

    private static function monev(?int $c, int $tahun, Carbon $mulai, Carbon $sampai, array $ring, array $prod): array
    {
        $b = EnergyBaseline::withoutGlobalScopes()->where('company_id', $c)->where('tahun', $tahun)->first()
          ?? EnergyBaseline::withoutGlobalScopes()->whereNull('company_id')->where('tahun', $tahun)->first();

        $model = $b?->model_enb;
        $prediksi = $model ? Enpi::prediksi($model, ['ton' => $prod['ton'], 'bcm' => $prod['bcm']]) : null;
        $tinjau = Tinjauan::withoutGlobalScopes()->where('company_id', $c)->where('tahun', $tahun)->orderByDesc('tanggal')->first();

        return [
            'baseline' => $b ? ['baseline_gj_ton' => $b->baseline_gj_ton, 'target_gj_ton' => $b->target_gj_ton,
                                'target_tco2e_ton' => $b->target_tco2e_ton, 'status' => $b->status, 'model' => $model] : null,
            'enb_prediksi_gj' => $prediksi,
            'penurunan_vs_enb_persen' => $prediksi ? ($prediksi - $ring['gj']) / $prediksi * 100 : null,
            'program' => self::program($c, $tahun),
            'penghematan' => self::penghematan($c),
            'tinjauan' => $tinjau ? ['tanggal' => $tinjau->tanggal?->toDateString(), 'peserta' => $tinjau->peserta,
                                     'masukan' => $tinjau->masukan, 'keluaran' => $tinjau->keluaran] : null,
            'rekomendasi_terbuka' => Rekomendasi::withoutGlobalScopes()->where('company_id', $c)
                ->whereIn('status', ['terbuka', 'ditindaklanjuti'])->count(),
        ];
    }

    private static function grk(?int $c, int $tahun, string $templat, array $ring, array $baris, Pengaturan $atur): array
    {
        $batas = GrkBatas::withoutGlobalScopes()->where('company_id', $c)->where('tahun', $tahun)->first();

        $dasar = null;
        if ($atur->tahun_dasar && $atur->tahun_dasar !== $tahun) {
            $d = $atur->dasar_snapshot ?: self::potong(Inventaris::ringkas(Inventaris::hitung(
                $atur->tahun_dasar.'-01-01', $atur->tahun_dasar.'-12-31', $c !== null ? ['company_id' => $c] : [])['baris']));
            $dasar = ['tahun' => $atur->tahun_dasar, 's1' => $d['s1'] ?? 0, 's2_lb' => $d['s2_lb'] ?? 0,
                      'total' => ($d['s1'] ?? 0) + ($d['s2_lb'] ?? 0),
                      'perubahan_persen' => (($d['s1'] ?? 0) + ($d['s2_lb'] ?? 0)) > 0
                          ? ($ring['total'] - (($d['s1'] ?? 0) + ($d['s2_lb'] ?? 0))) / (($d['s1'] ?? 0) + ($d['s2_lb'] ?? 0)) * 100 : null];
        }

        $s3 = collect($baris)->where('scope', 3)->groupBy('scope3_kategori')->map(fn ($g, $k) => [
            'kategori' => (int) $k, 'label' => Scope3::KATEGORI[(int) $k] ?? '', 'co2e_t' => $g->sum('co2e_t'),
            'terpisah' => (int) $k === 11,
        ])->values()->all();

        $out = [
            'batas' => $batas ? [
                'pendekatan' => GrkBatas::PENDEKATAN[$batas->pendekatan] ?? $batas->pendekatan,
                'persen_ekuitas' => $batas->persen_ekuitas, 'uraian' => $batas->uraian_batas,
                'pengecualian' => $batas->pengecualian ?? [], 'relevansi_scope3' => $batas->relevansi_scope3 ?? [],
                'disetujui' => $batas->disetujui(), 'disetujui_pada' => $batas->disetujui_pada?->toDateString(),
            ] : ['disetujui' => false],
            'per_gas' => [
                ['gas' => 'CO₂', 'massa_t' => $ring['co2'], 'co2e_t' => $ring['co2']],
                ['gas' => 'CH₄', 'massa_t' => $ring['ch4'], 'co2e_t' => null],
                ['gas' => 'N₂O', 'massa_t' => $ring['n2o'], 'co2e_t' => null],
                ['gas' => 'HFC', 'massa_t' => null, 'co2e_t' => $ring['hfc_co2e']],
                ['gas' => 'PFC', 'massa_t' => null, 'co2e_t' => 0.0],
                ['gas' => 'SF₆', 'massa_t' => null, 'co2e_t' => $ring['sf6_co2e']],
            ],
            'per_kategori_s1' => collect($ring['per_kategori_s1'])->map(fn ($v, $k) => [
                'kategori' => Katalog::KATEGORI_IPCC[$k] ?? $k, 'co2e_t' => $v])->values()->all(),
            'scope3' => $s3,
            'tahun_dasar' => $dasar,
            'mitigasi' => self::mitigasi($c, $tahun),
        ];

        if ($templat === 'ISO14064') {
            /* Kategori ISO 14064-1:2018 klausul 5.2.4: 1 langsung, 2 energi impor,
               3 transportasi, 4 produk yang dipakai organisasi, 5 penggunaan
               produk organisasi, 6 sumber lain. */
            $kat = fn (array $kode) => collect($s3)->whereIn('kategori', $kode)->sum('co2e_t');
            $out['iso_kategori'] = [
                ['no' => 1, 'label' => 'Emisi & pemindahan GRK langsung', 'co2e_t' => $ring['s1']],
                ['no' => 2, 'label' => 'Emisi tidak langsung dari energi impor', 'co2e_t' => $ring['s2_lb']],
                ['no' => 3, 'label' => 'Emisi tidak langsung dari transportasi', 'co2e_t' => $kat([4, 6, 7, 9])],
                ['no' => 4, 'label' => 'Emisi tidak langsung dari produk yang dipakai organisasi', 'co2e_t' => $kat([1, 2, 3, 5, 8])],
                ['no' => 5, 'label' => 'Emisi tidak langsung dari penggunaan produk organisasi', 'co2e_t' => $kat([10, 11, 12, 13])],
                ['no' => 6, 'label' => 'Emisi tidak langsung dari sumber lain', 'co2e_t' => $kat([14, 15])],
            ];
        }

        if (in_array($templat, ['PSPK2', 'OJK_LK'], true)) {
            $out['target'] = ['tahun' => $atur->target_tahun, 'penurunan_persen' => $atur->target_penurunan_persen,
                              'tahun_dasar' => $atur->tahun_dasar];
            $out['harga_karbon'] = RegistriFaktor::nilai('karbon', 'harga_dasar', Carbon::create($tahun, 12, 31), ['company_id' => $c]);
            $out['program'] = self::program($c, $tahun);
            $out['penghematan'] = self::penghematan($c);
        }

        return $out;
    }

    private static function proper(?int $c, int $tahun): array
    {
        $deret = [];
        foreach (range($tahun - 3, $tahun) as $t) {
            $r = Inventaris::ringkas(Inventaris::hitung("{$t}-01-01", "{$t}-12-31", $c !== null ? ['company_id' => $c] : [])['baris']);
            $p = Inventaris::produksi("{$t}-01-01", "{$t}-12-31", $c !== null ? ['company_id' => $c] : []);
            $deret[] = ['tahun' => $t, 'gj' => $r['gj'], 'ton' => $p['ton'], 'gj_ton' => $p['ton'] > 0 ? $r['gj'] / $p['ton'] : null,
                        'tco2e' => $r['total'], 'tco2e_ton' => $p['ton'] > 0 ? $r['total'] / $p['ton'] : null, 'rupiah' => $r['rupiah']];
        }

        return ['deret' => $deret, 'program' => self::program($c, $tahun), 'penghematan' => self::penghematan($c),
                'mitigasi' => self::mitigasi($c, $tahun)];
    }

    private static function internal(?int $c, Carbon $mulai, Carbon $sampai): array
    {
        return [
            'peluang' => EnergyOpportunity::withoutGlobalScopes()->where('company_id', $c)->orderByDesc('hemat_liter')->limit(20)->get()
                ->map(fn ($o) => ['judul' => $o->judul, 'status' => $o->status, 'gj' => $o->gj(), 'tco2e' => $o->tco2e(),
                                  'rupiah' => $o->rupiah(), 'terverifikasi' => $o->terverifikasi()])->values()->all(),
            'instruksi' => TindakLanjut::withoutGlobalScopes()->where('company_id', $c)->where('modul', 'energi')
                ->whereIn('status', TindakLanjut::TERBUKA)->orderBy('target_selesai')->limit(20)->get()
                ->map(fn ($t) => ['judul' => $t->judul, 'pic' => $t->penanggung_jawab, 'tenggat' => $t->target_selesai?->toDateString(),
                                  'status' => $t->status])->values()->all(),
            'kewajiban' => Kewajiban::daftar($c, $sampai->copy()->min(now()->startOfDay())),
            'anomali' => array_slice(Anomali::bbm($sampai->copy()->min(now()->startOfDay()), 30, $c), 0, 15),
        ];
    }

    /* ═══════════════ bantu ═══════════════ */

    private static function program(?int $c, int $tahun): array
    {
        return Program::withoutGlobalScopes()->where('company_id', $c)->where('tahun', $tahun)->orderBy('id')->get()
            ->map(fn ($p) => ['judul' => $p->judul, 'sasaran' => $p->sasaran, 'target_hemat_gj' => $p->target_hemat_gj,
                              'target_hemat_tco2e' => $p->target_hemat_tco2e, 'anggaran_rp' => $p->anggaran_rp,
                              'pic' => $p->pic, 'status' => $p->status])->values()->all();
    }

    private static function penghematan(?int $c): array
    {
        $o = EnergyOpportunity::withoutGlobalScopes()->where('company_id', $c)->get();
        $v = $o->filter(fn ($x) => $x->terverifikasi());

        return [
            'rencana_gj_bulan' => $o->whereIn('status', ['disetujui', 'berjalan', 'selesai'])->sum(fn ($x) => $x->gj()),
            'terverifikasi_gj_bulan' => $v->sum(fn ($x) => $x->gjTerverifikasi()),
            'terverifikasi_tco2e_bulan' => $v->sum(fn ($x) => $x->tco2eTerverifikasi()),
            'terverifikasi_rp_bulan' => $v->sum(fn ($x) => $x->rupiahTerverifikasi()),
            'jumlah_terverifikasi' => $v->count(),
            'rincian' => $v->map(fn ($x) => ['judul' => $x->judul, 'metode_mv' => $x->metode_mv,
                'gj_bulan' => $x->gjTerverifikasi(), 'tco2e_bulan' => $x->tco2eTerverifikasi()])->values()->all(),
        ];
    }

    private static function mitigasi(?int $c, int $tahun): array
    {
        return Mitigasi::withoutGlobalScopes()->where('company_id', $c)->orderBy('mulai')->get()
            ->map(fn ($m) => ['judul' => $m->judul, 'jenis' => Mitigasi::JENIS[$m->jenis] ?? $m->jenis,
                'mulai' => $m->mulai?->toDateString(), 'selesai' => $m->selesai?->toDateString(),
                'metodologi' => $m->metodologi, 'baseline_tco2e_tahun' => $m->baseline_tco2e_tahun,
                'target_penurunan_tco2e_tahun' => $m->target_penurunan_tco2e_tahun,
                'capaian_tahun' => $m->capaianTahun($tahun), 'status_srn' => Mitigasi::STATUS_SRN[$m->status_srn] ?? $m->status_srn,
                'nomor_srn' => $m->nomor_srn])->values()->all();
    }

    private static function statusPeriode(?int $c, Carbon $mulai, Carbon $sampai): array
    {
        $out = [];
        for ($m = $mulai->copy()->startOfMonth(); $m->lte($sampai); $m->addMonth()) {
            $p = Periode::withoutGlobalScopes()->where('company_id', $c)->where('tahun', $m->year)->where('bulan', $m->month)->first();
            $out[] = ['tahun' => $m->year, 'bulan' => $m->month, 'label' => $m->translatedFormat('M Y'),
                      'terkunci' => $p?->terkunci() ?? false];
        }

        return $out;
    }

    private static function perSumber(array $baris): array
    {
        $g = [];
        foreach ($baris as $b) {
            $k = $b['scope'].'|'.$b['sumber']['jenis'].'|'.$b['sumber']['kode'].'|'.$b['kode'];
            $g[$k] ??= ['scope' => $b['scope'], 'jenis' => $b['sumber']['jenis'], 'label' => $b['sumber']['label'],
                        'kelompok' => $b['sumber']['kelompok'], 'kode' => $b['kode'], 'energi' => Katalog::label($b['kode']),
                        'kategori_ipcc' => $b['kategori_ipcc'], 'jumlah' => 0.0, 'satuan' => $b['satuan'],
                        'gj' => 0.0, 'co2_fosil_t' => 0.0, 'co2_bio_t' => 0.0, 'ch4_t' => 0.0, 'n2o_t' => 0.0,
                        'co2e_t' => 0.0, 'co2e_mb_t' => 0.0, 'rupiah' => 0.0, 'kurang' => [], 'terpisah' => $b['terpisah'] ?? false,
                        'ketidakpastian_persen' => $b['ketidakpastian_persen']];
            foreach (['jumlah', 'gj', 'co2_fosil_t', 'co2_bio_t', 'ch4_t', 'n2o_t', 'co2e_t', 'co2e_mb_t', 'rupiah'] as $f) {
                $g[$k][$f] += (float) $b[$f];
            }
            $g[$k]['kurang'] = array_values(array_unique(array_merge($g[$k]['kurang'], $b['kurang'])));
        }

        $g = array_values($g);
        usort($g, fn ($a, $b) => [$a['scope'], -$a['co2e_t']] <=> [$b['scope'], -$b['co2e_t']]);

        return $g;
    }

    /** Ringkasan tanpa rincian besar, untuk disimpan di snapshot. */
    public static function potong(array $r, bool $kecil = false): array
    {
        $buang = $kecil ? ['per_kode', 'per_kategori_s1', 'kurang'] : [];

        return array_diff_key($r, array_flip($buang));
    }

    private static function versiAplikasi(): ?string
    {
        $head = base_path('.git/HEAD');
        if (!is_file($head)) return null;

        $isi = trim((string) file_get_contents($head));
        if (str_starts_with($isi, 'ref: ')) {
            $ref = base_path('.git/'.substr($isi, 5));
            $isi = is_file($ref) ? trim((string) file_get_contents($ref)) : $isi;
        }

        return substr($isi, 0, 12) ?: null;
    }
}
