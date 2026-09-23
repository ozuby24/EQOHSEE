<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Frop\{Coaching, Observasi};
use App\Support\Frop\{Impor, Penilaian};
use App\Support\Perusahaan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Observasi operator loader — FROP, Learning Center.
 *
 * Satu modul pembinaan operator excavator: sesi observasi cycle time di
 * lapangan, penilaiannya terhadap Plan CT material, rekap performa per
 * operator, KPI bulanan program, temuan beserta tindakan perbaikannya,
 * dan coaching yang menutup lingkarannya.
 *
 * Seluruh angka turunan dihitung App\Support\Frop\Penilaian dari data
 * yang tersimpan, tidak pernah disimpan. Halaman-halaman di sini hanya
 * menyusun hasilnya.
 */
class FropController extends Controller
{
    /* ══════════════════ sesi observasi ══════════════════ */

    public function index(Request $r)
    {
        $semua = $this->semua();
        $bulan = $this->bulanDipilih($r, $semua);

        $sesi = $semua
            ->when($bulan !== 'semua', fn ($c) => $c->filter(fn (Observasi $o) => $o->tanggal->format('Y-m') === $bulan))
            ->when($r->filled('operator'), fn ($c) => $c->filter(
                fn (Observasi $o) => Penilaian::kunciOrang($o->operator) === Penilaian::kunciOrang($r->get('operator'))))
            ->when($r->filled('unit'), fn ($c) => $c->filter(
                fn (Observasi $o) => Penilaian::kunciUnit($o->unit) === Penilaian::kunciUnit($r->get('unit'))))
            ->when($r->get('status') === 'on', fn ($c) => $c->filter(fn (Observasi $o) => Penilaian::onTarget($o->nilai()) === true))
            ->when($r->get('status') === 'over', fn ($c) => $c->filter(fn (Observasi $o) => Penilaian::onTarget($o->nilai()) === false))
            ->when($r->filled('cari'), function ($c) use ($r) {
                $k = mb_strtolower(trim($r->get('cari')));
                return $c->filter(fn (Observasi $o) => str_contains(mb_strtolower(
                    $o->operator.' '.$o->unit.' '.$o->temuan.' '.$o->material.' '.$o->observer), $k));
            })
            ->sortByDesc(fn (Observasi $o) => $o->tanggal->format('Ymd').str_pad((string) $o->id, 9, '0', STR_PAD_LEFT))
            ->values();

        $nilai   = $sesi->map->nilai()->all();
        $dinilai = array_filter($nilai, fn ($s) => Penilaian::onTarget($s) !== null);
        $on      = array_filter($dinilai, fn ($s) => Penilaian::onTarget($s));
        $pty     = array_values(array_filter(array_map([Penilaian::class, 'pencapaian'], $nilai), fn ($v) => $v !== null));
        $sel     = array_map([Penilaian::class, 'selisih'], $dinilai);
        $open    = $sesi->filter(fn (Observasi $o) => $o->temuan && $o->status_ca !== 'Closed')->count();

        return Inertia::render('Frop/Daftar', [
            'judul'    => 'Observasi Operator Loader',
            'subjudul' => 'Sesi observasi cycle time & produktivitas excavator — FROP',
            'kop' => [
                'angka' => [
                    'label'   => 'Sesi ON TARGET',
                    'nilai'   => $dinilai ? round(count($on) / count($dinilai) * 100).'%' : '–',
                    'catatan' => count($on).' dari '.count($dinilai).' sesi · target ≥ '.(Penilaian::TARGET_KPI['on_target'] * 100).'%',
                ],
                'sisi' => [
                    ['Sesi', (string) count($nilai)],
                    ['Rata PTY', $pty ? round(array_sum($pty) / count($pty) * 100).'%' : '–',
                        $pty && array_sum($pty) / count($pty) >= Penilaian::PTY_TERCAPAI ? 'baik' : 'ingat'],
                    ['CA terbuka', (string) $open, $open ? 'ingat' : 'baik'],
                ],
                'aksi' => ['label' => '+ Sesi Observasi', 'url' => route('frop.create')],
            ],

            'saring' => [
                'bulan'    => $bulan,
                'operator' => (string) $r->get('operator', ''),
                'unit'     => (string) $r->get('unit', ''),
                'status'   => (string) $r->get('status', ''),
                'cari'     => (string) $r->get('cari', ''),
            ],
            'opsi' => [
                'bulan'    => $this->daftarBulan($semua),
                'operator' => $this->namaOperator($semua),
                'unit'     => $semua->groupBy(fn (Observasi $o) => Penilaian::kunciUnit($o->unit))
                                    ->map(fn ($g) => $g->last()->unit)->sort()->values()->all(),
            ],
            'ringkas' => [
                'sesi'     => count($nilai),
                'dinilai'  => count($dinilai),
                'on'       => count($on),
                'rata_sel' => $sel ? round(array_sum($sel) / count($sel), 2) : null,
                'pty'      => $pty ? array_sum($pty) / count($pty) : null,
                'open'     => $open,
                'operator' => count(array_unique(array_map(fn ($s) => Penilaian::kunciOrang($s['operator']), $nilai))),
            ],
            'daftar' => $sesi->map(fn (Observasi $o) => $this->baris($o))->all(),
            'tautan' => $this->tautan(),
        ]);
    }

    public function create(Request $r)
    {
        return Inertia::render('Frop/Form', [
            'judul'    => 'Sesi Observasi Baru',
            'subjudul' => 'Isi saat observasi berlangsung — cycle time dihitung langsung',
            'awal'     => $this->kosong(),
            'sunting'  => false,
            'opsi'     => $this->opsiForm(),
            'tautan'   => ['simpan' => route('frop.store'), 'batal' => route('frop.index')] + $this->tautan(),
        ]);
    }

    public function store(Request $r)
    {
        $d = self::selesai($this->v($r));
        $d['user_id']    = auth()->id();
        $d['company_id'] = auth()->user()->company_id ?? Perusahaan::terpilih();
        $d['pekerja_id'] = Impor::cariPekerja($d['operator'], $d['company_id']);

        $o = Observasi::create($d);

        ActivityLog::write('Catat observasi operator', $o->operator.' — '.$o->unit, 'lms');

        return redirect()->route('frop.show', $o)->with('ok', 'Sesi observasi '.$o->operator.' tersimpan.');
    }

