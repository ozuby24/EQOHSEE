<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, TpkkpAssessment, TpkkpPengujian};
use App\Support\{TautanPublik, Tpkkp, TpkkpKuesioner, TpkkpNav, TpkkpUji};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Pengujian (metode PJ) — kuis kesadaran risiko keselamatan pertambangan.
 *
 * ── Mengapa sesinya dijalankan server, bukan di peramban ──
 *
 * Acuan yang saya ikuti menjalankan seluruh kuis di dalam satu berkas
 * JavaScript: bank soal, kunci jawaban, pencacah waktu, dan penilaian.
 * Yang dikirim ke server hanyalah hasilnya. Tiga hal runtuh sekaligus
 * di sana, dan ketiganya tanpa jejak:
 *
 *   • kunci jawaban ada di dalam berkas yang dapat dibuka siapa saja
 *     lewat "View Source" — 50 soal beserta jawabannya, terurut;
 *   • pencacah waktunya sebuah `setInterval`, yang dapat dihentikan
 *     dari konsol peramban dengan satu baris;
 *   • nilainya dihitung klien lalu dikirim, jadi nilai sempurna dapat
 *     diketik tangan tanpa menjawab satu soal pun.
 *
 * Di sini yang dikirim ke layar hanyalah teks satu soal beserta
 * pilihannya yang sudah diacak. Kunci jawaban, susunan soal, dan batas
 * waktunya tinggal di session di server; peserta mengirimkan huruf
 * pilihannya, server yang memeriksa. Pencacah di layar tinggal menjadi
 * apa yang seharusnya — pemberi tahu, bukan penegak.
 *
 * ── Satu arah ──
 *
 * Soal disimpan sebagai daftar nomor, dan yang digambar hanya nomor
 * yang sedang dikerjakan. Tidak ada permintaan yang mengembalikan soal
 * berikutnya sebelum yang sekarang dijawab, jadi "tidak bisa membaca
 * soal berikutnya lebih dulu" bukan aturan tampilan melainkan keadaan
 * yang memang tidak tersedia.
 */
class PengujianController extends Controller
{
    /* ============ SISI PUBLIK (tanpa login) ============ */

    /** Langkah 1 — identitas. */
    public function mulai(string $token)
    {
        $company = TautanPublik::wajib($token);

        return Inertia::render('Pengujian/Mulai', [
            'token'       => $token,
            'company'     => ['name' => $company->name],
            'identitas'   => TpkkpKuesioner::identitas(),
            'jumlahSoal'  => TpkkpUji::jumlahSoal(),
            'detikPerSoal'=> TpkkpUji::detikPerSoal(),
            'judul'       => TpkkpUji::meta()['title'] ?? 'Pengujian',
        ]);
    }

    /** Simpan identitas, lanjut ke halaman aturan. */
    public function siap(Request $request, string $token)
    {
        $company = TautanPublik::wajib($token);

        $d = $request->validate([
            'nama'       => ['required', 'string', 'max:120'],
            'nrp'        => ['nullable', 'string', 'max:50'],
            'jabatan'    => ['required', 'string', 'max:100'],
            'dept'       => ['required', 'string', 'max:100'],
            'perusahaan' => ['required', 'string', 'max:150'],
        ]);

        session([$this->kunciSesi($token) => [
            'identitas' => $d,
            'kunci'     => TpkkpUji::kunciIdentitas($d),
        ]]);

        return redirect()->route('pengujian.aturan', $token);
    }

    /** Langkah 2 — aturan. Pencacah waktu BELUM berjalan di sini. */
    public function aturan(string $token)
    {
        $company = TautanPublik::wajib($token);
        $sesi    = $this->sesi($token);

        if (!isset($sesi['identitas'])) return redirect()->route('pengujian.mulai', $token);

        /* Sudah pernah mengerjakan → dihentikan di sini, sebelum
           mengerjakan ulang 15 soal yang akan ditolak di akhir. */
        if ($this->sudahPernah($company, $sesi['kunci'])) {
            return redirect()->route('pengujian.selesai', $token)
                ->with('ok', 'Anda sudah pernah mengerjakan pengujian ini.');
        }

        return Inertia::render('Pengujian/Aturan', [
            'token'        => $token,
            'company'      => ['name' => $company->name],
            'identitas'    => $sesi['identitas'],
            'jumlahSoal'   => TpkkpUji::jumlahSoal(),
            'detikPerSoal' => TpkkpUji::detikPerSoal(),
        ]);
    }

