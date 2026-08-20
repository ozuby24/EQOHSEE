<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, TpkkpAssessment, TpkkpResponse};
use App\Support\{Tpkkp, TpkkpKuesioner};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Kuesioner persepsi (Metode B — Skala Likert).
 * Sasaran mengikuti struktur TPKKP:
 *   cat 'pekerja'  → Indikator 1 (Partisipasi Pekerja Tambang)
 *   cat 'pimpinan' → Indikator 2 (Tanggung Jawab Pimpinan Unit Kerja)
 *
 * Halaman pengisian BEBAS AKSES (tanpa login) lewat tautan bertoken,
 * agar mudah disebar ke pekerja & pimpinan unit kerja.
 */
class KuesionerController extends Controller
{
    public const KATEGORI = [
        'pekerja'  => ['label' => 'Pekerja Tambang',      'indicator' => 1],
        'pimpinan' => ['label' => 'Pimpinan Unit Kerja',  'indicator' => 2],
    ];

    /* ============ SISI ADMIN ============ */

    public function admin(Request $request)
    {
        $me = auth()->user();
        $company = (!$me->isAdmin() && $me->company_id)
            ? Company::find($me->company_id)
            : Company::find($request->get('company') ?? session('tpkkp_company')) ?? Company::orderBy('name')->first();
        $a      = TpkkpAssessment::forYear((int) (session('tpkkp_tahun') ?? now()->year));
        $tahunn = TpkkpAssessment::orderByDesc('tahun')->pluck('tahun')->all();
        $picker = \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn);

        /*
         * Belum ada perusahaan sama sekali.
         *
         * Dulu ini abort 404. Menunya selalu tampak di bilah samping, jadi
         * pemasangan yang baru berujung pada halaman galat tanpa satu pun
         * petunjuk tentang apa yang kurang. Halaman kosong yang mengatakan
         * apa yang harus dilakukan lebih berguna daripada kode galat.
         */
        if (!$company) {
            return \Inertia\Inertia::render('Tpkkp/Kuesioner', [
                'judul'    => 'Kuesioner PTPKKP',
                'subjudul' => 'Belum ada perusahaan terdaftar',
                'picker'   => $picker,

                'perusahaan'       => null,
                'daftarPerusahaan' => [],
                'urlPublik'        => null,
                'ringkas'          => [],
                'responden'        => [],
                'bisaTarik'        => false,
            ]);
        }

        session(['tpkkp_company' => $company->id]);

        $token = $this->token($company);
        $companies = $me->isAdmin() ? Company::orderBy('name')->get() : collect([$company]);

        $responses = TpkkpResponse::where('company_id', $company->id)->latest('ts')->get();

        $ringkas = [];
        foreach (self::KATEGORI as $key => $k) {
            $rows = $responses->where('cat', $key);

            $ringkas[] = [
                'kunci'  => $key,
                'label'  => $k['label'],
                'jumlah' => $rows->count(),
                'rerata' => TpkkpKuesioner::rerata($rows),
                'url'    => route('kuesioner.form', [$token, $key]),
                'params' => array_map(fn ($p) => [
                    'kode'   => $p['code'],
                    'nama'   => $p['name'],
                    'rerata' => $p['rerata'] ?? null,
                    'pct'    => (float) ($p['pct'] ?? 0),
                ], array_values(TpkkpKuesioner::rerataParam($rows, $key))),
            ];
        }