    public function show(Observasi $observasi)
    {
        $o     = $observasi;
        $s     = $o->nilai();
        $semua = $this->semua();

        /* Sesi operator yang sama, berurutan waktu. Pembanding "CT vs
           sesi sebelumnya" diambil dari sini — bukan baris di atasnya
           pada tabel, yang hampir selalu milik operator lain. */
        $kunci   = Penilaian::kunciOrang($o->operator);
        $riwayat = $semua->filter(fn (Observasi $x) => Penilaian::kunciOrang($x->operator) === $kunci)->values();
        $sebelum = null;
        $baris   = [];
        foreach ($riwayat as $i => $x) {
            $ct = Penilaian::aktualCt($x->nilai());
            $baris[] = [
                'id'      => $x->id,
                'url'     => route('frop.show', $x),
                'ke'      => $i + 1,
                'tanggal' => $x->tanggal->toDateString(),
                'unit'    => $x->unit,
                'level'   => Penilaian::LEVEL[$x->level]['label'] ?? $x->level,
                'ct'      => $ct,
                'plan'    => Penilaian::plan($x->nilai()),
                'selisih' => Penilaian::selisih($x->nilai()),
                'on'      => Penilaian::onTarget($x->nilai()),
                'pty'     => Penilaian::pencapaian($x->nilai()),
                'banding' => $ct !== null && $sebelum !== null ? round($ct - $sebelum, 2) : null,
                'kini'    => $x->id === $o->id,
            ];
            if ($ct !== null) $sebelum = $ct;
        }
        $ke = collect($baris)->firstWhere('kini', true);

        $ulang = Penilaian::berulang($semua->map->nilai()->all())[$o->id] ?? [];

        return Inertia::render('Frop/Rincian', [
            'judul'    => $o->operator,
            'subjudul' => 'Laporan observasi '.$o->tanggal->translatedFormat('d F Y').' · '.$o->unit.' · Shift '.($o->shift ?? '–'),
            'kop' => [
                'angka' => [
                    'label'   => 'Aktual CT',
                    'nilai'   => ($ct = Penilaian::aktualCt($s)) === null ? '–' : $this->angka($ct).' dtk',
                    'catatan' => 'Plan '.$this->angka(Penilaian::plan($s)).' dtk · '.(Penilaian::LEVEL[$o->level]['label'] ?? $o->level),
                ],
                'sisi' => [
                    ['Selisih', ($x = Penilaian::selisih($s)) === null ? '–' : ($x > 0 ? '+' : '').$this->angka($x).' dtk',
                        $x === null ? null : ($x <= 0 ? 'baik' : 'gawat')],
                    ['PTY', ($p = Penilaian::pencapaian($s)) === null ? '–' : round($p * 100).'%',
                        $p === null ? null : ($p >= Penilaian::PTY_TERCAPAI ? 'baik' : 'ingat')],
                    ['Sesi ke-', (string) ($ke['ke'] ?? 1)],
                ],
            ],

            'sesi' => $this->baris($o) + [
                'jam'                 => $o->jam_observasi,
                'gl_front'            => $o->gl_front,
                'observer'            => $o->observer,
                'verified_by'         => $o->verified_by,
                'kondisi_mesin'       => $o->kondisi_mesin,
                'mode_kerja'          => $o->mode_kerja,
                'material'            => $o->material,
                'metode_posisi'       => $o->metode_posisi,
                'operating_condition' => $o->operating_condition,
                'metode_loading'      => $o->metode_loading,
                'mto'                 => $o->mto,
                'tinggi_jenjang'      => $o->tinggi_jenjang,
                'lebar_front'         => $o->lebar_front,
                'kondisi_permukaan'   => $o->kondisi_permukaan,
                'boulder'             => $o->boulder,
                'sudut_pass'          => $o->sudut_pass,
                'cuaca'               => $o->cuaca,
                'spotting'            => $o->spotting,
                'loading'             => $o->loadingTeks(),
                'loading_lewat'       => Penilaian::loadingLewat($s),
                'n_passing'           => $o->n_passing,
                'passing_lewat'       => Penilaian::passingLewat($s),
                'bucket_heap'         => $o->bucket_heap,
                'target_pty'          => $o->target_pty,
                'aktual_pty'          => $o->aktual_pty,
                'gap'                 => Penilaian::gapBcm($s),
                'corrective_action'   => $o->corrective_action,
                'pic_ca'              => $o->pic_ca,
                'deadline_ca'         => $o->deadline_ca?->toDateString(),
                'selesai_ca'          => $o->selesai_ca?->toDateString(),
                'catatan'             => $o->catatan,
                'sumber'              => $o->sumber,
                'status_sesi'         => Penilaian::statusSesi($s),
                'spot_tinggi'         => Penilaian::spotTinggi($s),
                'butir'               => array_map(fn ($b) => [
                    'teks' => $b,
                    'kat'  => $k = Penilaian::kategoriButir($b),
                    'nama' => Penilaian::KATEGORI[$k],
                    'ulang'=> in_array($k, $ulang, true),
                ], Penilaian::butirTemuan($o->temuan)),
            ],
            'komponen' => Penilaian::komponen($s),
            'riwayat'  => $baris,
            'rekap'    => Penilaian::rekapOperator($riwayat->map->nilai()->all()),
            'coaching' => Coaching::where(fn ($q) => $q->where('observasi_id', $o->id)
                                ->orWhere('operator', $o->operator))
                            ->orderByDesc('tanggal')->get()
                            ->map(fn (Coaching $c) => $this->barisCoaching($c))->all(),
            'acuan' => [
                'loading_maks' => Observasi::detikKeTeks(Penilaian::LOADING_MAKS),
                'passing_maks' => Penilaian::N_PASSING_MAKS,
                'spot_tinggi'  => Penilaian::SPOTTING_TINGGI,
            ],
            'opsi'   => ['status_ca' => Observasi::STATUS_CA],
            'bolehHapus' => (bool) auth()->user()?->isAdmin(),
            'tautan' => $this->tautan() + [
                'ubah'     => route('frop.edit', $o),
                'hapus'    => route('frop.destroy', $o),
                'ca'       => route('frop.ca', $o),
                'coaching' => route('frop.coaching', ['observasi' => $o->id]),
                'operator' => route('frop.index', ['operator' => $o->operator, 'bulan' => 'semua']),
            ],
        ]);
    }

