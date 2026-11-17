<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Certificate, Company, Enrollment, GeoLereng, HazardReport, IzinAmbang, IzinKerja, P2hPeriksa, P2hUnit, WaterSump, WorkOrder};
use App\Models\Investigasi\{Insiden, KlasifikasiCedera};
use App\Support\{Berkas, Cuaca, Dasbor, Hazard, Menu, Modules, P2h, PeringatanIzin, Pillars, RisikoLapangan, Waktu};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Mode Lapangan — pendamping seluler EQOHSEE.
 *
 * Layar-layar ringkas untuk dipakai di muka tambang: beranda awal shift,
 * lapor bahaya bermatriks 5×5, P2H, izin kerja, sertifikat, dan daftar
 * modul. Bukan salinan kedua dari aturan: laporan bahaya tersimpan di
 * tabel hazard_reports yang sama, izin dibaca dari model izin yang sama,
 * dan tindak lanjut laporan memakai rute hazard.follow yang sudah ada.
 *
 * ── KIRIMAN YANG BISA TERULANG ──
 *
 * Laporan dan P2H dapat disusun tanpa sinyal lalu dikirim ulang oleh
 * perangkat begitu tersambung. Kiriman kedua — sinyal putus tepat saat
 * jawaban server datang — membawa klien_id yang sama dan dijawab dengan
 * catatan yang sudah ada, bukan salinan baru.
 */
class LapanganController extends Controller
{
    /** Kategori temuan: label lapangan → kategori Hazard yang tersimpan. */
    public const JENIS_TEMUAN = [
        'Unsafe Condition'  => 'Kondisi tidak aman',
        'Unsafe Action'     => 'Tindakan tidak aman',
        'Near Miss'         => 'Hampir celaka',
        'Bahaya Lingkungan' => 'Bahaya lingkungan',
    ];

    /* ═══════════════════ beranda ═══════════════════ */

    public function beranda(Request $request)
    {
        $u = $request->user();
        $perusahaan = $u->company;
        $kini = Waktu::kini();

        $peringatan = $this->peringatanSite();
        $tindakan   = $this->tindakan($u);

        return Inertia::render('Lapangan/Beranda', [
            'saya'       => $this->pengguna($u),
            'waktu'      => [
                'tanggal' => mb_strtoupper($kini->translatedFormat('D j M')),
                'shift'   => P2h::SHIFT[P2h::shiftSekarang($kini)],
                'salam'   => $this->salam($kini),
            ],
            'site'       => $perusahaan?->location ?: $perusahaan?->name,
            'cuaca'      => rescue(fn () => Cuaca::untuk($perusahaan), null, false),
            'angka'      => [
                'tanpaLti'   => $this->hariTanpaLti(),
                'perlu'      => count($tindakan),
                'peringatan' => count($peringatan),
            ],
            'peringatan' => $peringatan,
            'tindakan'   => array_slice($tindakan, 0, 4),
            'lencana'    => $this->lencana($u, $tindakan),
        ]);
    }

    /* ═══════════════════ modul ═══════════════════ */

    public function modul(Request $request)
    {
        $u = $request->user();

        /* Angka tunggakan per modul dari ringkasan dasbor yang sama —
           modul yang butuh perhatian naik ke atas beserta jumlahnya. */
        $perlu = [];
        foreach (Dasbor::ringkasanModul(Dasbor::modul($u)) as $m) {
            $perlu[$m['modul']] = (int) $m['perlu'];
        }

        $katalog = Modules::perKunciMenu();
        $pilar   = Pillars::all();

        $daftar = [];
        foreach (Menu::untuk($u) as $kunci => $m) {
            if (in_array($kunci, ['dasbor'], true)) continue;
            $k = $katalog[$kunci] ?? null;
            $p = $k ? ($pilar[$k['pilar']] ?? null) : null;
            $rute = Menu::ruteAwal($m);

            $daftar[] = [
                'kunci' => $kunci,
                'nama'  => $m['label'],
                'ket'   => $k['ket'] ?? ($m['semboyan'] ?? ''),
                'ikon'  => $m['icon'] ?? null,
                'pilar' => $k['pilar'] ?? 'lainnya',
                'warna' => $p['warna'] ?? '#3A4450',
                'perlu' => $perlu[$kunci] ?? 0,
                'url'   => $rute && \Illuminate\Support\Facades\Route::has($rute) ? route($rute) : null,
            ];
        }

        usort($daftar, fn ($a, $b) => [$b['perlu'] > 0, $b['perlu']] <=> [$a['perlu'] > 0, $a['perlu']]);

        $namaPilar = [];
        foreach ($daftar as $d) {
            if (isset($pilar[$d['pilar']])) $namaPilar[$d['pilar']] = $pilar[$d['pilar']]['nama'];
        }

        return Inertia::render('Lapangan/Modul', [
            'saya'     => $this->pengguna($u),
            'modul'    => $daftar,
            'pilar'    => $namaPilar,
            'lencana'  => $this->lencana($u),
        ]);
    }

