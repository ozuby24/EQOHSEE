<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, EnvAudit, EnvAuditScore, EnvAuditSertifikat, Signatory};
use App\Support\{AuditLingkungan, Berkas, KopDokumen, SertifikatLingkungan};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Audit Kinerja Pengelolaan dan Pemantauan Lingkungan (ISO 14001).
 *
 * Enam bagian, dua ratus satu kriteria bernilai 0–3, berbobot,
 * dikurangi nilai pengurang, lalu menghasilkan predikat penghargaan
 * dan peringkat warna.
 *
 * Bagiannya dibuka SATU PER SATU, bukan seluruhnya sekaligus: bagian B
 * sendirian berisi seratus lima puluh kriteria, dan satu halaman berisi
 * dua ratus baris berpenilaian tiga kolom adalah halaman yang tidak
 * pernah selesai dimuat maupun selesai diisi.
 */
class AuditLingkunganController extends Controller
{
    public function index(Request $request)
    {
        $tahun = (int) ($request->get('tahun') ?: now()->year);

        $daftar = EnvAudit::with('scores', 'company', 'sertifikatAktif')
            ->where('tahun', $tahun)
            ->latest('tanggal')->latest('id')
            ->get();

        return Inertia::render('AuditLingkungan/Daftar', [
            'judul'    => 'Audit Kinerja Lingkungan',
            'subjudul' => 'Instrumen audit internal ISO 14001 bagi mitra kerja',

            'saring' => ['tahun' => $tahun],
            'opsi'   => $this->opsi(),

            'daftar' => $daftar->map(fn (EnvAudit $a) => $this->baris($a))->all(),

            'bagian' => AuditLingkungan::daftarBagian(),

            'tautan' => ['buat' => route('audit-lingkungan.create'),
                         'index' => route('audit-lingkungan.index')],
        ]);
    }