    public function edit(Observasi $observasi)
    {
        $o = $observasi;

        return Inertia::render('Frop/Form', [
            'judul'    => 'Ubah Sesi Observasi',
            'subjudul' => $o->operator.' · '.$o->tanggal->translatedFormat('d F Y'),
            'awal'     => $this->isian($o),
            'sunting'  => true,
            'opsi'     => $this->opsiForm(),
            'tautan'   => ['simpan' => route('frop.update', $o), 'batal' => route('frop.show', $o)] + $this->tautan(),
        ]);
    }

    public function update(Request $r, Observasi $observasi)
    {
        $d = self::selesai($this->v($r), $observasi);

        if (Penilaian::kunciOrang($d['operator']) !== Penilaian::kunciOrang($observasi->operator)) {
            $d['pekerja_id'] = Impor::cariPekerja($d['operator'], $observasi->company_id);
        }

        $observasi->update($d);

        ActivityLog::write('Ubah observasi operator', $observasi->operator.' — '.$observasi->unit, 'lms');

        return redirect()->route('frop.show', $observasi)->with('ok', 'Sesi observasi diperbarui.');
    }

    public function destroy(Observasi $observasi)
    {
        ActivityLog::write('Hapus observasi operator', $observasi->operator.' — '.$observasi->tanggal->toDateString(), 'lms');
        $observasi->delete();

        return redirect()->route('frop.index')->with('ok', 'Sesi observasi dihapus.');
    }

    /** Tindak lanjut temuan: PIC, tenggat, status, tanggal selesai. */
    public function ca(Request $r, Observasi $observasi)
    {
        $d = $r->validate([
            'corrective_action' => ['nullable', 'string', 'max:2000'],
            'pic_ca'            => ['nullable', 'string', 'max:120'],
            'deadline_ca'       => ['nullable', 'date'],
            'status_ca'         => ['required', Rule::in(Observasi::STATUS_CA)],
            'selesai_ca'        => ['nullable', 'date', 'required_if:status_ca,Closed'],
        ], [
            'selesai_ca.required_if' => 'Tanggal selesai wajib diisi bila tindakan perbaikan dinyatakan Closed.',
        ], [
            'corrective_action' => 'corrective action', 'pic_ca' => 'PIC',
            'deadline_ca' => 'deadline', 'status_ca' => 'status CA', 'selesai_ca' => 'tanggal selesai',
        ]);

        /* Tanggal selesai milik status Closed saja. Dibiarkan ketika
           status dibuka kembali, temuan yang masih berjalan tampak
           sudah pernah ditutup pada tanggal itu. */
        if ($d['status_ca'] !== 'Closed') $d['selesai_ca'] = null;

        $observasi->update($d);

        return back()->with('ok', 'Tindak lanjut temuan diperbarui.');
    }

    /* ══════════════════ performa operator ══════════════════ */

    public function operator(Request $r)
    {
        $semua = $this->semua();
        $bulan = $r->get('bulan', 'semua');
        $bulan = $bulan === 'semua' || preg_match('/^\d{4}-\d{2}$/', $bulan) ? $bulan : 'semua';

        $pilih = $bulan === 'semua' ? $semua
            : $semua->filter(fn (Observasi $o) => $o->tanggal->format('Y-m') === $bulan)->values();

        $baris = $pilih->groupBy(fn (Observasi $o) => Penilaian::kunciOrang($o->operator))
            ->map(function (Collection $g) {
                $akhir = $g->last();
                $rek   = Penilaian::rekapOperator($g->map->nilai()->all());
                $sel   = array_values(array_filter($g->map(fn ($o) => Penilaian::selisih($o->nilai()))->all(), fn ($v) => $v !== null));

                return [
                    'operator' => $akhir->operator,
                    'unit'     => $akhir->unit,
                    'terakhir' => $akhir->tanggal->toDateString(),
                    'rata_sel' => $sel ? round(array_sum($sel) / count($sel), 2) : null,
                    'url'      => route('frop.index', ['operator' => $akhir->operator, 'bulan' => 'semua']),
                    'sesi_url' => route('frop.show', $akhir),
                    'coaching' => route('frop.coaching', ['operator' => $akhir->operator]),
                ] + $rek;
            })
            /* Peringkat: konsistensi on-target, lalu rata-rata SELISIH
               terhadap plan — bukan rata-rata CT mentah, sebab plan-nya
               berbeda per material dan operator yang lebih sering di
               material severe akan selalu tampak lebih lambat. */
            ->sort(function ($a, $b) {
                return [$b['konsistensi'] ?? -1, $a['rata_sel'] ?? INF, $b['total']]
                   <=> [$a['konsistensi'] ?? -1, $b['rata_sel'] ?? INF, $a['total']];
            })
            ->values()
            ->map(fn ($x, $i) => ['peringkat' => $i + 1] + $x)
            ->all();

        $hitung = fn (string $k) => count(array_filter($baris, fn ($x) => $x['performer']['kode'] === $k));

        return Inertia::render('Frop/Operator', [
            'judul'    => 'Performa Operator',
            'subjudul' => 'Konsistensi CT, PTY, dan catatan coaching per operator',
            'kop' => [
                'angka' => ['label' => 'Operator terobservasi', 'nilai' => (string) count($baris),
                            'catatan' => $bulan === 'semua' ? 'seluruh periode' : $this->namaBulan($bulan)],
                'sisi'  => [
                    ['Top Performer', (string) $hitung('top'), 'baik'],
                    ['Average', (string) $hitung('average'), 'ingat'],
                    ['Perlu Coaching', (string) $hitung('coaching'), $hitung('coaching') ? 'gawat' : 'baik'],
                ],
            ],
            'saring' => ['bulan' => $bulan],
            'opsi'   => ['bulan' => $this->daftarBulan($semua)],
            'daftar' => $baris,
            'ambang' => ['top' => 0.8, 'average' => 0.5, 'spot' => Penilaian::SPOTTING_RATA_TINGGI],
            'tautan' => $this->tautan(),
        ]);
    }

    /* ══════════════════ KPI bulanan ══════════════════ */