    /* ═══════════════════ tugas ═══════════════════ */

    public function tugas(Request $request)
    {
        $u = $request->user();
        $tindakan = $this->tindakan($u);

        return Inertia::render('Lapangan/Tugas', [
            'tindakan' => $tindakan,
            'laporanSaya' => HazardReport::where('user_id', $u->id)->latest('id')->limit(15)->get()
                ->map(fn (HazardReport $h) => $this->ringkasLaporan($h))->values(),
            'lencana' => $this->lencana($u, $tindakan),
        ]);
    }

    /* ═══════════════════ profil ═══════════════════ */

    public function profil(Request $request)
    {
        $u = $request->user();

        return Inertia::render('Lapangan/Profil', [
            'saya' => $this->pengguna($u) + [
                'email' => $u->email,
                'nik'   => $u->employee_id,
                'departemen' => $u->department,
            ],
            'tautan' => [
                'lengkap'    => route('dashboard'),
                'akun'       => \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : null,
                'hapusAkun'  => \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit').'#hapus-akun' : null,
                'privasi'    => route('hukum.privasi'),
                'keluar'     => route('logout'),
                'sertifikat' => route('lapangan.sertifikat'),
                'izin'       => route('lapangan.izin'),
                'p2h'        => route('lapangan.p2h'),
            ],
            'lencana' => $this->lencana($u),
        ]);
    }

    /* ═══════════════════ lapor bahaya ═══════════════════ */

    public function lapor(Request $request)
    {
        $u = $request->user();

        return Inertia::render('Lapangan/Lapor', [
            'jenis'      => self::JENIS_TEMUAN,
            'matriks'    => RisikoLapangan::untukLayar(),
            'perusahaan' => $this->perusahaanTujuan($u),
            'bawaan'     => ['company_id' => $u->company_id],
            'maksFoto'   => 6,
            'tautan'     => ['simpan' => route('lapangan.lapor.simpan')],
            'lencana'    => $this->lencana($u),
        ]);
    }

