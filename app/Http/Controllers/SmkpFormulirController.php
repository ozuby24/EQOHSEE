<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, SmkpAudit, SmkpFinding};
use App\Rules\LampiranBukti;
use App\Support\{BerkasUnggah, KopDokumen, Smkp, Waktu};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Paket formulir audit SMKP sesuai lampiran Kepdirjen Minerba
 * 185.K/37.04/DJB/2019: Rencana Audit, Formulir Kriteria (penilaian),
 * Kesesuaian, Ketidaksesuaian & Tindak Lanjut, Rekapitulasi
 * Ketidaksesuaian, Rekomendasi & OFI, dan Rencana Tindak Lanjut.
 *
 * Isian kesesuaian dan rekomendasi disimpan per audit; formulir lain
 * dibangun dari data audit yang sudah ada (temuan, rencana, penilaian),
 * sehingga satu data tidak pernah diketik dua kali.
 */
class SmkpFormulirController extends SmkpController
{
    /** Kunci formulir cetak → kop dokumen. */
    public const CETAK = [
        'penilaian'       => 'penilaian-smkp',
        'kesesuaian'      => 'formulir-kesesuaian',
        'ketidaksesuaian' => 'formulir-ketidaksesuaian',
        'rekapitulasi'    => 'rekapitulasi-ketidaksesuaian',
        'rekomendasi'     => 'rekomendasi-ofi',
        'tindak-lanjut'   => 'rencana-tindak-lanjut',
    ];

    /**
     * Bagian bertanda tangan: satu per formulir (termasuk Rencana Audit,
     * Berita Acara, dan Laporan Audit — bukan hanya paket formulir ini), dan
     * tiga per temuan pada Formulir Ketidaksesuaian (temuan, perbaikan oleh
     * auditi, verifikasi).
     */
    private const BAGIAN = '/^(?:kesesuaian|rekomendasi|penilaian|rekapitulasi|tindak-lanjut|rencana|berita-acara|laporan|ketidaksesuaian\.\d{1,10}\.(?:temuan|perbaikan|verifikasi))$/';