    public function kpi(Request $r)
    {
        $semua = $this->semua();
        $bulan = $this->bulanDipilih($r, $semua, false);
        [$th, $bl] = array_map('intval', explode('-', $bulan));

        $sesi = $semua->filter(fn (Observasi $o) => $o->tanggal->format('Y-m') === $bulan)->values();
        $kpi  = Penilaian::kpi($sesi->map->nilai()->all(), $th, $bl, Penilaian::berulang($semua->map->nilai()->all()));
        $t    = Penilaian::TARGET_KPI;

        /* Status tiap KPI terhadap targetnya. Null = belum ada data. */
        $capai = fn (?float $v, float $target, bool $makin = true) =>
            $v === null ? null : ($makin ? $v >= $target : $v <= $target);

        $tot = $kpi['total'];
        $baris = [
            ['kunci' => 'sesi', 'label' => 'Jumlah sesi observasi', 'bentuk' => 'angka',
             'target' => $t['sesi_mingguan'], 'target_teks' => '≥ '.$t['sesi_mingguan'].'/pekan · '.$kpi['target_sesi_bulan'].'/bulan',
             'status' => $capai($tot['sesi'], $kpi['target_sesi_bulan'])],
            ['kunci' => 'on_target', 'label' => '% sesi ON TARGET (CT ≤ Plan)', 'bentuk' => 'persen',
             'target' => $t['on_target'], 'target_teks' => '≥ '.($t['on_target'] * 100).'%',
             'status' => $capai($tot['on_target'], $t['on_target'])],
            ['kunci' => 'rata_sel', 'label' => 'Rata-rata selisih CT vs Plan', 'bentuk' => 'detik',
             'target' => 0, 'target_teks' => '≤ 0 dtk',
             'status' => $capai($tot['rata_sel'], 0, false)],
            ['kunci' => 'rata_ct', 'label' => 'Rata-rata Aktual CT', 'bentuk' => 'detik',
             'target' => null, 'target_teks' => 'informasi — plan berbeda per material', 'status' => null],
            ['kunci' => 'pty', 'label' => 'Rata-rata pencapaian PTY', 'bentuk' => 'persen',
             'target' => $t['pty'], 'target_teks' => '≥ '.($t['pty'] * 100).'%',
             'status' => $capai($tot['pty'], $t['pty'])],
            ['kunci' => 'ca_closed', 'label' => '% temuan dengan CA Closed', 'bentuk' => 'persen',
             'target' => $t['ca_closed'], 'target_teks' => '≥ '.($t['ca_closed'] * 100).'%',
             'status' => $capai($tot['ca_closed'], $t['ca_closed'])],
            ['kunci' => 'berulang', 'label' => '% masalah berulang (unit & kategori sama)', 'bentuk' => 'persen',
             'target' => $t['berulang'], 'target_teks' => '≤ '.($t['berulang'] * 100).'%',
             'status' => $capai($tot['berulang'], $t['berulang'], false)],
            ['kunci' => 'operator', 'label' => 'Operator terobservasi', 'bentuk' => 'angka',
             'target' => $t['operator'], 'target_teks' => '≥ '.$t['operator'].' orang',
             'status' => $capai($tot['operator'], $t['operator'])],
        ];

        $tercapai = count(array_filter($baris, fn ($b) => $b['status'] === true));
        $dinilai  = count(array_filter($baris, fn ($b) => $b['status'] !== null));

        /* Sebaran level material dan sesi per level: plan CT-nya berbeda,
           dan % on-target yang tinggi di bulan yang hampir seluruhnya
           easy tidak sama artinya dengan yang tinggi di bulan severe. */
        $level = [];
        foreach (Penilaian::LEVEL as $k => $l) {
            $g = $sesi->filter(fn (Observasi $o) => $o->level === $k)->map->nilai()->all();
            $d = array_filter($g, fn ($s) => Penilaian::onTarget($s) !== null);
            $level[] = [
                'kunci' => $k, 'label' => $l['label'], 'plan' => Penilaian::PLAN_CT[$k],
                'sesi' => count($g),
                'on'   => $d ? count(array_filter($d, fn ($s) => Penilaian::onTarget($s))) / count($d) : null,
                'rata_ct' => $d ? array_sum(array_map([Penilaian::class, 'aktualCt'], $d)) / count($d) : null,
            ];
        }

        return Inertia::render('Frop/Kpi', [
            'judul'    => 'KPI Bulanan FROP',
            'subjudul' => 'Capaian program observasi per pekan — '.$this->namaBulan($bulan),
            'kop' => [
                'angka' => ['label' => 'KPI tercapai', 'nilai' => $tercapai.' / '.$dinilai,
                            'catatan' => $this->namaBulan($bulan)],
                'sisi'  => [
                    ['Sesi', (string) $tot['sesi']],
                    ['ON TARGET', $tot['on_target'] === null ? '–' : round($tot['on_target'] * 100).'%',
                        $tot['on_target'] === null ? null : ($tot['on_target'] >= $t['on_target'] ? 'baik' : 'gawat')],
                ],
            ],
            'saring' => ['bulan' => $bulan],
            'opsi'   => ['bulan' => $this->daftarBulan($semua, false)],
            'pekan'  => $kpi['pekan'],
            'nilai'  => $kpi['nilai'],
            'total'  => $tot,
            'baris'  => $baris,
            'level'  => $level,
            'tautan' => $this->tautan(),
        ]);
    }

    /* ══════════════════ temuan & tindakan perbaikan ══════════════════ */