    public function laporSimpan(Request $request)
    {
        $u = $request->user();

        $d = $request->validate([
            'klien_id'    => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'company_id'  => ['nullable', 'integer', 'exists:companies,id'],
            'kategori'    => ['required', Rule::in(array_keys(self::JENIS_TEMUAN))],
            'deskripsi'   => ['required', 'string', 'max:3000'],
            'lokasi'      => ['nullable', 'string', 'max:200'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'akurasi_m'   => ['nullable', 'integer', 'min:0', 'max:65000'],
            'kemungkinan' => ['required', 'integer', 'between:1,5'],
            'keparahan'   => ['required', 'integer', 'between:1,5'],
            'rekomendasi' => ['nullable', 'string', 'max:2000'],
            'disusun_pada'=> ['nullable', 'date'],
            'foto'        => ['nullable', 'array', 'max:6'],
            'foto.*'      => Berkas::ATURAN_GAMBAR,
        ], [], [
            'deskripsi' => 'uraian temuan', 'kategori' => 'jenis temuan',
            'kemungkinan' => 'kemungkinan', 'keparahan' => 'keparahan', 'foto.*' => 'foto',
        ]);

        if (!empty($d['klien_id']) && ($ada = HazardReport::where('user_id', $u->id)->where('klien_id', $d['klien_id'])->first())) {
            return $this->jawabLaporan($request, $ada, false);
        }

        $tujuan = $d['company_id'] ?? $u->company_id;
        abort_if($tujuan === null, 422, 'Pilih perusahaan tujuan laporan.');
        abort_unless(in_array((int) $tujuan, array_column($this->perusahaanTujuan($u), 'id'), true), 403);

        $waktu = $this->waktuSusun($d['disusun_pada'] ?? null);
        $k = (int) $d['kemungkinan'];
        $p = (int) $d['keparahan'];

        $r = HazardReport::create([
            'kode'        => HazardReport::kodeBaru(),
            'klien_id'    => $d['klien_id'] ?? null,
            'user_id'     => $u->id,
            'pelapor_nama'       => $u->name,
            'pelapor_nrp'        => $u->employee_id,
            'pelapor_perusahaan' => $u->company?->name,
            'pelapor_departemen' => $u->department,
            'pelapor_jabatan'    => in_array($u->position, Hazard::JABATAN, true) ? $u->position : 'Lainnya',
            'company_id'  => (int) $tujuan,
            'tanggal'     => $waktu->toDateString(),
            'waktu'       => $waktu->format('H:i'),
            'lokasi'      => $d['lokasi'] ?? null,
            'lat'         => $d['lat'] ?? null,
            'lng'         => $d['lng'] ?? null,
            'akurasi_m'   => $d['akurasi_m'] ?? null,
            'kemungkinan' => $k,
            'keparahan'   => $p,
            'risiko'      => RisikoLapangan::risiko($k, $p),
            'batas_akhir' => RisikoLapangan::batasAkhir($k, $p, $waktu)->toDateString(),
            'kategori'    => $d['kategori'],
            'deskripsi'   => $d['deskripsi'],
            'rekomendasi' => $d['rekomendasi'] ?? null,
            'foto'        => Berkas::simpanBanyak($request->file('foto', []), 'hazard'),
            'status'      => 'Open',
        ]);

        ActivityLog::write('Buat hazard report (lapangan)', $r->kode.' — '.($r->lokasi ?: 'tanpa lokasi'), 'hazrep');

        return $this->jawabLaporan($request, $r, true);
    }

    public function laporan(Request $request, HazardReport $hazard)
    {
        $hazard->load(['company', 'user', 'closer']);
        $u = $request->user();

        return Inertia::render('Lapangan/Laporan', [
            'r' => $this->ringkasLaporan($hazard) + [
                'deskripsi'   => $hazard->deskripsi,
                'rekomendasi' => $hazard->rekomendasi,
                'perusahaan'  => $hazard->company?->name,
                'pelapor'     => $hazard->pelapor_nama,
                /* Jabatan di luar daftar tersimpan sebagai "Lainnya";
                   jabatan akunnya lebih berguna daripada kata itu. */
                'jabatan'     => $hazard->pelapor_jabatan === 'Lainnya' ? ($hazard->user?->position ?: 'Lainnya') : $hazard->pelapor_jabatan,
                'koordinat'   => $hazard->lat !== null ? ['lat' => $hazard->lat, 'lng' => $hazard->lng, 'akurasi' => $hazard->akurasi_m] : null,
                'kemungkinan' => $hazard->kemungkinan,
                'keparahan'   => $hazard->keparahan,
                'foto'        => Berkas::daftarUrl($hazard, 'hzd'),
                'fotoTindak'  => Berkas::daftarUrl($hazard, 'hzt'),
                'catatan'     => $hazard->catatan_penutupan,
            ],
            'linimasa' => $this->linimasa($hazard),
            'tautan'   => [
                'tindak'  => route('hazard.follow', $hazard),
                'lengkap' => route('hazard.show', $hazard),
            ],
            'lencana' => $this->lencana($u),
        ]);
    }

    /* ═══════════════════ izin kerja ═══════════════════ */

    public function izin(Request $request)
    {
        $u = $request->user();
        $kini = Waktu::kini();

        $izin = IzinKerja::with(['periksa', 'gas', 'penutup'])
            ->where('mulai', '<=', $kini->copy()->endOfDay())
            ->where('selesai', '>=', $kini->copy()->startOfDay())
            ->orderBy('mulai')->get();

        $ambang = IzinAmbang::berlaku(IzinAmbang::all());

        $bentrok = array_values(array_filter(
            PeringatanIzin::susun($izin, $ambang, true),
            fn ($p) => str_starts_with($p['kode'], 'bentrok'),
        ));

        $baris = $izin->map(function (IzinKerja $i) use ($ambang, $kini) {
            $g = $i->ujiTerakhir();
            $batas = $i->batasUji();
            $usia = $g ? (int) floor($g->waktu_uji->diffInMinutes($kini, true)) : null;

            return [
                'id'     => $i->id,
                'nomor'  => $i->nomor,
                'jenis'  => $i->jenis,
                'uraian' => $i->uraian,
                'lokasi' => $i->lokasi,
                'jam'    => $i->mulai?->format('H:i').'–'.$i->selesai?->format('H:i'),
                'pemohon'=> $i->pengaju?->name ?? $i->pelaksana,
                'status' => match (true) {
                    $i->sudahDitutup()      => 'ditutup',
                    $i->menungguTinjauan()  => 'menunggu',
                    $i->sudahDisetujui()    => 'aktif',
                    default                 => 'draf',
                },
                'statusLabel' => $i->toView($ambang)['statusLabel'],
                'gas' => $i->perluUjiGas() ? [
                    'usia'  => $usia,
                    'batas' => $batas,
                    'sisa'  => $usia === null ? null : $batas - $usia,
                    'segar' => $i->ujiMasihSegar($kini),
                    'bacaan'=> $g ? $g->toView($ambang, $batas) : null,
                ] : null,
                'bisaTutup' => $i->sudahDisetujui() && !$i->sudahDitutup(),
                'tautan' => [
                    'gas'   => route('izin.gas.simpan', $i),
                    'tutup' => route('izin.tutup', $i),
                ],
            ];
        })->values();

        return Inertia::render('Lapangan/Izin', [
            'izin'    => $baris,
            'bentrok' => $bentrok,
            'ambang'  => $ambang,
            'tautan'  => ['lengkap' => route('izin.daftar'), 'baru' => route('izin.index')],
            'petugas' => $u->name,
            'lencana' => $this->lencana($u),
        ]);
    }

    /* ═══════════════════ sertifikat ═══════════════════ */

    public function sertifikat(Request $request)
    {
        $u = $request->user();

        $sertifikat = Certificate::where('user_id', $u->id)->latest('issued_at')->get()->map(fn (Certificate $c) => [
            'id'       => $c->id,
            'nomor'    => $c->certificate_number,
            'nama'     => $c->recipient_name,
            'kursus'   => $c->course_title,
            'nilai'    => $c->final_score,
            'terbit'   => $c->issued_at?->translatedFormat('j M Y'),
            'penanda'  => $c->signed_by_name,
            'verifikasi' => $c->verification_code ? route('certificates.verify', $c->verification_code) : null,
            'unduh'    => route('certificates.show', $c),
        ])->values();

        $berjalan = Enrollment::with('course')->where('user_id', $u->id)
            ->where('status', '!=', 'finished')->latest('updated_at')->limit(3)->get()
            ->map(fn (Enrollment $e) => [
                'judul'    => $e->course?->title ?? 'Kursus',
                'progress' => (int) $e->progress,
                'url'      => $e->course ? (\Illuminate\Support\Facades\Route::has('courses.show') ? route('courses.show', $e->course) : null) : null,
            ])->values();

        return Inertia::render('Lapangan/Sertifikat', [
            'saya'       => $this->pengguna($u),
            'sertifikat' => $sertifikat,
            'berjalan'   => $berjalan,
            'lencana'    => $this->lencana($u),
        ]);
    }

    /* ═══════════════════ P2H ═══════════════════ */

    public function p2h(Request $request)
    {
        $u = $request->user();
        $kini = Waktu::kini();
        $shift = P2h::shiftSekarang($kini);

        $unit = P2hUnit::with('terakhir')->where('aktif', true)->orderByDesc('status')->orderBy('kode')->get();

        return Inertia::render('Lapangan/P2hDaftar', [
            'shift' => P2h::SHIFT[$shift],
            'unit'  => $unit->map(fn (P2hUnit $x) => $this->ringkasUnit($x, $kini, $shift))->values(),
            'dapatMelepas' => $this->dapatMelepas($u),
            'lencana' => $this->lencana($u),
        ]);
    }

    public function p2hIsi(Request $request, P2hUnit $unit)
    {
        $u = $request->user();
        $kini = Waktu::kini();

        return Inertia::render('Lapangan/P2h', [
            'unit'  => $this->ringkasUnit($unit->load('terakhir'), $kini, P2h::shiftSekarang($kini)),
            'butir' => P2h::butir($unit->jenis),
            'shift' => ['nilai' => P2h::shiftSekarang($kini), 'pilihan' => P2h::SHIFT],
            'operator' => $u->name,
            'tautan' => ['simpan' => route('lapangan.p2h.simpan', $unit), 'daftar' => route('lapangan.p2h')],
            'lencana' => $this->lencana($u),
        ]);
    }

    public function p2hSimpan(Request $request, P2hUnit $unit)
    {
        $u = $request->user();

        $d = $request->validate([
            'klien_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'operator' => ['required', 'string', 'max:150'],
            'shift'    => ['required', Rule::in(array_keys(P2h::SHIFT))],
            'hm'       => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'jawab'    => ['required', 'array'],
            'jawab.*'  => [Rule::in(P2h::NILAI)],
            'catatan'  => ['nullable', 'string', 'max:2000'],
            'disusun_pada' => ['nullable', 'date'],
            'foto'     => ['nullable', 'array', 'max:6'],
            'foto.*'   => Berkas::ATURAN_GAMBAR,
        ], [], ['jawab' => 'jawaban', 'foto.*' => 'foto']);

        if (!empty($d['klien_id']) && ($ada = P2hPeriksa::where('user_id', $u->id)->where('klien_id', $d['klien_id'])->first())) {
            return $this->jawabP2h($request, $ada, false);
        }

        $n = P2h::nilai($unit->jenis, $d['jawab']);

        if ($n['hasil'] === null) {
            return $this->tolak($request, 'jawab', 'Seluruh butir wajib dijawab: OK, Tidak, atau N/A.');
        }
        if ($n['kritisGagal'] && !$request->hasFile('foto')) {
            return $this->tolak($request, 'foto', 'Butir kritis yang gagal wajib disertai foto bukti.');
        }

        $waktu = $this->waktuSusun($d['disusun_pada'] ?? null);

        $periksa = DB::transaction(function () use ($request, $u, $unit, $d, $n, $waktu) {
            $periksa = P2hPeriksa::create([
                'company_id'   => $unit->company_id,
                'p2h_unit_id'  => $unit->id,
                'user_id'      => $u->id,
                'klien_id'     => $d['klien_id'] ?? null,
                'operator'     => $d['operator'],
                'shift'        => $d['shift'],
                'tanggal'      => $waktu->toDateString(),
                'hm'           => $d['hm'] ?? null,
                'jawaban'      => $n['jawaban'],
                'jumlah_ok'    => $n['ok'],
                'jumlah_tidak' => $n['tidak'],
                'jumlah_na'    => $n['na'],
                'hasil'        => $n['hasil'],
                'catatan'      => $d['catatan'] ?? null,
                'foto'         => Berkas::simpanBanyak($request->file('foto', []), 'p2h'),
            ]);

            if (isset($d['hm'])) $unit->hm = max((float) $unit->hm, (float) $d['hm']);

            if ($n['hasil'] === P2h::DITAHAN) {
                $alasan = implode('; ', $n['kritisGagal']);

                /* Mekanik diberi tahu lewat perintah kerja, bukan pesan:
                   perintah kerja punya pemilik, status, dan tercatat di
                   keandalan armada. Satu unit ditahan = satu perintah
                   kerja yang masih terbuka; P2H berikutnya yang gagal
                   untuk unit yang sama tidak membuka yang kedua. */
                $wo = $unit->ditahan()
                    ? P2hPeriksa::where('p2h_unit_id', $unit->id)->whereNotNull('work_order_id')->latest('id')->value('work_order_id')
                    : null;

                if (!$wo) {
                    $wo = WorkOrder::create([
                        'company_id'      => $unit->company_id,
                        'user_id'         => $u->id,
                        'ko_object_id'    => $unit->ko_object_id,
                        'jenis'           => 'korektif',
                        'prioritas'       => 'kritis',
                        'status'          => 'dibuka',
                        'gejala'          => "P2H {$unit->kode} ({$unit->nama}) — unit ditahan: {$alasan}"
                                             .($d['catatan'] ? ". Catatan operator: {$d['catatan']}" : ''),
                        'dilaporkan_pada' => $waktu,
                        'hm_saat_rusak'   => $d['hm'] ?? null,
                    ])->id;
                }

                $periksa->update(['work_order_id' => $wo]);
                $unit->fill([
                    'status' => P2h::DITAHAN,
                    'ditahan_sejak'  => $unit->ditahan() ? $unit->ditahan_sejak : now(),
                    'ditahan_karena' => mb_substr($alasan, 0, 500),
                ]);
            }

            $unit->save();

            return $periksa;
        });

        ActivityLog::write('Kirim P2H', "{$unit->kode} · {$periksa->hasil}", 'maintenance');

        return $this->jawabP2h($request, $periksa, true);
    }

    /** Lepas tahan — oleh orang yang berwenang, dengan alasannya. */
    public function p2hLepas(Request $request, P2hUnit $unit)
    {
        abort_unless($this->dapatMelepas($request->user()), 403);

        $d = $request->validate(['catatan' => ['required', 'string', 'min:5', 'max:500']],
            [], ['catatan' => 'catatan pelepasan']);

        abort_unless($unit->ditahan(), 422, 'Unit ini tidak sedang ditahan.');

        $unit->update([
            'status'        => P2h::LAIK,
            'dilepas_oleh'  => $request->user()->id,
            'dilepas_pada'  => now(),
            'catatan_lepas' => $d['catatan'],
        ]);

        ActivityLog::write('Lepas tahan unit P2H', "{$unit->kode} — {$d['catatan']}", 'maintenance');

        return back()->with('ok', "{$unit->kode} kembali laik operasi.");
    }

    /* ═══════════════════ pembantu ═══════════════════ */

    private function pengguna($u): array
    {
        $nama = trim((string) $u->name);

        /* Gelar di depan nama bukan nama panggilan: "Selamat pagi, Ir."
           dan inisial "IB" untuk Ir. Budi Santoso sama-sama salah. */
        $kata = array_values(array_filter(preg_split('/\s+/', $nama) ?: [$nama],
            fn ($k) => !preg_match('/^(ir|dr|drs|dra|prof|h|hj|kh|dr\.ir)\.?,?$/i', $k)));
        if (!$kata) $kata = [$nama];

        return [
            'nama'    => $nama,
            'depan'   => $kata[0] ?? $nama,
            'inisial' => mb_strtoupper(mb_substr($kata[0] ?? '', 0, 1).mb_substr($kata[1] ?? '', 0, 1)),
            'jabatan' => $u->position,
            'perusahaan' => $u->company?->name,
        ];
    }

    private function salam(Carbon $t): string
    {
        $j = (int) $t->format('G');

        return match (true) {
            $j < 11 => 'Selamat pagi',
            $j < 15 => 'Selamat siang',
            $j < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /** Pelepas tahan: administrator, KTT, atau petugas OHSE. */
    private function dapatMelepas($u): bool
    {
        return $u && ($u->isAdmin() || $u->isKtt() || $u->isOhse());
    }

    /**
     * Hari sejak kecelakaan kehilangan hari kerja terakhir (LTI atau lebih berat).
     *
     * Null bila register insiden belum mencatat satu pun — menghitung
     * "hari tanpa LTI" dari ketiadaan catatan akan memajang rekor yang
     * sebenarnya hanya berarti register belum dipakai.
     */
    private function hariTanpaLti(): ?int
    {
        return rescue(function () {
            $id = KlasifikasiCedera::whereIn('kode', ['lti', 'berat', 'cacat_tetap', 'mati'])->pluck('id');
            $akhir = Insiden::whereIn('klasifikasi_cedera_id', $id)->max('tanggal_kejadian');

            return $akhir ? (int) Carbon::parse($akhir)->startOfDay()->diffInDays(Waktu::kini()->startOfDay()) : null;
        }, null, false);
    }

    /** Perusahaan yang boleh dituju laporan: miliknya sendiri, atau semua bagi admin. */
    private function perusahaanTujuan($u): array
    {
        $q = Company::query()->orderBy('name');
        if (!$u->isAdmin()) $q->whereKey($u->company_id);

        return $q->get(['id', 'name'])->map(fn ($c) => ['id' => (int) $c->id, 'nama' => $c->name])->all();
    }

    /**
     * Waktu penyusunan: jam perangkat saat laporan disusun luring, dibatasi.
     *
     * Laporan yang tertahan tanpa sinyal tercatat pada saat temuannya
     * disusun, bukan saat sinyalnya kembali. Tetapi jam perangkat tidak
     * dipercaya begitu saja: yang di masa depan menjadi sekarang, yang
     * lebih tua dari tujuh hari menjadi tujuh hari lalu.
     */
    private function waktuSusun(?string $disusun): Carbon
    {
        $kini = Waktu::kini();
        if (!$disusun) return $kini;

        $t = rescue(fn () => Carbon::parse($disusun)->setTimezone($kini->getTimezone()), $kini, false);

        return $t->min($kini)->max($kini->copy()->subDays(7));
    }

    private function jawabLaporan(Request $request, HazardReport $r, bool $baru)
    {
        $url = route('lapangan.laporan', $r);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'baru' => $baru, 'id' => $r->id, 'kode' => $r->kode, 'url' => $url], $baru ? 201 : 200);
        }

        return redirect($url)->with('ok', "Laporan {$r->kode} terkirim.");
    }