    public function create()
    {
        return Inertia::render('AuditLingkungan/Form', [
            'judul'    => 'Audit Lingkungan Baru',
            'subjudul' => 'Identitas dan profil perusahaan yang diaudit',

            'awal' => [
                'judul' => '', 'company_id' => '', 'tahun' => (string) now()->year,
                'lokasi' => '', 'tanggal' => now()->toDateString(), 'catatan' => '',
                'profil' => $this->profilKosong(),
            ],
            'sunting' => false,
            'opsi'    => $this->opsi(),
            'tautan'  => ['simpan' => route('audit-lingkungan.store'),
                          'batal'  => route('audit-lingkungan.index')],
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->v($request);
        $d['user_id'] = auth()->id();
        $d['kode']    = EnvAudit::kodeBaru($d['tahun']);

        $a = EnvAudit::create($d);

        ActivityLog::write('Buat audit lingkungan', $a->kode.' — '.$a->judul, 'lingkungan');

        return redirect()->route('audit-lingkungan.bagian', [$a, 'a'])
            ->with('ok', 'Audit '.$a->kode.' dibuat. Mulai dari bagian A.');
    }

    /** Ikhtisar: skor per bagian, pengurang, predikat, peringkat. */
    public function show(EnvAudit $audit)
    {
        $audit->load(['scores', 'company.owner', 'sertifikatAktif.company.owner', 'sertifikatAktif.signatory']);
        $skor = $audit->skor();

        return Inertia::render('AuditLingkungan/Ikhtisar', [
            'judul'    => $audit->kode,
            'subjudul' => $audit->judul,

            'a'     => $this->baris($audit) + ['profil' => AuditLingkungan::profil($audit->profil), 'catatan' => $audit->catatan],
            'skor'  => $skor,
            'opsi'  => $this->opsi(),

            'sertifikat' => $this->sertifikat($audit, $skor),

            'tautan' => $this->tautan($audit),
        ]);
    }

    /**
     * Keadaan sertifikat pada ikhtisar: yang berlaku, riwayat yang
     * dicabut, dan — bila boleh terbit — pratinjau lembarnya.
     *
     * Pratinjau digambar dari potret yang SAMA dengan yang akan
     * dibekukan saat terbit, jadi yang dilihat sebelum menekan tombol
     * adalah yang akan tercetak.
     */
    private function sertifikat(EnvAudit $audit, array $skor): array
    {
        $aktif = $audit->sertifikatAktif;
        $k = SertifikatLingkungan::kelayakan($audit, $skor, $aktif);

        $penandatangan = Signatory::untukPerusahaan($audit->company_id)->get();

        $pratinjau = null;
        if ($k['layak']) {
            $contoh = new EnvAuditSertifikat([
                'company_id' => $audit->company_id,
                'nomor'      => 'AKL-SERT/'.KopDokumen::prefiksDari(SertifikatLingkungan::penerbit($audit) ?? $audit->company?->name)
                               .'/'.now()->year.'/···',
                'kode'       => 'PRATINJAU000',
                'terbit'     => now()->toDateString(),
                'berlaku'    => now()->addYear()->subDay()->toDateString(),
                'tempat'     => $audit->company?->location ?: $audit->lokasi,
                'predikat'   => $skor['predikat']['nama'],
                'peringkat'  => $skor['peringkat']['nama'],
                'skor'       => $skor['akhir'],
                'data'       => SertifikatLingkungan::potret($audit, $skor, $penandatangan->first()),
            ]);
            $contoh->setRelation('company', $audit->company);
            $contoh->setRelation('signatory', $penandatangan->first());

            $pratinjau = SertifikatLingkungan::lembar($contoh);
        }

        return [
            'aktif' => $aktif ? SertifikatLingkungan::lembar($aktif) + [
                'url'      => route('audit-lingkungan.sertifikat.lihat', $aktif),
                'urlCabut' => route('audit-lingkungan.sertifikat.cabut', $aktif),
                'berubah'  => SertifikatLingkungan::berubah($aktif, $skor),
            ] : null,

            'riwayat' => $audit->sertifikat()->whereNotNull('dicabut_at')->latest('id')->get()
                ->map(fn (EnvAuditSertifikat $s) => [
                    'id'      => $s->id,
                    'nomor'   => $s->nomor,
                    'terbit'  => $s->terbit?->translatedFormat('j M Y'),
                    'dicabut' => $s->dicabut_at?->translatedFormat('j M Y'),
                    'alasan'  => $s->alasan_cabut,
                    'predikat'=> $s->predikat,
                    'url'     => route('audit-lingkungan.sertifikat.lihat', $s),
                ])->all(),

            'layak'     => $k['layak'],
            'alasan'    => $k['alasan'],
            'pratinjau' => $pratinjau,

            'penandatangan' => $penandatangan->map(fn (Signatory $t) => [
                'id' => $t->id, 'nama' => $t->name, 'jabatan' => $t->title,
            ])->all(),
            'bawaan' => [
                'terbit'  => now()->toDateString(),
                'berlaku' => now()->addYear()->subDay()->toDateString(),
                'tempat'  => (string) ($audit->company?->location ?: $audit->lokasi),
                'signatory_id' => $penandatangan->first()?->id,
            ],

            'urlTerbit'  => route('audit-lingkungan.sertifikat.terbitkan', $audit),
            'urlLogo'    => route('personalia.perusahaan'),
            'bolehCabut' => (bool) auth()->user()?->isAdmin(),
        ];
    }

    /** Satu bagian beserta kriterianya. */
    public function bagian(EnvAudit $audit, string $bagian)
    {
        $b = AuditLingkungan::satu($bagian);
        abort_if(!$b, 404, 'Bagian audit tidak dikenal.');

        $audit->load('scores');
        $nilai = $audit->scores->keyBy('kode');

        /* Kriteria dikelompokkan lagi untuk layar: subbagian → kelompok
           → butir. Rata begitu saja, seratus lima puluh baris bagian B
           tidak dapat dibaca sebagai apa pun. */
        $susun = [];

        foreach (AuditLingkungan::kriteria($bagian) as $k) {
            $sub  = $k['sub'] ?? '';
            $grup = $k['kelompok'];

            /* ->get(), BUKAN $nilai[$kode]. Collection::offsetGet
               membangkitkan "Undefined array key" pada kunci yang tidak
               ada, dan galat itu terjadi SEBELUM `??` sempat
               menangkapnya. Audit yang baru dibuat belum punya satu
               baris nilai pun — yaitu justru keadaan pertama kali
               halaman ini dibuka. */
            $s = $nilai->get($k['kode']);

            $susun[$sub]['nama'] = $sub;
            $susun[$sub]['kelompok'][$grup]['nama'] = $grup;
            $susun[$sub]['kelompok'][$grup]['butir'][] = [
                'kode'       => $k['kode'],
                'huruf'      => $k['huruf'],
                'uraian'     => $k['uraian'],
                'kosong'     => $k['kosong'],
                'nilai'      => $s?->nilai,
                'verifikasi' => $s?->verifikasi,
                'keterangan' => $s?->keterangan ?? '',
                'selisih'    => $s?->berselisih() ?? false,
                'berkas'     => $s ? Berkas::daftarUrl($s, 'akl') : [],
                'urlBerkas'  => $s ? route('audit-lingkungan.berkas', $s) : null,
                /* Nama asli tiap bukti, sejajar dengan `berkas`. Baris
                   yang diunggah sebelum nama disimpan memakai urutannya. */
                'bukti'      => $s ? array_map(fn ($url, $i) => [
                    'url'  => $url,
                    'nama' => ((array) $s->berkas_nama)[$i] ?? '' ?: 'Dokumen '.($i + 1),
                ], $url = Berkas::daftarUrl($s, 'akl'), array_keys($url)) : [],
                'urlBukti'      => route('audit-lingkungan.bukti', [$audit, $k['kode']]),
                'urlHapusBukti' => route('audit-lingkungan.bukti.hapus', [$audit, $k['kode']]),
            ];
        }

        $susun = array_values(array_map(fn ($s) => [
            'nama'     => $s['nama'],
            'kelompok' => array_values($s['kelompok']),
        ], $susun));

        $skor = $audit->skor();

        return Inertia::render('AuditLingkungan/Bagian', [
            'judul'    => $audit->kode.' · Bagian '.strtoupper($bagian),
            'subjudul' => $b['judul'],

            'a'      => $this->baris($audit),
            'kini'   => $bagian,
            'bagian' => AuditLingkungan::daftarBagian(),
            'susun'  => $susun,

            'skorBagian' => $skor['bagian'][$bagian],
            'skor'       => ['akhir' => $skor['akhir'], 'belum' => $skor['belum'],
                             'kriteria' => $skor['kriteria']],

            'tangga' => $bagian === 'd' ? AuditLingkungan::TANGGA['d'] : AuditLingkungan::TANGGA['umum'],

            /* Batas keterangan dikirim, bukan ditulis ulang di layar:
               lihat AuditLingkungan::MAKS_KETERANGAN. */
            'maksKeterangan' => AuditLingkungan::MAKS_KETERANGAN,
            'maksBukti'      => self::MAKS_BUKTI,
            'maksBuktiMb'    => intdiv(Berkas::MAKS_DOKUMEN_KB, 1024),

            'tautan' => $this->tautan($audit) + ['simpanNilai' => route('audit-lingkungan.nilai', $audit)],
        ]);
    }

    /**
     * Simpan nilai satu bagian dalam SATU kiriman.
     *
     * Bagian B berisi seratus lima puluh kriteria. Menyimpan per baris
     * berarti yang mengisinya menunggu seratus lima puluh kali, dan
     * kehilangan sebagian isian tiap kali sambungan terputus di tengah.
     */
    public function simpanNilai(Request $request, EnvAudit $audit)
    {
        /* Pesannya menyebut KODE KRITERIANYA, bukan "nilai.3.b.keterangan".
           Lembar bagian B berisi seratus lima puluh baris; pesan galat
           yang menyebut nomor larik memaksa yang mengisinya menghitung
           sendiri baris keberapa yang dimaksud, dan hampir selalu salah
           hitung. */
        $nama = [];
        foreach (array_keys((array) $request->input('nilai', [])) as $kode) {
            $nama["nilai.{$kode}.keterangan"] = "keterangan kriteria {$kode}";
            $nama["nilai.{$kode}.nilai"]      = "nilai kriteria {$kode}";
            $nama["nilai.{$kode}.verifikasi"] = "verifikasi kriteria {$kode}";
        }

        $d = $request->validate([
            'nilai'                 => ['required', 'array'],
            'nilai.*.nilai'         => ['nullable', 'integer', 'min:0', 'max:3'],
            'nilai.*.verifikasi'    => ['nullable', 'integer', 'min:0', 'max:3'],
            'nilai.*.keterangan'    => ['nullable', 'string', 'max:'.AuditLingkungan::MAKS_KETERANGAN],
        ], [], $nama);

        $sah = array_column(AuditLingkungan::kriteria($request->get('bagian', '')), 'kode');
        $sah = array_flip($sah);

        foreach ($d['nilai'] as $kode => $baris) {
            /* Kode yang tidak ada di bagian ini DILEWATI, bukan
               disimpan. Kiriman yang membawa kode asing akan menaruh
               nilai pada kriteria yang tidak pernah tampil di layar —
               dan nilai itu ikut menentukan skor akhir tanpa pernah
               dapat ditinjau. */
            if ($sah && !isset($sah[$kode])) continue;

            EnvAuditScore::updateOrCreate(
                ['audit_id' => $audit->id, 'kode' => $kode],
                [
                    'nilai'      => $baris['nilai'] ?? null,
                    'verifikasi' => $baris['verifikasi'] ?? null,
                    'keterangan' => $baris['keterangan'] ?? null,
                ],
            );
        }

        return back()->with('ok', 'Nilai bagian tersimpan.');
    }

    /** Dokumen pendukung satu kriteria — DITAMBAHKAN, tidak menimpa. */
    public function berkas(Request $request, EnvAuditScore $skor)
    {
        $request->validate(['berkas' => ['required', 'array', 'max:6']]);
        $request->validate(['berkas.*' => Berkas::ATURAN_DOKUMEN], [], ['berkas.*' => 'berkas']);

        /* Lewat auditnya, bukan langsung dari $skor: baris milik
           perusahaan lain harus tidak dapat ditemukan. */
        EnvAudit::findOrFail($skor->audit_id);

        $this->lampirkan($skor, (array) $request->file('berkas'));

        return back()->with('ok', 'Dokumen pendukung tersimpan.');
    }

    /** Batas bukti per kriteria. Lebih dari ini hampir selalu berarti
     *  satu bundel dokumen dipecah ke satu butir yang tidak akan dicari
     *  siapa pun ketika bundel itu dibutuhkan. */
    public const MAKS_BUKTI = 10;

    /**
     * Unggah dokumen bukti untuk satu kriteria.
     *
     * Dikunci pada kode kriteria, dan baris nilainya dibuat bila belum
     * ada: bukti biasanya diunggah SEBELUM nilainya diberikan, dan
     * tombol unggah yang baru muncul sesudah bagian disimpan adalah
     * tombol yang tidak pernah ditemukan.
     */
    public function buktiUnggah(Request $request, EnvAudit $audit, string $kode)
    {
        $this->pastikanKriteria($kode);

        $skor = EnvAuditScore::firstOrNew(['audit_id' => $audit->id, 'kode' => $kode]);
        $ada  = count((array) $skor->berkas);
        $sisa = max(0, self::MAKS_BUKTI - $ada);

        $request->validate(
            ['berkas' => ['required', 'array', 'max:'.max(1, $sisa)], 'berkas.*' => Berkas::ATURAN_DOKUMEN],
            ['berkas.max' => $sisa
                ? "Kriteria {$kode} hanya dapat menerima {$sisa} dokumen lagi (paling banyak ".self::MAKS_BUKTI.').'
                : "Kriteria {$kode} sudah memuat ".self::MAKS_BUKTI.' dokumen. Hapus salah satu lebih dulu.'],
            ['berkas.*' => 'dokumen bukti'],
        );
        abort_if($sisa === 0, 422, 'Batas dokumen bukti tercapai.');

        $n = $this->lampirkan($skor, (array) $request->file('berkas'));

        return back()->with('ok', $n === 1 ? "1 dokumen bukti tersimpan pada kriteria {$kode}."
                                           : "{$n} dokumen bukti tersimpan pada kriteria {$kode}.");
    }

    /** Hapus satu dokumen bukti — berkasnya ikut dibuang dari disk. */
    public function buktiHapus(Request $request, EnvAudit $audit, string $kode)
    {
        $this->pastikanKriteria($kode);
        $i = (int) $request->validate(['indeks' => ['required', 'integer', 'min:0']])['indeks'];

        $skor   = EnvAuditScore::where('audit_id', $audit->id)->where('kode', $kode)->firstOrFail();
        $berkas = array_values((array) $skor->berkas);
        $nama   = array_values((array) $skor->berkas_nama);
        abort_unless(isset($berkas[$i]), 404);

        Berkas::buang($berkas[$i]);
        array_splice($berkas, $i, 1);
        if (array_key_exists($i, $nama)) array_splice($nama, $i, 1);

        $skor->update(['berkas' => $berkas ?: null, 'berkas_nama' => $nama ?: null]);

        return back()->with('ok', 'Dokumen bukti dihapus.');
    }

    /** Kode yang bukan kriteria mana pun ditolak, bukan dibuatkan baris. */
    private function pastikanKriteria(string $kode): void
    {
        $bagian = strtok($kode, '.');
        abort_unless(
            in_array($kode, array_column(AuditLingkungan::kriteria((string) $bagian), 'kode'), true),
            404,
        );
    }

    /**
     * Simpan unggahan pada satu baris nilai, berikut nama aslinya.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $unggah
     */
    private function lampirkan(EnvAuditScore $skor, array $unggah): int
    {
        $berkas = array_values((array) $skor->berkas);
        /* Baris lama belum punya daftar nama: diisi kosong sepanjang
           berkasnya supaya indeks keduanya tetap sejajar. */
        $nama = array_pad(array_values((array) $skor->berkas_nama), count($berkas), '');

        $n = 0;
        foreach ($unggah as $f) {
            if (!$j = Berkas::simpan($f, 'audit-lingkungan')) continue;
            $berkas[] = $j;
            $nama[]   = mb_substr(basename((string) $f->getClientOriginalName()), 0, 150);
            $n++;
        }

        if ($n) {
            $skor->fill(['berkas' => $berkas, 'berkas_nama' => $nama])->save();
        }

        return $n;
    }

    public function edit(EnvAudit $audit)
    {
        return Inertia::render('AuditLingkungan/Form', [
            'judul'    => 'Ubah '.$audit->kode,
            'subjudul' => $audit->judul,

            'awal' => [
                'judul'      => $audit->judul,
                'company_id' => (string) $audit->company_id,
                'tahun'      => (string) $audit->tahun,
                'lokasi'     => (string) $audit->lokasi,
                'tanggal'    => $audit->tanggal?->toDateString() ?? '',
                'catatan'    => (string) $audit->catatan,
                'profil'     => array_merge($this->profilKosong(), (array) $audit->profil),
                'status'     => $audit->status,
            ],
            'sunting' => true,
            'opsi'    => $this->opsi(),
            'tautan'  => ['simpan' => route('audit-lingkungan.update', $audit),
                          'batal'  => route('audit-lingkungan.show', $audit)],
        ]);
    }

    public function update(Request $request, EnvAudit $audit)
    {
        $d = $this->v($request);

        if ($request->filled('status')) {
            $request->validate(['status' => [Rule::in(EnvAudit::STATUS)]]);
            $d['status'] = $request->input('status');
        }

        $audit->update($d);

        return redirect()->route('audit-lingkungan.show', $audit)->with('ok', 'Audit diperbarui.');
    }

    /** Nilai pengurang yang berlaku — dipotong dari skor akhir. */
    public function pengurang(Request $request, EnvAudit $audit)
    {
        $d = $request->validate([
            'pengurang'   => ['nullable', 'array'],
            'pengurang.*' => [Rule::in(array_keys(AuditLingkungan::PENGURANG))],
        ]);

        $audit->update(['pengurang' => array_values(array_unique($d['pengurang'] ?? []))]);

        return back()->with('ok', 'Nilai pengurang diperbarui.');
    }

    public function destroy(EnvAudit $audit)
    {
        /* Audit yang sertifikatnya masih berlaku tidak dihapus diam-diam:
           QR-nya sudah tercetak, dan pemegangnya berhak atas halaman
           verifikasi yang menyatakan keadaan sebenarnya. Cabut dulu —
           jejak terbitannya tetap tersimpan sesudah auditnya dihapus. */
        if ($s = $audit->sertifikatAktif()->first()) {
            return back()->with('galat', 'Audit ini memiliki sertifikat berlaku ('.$s->nomor.'). Cabut sertifikatnya lebih dulu.');
        }

        $kode = $audit->kode;
        $audit->delete();

        ActivityLog::write('Hapus audit lingkungan', $kode, 'lingkungan');

        return redirect()->route('audit-lingkungan.index')->with('ok', $kode.' dihapus.');
    }

    public function lembar(EnvAudit $audit)
    {
        $audit->load(['scores', 'company']);
        $nilai = $audit->scores->keyBy('kode');

        $bagian = [];

        foreach (AuditLingkungan::bagian() as $kunci => $b) {
            $butir = [];

            foreach (AuditLingkungan::kriteria($kunci) as $k) {
                /* ->get(), dengan alasan yang sama seperti pada
                   bagian(): lembar kosong untuk audit yang baru dibuat
                   adalah penggunaan pertamanya, bukan keadaan langka. */
                $s = $nilai->get($k['kode']);

                $butir[] = [
                    'kode'       => $k['kode'],
                    'sub'        => $k['sub'],
                    'kelompok'   => $k['kelompok'],
                    'huruf'      => $k['huruf'],
                    'uraian'     => $k['uraian'],
                    'nilai'      => $s?->nilai,
                    'verifikasi' => $s?->verifikasi,
                    'keterangan' => $s?->keterangan,
                ];
            }

            $bagian[] = ['kunci' => $kunci, 'huruf' => strtoupper($kunci),
                         'judul' => $b['judul'], 'butir' => $butir];
        }

        return Inertia::render('Print/AuditLingkunganLembar', [
            'judul' => 'Audit Kinerja Lingkungan '.$audit->kode,
            'dok'   => KopDokumen::untuk('audit-lingkungan', $audit->company),

            'a'      => $this->baris($audit) + ['profil' => AuditLingkungan::profil($audit->profil), 'catatan' => $audit->catatan],
            'skor'   => $audit->skor(),
            'bagian' => $bagian,
            'tangga' => AuditLingkungan::TANGGA,

            'kembali' => route('audit-lingkungan.show', $audit),
        ]);
    }

    /* ══════════════════ dalaman ══════════════════ */

    private function baris(EnvAudit $a): array
    {
        $s = $a->skor();

        return [
            'id'         => $a->id,
            'kode'       => $a->kode,
            'judul'      => $a->judul,
            'tahun'      => $a->tahun,
            'lokasi'     => $a->lokasi,
            'tanggal'    => $a->tanggal?->format('d M Y'),
            'status'     => $a->status,
            'perusahaan' => $a->company?->name,
            'pengurang'  => (array) $a->pengurang,
            'akhir'      => $s['akhir'],
            'pemenuhan'  => $s['pemenuhan'],
            'belum'      => $s['belum'],
            'kriteria'   => $s['kriteria'],
            'predikat'   => $s['predikat'],
            'peringkat'  => $s['peringkat'],
            'bagianPersen' => array_map(fn ($b) => $b['persen'], $s['bagian']),
            'sertifikat' => ($z = $a->sertifikatAktif) ? [
                'nomor' => $z->nomor,
                'url'   => route('audit-lingkungan.sertifikat.lihat', $z),
            ] : null,
            'url'        => route('audit-lingkungan.show', $a),
        ];
    }

    private function tautan(EnvAudit $a): array
    {
        return [
            'index'     => route('audit-lingkungan.index'),
            'ikhtisar'  => route('audit-lingkungan.show', $a),
            'ubah'      => route('audit-lingkungan.edit', $a),
            'hapus'     => route('audit-lingkungan.destroy', $a),
            'lembar'    => route('audit-lingkungan.lembar', $a),
            'pengurang' => route('audit-lingkungan.pengurang', $a),
            'bagian'    => array_combine(
                array_keys(AuditLingkungan::bagian()),
                array_map(fn ($k) => route('audit-lingkungan.bagian', [$a, $k]),
                          array_keys(AuditLingkungan::bagian())),
            ),
        ];
    }

    private function opsi(): array
    {
        return [
            'tahun'      => range(now()->year + 1, now()->year - 4),
            'status'     => EnvAudit::STATUS,
            'nilai'      => AuditLingkungan::NILAI,
            'profil'     => array_map(fn ($k) => ['kunci' => $k, 'label' => AuditLingkungan::PROFIL[$k]],
                                      array_keys(AuditLingkungan::PROFIL)),
            'pengurang'  => array_map(
                fn ($k) => ['kunci' => $k] + AuditLingkungan::PENGURANG[$k],
                array_keys(AuditLingkungan::PENGURANG),
            ),
            'predikat'   => AuditLingkungan::PREDIKAT,
            'peringkat'  => AuditLingkungan::PERINGKAT,
            'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
        ];
    }

    private function profilKosong(): array
    {
        return array_fill_keys(array_keys(AuditLingkungan::PROFIL), '');
    }

    private function v(Request $request): array
    {
        $d = $request->validate([
            'judul'      => ['required', 'string', 'max:300'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'tahun'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'lokasi'     => ['nullable', 'string', 'max:200'],
            'tanggal'    => ['nullable', 'date'],
            'catatan'    => ['nullable', 'string', 'max:5000'],
            'profil'     => ['nullable', 'array'],
            'profil.*'   => ['nullable', 'string', 'max:300'],
        ]);

        return $d;
    }
}