    public function tracker(Request $r)
    {
        $semua = $this->semua();
        $ulang = Penilaian::berulang($semua->map->nilai()->all());
        $bulan = $r->get('bulan', 'semua');
        $bulan = $bulan === 'semua' || preg_match('/^\d{4}-\d{2}$/', $bulan) ? $bulan : 'semua';
        $kat   = array_key_exists($r->get('kategori'), Penilaian::KATEGORI) ? $r->get('kategori') : '';
        $stat  = in_array($r->get('status'), Observasi::STATUS_CA, true) ? $r->get('status') : '';
        $hari  = CarbonImmutable::today();

        $pilih = $semua->filter(fn (Observasi $o) => filled($o->temuan))
            ->when($bulan !== 'semua', fn ($c) => $c->filter(fn (Observasi $o) => $o->tanggal->format('Y-m') === $bulan));

        /* Frekuensi per kategori dihitung dari periode yang dipilih,
           SEBELUM disaring kategori dan status — ringkasannya menjawab
           "masalah apa yang paling sering", bukan "berapa yang sedang
           ditampilkan". */
        $frek = array_fill_keys(array_keys(Penilaian::KATEGORI), ['butir' => 0, 'sesi' => 0, 'ulang' => 0]);
        foreach ($pilih as $o) {
            foreach (Penilaian::kategoriTemuan($o->temuan) as $k) {
                $frek[$k]['sesi']++;
                if (in_array($k, $ulang[$o->id] ?? [], true)) $frek[$k]['ulang']++;
            }
            foreach (Penilaian::butirTemuan($o->temuan) as $b) $frek[Penilaian::kategoriButir($b)]['butir']++;
        }

        $status = array_fill_keys(Observasi::STATUS_CA, 0);
        foreach ($pilih as $o) $status[$o->status_ca] = ($status[$o->status_ca] ?? 0) + 1;

        $lewat = $pilih->filter(fn (Observasi $o) => $o->status_ca !== 'Closed' && $o->deadline_ca && $o->deadline_ca->lt($hari))->count();

        $daftar = $pilih
            ->when($kat !== '', fn ($c) => $c->filter(fn (Observasi $o) => in_array($kat, Penilaian::kategoriTemuan($o->temuan), true)))
            ->when($stat !== '', fn ($c) => $c->filter(fn (Observasi $o) => $o->status_ca === $stat))
            ->sortByDesc(fn (Observasi $o) => $o->tanggal->format('Ymd').str_pad((string) $o->id, 9, '0', STR_PAD_LEFT))
            ->values()
            ->map(fn (Observasi $o) => [
                'id'       => $o->id,
                'url'      => route('frop.show', $o),
                'ca_url'   => route('frop.ca', $o),
                'tanggal'  => $o->tanggal->toDateString(),
                'shift'    => $o->shift,
                'unit'     => $o->unit,
                'operator' => $o->operator,
                'butir'    => array_map(fn ($b) => [
                    'teks'  => $b,
                    'kat'   => $k = Penilaian::kategoriButir($b),
                    'ulang' => in_array($k, $ulang[$o->id] ?? [], true),
                ], Penilaian::butirTemuan($o->temuan)),
                'corrective_action' => $o->corrective_action,
                'pic_ca'      => $o->pic_ca ?: $o->observer,
                'pic_isian'   => $o->pic_ca,
                'deadline_ca' => $o->deadline_ca?->toDateString(),
                'selesai_ca'  => $o->selesai_ca?->toDateString(),
                'status_ca'   => $o->status_ca,
                'lewat'       => $o->status_ca !== 'Closed' && $o->deadline_ca && $o->deadline_ca->lt($hari),
            ])->all();

        $totalSesi = $pilih->count();
        $closed    = $status['Closed'] ?? 0;

        return Inertia::render('Frop/Tracker', [
            'judul'    => 'Temuan & Tindakan Perbaikan',
            'subjudul' => 'Problem & CA tracker — dikelompokkan per kategori, ditandai bila berulang',
            'kop' => [
                'angka' => ['label' => 'CA Closed', 'nilai' => $totalSesi ? round($closed / $totalSesi * 100).'%' : '–',
                            'catatan' => $closed.' dari '.$totalSesi.' sesi bertemuan · target ≥ '.(Penilaian::TARGET_KPI['ca_closed'] * 100).'%'],
                'sisi'  => [
                    ['Open', (string) ($status['Open'] ?? 0), ($status['Open'] ?? 0) ? 'ingat' : 'baik'],
                    ['In Progress', (string) ($status['In Progress'] ?? 0)],
                    ['Lewat tenggat', (string) $lewat, $lewat ? 'gawat' : 'baik'],
                ],
            ],
            'saring'   => ['bulan' => $bulan, 'kategori' => $kat, 'status' => $stat],
            'opsi'     => ['bulan' => $this->daftarBulan($semua), 'status' => Observasi::STATUS_CA,
                           'kategori' => collect(Penilaian::KATEGORI)->map(fn ($n, $k) => ['kunci' => $k, 'nama' => $n])->values()->all()],
            'frekuensi'=> collect($frek)->map(fn ($f, $k) => ['kunci' => $k, 'nama' => Penilaian::KATEGORI[$k]] + $f)
                            ->filter(fn ($f) => $f['butir'] > 0)->sortByDesc('butir')->values()->all(),
            'daftar'   => $daftar,
            'tautan'   => $this->tautan(),
        ]);
    }

    /* ══════════════════ coaching log ══════════════════ */

    public function coaching(Request $r)
    {
        $awal = $this->coachingKosong();

        /* Datang dari rincian sesi: isian diisi dari sesi itu — operator,
           unit, dan rekomendasi coaching-nya sebagai materi awal. */
        if ($r->filled('observasi') && ($o = Observasi::find($r->integer('observasi')))) {
            $rek = Penilaian::rekomendasi($o->nilai());
            $awal = array_merge($awal, [
                'observasi_id' => (string) $o->id,
                'operator'     => $o->operator,
                'unit'         => $o->unit,
                'materi'       => $rek['kode'] === 'belum' ? '' : preg_replace('/^Coaching:\s*/', '', $rek['teks']),
                'coach'        => $o->observer ?? '',
            ]);
        } elseif ($r->filled('operator')) {
            $awal['operator'] = (string) $r->get('operator');
        }

        $daftar = Coaching::with('observasi')
            ->when($r->filled('operator'), fn ($q) => $q->where('operator', $r->get('operator')))
            ->orderByDesc('tanggal')->orderByDesc('id')->get();

        $open = $daftar->where('status', '!=', 'Closed')->count();

        return Inertia::render('Frop/Coaching', [
            'judul'    => 'Coaching Log',
            'subjudul' => 'Pendampingan operator dan tindak lanjutnya',
            'kop' => [
                'angka' => ['label' => 'Sesi coaching', 'nilai' => (string) $daftar->count(),
                            'catatan' => $r->filled('operator') ? $r->get('operator') : 'seluruh operator'],
                'sisi'  => [['Follow up terbuka', (string) $open, $open ? 'ingat' : 'baik']],
            ],
            'saring' => ['operator' => (string) $r->get('operator', '')],
            'awal'   => $awal,
            'bukaForm' => $r->filled('observasi'),
            'daftar' => $daftar->map(fn (Coaching $c) => $this->barisCoaching($c))->all(),
            'opsi'   => ['status' => Coaching::STATUS, 'operator' => $this->namaOperator($this->semua())],
            'bolehHapus' => (bool) auth()->user()?->isAdmin(),
            'tautan' => $this->tautan() + ['simpanCoaching' => route('frop.coaching.store')],
        ]);
    }

    public function simpanCoaching(Request $r)
    {
        $d = $this->vCoaching($r);
        $d['user_id']    = auth()->id();
        $d['company_id'] = auth()->user()->company_id ?? Perusahaan::terpilih();

        $c = Coaching::create($d);
        ActivityLog::write('Catat coaching operator', $c->operator, 'lms');

        return redirect()->route('frop.coaching')->with('ok', 'Coaching '.$c->operator.' tercatat.');
    }