    private function jawabP2h(Request $request, P2hPeriksa $p, bool $baru)
    {
        $pesan = $p->hasil === P2h::DITAHAN
            ? 'P2H terkirim. Unit DITAHAN — perintah kerja terbit untuk mekanik.'
            : 'P2H terkirim. Unit laik operasi.';

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'baru' => $baru, 'id' => $p->id, 'hasil' => $p->hasil,
                'url' => route('lapangan.p2h')], $baru ? 201 : 200);
        }

        return redirect()->route('lapangan.p2h')->with('ok', $pesan);
    }

    private function tolak(Request $request, string $medan, string $pesan)
    {
        throw \Illuminate\Validation\ValidationException::withMessages([$medan => $pesan]);
    }

    private function ringkasLaporan(HazardReport $h): array
    {
        $skor = $h->kemungkinan && $h->keparahan ? $h->kemungkinan * $h->keparahan : null;
        $pita = $skor ? RisikoLapangan::PITA[RisikoLapangan::pita($h->kemungkinan, $h->keparahan)]['nama'] : $h->risiko;
        $kini = Waktu::kini()->startOfDay();

        return [
            'id'      => $h->id,
            'kode'    => $h->kode,
            'judul'   => \Illuminate\Support\Str::limit(trim((string) $h->deskripsi), 90),
            'lokasi'  => $h->lokasi,
            'kategori'=> self::JENIS_TEMUAN[$h->kategori] ?? $h->kategori,
            'risiko'  => $h->risiko,
            'pita'    => $pita,
            'skor'    => $skor,
            'status'  => $h->status,
            'tanggal' => $h->tanggal?->translatedFormat('j M Y'),
            'batas'   => $h->batas_akhir?->translatedFormat('j M'),
            'sisaHari'=> $h->batas_akhir && $h->status !== 'Closed' ? (int) $kini->diffInDays($h->batas_akhir, false) : null,
            'url'     => route('lapangan.laporan', $h),
        ];
    }

    /**
     * Linimasa laporan: dilaporkan, tindak lanjut yang tercatat, penutupan.
     *
     * Dibaca dari catatan aktivitas yang memang ditulis tiap perubahan
     * status — bukan dikarang dari status saat ini.
     */
    private function linimasa(HazardReport $h): array
    {
        $out = [[
            'judul' => 'Dilaporkan',
            'waktu' => Waktu::lokal($h->created_at)?->format('d M H:i'),
            'ket'   => trim($h->pelapor_nama.' · '.count((array) $h->foto).' foto'.($h->lat !== null ? ', lokasi GPS' : '')),
            'tahap' => 'lewat',
        ]];

        $log = ActivityLog::where('module', 'hazrep')
            ->where('detail', 'like', $h->kode.' → %')
            ->orderBy('id')->get(['action', 'detail', 'username', 'created_at']);

        foreach ($log as $l) {
            $status = trim(\Illuminate\Support\Str::after($l->detail, '→'));
            $out[] = [
                'judul' => match ($status) {
                    'In Progress' => 'Perbaikan berjalan',
                    'Closed'      => 'Ditutup',
                    default       => 'Dibuka kembali',
                },
                'waktu' => Waktu::lokal($l->created_at)?->format('d M H:i'),
                'ket'   => $l->username,
                'tahap' => 'lewat',
            ];
        }

        if ($h->status !== 'Closed') {
            if ($h->status === 'In Progress' && count($out) > 1) $out[count($out) - 1]['tahap'] = 'kini';
            $out[] = [
                'judul' => 'Verifikasi & penutupan',
                'waktu' => null,
                'ket'   => 'Pengawas memeriksa hasil di lapangan sebelum laporan ditutup.',
                'tahap' => $h->status === 'Open' ? 'kini' : 'nanti',
            ];
        } elseif ($h->catatan_penutupan) {
            $out[count($out) - 1]['ket'] = trim(($h->closer?->name ?? '').' — '.$h->catatan_penutupan, ' —');
        }

        return $out;
    }

    private function ringkasUnit(P2hUnit $x, Carbon $kini, string $shift): array
    {
        $t = $x->terakhir;
        $sudah = $t && $t->tanggal?->isSameDay($kini) && $t->shift === $shift;

        return [
            'id'       => $x->id,
            'kode'     => $x->kode,
            'nama'     => $x->nama,
            'jenis'    => $x->namaJenis(),
            'ket'      => $x->keterangan,
            'hm'       => $x->hm,
            'status'   => $x->status,
            'ditahanSejak' => Waktu::lokal($x->ditahan_sejak)?->format('d M H:i'),
            'ditahanKarena'=> $x->ditahan_karena,
            'sudahShiftIni'=> $sudah,
            'terakhir' => $t ? [
                'tanggal' => $t->tanggal?->translatedFormat('j M'),
                'shift'   => P2h::SHIFT[$t->shift] ?? $t->shift,
                'hasil'   => $t->hasil,
                'operator'=> $t->operator,
            ] : null,
            'url'   => route('lapangan.p2h.isi', $x),
            'lepas' => route('lapangan.p2h.lepas', $x),
        ];
    }

    /**
     * Peringatan site dari modul Water & Dewatering dan Kestabilan Lereng.
     *
     * Hanya yang benar-benar melewati ambang: kolam yang terisi ≥ 70%
     * atau tinggal sanggup menahan < 25 mm hujan, dan lereng yang
     * lajunya sudah waspada atau lebih.
     */
    private function peringatanSite(): array
    {
        $out = [];

        rescue(function () use (&$out) {
            foreach (WaterSump::where('status', 'aktif')->with('logs')->get() as $s) {
                $v = $s->toView();
                $persen = (float) $v['terisiPersen'];
                $tampung = $v['hujanTertampung'];
                if ($persen < 70 && ($tampung === null || $tampung >= 25)) continue;

                $out[] = [
                    'jenis' => 'air',
                    'kode'  => mb_strtoupper($s->kode),
                    'level' => $persen >= 90 || ($tampung !== null && $tampung < 10) ? 'siaga' : 'waspada',
                    'judul' => number_format($persen, 0, ',', '.').'% kapasitas',
                    'ket'   => $tampung !== null
                        ? 'Sanggup menahan '.number_format((float) $tampung, 0, ',', '.').' mm hujan lagi sebelum meluap.'
                        : 'Daya tampung hujan belum dapat dihitung.',
                    'persen'=> min(100, $persen),
                    'kaki'  => $s->lokasi,
                    'url'   => \Illuminate\Support\Facades\Route::has('air.index') ? route('air.index') : null,
                ];
            }
        }, null, false);

        rescue(function () use (&$out) {
            foreach (GeoLereng::where('status', 'aktif')->with(['bacaan', 'instrumen'])->get() as $l) {
                $g = $l->gerakan();
                if (!in_array($g['tingkat'], ['waspada', 'siaga', 'awas'], true)) continue;

                $out[] = [
                    'jenis' => 'lereng',
                    'kode'  => 'LERENG '.mb_strtoupper($l->kode),
                    'level' => $g['tingkat'] === 'waspada' ? 'waspada' : 'siaga',
                    'judul' => number_format((float) $g['laju'], 1, ',', '.').' mm/hari',
                    'ket'   => 'Laju gerakan pada tingkat '.$g['tingkat'].'. '.($l->lokasi ? 'Lokasi '.$l->lokasi.'.' : ''),
                    'persen'=> null,
                    'kaki'  => $l->nama,
                    'url'   => \Illuminate\Support\Facades\Route::has('geoteknik.index') ? route('geoteknik.index') : null,
                ];
            }
        }, null, false);

        usort($out, fn ($a, $b) => ($b['level'] === 'siaga') <=> ($a['level'] === 'siaga'));

        return $out;
    }

    /**
     * Yang menunggu tindakan pengguna ini, berurut menurut tenggat.
     *
     * Pengawas (admin, KTT, OHSE) melihat seluruh laporan bahaya yang
     * belum ditutup di perusahaannya; pekerja melihat laporannya sendiri
     * yang masih berjalan.
     */
    private function tindakan($u): array
    {
        $out = [];
        $kini = Waktu::kini();
        $pengawas = $this->dapatMelepas($u);

        $q = HazardReport::where('status', '!=', 'Closed')->orderByRaw('batas_akhir is null')->orderBy('batas_akhir');
        if (!$pengawas) $q->where('user_id', $u->id);

        foreach ($q->limit(8)->get() as $h) {
            $r = $this->ringkasLaporan($h);
            $sisa = $r['sisaHari'];
            $out[] = [
                'jenis' => 'hazard',
                'kode'  => $h->kode.' · '.($h->status === 'Open' ? 'MENUNGGU' : 'DIPERBAIKI'),
                'judul' => $r['judul'],
                'ket'   => 'Risiko '.mb_strtolower($r['pita']).($sisa === null ? '' : ($sisa < 0 ? ' · lewat '.abs($sisa).' hari' : ($sisa === 0 ? ' · tenggat hari ini' : " · tenggat {$r['batas']}"))),
                'nada'  => $sisa !== null && $sisa <= 0 ? 'bahaya' : ($h->risiko === 'Tinggi' ? 'bahaya' : 'waspada'),
                'url'   => $r['url'],
                'urut'  => $sisa ?? 99,
            ];
        }

        rescue(function () use (&$out, $kini) {
            $izin = IzinKerja::with('gas')->where('mulai', '<=', $kini)->where('selesai', '>=', $kini)->get();
            foreach ($izin as $i) {
                if (!$i->sudahDisetujui() || $i->sudahDitutup() || !$i->perluUjiGas()) continue;
                $g = $i->ujiTerakhir();
                $usia = $g ? (int) floor($g->waktu_uji->diffInMinutes($kini, true)) : null;
                $sisa = $usia === null ? null : $i->batasUji() - $usia;
                if ($sisa !== null && $sisa > 15) continue;

                $out[] = [
                    'jenis' => 'izin',
                    'kode'  => $i->nomor.' · '.mb_strtoupper(str_replace('_', ' ', $i->jenis)),
                    'judul' => 'Uji gas ulang di '.($i->lokasi ?: 'lokasi izin'),
                    'ket'   => $sisa === null ? 'Belum ada uji gas' : ($sisa <= 0 ? 'Uji gas kedaluwarsa '.abs($sisa).' mnt lalu' : "Kedaluwarsa {$sisa} mnt lagi"),
                    'nada'  => 'bahaya',
                    'url'   => route('lapangan.izin'),
                    'urut'  => -1,
                ];
            }
        }, null, false);

        rescue(function () use (&$out, $kini) {
            $shift = P2h::shiftSekarang($kini);
            $unit = P2hUnit::with('terakhir')->where('aktif', true)->get();
            $ditahan = $unit->filter(fn ($x) => $x->ditahan());
            $belum = $unit->reject(fn ($x) => $x->ditahan())->filter(fn ($x) =>
                !($x->terakhir && $x->terakhir->tanggal?->isSameDay($kini) && $x->terakhir->shift === $shift));

            foreach ($ditahan as $x) {
                $out[] = [
                    'jenis' => 'p2h', 'kode' => 'P2H · '.$x->kode.' · DITAHAN',
                    'judul' => $x->nama, 'ket' => \Illuminate\Support\Str::limit((string) $x->ditahan_karena, 70),
                    'nada' => 'bahaya', 'url' => route('lapangan.p2h'), 'urut' => 0,
                ];
            }
            if ($belum->isNotEmpty()) {
                $out[] = [
                    'jenis' => 'p2h', 'kode' => 'P2H · '.mb_strtoupper(P2h::SHIFT[$shift]),
                    'judul' => 'Pemeriksaan pra-operasi belum dilakukan',
                    'ket'   => $belum->count().' unit · '.$belum->take(3)->pluck('kode')->implode(', '),
                    'nada'  => 'netral', 'url' => route('lapangan.p2h'), 'urut' => 1,
                ];
            }
        }, null, false);

        usort($out, fn ($a, $b) => $a['urut'] <=> $b['urut']);

        return array_map(fn ($x) => array_diff_key($x, ['urut' => 1]), $out);
    }

    /** Angka pada tab bawah. */
    private function lencana($u, ?array $tindakan = null): array
    {
        return ['tugas' => count($tindakan ?? $this->tindakan($u))];
    }
}