        return \Inertia\Inertia::render('Tpkkp/Kuesioner', [
            'judul'    => 'Kuesioner PTPKKP',
            'subjudul' => "Persepsi pekerja dan pimpinan — {$company->name}",
            'picker'   => $picker,

            'perusahaan' => ['id' => $company->id, 'nama' => $company->name],
            'daftarPerusahaan' => $companies
                ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->values()->all(),

            'urlPublik' => route('kuesioner.pilih', $token),
            'ringkas'   => $ringkas,

            'responden' => $responses->map(fn ($r) => [
                'id'            => $r->id,
                'kategori'      => $r->cat,
                'kategoriLabel' => self::KATEGORI[$r->cat]['label'] ?? $r->cat,
                'nrp'           => $r->nrp ?: null,
                'jabatan'       => $r->jabatan ?: null,
                'dept'          => $r->dept ?: null,
                'jumlahJawaban' => count((array) $r->answers),
                'waktu'         => optional($r->ts)->format('d M · H:i'),
            ])->values()->all(),

            /* ═══ RESPONS MITRA KERJA, TERPISAH ═══
             *
             * Tidak masuk skor KS — kematangan yang dinilai adalah milik
             * pemegang IUP — tetapi TIDAK dibuang. Ia analisa tentang
             * rantai kerja: mitra yang persepsi keselamatannya rendah
             * adalah mitra yang perlu dibina, dan itu temuan tersendiri
             * yang tidak muncul di mana pun bila datanya hanya
             * disingkirkan diam-diam. */
            'mitra' => $this->ringkasMitra($responses, $company),

            'bisaTarik' => $me->isAdmin(),
        ]);
    }

    /**
     * Rerata persepsi per mitra kerja, di luar skor penilaian.
     *
     * @return list<array<string,mixed>>
     */
    private function ringkasMitra($responses, Company $tuan): array
    {
        $samakan = fn (?string $x) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $x), 'UTF-8'));
        $nama    = $samakan($tuan->name);

        $mitra = $responses->filter(fn ($r) => filled($r->perusahaan)
            && $samakan($r->perusahaan) !== $nama);

        return $mitra->groupBy('perusahaan')
            ->map(fn ($rows, $perusahaan) => [
                'perusahaan' => $perusahaan,
                'jumlah'     => $rows->count(),
                'rerata'     => TpkkpKuesioner::rerata($rows),
            ])
            ->sortByDesc('jumlah')
            ->values()->all();
    }

    public function resetToken(Request $request)
    {
        $company = Company::findOrFail($request->input('company_id'));
        $this->setToken($company, Str::random(24));
        ActivityLog::write('Reset tautan kuesioner', $company->name, 'tpkkp');

        return back()->with('ok', 'Tautan kuesioner diperbarui. Tautan lama tidak berlaku lagi.');
    }

    public function destroyResponse(TpkkpResponse $response)
    {
        $response->delete();
        return back()->with('ok', 'Responden dihapus.');
    }

    /* ============ SISI PUBLIK (tanpa login) ============ */

    /**
     * Halaman pembuka: identitas dahulu, kuesionernya menyusul.
     *
     * ALUR DIBALIK. Sebelumnya halaman ini menyodorkan dua tab dan
     * meminta responden memilih sendiri — dan pekerja tambang berulang
     * kali mengisi kuesioner pimpinan unit kerja, bukan karena lalai
     * melainkan karena tab pertama yang terlihat memang itu. Jawabannya
     * masuk sebagai persepsi pimpinan atas dirinya sendiri, tanpa satu
     * pun tanda bahwa itu terjadi.
     *
     * Kini jabatan yang menentukan, dan respondennya diberi tahu
     * kuesioner mana yang akan ia isi sebelum mulai.
     */
    public function pilih(string $token)
    {
        $company = $this->byToken($token);

        return Inertia::render('Kuesioner/Mulai', [
            'token'    => $token,
            'company'  => ['name' => $company->name],
            'kategori' => self::KATEGORI,

            /* Jabatan dikelompokkan supaya respondennya melihat sendiri
               batas antara pimpinan unit kerja dan pekerja tambang —
               daftar datar membuat batas itu tak terlihat. */
            'kelompok'   => TpkkpKuesioner::kelompokJabatan(),
            'identitas'  => TpkkpKuesioner::identitas(),

            /* Berapa pertanyaan tiap kuesioner. Disebut di muka: yang
               tahu sedang mengisi tiga pertanyaan tidak berhenti di
               tengah karena mengira daftarnya panjang. */
            'jumlahButir' => collect(self::KATEGORI)
                ->map(fn ($_, $k) => count(TpkkpKuesioner::kodeSah($k)))->all(),
        ]);
    }

    /**
     * Kuesioner yang sesuai jabatan responden.
     *
     * Jabatannya dibawa sebagai kueri, bukan kategorinya: yang memilih
     * kategori adalah sistem, dan alamat yang menyebut kategori langsung
     * mengembalikan pilihan itu ke tangan responden — persis yang
     * hendak dihindari.
     */
    public function form(Request $request, string $token, string $cat)
    {
        $company = $this->byToken($token);
        abort_unless(isset(self::KATEGORI[$cat]), 404);
        abort_unless(TpkkpKuesioner::punya($cat), 404);

        $jabatan = trim((string) $request->get('jabatan'));

        /* Alamat yang menyebut kategori tidak sesuai jabatannya
           diluruskan, bukan ditolak: yang menempelkan tautan lama tetap
           sampai ke kuesioner yang benar. */
        if ($jabatan !== '') {
            $sesuai = TpkkpKuesioner::kategoriUntukJabatan($jabatan);

            if ($sesuai !== null && $sesuai !== $cat) {
                return redirect()->route('kuesioner.form', [
                    'token' => $token, 'cat' => $sesuai, 'jabatan' => $jabatan,
                ] + $request->only(['nrp', 'dept', 'perusahaan']));
            }
        }

        return Inertia::render('Kuesioner/Form', [
            'company'  => ['name' => $company->name], 'token' => $token, 'cat' => $cat,
            'kategori' => self::KATEGORI[$cat],
            'entitas'  => TpkkpKuesioner::entitas($cat),
            'skala'    => TpkkpKuesioner::skala(),
            'params'   => TpkkpKuesioner::butirPerParam($cat),

            /* Identitas yang sudah diisi di halaman pembuka dibawa serta
               supaya tidak perlu diketik dua kali. */
            'identitas' => [
                'nrp'        => $request->get('nrp'),
                'jabatan'    => $jabatan ?: null,
                'dept'       => $request->get('dept'),
                'perusahaan' => $request->get('perusahaan'),
            ],
        ]);
    }

    public function submit(Request $request, string $token, string $cat)
    {
        $company = $this->byToken($token);
        abort_unless(isset(self::KATEGORI[$cat]), 404);

        $d = $request->validate([
            'nrp'        => ['nullable','string','max:50'],
            'jabatan'    => ['nullable','string','max:100'],
            'dept'       => ['nullable','string','max:100'],
            'perusahaan' => ['nullable','string','max:150'],
            'answers'    => ['required','array','min:1'],
        ]);

        /* Kategorinya ditentukan ULANG dari jabatannya di sisi server.
           Penyaringan di layar hanya menuntun; yang menentukan isi
           basis data adalah baris ini. Tanpanya, alamat yang disusun
           tangan tetap dapat memasukkan jawaban pekerja ke kuesioner
           pimpinan — dan itu tepat kegagalan yang hendak dihentikan. */
        if (filled($d['jabatan'] ?? null)) {
            $cat = TpkkpKuesioner::kategoriUntukJabatan($d['jabatan']) ?? $cat;
        }

        $sah     = TpkkpKuesioner::kodeSah($cat);
        $answers = [];
        foreach ($d['answers'] as $code => $v) {
            if ($v === '' || $v === null) continue;
            if (!in_array((string) $code, $sah, true)) continue;   // buang kode asing
            $answers[(string) $code] = max(1, min(5, (int) $v));
        }
        abort_if(empty($answers), 422, 'Tidak ada jawaban.');

        TpkkpResponse::create([
            'company_id' => $company->id,
            'ext_id'     => (string) Str::uuid(),
            'cat'        => $cat,
            'nrp'        => $d['nrp'] ?? null,
            'jabatan'    => $d['jabatan'] ?? null,
            'dept'       => $d['dept'] ?? null,
            'perusahaan' => $d['perusahaan'] ?? $company->name,
            'answers'    => $answers,
            'ts'         => now(),
        ]);

        return redirect()->route('kuesioner.selesai', $token);
    }

    public function selesai(string $token)
    {
        $company = $this->byToken($token);

        return Inertia::render('Kuesioner/Selesai', [
            'company' => ['name' => $company->name],
            'token' => $token,
        ]);
    }

    /* ============ BANTU ============ */

    /** Penilaian periode berjalan — token kuesioner menumpang di sini. */
    private function assessment(): TpkkpAssessment
    {
        return TpkkpAssessment::forYear((int) now()->year);
    }

    private function token(Company $c): string
    {
        $a = $this->assessment();
        $t = $a->profil['tokens'][$c->id] ?? null;

        return $t ?: $this->setToken($c, Str::random(24));
    }

    private function setToken(Company $c, string $token): string
    {
        $a = $this->assessment();
        $p = (array) $a->profil;
        $p['tokens'] ??= [];
        $p['tokens'][$c->id] = $token;
        $a->update(['profil' => $p]);

        return $token;
    }

    private function byToken(string $token): Company
    {
        foreach (TpkkpAssessment::all() as $a) {
            foreach ((array) ($a->profil['tokens'] ?? []) as $cid => $t) {
                if ($t === $token && ($c = Company::find($cid))) return $c;
            }
        }
        abort(404, 'Tautan kuesioner tidak valid atau sudah diganti.');
    }

    /* ============ TARIK KE SKOR METODE KS ============ */

    public function tarikKs(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh menarik skor.');

        $tahun = (int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year);
        $a     = TpkkpAssessment::forYear($tahun);

        $rows = TpkkpResponse::all();

        /* ═══ RESPONS MITRA KERJA TIDAK MASUK SKOR ═══
         *
         * Yang dinilai penilaian ini adalah kematangan PEMEGANG IUP.
         * Respons dari mitra kerja menyatakan persepsi orang-orang yang
         * bekerja di perusahaan lain, dengan pengawas lain dan aturan
         * internal lain; memasukkannya ke skor KS berarti nilai CAM naik
         * atau turun oleh keadaan yang bukan miliknya, dan tidak ada
         * satu pun tanda di layar bahwa itu terjadi.
         *
         * Datanya TIDAK dibuang — ia analisa yang berharga tentang
         * rantai kerja, dan tetap tampil di halaman kuesioner serta
         * rekap perusahaan. Yang dipisahkan hanya pengaruhnya terhadap
         * nilai.
         */
        [$milikSendiri, $mitra] = $this->pisahResponsMitra($rows, $a);

        $agregat = TpkkpKuesioner::agregat($milikSendiri);
        $hasil   = TpkkpKuesioner::tulisKeSkor($a->scores ?? [], $agregat);

        $a->scores = $hasil['scores'];
        $a->save();

        ActivityLog::write(
            'Tarik kuesioner ke skor KS',
            $hasil['ditulis'].' item · '.$hasil['entitas'].' sel entitas · '
            .$milikSendiri->count().' respons pemegang IUP · '
            .$mitra->count().' respons mitra dipisahkan · periode '.$tahun,
            'tpkkp'
        );

        /* Angkanya disebut apa adanya. "Skor KS diperbarui" tanpa
           menyebut berapa respons yang TIDAK ikut membuat orang mengira
           seluruh responsnya terhitung — dan selisihnya baru ketahuan
           saat ada yang menghitung ulang dengan tangan. */
        $pesan = "Skor KS diperbarui: {$hasil['ditulis']} item, {$hasil['entitas']} sel entitas"
            ." dari {$milikSendiri->count()} respons pemegang IUP.";

        if ($mitra->count()) {
            $jumlahMitra = $mitra->pluck('perusahaan')->unique()->count();

            $pesan .= " {$mitra->count()} respons dari {$jumlahMitra} mitra kerja disimpan"
                .' terpisah — tidak masuk Summary maupun nilai total.';
        }

        if ($hasil['dilewati']) {
            $pesan .= ' Dilewati (bukan item KS): '.implode(', ', array_slice($hasil['dilewati'], 0, 8)).'.';
        }

        return back()->with('ok', $pesan);
    }

    /**
     * Memisahkan respons pemegang IUP dari respons mitra kerja.
     *
     * Dibandingkan dengan nama perusahaan pemilik penilaian, disamakan
     * huruf besar-kecil dan spasi gandanya lebih dulu: "PT Cemerlang
     * Asa Mandiri" dan "pt cemerlang  asa mandiri" adalah perusahaan
     * yang sama, dan yang mengetiknya di lapangan bukan basis data.
     *
     * Respons TANPA nama perusahaan dihitung milik pemegang IUP —
     * tautan kuesionernya memang miliknya, dan mengeluarkannya berarti
     * membuang jawaban yang sah hanya karena satu kolom opsional
     * dikosongkan.
     *
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection}
     */
    private function pisahResponsMitra($rows, TpkkpAssessment $a): array
    {
        /* Dibandingkan PER RESPONS terhadap perusahaan pemilik TAUTAN
           yang dipakai mengisinya, bukan terhadap perusahaan pemilik
           penilaian.
           
           Sebabnya: satu penilaian dapat memakai beberapa tautan, dan
           TpkkpAssessment sendiri tidak selalu punya company_id —
           forYear() membuatnya tanpa itu. Membandingkan dengan sesuatu
           yang kerap null membuat SELURUH respons terbaca sebagai mitra,
           dan skor KS-nya kosong tanpa satu pun galat. */
        $namaPerusahaan = Company::pluck('name', 'id');

        $samakan = fn (?string $x) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $x), 'UTF-8'));

        $tuanSendiri = function ($r) use ($namaPerusahaan, $samakan): bool {
            /* Tanpa nama perusahaan → dihitung milik pemilik tautan.
               Tautannya memang miliknya, dan mengeluarkannya berarti
               membuang jawaban sah hanya karena satu kolom opsional
               dikosongkan. */
            if (blank($r->perusahaan)) return true;

            $tuan = $namaPerusahaan[$r->company_id] ?? null;

            return $tuan !== null && $samakan($r->perusahaan) === $samakan($tuan);
        };

        return [
            $rows->filter($tuanSendiri)->values(),
            $rows->reject($tuanSendiri)->values(),
        ];
    }

    /** Parameter milik satu indikator */
    private function paramsOf(int $indicatorId): array
    {
        foreach (Tpkkp::indicators() as $I) if ($I['id'] === $indicatorId) return $I['params'];
        return [];
    }

    /** Rerata seluruh jawaban (skala 1–5 → persen) */
    private function rerata($rows, int $indicatorId): ?float
    {
        return TpkkpKuesioner::rerata($rows);
    }

    /** Rerata per parameter */
    private function rerataParam($rows, int $indicatorId): array
    {
        $out = [];
        foreach ($this->paramsOf($indicatorId) as $p) {
            $codes = array_column($p['items'] ?? [], 'code');
            $sum = 0; $n = 0;
            foreach ($rows as $r) {
                foreach ((array) $r->answers as $code => $v) {
                    if (in_array($code, $codes, true)) { $sum += (int) $v; $n++; }
                }
            }
            $out[] = [
                'code' => $p['code'], 'name' => $p['name'],
                'rerata' => $n ? round($sum / $n, 2) : null,
                'pct'    => $n ? round($sum / $n / 5 * 100) : null,
                'n' => $n,
            ];
        }
        return $out;
    }
}