    public function ubahCoaching(Request $r, Coaching $coaching)
    {
        $coaching->update($this->vCoaching($r));

        return back()->with('ok', 'Coaching diperbarui.');
    }

    public function hapusCoaching(Coaching $coaching)
    {
        $coaching->delete();

        return back()->with('ok', 'Coaching dihapus.');
    }

    /* ══════════════════ impor & panduan ══════════════════ */

    public function impor()
    {
        return Inertia::render('Frop/Impor', [
            'judul'    => 'Impor Berkas Observasi',
            'subjudul' => 'Unggah berkas kerja "Observasi PTY-CT Loader" (.xlsx)',
            'hasil'    => session('hasilImpor'),
            'tautan'   => $this->tautan() + ['kirim' => route('frop.impor.kirim')],
        ]);
    }

    public function kirimImpor(Request $r)
    {
        $r->validate([
            'berkas' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ], [], ['berkas' => 'berkas']);

        $f = $r->file('berkas');

        try {
            $hasil = Impor::dariBerkas(
                $f->getRealPath(),
                auth()->user()->company_id ?? Perusahaan::terpilih(),
                auth()->id(),
                mb_substr($f->getClientOriginalName(), 0, 120),
            );
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['berkas' => 'Berkas tidak dapat dibaca sebagai lembar kerja Excel (.xlsx).']);
        }

        ActivityLog::write('Impor observasi operator',
            $hasil['observasi'].' sesi, '.$hasil['coaching'].' coaching', 'lms');

