<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, SmkpAudit, SmkpBukti, User};
use App\Rules\LampiranBukti;
use App\Support\{BerkasUnggah, Smkp, Waktu};
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/**
 * Bukti kelengkapan audit per butir kriteria.
 *
 * Alur: auditor/administrator menetapkan dokumen & sampel yang diminta per
 * sub-elemen di Rencana Audit → auditi mengunggah berkasnya pada butir yang
 * bersangkutan → auditor/administrator memverifikasi tiap berkas (diterima
 * atau perlu perbaikan) lalu menetapkan nilai butirnya di halaman Penilaian.
 *
 * Batas perusahaan datang dari pengikatan rute: SmkpAudit bercakupan
 * jangkauan pengguna, jadi audit di luar jangkauan menjadi 404; berkas
 * selalu dicocokkan dengan auditnya.
 */
class BuktiAuditController extends SmkpController
{
    public const MAKS_BERKAS = 5;
    public const MAKS_KB     = 10240;
    public const EKSTENSI    = LampiranBukti::EKSTENSI;

    /* Kuota penyimpanan: per butir cukup untuk puluhan dokumen & foto; per
       audit dan per pengguna per hari membatasi unggahan berlebihan. */
    public const KUOTA_BUTIR  = 100 * 1024 * 1024;
    public const KUOTA_AUDIT  = 2048 * 1024 * 1024;
    public const KUOTA_HARIAN = 250 * 1024 * 1024;

    /** Antrean verifikasi menampilkan berkas terlama lebih dulu, sebanyak ini. */
    public const BATAS_ANTREAN = 30;

    /* ---------- Ringkasan: dokumen diminta Rencana Audit & status bukti ---------- */
    public function halaman(Request $request, SmkpAudit $smkp)
    {
        $smkp->load('company');
        $u = $request->user();
        $diminta = self::diminta($smkp);

        $hitung = SmkpBukti::where('audit_id', $smkp->id)
            ->selectRaw('kode, status, COUNT(*) AS n')->groupBy('kode', 'status')->get()
            ->groupBy('kode')->map(fn ($g) => $g->pluck('n', 'status')->map(fn ($n) => (int) $n));

        $elemen = [];
        foreach (Smkp::elemen() as $e) {
            $sub = [];
            foreach ($e['sub'] as $s) {
                $butir = array_map(fn ($b) => [
                    'kode'     => $b['kode'],
                    'nama'     => $b['nama'],
                    'menunggu' => (int) ($hitung[$b['kode']][SmkpBukti::MENUNGGU] ?? 0),
                    'diterima' => (int) ($hitung[$b['kode']][SmkpBukti::DITERIMA] ?? 0),
                    'ditolak'  => (int) ($hitung[$b['kode']][SmkpBukti::DITOLAK] ?? 0),
                ], Smkp::butirSub($s));
                $sub[] = [
                    'kode'    => $s['kode'],
                    'nama'    => $s['nama'],
                    'diminta' => $diminta[$s['kode']] ?? null,
                    'butir'   => $butir,
                    'url'     => route('smkp.nilai', [$smkp, $e['kode'], 'sub' => $s['kode']]),
                ];
            }
            $elemen[] = ['kode' => $e['kode'], 'nama' => $e['nama'], 'sub' => $sub];
        }

        // `url` tetap alamat unduh (seperti BerkasBukti lainnya); `tautan` menuju butirnya di halaman Penilaian.
        $antrean = $u->bisaAudit()
            ? SmkpBukti::where('audit_id', $smkp->id)->where('status', SmkpBukti::MENUNGGU)->with(['pengunggah:id,name', 'verifikator:id,name'])
                ->orderBy('id')->limit(self::BATAS_ANTREAN)->get()->map(fn (SmkpBukti $b) => self::bentuk($b, $smkp, $u) + [
                    'tautan' => route('smkp.nilai', [$smkp, self::elemenDari($b->kode), 'sub' => self::subDari($b->kode)]).'#butir-'.$b->kode,
                ])
            : [];

        return Inertia::render('Smkp/Bukti', [
            'audit'   => $this->ringkasAudit($smkp),
            'elemen'  => $elemen,
            'antrean' => $antrean,
            'rencanaUrl' => route('smkp.rencana', $smkp),
            'bisaSunting'    => $u->bisaAudit(),
            'bisaUnggah'     => self::bisaUnggah($u, $smkp),
        ]);
    }