    public function halaman(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $temuan = $smkp->findings()->get();
        $rekap  = $smkp->rekapRencana();

        $hitung = fn (array $daftar) => count(array_filter($daftar, fn ($r) => trim((string) ($r['keterangan'] ?? $r['uraian'] ?? '')) !== ''));

        return Inertia::render('Smkp/Formulir', [
            'audit'       => $this->ringkasAudit($smkp),
            'kesesuaian'  => array_values((array) ($smkp->kesesuaian ?? [])),
            'rekomendasi' => array_values((array) ($smkp->rekomendasi ?? [])),
            'kriteria'    => $this->daftarSubElemen(),
            'ringkas'     => [
                'rencana'         => ['terisi' => $rekap['jumlah'], 'total' => $rekap['total']],
                'penilaian'       => ['terisi' => $smkp->rekap()['dinilai'], 'total' => $smkp->rekap()['berlaku']],
                'kesesuaian'      => $hitung((array) ($smkp->kesesuaian ?? [])),
                'rekomendasi'     => $hitung((array) ($smkp->rekomendasi ?? [])),
                'ketidaksesuaian' => $temuan->count(),
                'kategori'        => collect(SmkpFinding::KATEGORI)->map(fn ($l, $k) => $temuan->where('jenis', $k)->count())->all(),
                'rtl'             => $temuan->filter(fn ($t) => trim((string) $t->tindakan) !== '')->count(),
            ],
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    public function simpan(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'kesesuaian'                => ['nullable', 'array', 'max:150'],
            'kesesuaian.*.elemen'       => ['nullable', 'string', 'max:10'],
            'kesesuaian.*.sub'          => ['nullable', 'string', 'max:20'],
            'kesesuaian.*.subsub'       => ['nullable', 'string', 'max:20'],
            'kesesuaian.*.keterangan'   => ['nullable', 'string', 'max:3000'],
            'rekomendasi'               => ['nullable', 'array', 'max:150'],
            'rekomendasi.*.elemen'      => ['nullable', 'string', 'max:10'],
            'rekomendasi.*.sub'         => ['nullable', 'string', 'max:20'],
            'rekomendasi.*.subsub'      => ['nullable', 'string', 'max:20'],
            'rekomendasi.*.uraian'      => ['nullable', 'string', 'max:3000'],
        ]);

        $smkp->update([
            'kesesuaian'  => $this->bersihkan((array) ($d['kesesuaian'] ?? []), 'keterangan'),
            'rekomendasi' => $this->bersihkan((array) ($d['rekomendasi'] ?? []), 'uraian'),
        ]);

        ActivityLog::write('Simpan formulir kesesuaian & OFI', $smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Formulir kesesuaian dan rekomendasi tersimpan.');
    }

    /**
     * Baris dengan elemen/sub-elemen yang tidak dikenal dibuang, begitu
     * pula baris tanpa uraian — formulir cetak tidak boleh memuat kotak
     * kosong yang tampak seperti isian terlewat. Sub-sub elemen harus
     * milik sub elemen yang dipilih.
     */
    private function bersihkan(array $baris, string $kolom): array
    {
        $peta = [];
        foreach ($this->daftarSubElemen() as $e) {
            foreach ($e['sub'] as $s) $peta[$e['kode']][$s['kode']] = array_column($s['subsub'], 'kode');
        }

        $out = [];
        foreach ($baris as $b) {
            $b = (array) $b;
            $isi = trim((string) ($b[$kolom] ?? ''));
            $el  = (string) ($b['elemen'] ?? '');
            $sub = (string) ($b['sub'] ?? '');
            $ss  = (string) ($b['subsub'] ?? '');
            if ($isi === '' || !isset($peta[$el])) continue;
            if ($sub !== '' && !isset($peta[$el][$sub])) $sub = '';
            if ($sub === '' || !in_array($ss, $peta[$el][$sub], true)) $ss = '';
            $out[] = ['elemen' => $el, 'sub' => $sub, 'subsub' => $ss, $kolom => $isi];
        }

        return $out;
    }

    /* ================= Cetak ================= */

    public function cetak(Request $request, SmkpAudit $smkp, string $jenis)
    {
        abort_unless(isset(self::CETAK[$jenis]), 404);
        $smkp->load('company');

        $nama = [];
        foreach ($this->daftarSubElemen() as $e) {
            $nama[$e['kode']] = $e['nama'];
            foreach ($e['sub'] as $s) {
                $nama[$s['kode']] = $s['nama'];
                foreach ($s['subsub'] as $x) $nama[$x['kode']] = $x['nama'];
            }
        }

        $ttd = $this->penandaTangan($smkp);
        $dasar = [
            'audit'   => $this->ringkasAudit($smkp),
            'dok'     => KopDokumen::cetak(self::CETAK[$jenis], $smkp->company),
            'ttd'     => ['ketua' => $ttd['ketua'], 'ktt' => $ttd['ktt']] + $this->isianTtd($request, $smkp),
            'kembali' => route('smkp.formulir', $smkp),
        ];

        return match ($jenis) {
            'penilaian'   => Inertia::render('Print/Smkp/FormulirPenilaian', $dasar + $this->dataPenilaian($smkp)),
            'kesesuaian'  => Inertia::render('Print/Smkp/FormulirKesesuaian', $dasar + [
                'baris' => $this->dengan_nama((array) ($smkp->kesesuaian ?? []), $nama, 'keterangan'),
            ]),
            'rekomendasi' => Inertia::render('Print/Smkp/FormulirRekomendasi', $dasar + [
                'baris' => $this->dengan_nama((array) ($smkp->rekomendasi ?? []), $nama, 'uraian'),
            ]),
            default       => Inertia::render(match ($jenis) {
                'ketidaksesuaian' => 'Print/Smkp/FormulirKetidaksesuaian',
                'rekapitulasi'    => 'Print/Smkp/FormulirRekapitulasi',
                'tindak-lanjut'   => 'Print/Smkp/FormulirTindakLanjut',
            }, $dasar + ['temuan' => $this->dataTemuan($smkp, $request->integer('temuan') ?: null)]),
        };
    }

    private function dengan_nama(array $baris, array $nama, string $kolom): array
    {
        return array_map(fn ($b) => [
            'elemen'     => (string) ($b['elemen'] ?? ''),
            'elemenNama' => $nama[$b['elemen'] ?? ''] ?? '',
            'sub'        => (string) ($b['sub'] ?? ''),
            'subNama'    => $nama[$b['sub'] ?? ''] ?? '',
            'subsub'     => (string) ($b['subsub'] ?? ''),
            'subsubNama' => $nama[$b['subsub'] ?? ''] ?? '',
            'isi'        => (string) ($b[$kolom] ?? ''),
        ], array_values($baris));
    }

    /* ================= Tanda tangan formulir ================= */

    /** Alasan tanda tangan bagian tindak lanjut belum dapat dibubuhkan; null bila sudah dapat. */
    private static function syaratBubuh(SmkpFinding $t, string $tahap): ?string
    {
        if ($t->tindakLanjut->isEmpty() && trim((string) $t->tindakan) === '') return 'Menunggu tindak lanjut auditi.';
        if ($tahap === 'verifikasi' && trim((string) $t->verifikasi) === '') return 'Verifikasi bukti tindak lanjut belum diisi.';

        return null;
    }

    /** Baca-ubah-tulis isian tanda tangan dengan kunci baris audit. */
    private function ubahTtd(SmkpAudit $smkp, \Closure $ubah): mixed
    {
        return DB::transaction(function () use ($smkp, $ubah) {
            $a = SmkpAudit::query()->whereKey($smkp->id)->lockForUpdate()->firstOrFail();
            $t = (array) ($a->ttd_formulir ?? []);
            $hasil = $ubah($t);
            $a->ttd_formulir = $t ?: null;
            $a->save();

            return $hasil;
        });
    }

    /** Tanggal penandatanganan satu pihak pada satu bagian formulir (kosong = hapus). */
    public function simpanTanggal(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'bagian'  => ['required', 'string', 'max:60', 'regex:'.self::BAGIAN],
            'pihak'   => ['required', Rule::in(self::PIHAK)],
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ], ['tanggal.date_format' => 'Tanggal tidak sah.']);

        // Auditi hanya mengisi tanggal tanda tangannya sendiri pada bagian perbaikan.
        $u = $request->user();
        abort_unless($u->bisaAudit() || (preg_match(self::BAGIAN_MANUAL, $d['bagian'], $m)
            && self::PIHAK_MANUAL[$m[2]] === $d['pihak'] && self::bolehBubuh($u, $d['pihak'])), 403);

        $this->ubahTtd($smkp, function (array &$t) use ($d) {
            if ($d['tanggal'] ?? null) {
                $t['tanggal'][$d['bagian']][$d['pihak']] = $d['tanggal'];
            } else {
                unset($t['tanggal'][$d['bagian']][$d['pihak']]);
                if (empty($t['tanggal'][$d['bagian']])) unset($t['tanggal'][$d['bagian']]);
                if (empty($t['tanggal'])) unset($t['tanggal']);
            }
        });

        return back();
    }