        return redirect()->route('frop.impor')->with('hasilImpor', $hasil);
    }

    public function panduan()
    {
        return Inertia::render('Frop/Panduan', [
            'judul'    => 'Panduan & Acuan FROP',
            'subjudul' => 'Cara observasi, cara pengisian, dan aturan penilaiannya',
            'plan'     => collect(Penilaian::LEVEL)->map(fn ($l, $k) => $l + ['kunci' => $k, 'plan' => Penilaian::PLAN_CT[$k],
                            'komponen' => collect(Penilaian::RASIO_KOMPONEN)->map(
                                fn ($x) => round(Penilaian::PLAN_CT[$k] * $x / array_sum(Penilaian::RASIO_KOMPONEN), 1))->all()])->values()->all(),
            'komponen' => collect(Penilaian::KOMPONEN)->map(fn ($k, $kunci) => $k + ['kunci' => $kunci,
                            'rasio' => Penilaian::RASIO_KOMPONEN[$kunci]])->values()->all(),
            'ambang'   => [
                'loading'  => Observasi::detikKeTeks(Penilaian::LOADING_MAKS),
                'passing'  => Penilaian::N_PASSING_MAKS,
                'spotting' => Penilaian::SPOTTING_TINGGI,
                'digging'  => Penilaian::DIGGING_TINGGI,
                'spot_rata'=> Penilaian::SPOTTING_RATA_TINGGI,
                'pty'      => Penilaian::PTY_TERCAPAI,
                'pty_cukup'=> Penilaian::PTY_CUKUP,
            ],
            'kpi'      => Penilaian::TARGET_KPI,
            'kategori' => collect(Penilaian::KATEGORI)->map(fn ($n, $k) => ['kunci' => $k, 'nama' => $n])->values()->all(),
            'tautan'   => $this->tautan(),
        ]);
    }

    /* ══════════════════ bantuan ══════════════════ */

    /** Seluruh sesi yang terlihat, berurutan waktu naik. */
    private function semua(): Collection
    {
        return Observasi::orderBy('tanggal')->orderBy('id')->get();
    }

    private function baris(Observasi $o): array
    {
        $s   = $o->nilai();
        $kes = Penilaian::kesimpulan($s);

        return [
            'id'          => $o->id,
            'url'         => route('frop.show', $o),
            'tanggal'     => $o->tanggal->toDateString(),
            'shift'       => $o->shift,
            'jam'         => $o->jam_observasi,
            'unit'        => $o->unit,
            'operator'    => $o->operator,
            'observer'    => $o->observer,
            'level'       => $o->level,
            'level_label' => Penilaian::LEVEL[$o->level]['label'] ?? $o->level,
            'digging'     => $o->digging,
            'swl'         => $o->swl,
            'dump'        => $o->dump,
            'swe'         => $o->swe,
            'ct'          => Penilaian::aktualCt($s),
            'plan'        => Penilaian::plan($s),
            'selisih'     => Penilaian::selisih($s),
            'on'          => Penilaian::onTarget($s),
            'pty'         => Penilaian::pencapaian($s),
            'kesimpulan'  => $kes,
            'rekomendasi' => Penilaian::rekomendasi($s),
            'temuan'      => $o->temuan,
            'kategori'    => Penilaian::kategoriTemuan($o->temuan),
            'status_ca'   => $o->status_ca,
        ];
    }

    private function barisCoaching(Coaching $c): array
    {
        return [
            'id'             => $c->id,
            'tanggal'        => $c->tanggal->toDateString(),
            'operator'       => $c->operator,
            'unit'           => $c->unit,
            'materi'         => $c->materi,
            'respons'        => $c->respons,
            'coach'          => $c->coach,
            'follow_up'      => $c->follow_up,
            'target_selesai' => $c->target_selesai?->toDateString(),
            'status'         => $c->status,
            'observasi_id'   => $c->observasi_id,
            'observasi_url'  => $c->observasi_id ? route('frop.show', $c->observasi_id) : null,
            'ubah'           => route('frop.coaching.update', $c),
            'hapus'          => route('frop.coaching.destroy', $c),
        ];
    }

    private function tautan(): array
    {
        return [
            'index'    => route('frop.index'),
            'buat'     => route('frop.create'),
            'operatorRekap' => route('frop.operator'),
            'kpi'      => route('frop.kpi'),
            'tracker'  => route('frop.tracker'),
            'coachingLog' => route('frop.coaching'),
            'impor'    => route('frop.impor'),
            'panduan'  => route('frop.panduan'),
        ];
    }

    /**
     * Bulan yang dipilih, atau bulan terbaru yang ADA datanya.
     *
     * Bawaannya bukan bulan berjalan: di awal bulan halaman itu kosong,
     * dan yang membukanya menyimpulkan datanya hilang.
     */
    private function bulanDipilih(Request $r, Collection $semua, bool $bolehSemua = true): string
    {
        $b = (string) $r->get('bulan', '');

        if ($bolehSemua && $b === 'semua') return 'semua';
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $b)) return $b;

        return $semua->last()?->tanggal->format('Y-m') ?? now()->format('Y-m');
    }

    private function daftarBulan(Collection $semua, bool $denganSemua = true): array
    {
        /* toBase(): pada koleksi Eloquent yang KOSONG, map() tetap
           mengembalikan koleksi Eloquent, dan unique() lalu memanggil
           getKey() pada teks bulan — halaman kosong menjadi galat 500. */
        $b = $semua->toBase()->map(fn (Observasi $o) => $o->tanggal->format('Y-m'))
            ->push(now()->format('Y-m'))->unique()->sortDesc()->values()
            ->map(fn ($x) => ['nilai' => $x, 'label' => $this->namaBulan($x)])->all();

        return $denganSemua ? array_merge([['nilai' => 'semua', 'label' => 'Seluruh periode']], $b) : $b;
    }

    private function namaBulan(string $ym): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $ym)->translatedFormat('F Y');
    }

    private function namaOperator(Collection $semua): array
    {
        return $semua->groupBy(fn (Observasi $o) => Penilaian::kunciOrang($o->operator))
            ->map(fn ($g) => $g->last()->operator)->sort()->values()->all();
    }

    private function angka(?float $v): string
    {
        return $v === null ? '–' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }

    private function kosong(): array
    {
        return [
            'tanggal' => now()->toDateString(), 'shift' => '1', 'jam_observasi' => '',
            'unit' => '', 'operator' => '', 'gl_front' => '', 'observer' => auth()->user()?->name ?? '',
            'verified_by' => '',
            'kondisi_mesin' => '', 'mode_kerja' => '', 'level' => 'average', 'material' => '',
            'metode_posisi' => '', 'operating_condition' => '', 'metode_loading' => '', 'mto' => '',
            'tinggi_jenjang' => '', 'lebar_front' => '',
            'kondisi_permukaan' => '', 'boulder' => '', 'sudut_pass' => '', 'cuaca' => '',
            'spotting' => '', 'digging' => '', 'swl' => '', 'dump' => '', 'swe' => '',
            'plan_ct' => '',
            'loading' => '', 'n_passing' => '', 'bucket_heap' => '',
            'target_pty' => '', 'aktual_pty' => '',
            'temuan' => '', 'corrective_action' => '', 'status_ca' => 'Open',
            'pic_ca' => '', 'deadline_ca' => '', 'catatan' => '',
        ];
    }

    private function isian(Observasi $o): array
    {
        $s = fn ($v) => $v === null ? '' : (string) $v;
        $b = fn (?bool $v) => $v === null ? '' : ($v ? '1' : '0');
        $a = fn (?float $v) => $v === null ? '' : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        $plan = Penilaian::planCt($o->level);

        return [
            'tanggal' => $o->tanggal->toDateString(), 'shift' => $s($o->shift), 'jam_observasi' => $s($o->jam_observasi),
            'unit' => $o->unit, 'operator' => $o->operator, 'gl_front' => $s($o->gl_front), 'observer' => $s($o->observer),
            'verified_by' => $s($o->verified_by),
            'kondisi_mesin' => $s($o->kondisi_mesin), 'mode_kerja' => $s($o->mode_kerja), 'level' => $o->level,
            'material' => $s($o->material), 'metode_posisi' => $s($o->metode_posisi),
            'operating_condition' => $s($o->operating_condition), 'metode_loading' => $s($o->metode_loading),
            'mto' => $s($o->mto), 'tinggi_jenjang' => $a($o->tinggi_jenjang), 'lebar_front' => $a($o->lebar_front),
            'kondisi_permukaan' => $s($o->kondisi_permukaan), 'boulder' => $b($o->boulder),
            'sudut_pass' => $s($o->sudut_pass), 'cuaca' => $s($o->cuaca),
            'spotting' => $a($o->spotting), 'digging' => $a($o->digging), 'swl' => $a($o->swl),
            'dump' => $a($o->dump), 'swe' => $a($o->swe),
            /* Kosong berarti "mengikuti level". Hanya plan yang memang
               disetel berbeda dari acuan levelnya yang ditampilkan. */
            'plan_ct' => $plan !== null && abs($plan - $o->plan_ct) < 0.001 ? '' : $a($o->plan_ct),
            'loading' => $s($o->loadingTeks()), 'n_passing' => $s($o->n_passing), 'bucket_heap' => $b($o->bucket_heap),
            'target_pty' => $s($o->target_pty), 'aktual_pty' => $s($o->aktual_pty),
            'temuan' => $s($o->temuan), 'corrective_action' => $s($o->corrective_action), 'status_ca' => $o->status_ca,
            'pic_ca' => $s($o->pic_ca), 'deadline_ca' => $s($o->deadline_ca?->toDateString()), 'catatan' => $s($o->catatan),
        ];
    }

    private function opsiForm(): array
    {
        $semua = $this->semua();

        return [
            'level'   => collect(Penilaian::LEVEL)->map(fn ($l, $k) => ['kunci' => $k, 'label' => $l['label'],
                           'contoh' => $l['contoh'], 'plan' => Penilaian::PLAN_CT[$k]])->values()->all(),
            'rasio'   => Penilaian::RASIO_KOMPONEN,
            'kondisi_mesin'       => Observasi::KONDISI_MESIN,
            'mode_kerja'          => Observasi::MODE_KERJA,
            'operating_condition' => Observasi::OPERATING_CONDITION,
            'metode_loading'      => Observasi::METODE_LOADING,
            'mto'                 => Observasi::MTO,
            'kondisi_permukaan'   => Observasi::KONDISI_PERMUKAAN,
            'cuaca'               => Observasi::CUACA,
            'status_ca'           => Observasi::STATUS_CA,
            /* Isian yang pernah dipakai, sebagai saran ketik. */
            'operator'      => $this->namaOperator($semua),
            'unit'          => $semua->pluck('unit')->unique()->sort()->values()->all(),
            'material'      => $semua->pluck('material')->filter()->unique()->sort()->values()->all(),
            'metode_posisi' => $semua->pluck('metode_posisi')->filter()->unique()->sort()->values()->all(),
            'orang'         => $semua->toBase()->flatMap(fn ($o) => [$o->gl_front, $o->observer])->filter()->unique()->sort()->values()->all(),
            'ambang' => [
                'loading' => Penilaian::LOADING_MAKS, 'passing' => Penilaian::N_PASSING_MAKS,
                'spotting' => Penilaian::SPOTTING_TINGGI, 'digging' => Penilaian::DIGGING_TINGGI,
                'pty' => Penilaian::PTY_TERCAPAI,
            ],
        ];
    }

    private function v(Request $r): array
    {
        $opsi = fn (array $a) => ['nullable', Rule::in($a)];
        $detik = ['nullable', 'numeric', 'min:0', 'max:600'];

        $d = $r->validate([
            'tanggal'             => ['required', 'date'],
            'shift'               => ['nullable', Rule::in(['1', '2', '3', 1, 2, 3])],
            'jam_observasi'       => ['nullable', 'string', 'max:40'],
            'unit'                => ['required', 'string', 'max:60'],
            'operator'            => ['required', 'string', 'max:120'],
            'gl_front'            => ['nullable', 'string', 'max:120'],
            'observer'            => ['nullable', 'string', 'max:120'],
            'verified_by'         => ['nullable', 'string', 'max:120'],
            'kondisi_mesin'       => $opsi(Observasi::KONDISI_MESIN),
            'mode_kerja'          => $opsi(Observasi::MODE_KERJA),
            'level'               => ['required', Rule::in(array_keys(Penilaian::PLAN_CT))],
            'material'            => ['nullable', 'string', 'max:80'],
            'metode_posisi'       => ['nullable', 'string', 'max:80'],
            'operating_condition' => $opsi(Observasi::OPERATING_CONDITION),
            'metode_loading'      => $opsi(Observasi::METODE_LOADING),
            'mto'                 => $opsi(Observasi::MTO),
            'tinggi_jenjang'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lebar_front'         => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'kondisi_permukaan'   => $opsi(Observasi::KONDISI_PERMUKAAN),
            'boulder'             => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'sudut_pass'          => ['nullable', 'string', 'max:20'],
            'cuaca'               => $opsi(Observasi::CUACA),
            'spotting'            => $detik,
            'digging'             => ['required', 'numeric', 'min:0', 'max:600'],
            'swl'                 => ['required', 'numeric', 'min:0', 'max:600'],
            'dump'                => ['required', 'numeric', 'min:0', 'max:600'],
            'swe'                 => ['required', 'numeric', 'min:0', 'max:600'],
            'plan_ct'             => ['nullable', 'numeric', 'min:1', 'max:120'],
            'loading'             => ['nullable', 'string', 'max:8', 'regex:/^(\d{1,2}:[0-5]\d|\d{1,4})$/'],
            'n_passing'           => ['nullable', 'string', 'max:20', 'regex:/^\d+(\s*[-–]\s*\d+)?$/'],
            'bucket_heap'         => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'target_pty'          => ['nullable', 'integer', 'min:0', 'max:100000'],
            'aktual_pty'          => ['nullable', 'integer', 'min:0', 'max:100000'],
            'temuan'              => ['nullable', 'string', 'max:2000'],
            'corrective_action'   => ['nullable', 'string', 'max:2000'],
            'status_ca'           => ['required', Rule::in(Observasi::STATUS_CA)],
            'pic_ca'              => ['nullable', 'string', 'max:120'],
            'deadline_ca'         => ['nullable', 'date'],
            'catatan'             => ['nullable', 'string', 'max:2000'],
        ], [
            'loading.regex'   => 'Loading time ditulis m:ss (mis. 1:25) atau jumlah detik (mis. 85).',
            'n_passing.regex' => 'N passing berupa angka (mis. 5) atau rentang (mis. 5-6).',
        ], [
            'digging' => 'digging', 'swl' => 'swing loaded', 'dump' => 'dump', 'swe' => 'swing empty',
            'plan_ct' => 'plan CT', 'level' => 'jenis material', 'unit' => 'CN unit',
            'target_pty' => 'target PTY', 'aktual_pty' => 'aktual PTY', 'status_ca' => 'status CA',
        ]);

        $d['loading_detik'] = Observasi::teksKeDetik($d['loading'] ?? null);
        unset($d['loading']);

        /* Plan kosong = mengikuti levelnya, dan angka itu yang disimpan:
           patokan pada hari observasi, bukan acuan yang kelak direvisi. */
        $d['plan_ct'] = is_numeric($d['plan_ct'] ?? null) ? (float) $d['plan_ct'] : Penilaian::planCt($d['level']);

        foreach (['boulder', 'bucket_heap'] as $k) {
            $d[$k] = ($d[$k] ?? '') === '' || $d[$k] === null ? null : (bool) (int) $d[$k];
        }
        $d['shift'] = ($d['shift'] ?? '') === '' ? null : (int) $d['shift'];

        return $d;
    }

    /**
     * Tanggal selesai CA mengikuti statusnya: diisi hari ini saat
     * pertama kali Closed, dipertahankan bila sudah ada, dan dikosongkan
     * bila temuan dibuka kembali.
     */
    private static function selesai(array $d, ?Observasi $lama = null): array
    {
        $d['selesai_ca'] = $d['status_ca'] === 'Closed'
            ? ($lama?->selesai_ca?->toDateString() ?? now()->toDateString())
            : null;

        return $d;
    }

    private function coachingKosong(): array
    {
        return [
            'observasi_id' => '', 'tanggal' => now()->toDateString(), 'operator' => '', 'unit' => '',
            'materi' => '', 'respons' => '', 'coach' => auth()->user()?->name ?? '', 'follow_up' => '',
            'target_selesai' => '', 'status' => 'Open',
        ];
    }

    private function vCoaching(Request $r): array
    {
        $d = $r->validate([
            'observasi_id'   => ['nullable', 'integer'],
            'tanggal'        => ['required', 'date'],
            'operator'       => ['required', 'string', 'max:120'],
            'unit'           => ['nullable', 'string', 'max:60'],
            'materi'         => ['required', 'string', 'max:2000'],
            'respons'        => ['nullable', 'string', 'max:2000'],
            'coach'          => ['nullable', 'string', 'max:120'],
            'follow_up'      => ['nullable', 'string', 'max:2000'],
            'target_selesai' => ['nullable', 'date'],
            'status'         => ['required', Rule::in(Coaching::STATUS)],
        ], [], ['materi' => 'materi coaching', 'coach' => 'PIC coach']);

        /* Rujukan sesi hanya ke sesi yang TERLIHAT — batas perusahaan
           ikut berlaku. Rujukan ke sesi perusahaan lain dibuang, bukan
           disimpan diam-diam. */
        $d['observasi_id'] = ! empty($d['observasi_id']) && Observasi::whereKey($d['observasi_id'])->exists()
            ? (int) $d['observasi_id'] : null;

        return $d;
    }
}