    /** Susun soal dan jalankan pencacah waktu. */
    public function jalan(string $token)
    {
        $company = TautanPublik::wajib($token);
        $sesi    = $this->sesi($token);

        if (!isset($sesi['identitas'])) return redirect()->route('pengujian.mulai', $token);

        if ($this->sudahPernah($company, $sesi['kunci'])) {
            return redirect()->route('pengujian.selesai', $token)
                ->with('ok', 'Anda sudah pernah mengerjakan pengujian ini.');
        }

        $set = TpkkpUji::susun();

        session([$this->kunciSesi($token) => $sesi + [
            'set'    => $set,
            'jawab'  => array_fill(0, count($set), null),
            'idx'    => 0,
            'mulai'  => now()->toIso8601String(),
            'batas'  => now()->addSeconds(TpkkpUji::detikPerSoal())->getTimestamp(),
            'pindah' => 0,
        ]]);

        return redirect()->route('pengujian.soal', $token);
    }

    /** Satu soal per layar. */
    public function soal(string $token)
    {
        $company = TautanPublik::wajib($token);
        $sesi    = $this->sesi($token);

        if (!isset($sesi['set'])) return redirect()->route('pengujian.mulai', $token);

        $idx  = (int) $sesi['idx'];
        $soal = TpkkpUji::soalKe($sesi['set'], $idx);

        return Inertia::render('Pengujian/Soal', [
            'token'        => $token,
            'nomor'        => $idx + 1,
            'jumlahSoal'   => count($sesi['set']),
            'pertanyaan'   => $soal['teks'],
            'pilihan'      => $soal['pilihan'],
            'detikPerSoal' => TpkkpUji::detikPerSoal(),

            /* Sisa waktu dihitung server dari batas yang server simpan,
               bukan dimulai ulang dari 90 tiap kali halaman digambar.
               Memuat ulang halaman karena itu tidak memperpanjang waktu
               — dan memuat ulang adalah hal pertama yang dicoba orang
               ketika waktunya menipis. */
            'sisaDetik'    => max(0, $sesi['batas'] - now()->getTimestamp()),
        ]);
    }