    /* ---------- Unggah berkas untuk satu butir ---------- */
    public function unggah(Request $request, SmkpAudit $smkp)
    {
        $u = $request->user();
        if (!self::bisaUnggah($u, $smkp)) {
            throw ValidationException::withMessages(['berkas' => 'Audit sudah selesai — bukti tidak dapat ditambahkan lagi.']);
        }

        $d = $request->validate([
            'kode'       => ['required', 'string', Rule::in(array_column(Smkp::butir(), 'kode'))],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'berkas'     => ['required', 'array', 'min:1', 'max:'.self::MAKS_BERKAS],
            'berkas.*'   => ['file', 'max:'.self::MAKS_KB, 'extensions:'.implode(',', self::EKSTENSI), new LampiranBukti()],
        ], [
            'kode.in'            => 'Butir kriteria tidak dikenal.',
            'berkas.required'    => 'Pilih berkas bukti yang akan diunggah.',
            'berkas.max'         => 'Paling banyak '.self::MAKS_BERKAS.' berkas sekali unggah.',
            'berkas.*.uploaded'  => ':attribute gagal terunggah — periksa koneksi, lalu unggah ulang.',
            'berkas.*.file'      => ':attribute gagal terunggah — periksa koneksi, lalu unggah ulang.',
            'berkas.*.max'       => ':attribute lebih dari 10 MB.',
            'berkas.*.extensions'=> ':attribute bukan PDF, gambar (JPG/PNG/WEBP), atau dokumen Office.',
        ], BerkasUnggah::namaAtribut($request, 'berkas'));

        $this->pastikanKuota($request, $smkp, $d['kode']);

        // Berkas dari auditor/administrator langsung terverifikasi olehnya sendiri.
        $verifikator = $u->bisaAudit();
        $tersimpan = [];
        try {
            foreach ($request->file('berkas', []) as $f) {
                /** @var UploadedFile $f */
                $ext = strtolower($f->getClientOriginalExtension());
                $path = $f->storeAs(SmkpBukti::folder($smkp->id), Str::random(40).'.'.$ext, 'local');
                if ($path === false) throw new \RuntimeException('Berkas gagal disimpan.');
                $tersimpan[] = [
                    'audit_id'   => $smkp->id,
                    'kode'       => $d['kode'],
                    'user_id'    => $u->id,
                    'path'       => $path,
                    'nama'       => BerkasUnggah::namaAman($f->getClientOriginalName(), $ext, 'bukti'),
                    // Jenis isi hasil deteksi server; null bila tidak terdeteksi (berkas tetap diunduh, tak dipratinjau).
                    'mime'       => $f->getMimeType() ?: null,
                    'ukuran'     => (int) $f->getSize(),
                    'keterangan' => trim((string) ($d['keterangan'] ?? '')) ?: null,
                    'status'     => $verifikator ? SmkpBukti::DITERIMA : SmkpBukti::MENUNGGU,
                    'diverifikasi_oleh' => $verifikator ? $u->id : null,
                    'diverifikasi_pada' => $verifikator ? now() : null,
                ];
            }
            DB::transaction(function () use ($tersimpan) {
                foreach ($tersimpan as $b) SmkpBukti::create($b);
            });
        } catch (Throwable $e) {
            if ($tersimpan) Storage::disk('local')->delete(array_column($tersimpan, 'path'));
            throw $e;
        }

        ActivityLog::write('Unggah bukti audit', $d['kode'].' · '.count($tersimpan).' berkas · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', count($tersimpan).' berkas bukti '.$d['kode'].' terunggah'.($verifikator ? '.' : ' — menunggu verifikasi auditor.'));
    }