    /** Unggah tanda tangan digital satu pihak — gambar PNG/JPG/WEBP, maks 1 MB. */
    public function unggahTtd(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'pihak'  => ['required', Rule::in(self::PIHAK)],
            'berkas' => ['required', 'file', 'max:1024', 'extensions:png,jpg,jpeg,webp', new LampiranBukti()],
        ], [
            'berkas.required'   => 'Pilih gambar tanda tangan.',
            'berkas.max'        => 'Gambar tanda tangan paling besar 1 MB.',
            'berkas.extensions' => 'Tanda tangan harus gambar PNG, JPG, atau WEBP.',
            'berkas.uploaded'   => 'Gambar gagal terunggah — coba lagi.',
        ]);
        abort_unless(self::bolehGambar($request->user(), $d['pihak']), 403);

        $f = $request->file('berkas');
        $jalur = $f->storeAs(SmkpAudit::folderTtd($smkp->id), $d['pihak'].'-'.Str::random(24).'.'.strtolower($f->getClientOriginalExtension()), 'local');
        abort_if($jalur === false, 500, 'Gambar tanda tangan gagal disimpan.');

        $lama = $this->ubahTtd($smkp, function (array &$t) use ($d, $jalur) {
            $lama = $t['gambar'][$d['pihak']] ?? null;
            $t['gambar'][$d['pihak']] = $jalur;

            return $lama;
        });
        if (is_string($lama) && $lama !== $jalur) Storage::disk('local')->delete($lama);

        ActivityLog::write('Unggah tanda tangan formulir', (self::pihakAuditor($d['pihak']) ? 'Auditor' : 'Auditi').' · '.$smkp->namaTampil(), 'smkp');

        return back();
    }

    public function hapusTtd(Request $request, SmkpAudit $smkp, string $pihak)
    {
        abort_unless(self::bolehGambar($request->user(), $pihak), 403);

        $lama = $this->ubahTtd($smkp, function (array &$t) use ($pihak) {
            $lama = $t['gambar'][$pihak] ?? null;
            unset($t['gambar'][$pihak]);
            if (empty($t['gambar'])) unset($t['gambar']);

            return $lama;
        });
        if (is_string($lama)) Storage::disk('local')->delete($lama);

        ActivityLog::write('Hapus tanda tangan formulir', (self::pihakAuditor($pihak) ? 'Auditor' : 'Auditi').' · '.$smkp->namaTampil(), 'smkp');

        return back();
    }

    /**
     * Bubuhkan (ttd = true) atau batalkan tanda tangan pada bagian tindak
     * lanjut Formulir Ketidaksesuaian. Tanggalnya diisi hari ini bila masih
     * kosong; membatalkan ikut menghapus tanggalnya.
     */
    public function bubuhTtd(Request $request, SmkpAudit $smkp)
    {
        $d = $request->validate([
            'bagian' => ['required', 'string', 'max:60', 'regex:'.self::BAGIAN_MANUAL],
            'ttd'    => ['required', 'boolean'],
        ]);
        preg_match(self::BAGIAN_MANUAL, $d['bagian'], $m);
        $pihak = self::PIHAK_MANUAL[$m[2]];
        abort_unless(self::bolehBubuh($request->user(), $pihak), 403);
        $temuan = $smkp->findings()->with('tindakLanjut:id,finding_id')->whereKey((int) $m[1])->firstOrFail();

        if ($d['ttd']) {
            $alasan = self::syaratBubuh($temuan, $m[2])
                ?? (empty($smkp->ttd_formulir['gambar'][$pihak]) ? 'Unggah tanda tangan digital terlebih dahulu.' : null);
            if ($alasan) throw ValidationException::withMessages(['ttd' => $alasan]);
        }

        $this->ubahTtd($smkp, function (array &$t) use ($d, $pihak) {
            $b = $d['bagian'];
            if ($d['ttd']) {
                $t['bubuh'][$b][$pihak] = true;
                $t['tanggal'][$b][$pihak] ??= Waktu::kini()->toDateString();
            } else {
                unset($t['bubuh'][$b], $t['tanggal'][$b][$pihak]);
                if (empty($t['tanggal'][$b])) unset($t['tanggal'][$b]);
                if (empty($t['tanggal'])) unset($t['tanggal']);
                if (empty($t['bubuh'])) unset($t['bubuh']);
            }
        });

        ActivityLog::write($d['ttd'] ? 'Tanda tangani tindak lanjut' : 'Batalkan tanda tangan tindak lanjut',
            trim($temuan->nomor.' '.$temuan->kode_kriteria).' · '.(self::pihakAuditor($pihak) ? 'Auditor' : 'Auditi').' · '.$smkp->namaTampil(), 'smkp');

        return back();
    }

    /** Gambar tanda tangan untuk halaman cetak — hanya pengguna yang menjangkau audit ini. */
    public function lihatTtd(SmkpAudit $smkp, string $pihak)
    {
        $jalur = $smkp->ttd_formulir['gambar'][$pihak] ?? null;
        $ext = strtolower(pathinfo((string) $jalur, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? null;
        // Hanya berkas di folder tanda tangan audit ini yang dapat dikirim.
        abort_unless(is_string($jalur) && $mime && str_starts_with($jalur, SmkpAudit::folderTtd($smkp->id).'/') && !str_contains($jalur, '..'), 404);

        return BerkasUnggah::kirim($jalur, "ttd-{$pihak}.{$ext}", $mime, true);
    }

    /** Temuan untuk formulir ketidaksesuaian, rekapitulasi, dan RTL. */
    private function dataTemuan(SmkpAudit $smkp, ?int $hanya): array
    {
        $urut = 0;

        return $smkp->findings()->with('tindakLanjut:id,finding_id,lampiran')->get()->sort([SmkpFinding::class, 'banding'])->values()
            ->map(function (SmkpFinding $t) use (&$urut, $smkp) {
                $urut++;
                $ref = Smkp::referensi((string) $t->kode_kriteria);
                $nomor = $t->nomorTampil($urut);

                return [
                    'id'          => $t->id,
                    'nomor'       => $nomor,
                    'kode'        => $t->kode_kriteria,
                    'elemen'      => $ref['elemen'],
                    'sub'         => $ref['sub'],
                    'subsub'      => $ref['subsub'],
                    // Deskripsi Ketidaksesuaian di rekapitulasi, ditutup "Hal ini tidak sesuai dengan …".
                    'pernyataan'  => Smkp::uraianTemuan((string) $t->kode_kriteria, $t->deskripsi, $t->uraian),
                    'kategori'    => $t->jenis,
                    'labelKategori' => $t->labelKategori(),
                    'uraian'      => $t->uraian,
                    'lokasi'      => (string) $t->lokasi,
                    'divisi'      => (string) $t->divisi,
                    'departemen'  => (string) $t->departemen,
                    'bukti'       => (string) $t->bukti,
                    'deskripsi'   => (string) $t->deskripsi,
                    // Formulir Ketidaksesuaian: dipakai bila Deskripsi belum disimpan.
                    'deskripsiOtomatis' => Smkp::deskripsiTemuan((string) $t->kode_kriteria, $t->uraian, $t->bukti, $t->lokasi),
                    'akar'        => (string) $t->akar_masalah,
                    'tindakan'    => (string) $t->tindakan,
                    'buktiPerbaikan' => (string) $t->bukti_perbaikan,
                    'pj'          => (string) $t->penanggung_jawab,
                    'batas'       => $t->target_selesai?->translatedFormat('d F Y'),
                    'tanggalPerbaikan' => $t->tanggal_selesai?->translatedFormat('d F Y'),
                    'status'      => $t->status,
                    'verifikasi'  => (string) $t->verifikasi,
                    // Nomor tindak lanjut = nomor ketidaksesuaian.
                    'nomorTindakLanjut' => $nomor,
                    // Formulir Ketidaksesuaian: syarat tanda tangan bagian tindak lanjut
                    // dan lampiran bukti untuk diverifikasi auditor (tidak tercetak).
                    'syaratTtd'   => ['perbaikan' => self::syaratBubuh($t, 'perbaikan'), 'verifikasi' => self::syaratBubuh($t, 'verifikasi')],
                    'lampiran'    => $t->tindakLanjut->flatMap(fn ($k) => collect((array) $k->lampiran)->map(fn ($l, $i) => [
                        'nama' => (string) ($l['nama'] ?? 'Berkas '.($i + 1)),
                        'url'  => route('smkp.tindak-lanjut.unduh', ['smkp' => $smkp, 'temuan' => $t, 'kiriman' => $k->id, 'berkas' => $i], false).'?lihat=1',
                    ]))->values()->all(),
                ];
            })
            ->when($hanya, fn ($c) => $c->where('id', $hanya))
            ->values()->all();
    }

    /**
     * Baris Formulir Kriteria Audit SMKP Minerba — matriks Kepdirjen hal.
     * 605–608: Nilai Maksimal Kriteria (persentase, sub elemen, sub-sub
     * elemen) dan Nilai Audit. Butir N/A tidak dinilai: nilai maksimalnya
     * tampil "N/A" dan tidak ikut Total Nilai Elemen Maksimal.
     */
    private function dataPenilaian(SmkpAudit $smkp): array
    {
        $hasil = $smkp->hasil ?? [];
        $rekap = Smkp::rekap($hasil);
        $elemen = [];

        foreach (Smkp::elemen() as $e) {
            $re = $rekap['elemen'][$e['kode']];
            $sub = [];
            foreach ($e['sub'] as $s) {
                $rs = $re['sub'][$s['kode']];
                $sub[] = [
                    'kode'  => $s['kode'], 'nama' => $s['nama'],
                    'maks'  => $rs['maks'], 'nilai' => $rs['nilai'], 'dinilai' => $rs['dinilai'],
                    'na'    => $rs['berlaku'] === 0,
                    'rinci' => !empty($s['subsub']) ? array_map(fn ($b) => [
                        'kode'  => $b['kode'], 'nama' => $b['nama'], 'maks' => $b['maks'],
                        'nilai' => $rs['butir'][$b['kode']] ?? null,
                        'na'    => ($rs['butir'][$b['kode']] ?? null) === Smkp::NA,
                    ], Smkp::butirSub($s)) : [],
                ];
            }
            $elemen[] = [
                'kode' => $e['kode'], 'nama' => $e['nama'], 'bobot' => $re['bobot'],
                'maks' => $re['maks'], 'nilai' => $re['nilai'], 'skor' => $re['skor'],
                'na'   => $re['berlaku'] === 0,
                'sub'  => $sub,
            ];
        }

        return [
            'elemen'  => $elemen,
            'total'   => ['bobot' => $rekap['bobotTerpakai'], 'maks' => $rekap['maks'], 'nilai' => $rekap['nilai'], 'skor' => $rekap['skor']],
            'tingkat' => $rekap['tingkat'],
        ];
    }
}