    /**
     * Terima jawaban satu soal, lanjut.
     *
     * `nomor` ikut dikirim dan dicocokkan. Tanpanya, satu kiriman yang
     * terulang — tombol ditekan dua kali, jaringan mengirim ulang —
     * akan melompati satu soal beserta jawabannya.
     */
    public function jawab(Request $request, string $token)
    {
        TautanPublik::wajib($token);
        $sesi = $this->sesi($token);

        if (!isset($sesi['set'])) return redirect()->route('pengujian.mulai', $token);

        $d = $request->validate([
            'nomor'  => ['required', 'integer'],
            'pilih'  => ['nullable', 'integer', 'min:0', 'max:9'],
            'pindah' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $idx = (int) $sesi['idx'];

        /* Kiriman untuk soal yang bukan soal berjalan diabaikan, bukan
           ditolak dengan galat: yang menekan tombol dua kali tidak
           sedang berbuat curang, ia hanya tidak yakin kirimannya
           masuk. */
        if ((int) $d['nomor'] !== $idx + 1) {
            return redirect()->route('pengujian.soal', $token);
        }

        /* Batas waktu ditegakkan DI SINI. Jawaban yang tiba lewat batas
           (di luar kelonggaran jaringan) dihitung tidak dijawab —
           sama persis dengan yang dibiarkan kosong sampai waktunya
           habis. */
        $telat = now()->getTimestamp() > $sesi['batas'] + TpkkpUji::KELONGGARAN;

        $sesi['jawab'][$idx] = $telat ? null : ($d['pilih'] ?? null);
        $sesi['pindah'] = max((int) $sesi['pindah'], (int) ($d['pindah'] ?? 0));

        if ($idx + 1 < count($sesi['set'])) {
            $sesi['idx']   = $idx + 1;
            $sesi['batas'] = now()->addSeconds(TpkkpUji::detikPerSoal())->getTimestamp();
            session([$this->kunciSesi($token) => $sesi]);

            return redirect()->route('pengujian.soal', $token);
        }

        return $this->rampung($token, $sesi);
    }

    /** Hitung, simpan, tutup nilainya dari peserta. */
    private function rampung(string $token, array $sesi)
    {
        $company = TautanPublik::wajib($token);

        $set   = $sesi['set'];
        $benar = TpkkpUji::hitungBenar($set, $sesi['jawab']);
        $total = count($set);
        $pct   = $total ? $benar / $total : 0.0;
        $mulai = \Illuminate\Support\Carbon::parse($sesi['mulai']);

        /* firstOrCreate, bukan create: dua tab yang dikirim hampir
           bersamaan tidak boleh menjadi dua baris, dan indeks unik pada
           tabel akan melempar galat yang tidak berguna bagi peserta. */
        TpkkpPengujian::firstOrCreate(
            ['company_id' => $company->id, 'kunci_identitas' => $sesi['kunci']],
            [
                'ext_id'       => (string) Str::uuid(),
                'nama'         => $sesi['identitas']['nama'],
                'nrp'          => $sesi['identitas']['nrp'] ?? null,
                'jabatan'      => $sesi['identitas']['jabatan'] ?? null,
                'dept'         => $sesi['identitas']['dept'] ?? null,
                'perusahaan'   => $sesi['identitas']['perusahaan'] ?? $company->name,
                'benar'        => $benar,
                'total'        => $total,
                'skor_pct'     => round($pct, 4),
                'tingkat'      => TpkkpUji::tingkatDari($pct),
                'durasi_detik' => max(0, now()->diffInSeconds($mulai, absolute: true)),
                'pindah_layar' => (int) $sesi['pindah'],
                'mulai'        => $mulai,
                'ts'           => now(),
            ]
        );

        session()->forget($this->kunciSesi($token));

        return redirect()->route('pengujian.selesai', $token);
    }

    public function selesai(string $token)
    {
        $company = TautanPublik::wajib($token);

        return Inertia::render('Pengujian/Selesai', [
            'token'   => $token,
            'company' => ['name' => $company->name],

            /* Catatan penutup menyebut sekali lagi bahwa nilainya memang
               tidak ditampilkan — supaya yang mencarinya berhenti
               mencari, bukan mengira halamannya gagal memuat. */
            'catatan' => TpkkpUji::meta()['passNote'] ?? null,
        ]);
    }

    /* ============ SISI ADMIN ============ */

    public function admin(Request $request)
    {
        $me = auth()->user();

        $company = (!$me->isAdmin() && $me->company_id)
            ? Company::find($me->company_id)
            : Company::find($request->get('company') ?? session('tpkkp_company')) ?? Company::orderBy('name')->first();

        $a      = TpkkpAssessment::forYear((int) (session('tpkkp_tahun') ?? now()->year));
        $picker = TpkkpNav::untukInertia($a->tahun, TpkkpNav::daftarTahun());

        $dasar = [
            'judul'    => 'Pengujian PTPKKP',
            'subjudul' => 'Kuis kesadaran risiko keselamatan pertambangan — metode PJ',
            'picker'   => $picker,
            'bank'     => count(TpkkpUji::bank()),
            'jumlahSoal'   => TpkkpUji::jumlahSoal(),
            'detikPerSoal' => TpkkpUji::detikPerSoal(),
            'pita'     => array_map(fn ($b) => [
                'batas'   => (float) $b['lt'],
                'tingkat' => (int) $b['level'],
                'nama'    => Tpkkp::LV[$b['level'] - 1] ?? $b['name'],
            ], TpkkpUji::pita()),

            /* Warna tingkat datang dari server, bukan disalin ke berkas
               Vue. Salinan palet diam saja ketika paletnya berubah, dan
               satu halaman berwarna beda dari halaman di sebelahnya
               tanpa ada yang menyadarinya. */
            'warnaTingkat' => array_map(
                fn ($n) => Tpkkp::levelHex($n), range(1, 5)
            ),
            'namaTingkat' => Tpkkp::LV,
        ];

        if (!$company) {
            return Inertia::render('Tpkkp/Pengujian', $dasar + [
                'subjudul'   => 'Belum ada perusahaan terdaftar',
                'perusahaan' => null,
                'daftarPerusahaan' => [],
                'urlPublik'  => null,
                'ringkas'    => TpkkpUji::ringkas([]),
                'peserta'    => [],
                'skorKini'   => null,
                'bisaTerapkan' => false,
            ]);
        }

        session(['tpkkp_company' => $company->id]);

        $rows = TpkkpPengujian::where('company_id', $company->id)->latest('ts')->get();

        return Inertia::render('Tpkkp/Pengujian', $dasar + [
            'perusahaan' => ['id' => $company->id, 'nama' => $company->name],
            'daftarPerusahaan' => ($me->isAdmin() ? Company::orderBy('name')->get() : collect([$company]))
                ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->values()->all(),

            'urlPublik' => route('pengujian.mulai', TautanPublik::token($company)),
            'ringkas'   => TpkkpUji::ringkas($rows),

            'peserta' => $rows->map(fn ($r) => [
                'id'      => $r->id,
                'nama'    => $r->nama,
                'nrp'     => $r->nrp ?: null,
                'jabatan' => $r->jabatan ?: null,
                'dept'    => $r->dept ?: null,
                'perusahaan' => $r->perusahaan ?: null,
                'benar'   => $r->benar,
                'total'   => $r->total,
                'tingkat' => $r->tingkat,
                'label'   => TpkkpUji::labelTingkat($r->tingkat),

                /* Nilai peserta ditampilkan kepada ADMIN, bukan kepada
                   pesertanya. Yang menyimpulkan sebaran perlu melihat
                   angkanya; yang mengerjakan tidak, sebab nilai yang
                   terlihat mengubah kuis menjadi ujian perorangan dan
                   yang diukur bukan itu. */
                'durasi'  => $r->durasi_detik,
                'pindahLayar' => $r->pindah_layar,
                'waktu'   => optional($r->ts)->format('d M · H:i'),
            ])->values()->all(),

            /* Nilai PJ yang SEKARANG tercatat pada penilaian — supaya
               yang menekan "terapkan" melihat apa yang akan tergantikan,
               bukan menimpanya buta. */
            'skorKini' => $this->skorPjKini($a),

            'bisaTerapkan' => $me->isAdmin(),
        ]);
    }

    /** @return array{kode:string,nilai:int|null,ket:string|null}|null */
    private function skorPjKini(TpkkpAssessment $a): ?array
    {
        foreach (Tpkkp::allItems() as $it) {
            if (!in_array(TpkkpUji::METODE, $it['methods'], true)) continue;

            $rec = ($a->scores ?? [])[TpkkpUji::METODE][$it['code']] ?? null;

            return [
                'kode'  => $it['code'],
                'nama'  => $it['name'],
                'nilai' => isset($rec['v']) ? (int) $rec['v'] : null,
                'ket'   => $rec['ket'] ?? null,
            ];
        }

        return null;
    }

    public function terapkan(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh menerapkan nilai.');

        $company = Company::find($request->input('company_id') ?? session('tpkkp_company'));
        abort_unless($company, 404, 'Perusahaan tidak ditemukan.');

        $tahun = (int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year);
        $a     = TpkkpAssessment::forYear($tahun);

        $rows    = TpkkpPengujian::where('company_id', $company->id)->get();
        $ringkas = TpkkpUji::ringkas($rows);

        if (!$ringkas['peserta'] || $ringkas['tingkat'] === null) {
            return back()->with('ok', 'Belum ada peserta pengujian — skor PJ tidak diubah.');
        }

        $hasil = TpkkpUji::tulisKeSkor($a->scores ?? [], $ringkas['tingkat'], $ringkas['peserta']);

        $a->scores = $hasil['scores'];
        $a->save();

        ActivityLog::write(
            'Terapkan pengujian ke skor PJ',
            implode(', ', $hasil['ditulis']).' → tingkat '.$ringkas['tingkat']
            .' dari '.$ringkas['peserta'].' peserta · periode '.$tahun,
            'tpkkp'
        );

        return back()->with('ok', sprintf(
            'Skor PJ diperbarui: tingkat %d (%s) dari %d peserta — rerata tingkat %s, rerata benar %s%%.',
            $ringkas['tingkat'],
            TpkkpUji::labelTingkat($ringkas['tingkat']),
            $ringkas['peserta'],
            number_format($ringkas['rerataTingkat'], 2),
            number_format($ringkas['rerataPct'], 1)
        ));
    }

    public function destroyPeserta(TpkkpPengujian $peserta)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $peserta->delete();

        return back()->with('ok', 'Hasil peserta dihapus. Terapkan ulang bila skor PJ perlu disesuaikan.');
    }

    /* ============ BANTU ============ */

    private function kunciSesi(string $token): string { return 'uji.'.$token; }

    private function sesi(string $token): array { return (array) session($this->kunciSesi($token), []); }

    private function sudahPernah(Company $c, string $kunci): bool
    {
        return TpkkpPengujian::withoutGlobalScopes()
            ->where('company_id', $c->id)->where('kunci_identitas', $kunci)->exists();
    }
}