    /* ---------- Verifikasi oleh auditor / administrator ---------- */
    public function verifikasi(Request $request, SmkpAudit $smkp, SmkpBukti $bukti)
    {
        abort_if($bukti->audit_id !== $smkp->id, 404);

        $d = $request->validate([
            'status'  => ['required', Rule::in(array_keys(SmkpBukti::STATUS))],
            'catatan' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('status') === SmkpBukti::DITOLAK)],
        ], ['catatan.required' => 'Tuliskan apa yang perlu diperbaiki auditi.']);

        $bukti->update([
            'status'  => $d['status'],
            'catatan' => trim((string) ($d['catatan'] ?? '')) ?: null,
            'diverifikasi_oleh' => $d['status'] === SmkpBukti::MENUNGGU ? null : $request->user()->id,
            'diverifikasi_pada' => $d['status'] === SmkpBukti::MENUNGGU ? null : now(),
        ]);

        ActivityLog::write('Verifikasi bukti audit', $bukti->kode.' · '.$bukti->nama.' → '.SmkpBukti::STATUS[$d['status']].' · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', '"'.Str::limit($bukti->nama, 60).'" — '.mb_strtolower(SmkpBukti::STATUS[$d['status']]).'.');
    }

    /** Pengunggah dapat menarik berkasnya selama belum diterima dan audit belum selesai; auditor kapan saja. */
    public function hapus(Request $request, SmkpAudit $smkp, SmkpBukti $bukti)
    {
        abort_if($bukti->audit_id !== $smkp->id, 404);
        abort_unless(self::bisaHapus($request->user(), $bukti, $smkp), 403);

        $path = $bukti->path;
        $bukti->delete();
        // Berkas dihapus setelah barisnya: kegagalan basis data tidak meninggalkan rekaman tanpa berkas.
        Storage::disk('local')->delete($path);
        ActivityLog::write('Hapus bukti audit', $bukti->kode.' · '.$bukti->nama.' · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Berkas bukti dihapus.');
    }

    public function unduh(Request $request, SmkpAudit $smkp, SmkpBukti $bukti)
    {
        abort_if($bukti->audit_id !== $smkp->id, 404);

        return BerkasUnggah::kirim($bukti->path, $bukti->nama, $bukti->mime, $request->boolean('lihat'));
    }

    /* ================= dipakai halaman Penilaian ================= */

    /** Dokumen & sampel yang diminta Rencana Audit, per kode sub-elemen. @return array<string,array{metode:string,sampel:string}> */
    public static function diminta(SmkpAudit $smkp): array
    {
        $out = [];
        foreach ((array) (($smkp->rencana ?? [])['metode_sampel'] ?? []) as $kode => $v) {
            $m = trim((string) (((array) $v)['metode'] ?? ''));
            $s = trim((string) (((array) $v)['sampel'] ?? ''));
            if ($m !== '' || $s !== '') $out[(string) $kode] = ['metode' => $m, 'sampel' => $s];
        }

        return $out;
    }

    /** Berkas bukti audit dikelompokkan per kode butir, terbaru di atas. @return array<string,list<array>> */
    public static function perButir(SmkpAudit $smkp, User $u, ?array $kode = null): array
    {
        return SmkpBukti::where('audit_id', $smkp->id)
            ->when($kode !== null, fn ($q) => $q->whereIn('kode', $kode))
            ->with(['pengunggah:id,name', 'verifikator:id,name'])->orderByDesc('id')->get()
            ->groupBy('kode')->map(fn ($g) => $g->map(fn (SmkpBukti $b) => self::bentuk($b, $smkp, $u))->values()->all())
            ->all();
    }

    public static function batas(): array
    {
        return ['jumlah' => self::MAKS_BERKAS, 'kb' => self::MAKS_KB, 'ekstensi' => self::EKSTENSI];
    }

    public static function bisaUnggah(User $u, SmkpAudit $smkp): bool
    {
        return $u->bisaAudit() || $smkp->status !== 'selesai';
    }

    /** Auditi hanya menarik berkasnya sendiri yang belum diterima, dan tidak lagi setelah audit selesai. */
    private static function bisaHapus(User $u, SmkpBukti $b, SmkpAudit $smkp): bool
    {
        return $u->bisaAudit()
            || ($b->user_id === $u->id && $b->status !== SmkpBukti::DITERIMA && $smkp->status !== 'selesai');
    }

    private static function bentuk(SmkpBukti $b, SmkpAudit $smkp, User $u): array
    {
        return [
            'id'         => $b->id,
            'kode'       => $b->kode,
            'nama'       => $b->nama,
            'ukuran'     => $b->ukuran,
            'gambar'     => $b->gambar(),
            'keterangan' => $b->keterangan,
            'status'     => $b->status,
            'catatan'    => $b->catatan,
            'oleh'       => $b->pengunggah?->name,
            'waktu'      => Waktu::lokal($b->created_at)?->translatedFormat('d M Y H:i'),
            'verifikator'=> $b->verifikator?->name,
            'diverifikasi' => Waktu::lokal($b->diverifikasi_pada)?->translatedFormat('d M Y H:i'),
            'url'        => route('smkp.bukti.unduh', [$smkp, $b]),
            'bisaHapus'  => self::bisaHapus($u, $b, $smkp),
        ];
    }

    private static function elemenDari(string $kode): string
    {
        return explode('.', $kode)[0];
    }

    private static function subDari(string $kode): string
    {
        return implode('.', array_slice(explode('.', $kode), 0, 2));
    }

    /** Tolak unggahan yang melampaui kuota butir/audit/harian atau sisa disk server. */
    private function pastikanKuota(Request $request, SmkpAudit $smkp, string $kode): void
    {
        $baru = BerkasUnggah::ukuranKiriman($request, 'berkas');
        $mb = fn (int $b) => BerkasUnggah::mb($b);

        $galat = match (true) {
            (int) SmkpBukti::where('audit_id', $smkp->id)->where('kode', $kode)->sum('ukuran') + $baru > self::KUOTA_BUTIR
                => "Bukti butir {$kode} sudah mencapai batas ".$mb(self::KUOTA_BUTIR).'. Hapus berkas yang tidak diperlukan, atau gabungkan dalam satu PDF.',
            (int) SmkpBukti::where('audit_id', $smkp->id)->sum('ukuran') + $baru > self::KUOTA_AUDIT
                => 'Bukti audit ini sudah mencapai batas '.$mb(self::KUOTA_AUDIT).'. Hubungi administrator.',
            (int) SmkpBukti::where('user_id', $request->user()->id)->where('created_at', '>=', now()->subDay())->sum('ukuran') + $baru > self::KUOTA_HARIAN
                => 'Batas unggah harian '.$mb(self::KUOTA_HARIAN).' tercapai. Lanjutkan besok, atau hubungi administrator.',
            !BerkasUnggah::diskCukup($baru)
                => 'Ruang penyimpanan server hampir penuh — unggahan ditunda. Hubungi administrator.',
            default => null,
        };
        if ($galat) throw ValidationException::withMessages(['berkas' => $galat]);
    }
}
