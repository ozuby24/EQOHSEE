<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, SmkpAudit, SmkpFinding, SmkpTindakLanjut};
use App\Rules\LampiranBukti;
use App\Support\{BerkasUnggah, Smkp};
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/**
 * Tindak lanjut temuan oleh auditi: isian perbaikan beserta lampiran bukti.
 * Auditor memverifikasi dan menutup temuan di halaman Temuan audit.
 *
 * Batas perusahaan datang dari pengikatan rute: SmkpAudit dan SmkpFinding
 * bercakupan jangkauan pengguna, jadi temuan di luar jangkauan menjadi 404.
 */
class TindakLanjutController extends Controller
{
    public const MAKS_BERKAS = 5;
    public const MAKS_KB = 10240;
    public const EKSTENSI = LampiranBukti::EKSTENSI;

    /*
     * Kuota penyimpanan: satu akun tidak boleh dapat memenuhi disk server.
     * Per temuan cukup untuk puluhan foto & dokumen; per pengguna per hari
     * membatasi skrip unggah berulang; sisa disk dijaga untuk basis data & log.
     */
    public const KUOTA_TEMUAN   = 100 * 1024 * 1024;
    public const KUOTA_HARIAN   = 250 * 1024 * 1024;

    /* ---------- Daftar temuan lintas audit dalam jangkauan ---------- */
    public function index(Request $request)
    {
        $q = $this->teks($request, 'q');
        $status = $this->teks($request, 'status', 'terbuka');
        if ($status !== 'semua' && $status !== 'terbuka' && !in_array($status, SmkpFinding::STATUS, true)) $status = 'terbuka';

        $dasar = SmkpFinding::query()->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
            ->where('uraian', 'like', "%{$q}%")->orWhere('kode_kriteria', 'like', "%{$q}%")->orWhere('nomor', 'like', "%{$q}%")));

        $jumlah = (clone $dasar)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        $daftar = (clone $dasar)->with('audit.company:id,name')->withCount('tindakLanjut')
            ->when($status === 'terbuka', fn ($b) => $b->where('status', '<>', 'Closed'))
            ->when(in_array($status, SmkpFinding::STATUS, true), fn ($b) => $b->where('status', $status))
            ->orderByRaw("CASE status WHEN 'Open' THEN 0 WHEN 'In Progress' THEN 1 ELSE 2 END")
            ->orderByRaw('target_selesai IS NULL')->orderBy('target_selesai')
            ->orderByRaw(Smkp::urutJenisSql())->orderBy('id')
            ->paginate(20)->withQueryString();

        $daftar->getCollection()->transform(fn (SmkpFinding $t) => [
            'id'         => $t->id,
            'nomor'      => trim((string) $t->nomor),
            'kode'       => $t->kode_kriteria,
            'jenis'      => $t->jenis,
            'uraian'     => $t->uraian,
            'status'     => $t->status,
            'target'     => $t->target_selesai?->translatedFormat('d M Y'),
            'terlambat'  => $t->terlambat(),
            'kiriman'    => (int) $t->tindak_lanjut_count,
            'perusahaan' => $t->audit?->company?->name,
            'audit'      => $t->audit?->namaTampil(),
            'url'        => $t->audit ? route('smkp.temuan', [$t->audit, 'buka' => $t->id]) : null,
        ]);

        return Inertia::render('Smkp/TindakLanjut', [
            'daftar' => $daftar,
            'q'      => $q,
            'status' => $status,
            'jumlah' => [
                'terbuka'     => (int) (($jumlah['Open'] ?? 0) + ($jumlah['In Progress'] ?? 0)),
                'Open'        => (int) ($jumlah['Open'] ?? 0),
                'In Progress' => (int) ($jumlah['In Progress'] ?? 0),
                'Closed'      => (int) ($jumlah['Closed'] ?? 0),
                'semua'       => (int) $jumlah->sum(),
            ],
            'bisaKirim' => $request->user()->can('tindak-lanjut'),
        ]);
    }

    /* ---------- Auditi menyampaikan tindak lanjut ---------- */
    public function kirim(Request $request, SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);

        // Pesan galat menyebut nama berkasnya, bukan "lampiran.2".
        $nama = BerkasUnggah::namaAtribut($request, 'lampiran');

        $d = $request->validate([
            'akar_masalah'     => ['nullable', 'string', 'max:3000'],
            'tindakan'         => ['required', 'string', 'max:3000'],
            'bukti_perbaikan'  => ['nullable', 'string', 'max:3000'],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'lampiran'         => ['nullable', 'array', 'max:'.self::MAKS_BERKAS],
            'lampiran.*'       => ['file', 'max:'.self::MAKS_KB, 'extensions:'.implode(',', self::EKSTENSI), new LampiranBukti()],
        ], [
            'tindakan.required'     => 'Uraikan tindakan perbaikan yang sudah dilakukan.',
            'lampiran.max'          => 'Paling banyak '.self::MAKS_BERKAS.' berkas per pengiriman.',
            'lampiran.*.uploaded'   => ':attribute gagal terunggah — periksa koneksi, lalu kirim ulang.',
            'lampiran.*.file'       => ':attribute gagal terunggah — periksa koneksi, lalu kirim ulang.',
            'lampiran.*.max'        => ':attribute lebih dari 10 MB.',
            'lampiran.*.extensions' => ':attribute bukan PDF, gambar (JPG/PNG/WEBP), atau dokumen Office.',
        ], $nama);

        if ($temuan->status === 'Closed') {
            throw ValidationException::withMessages(['tindakan' => 'Temuan ini sudah ditutup auditor; tindak lanjut tidak dapat dikirim lagi.']);
        }
        $this->pastikanKuota($request, $temuan);

        $tersimpan = [];
        try {
            foreach ($request->file('lampiran', []) as $f) {
                /** @var UploadedFile $f */
                $path = $f->storeAs(
                    SmkpTindakLanjut::folder($smkp->id, $temuan->id),
                    Str::random(40).'.'.strtolower($f->getClientOriginalExtension()),
                    'local'
                );
                if ($path === false) throw new \RuntimeException('Berkas gagal disimpan.');
                $tersimpan[] = [
                    'path'   => $path,
                    'nama'   => BerkasUnggah::namaAman($f->getClientOriginalName(), strtolower($f->getClientOriginalExtension())),
                    'mime'   => (string) $f->getMimeType(),
                    'ukuran' => (int) $f->getSize(),
                ];
            }

            DB::transaction(function () use ($request, $temuan, $d, $tersimpan) {
                $temuan->tindakLanjut()->create([
                    'user_id'          => $request->user()->id,
                    'akar_masalah'     => $d['akar_masalah'] ?? null,
                    'tindakan'         => $d['tindakan'],
                    'bukti_perbaikan'  => $d['bukti_perbaikan'] ?? null,
                    'penanggung_jawab' => $d['penanggung_jawab'] ?? null,
                    'lampiran'         => $tersimpan ?: null,
                ]);

                // Isian terbaru menjadi kolom tindak lanjut temuan (formulir
                // ketidaksesuaian); menunggu verifikasi auditor.
                $temuan->update([
                    'akar_masalah'     => $d['akar_masalah'] ?? null,
                    'tindakan'         => $d['tindakan'],
                    'bukti_perbaikan'  => $d['bukti_perbaikan'] ?? null,
                    'penanggung_jawab' => $d['penanggung_jawab'] ?? null,
                    'status'           => $temuan->status === 'Open' ? 'In Progress' : $temuan->status,
                ]);
            });
        } catch (Throwable $e) {
            if ($tersimpan) Storage::disk('local')->delete(array_column($tersimpan, 'path'));
            throw $e;
        }

        ActivityLog::write('Tindak lanjut temuan', $temuan->kode_kriteria.' · '.count($tersimpan).' lampiran · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Tindak lanjut '.$temuan->kode_kriteria.' terkirim — menunggu verifikasi auditor.');
    }

    /** Pengirim dapat menarik kirimannya selama temuan belum ditutup; auditor kapan saja. */
    public function hapus(Request $request, SmkpAudit $smkp, SmkpFinding $temuan, int $kiriman)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $k = $temuan->tindakLanjut()->findOrFail($kiriman);

        $u = $request->user();
        abort_unless($u->bisaAudit() || ($k->user_id === $u->id && $temuan->status !== 'Closed'), 403);

        $berkas = array_values(array_filter(array_column($k->lampiran ?? [], 'path')));
        DB::transaction(function () use ($k, $temuan) {
            $k->delete();
            if ($temuan->status === 'Closed') return;

            // Isian temuan mengikuti kiriman terbaru yang tersisa. Tanpa kiriman
            // lagi, isian yang berasal dari kiriman ini dikosongkan dan temuan
            // kembali Open — teks yang ditarik tidak tertinggal di formulir.
            $kolom = ['akar_masalah', 'tindakan', 'bukti_perbaikan', 'penanggung_jawab'];
            $sisa = $temuan->tindakLanjut()->first();
            if ($sisa) {
                $temuan->update($sisa->only($kolom));
            } else {
                $ubah = [];
                foreach ($kolom as $c) if ((string) $temuan->{$c} === (string) $k->{$c}) $ubah[$c] = null;
                if ($temuan->status === 'In Progress') $ubah['status'] = 'Open';
                if ($ubah) $temuan->update($ubah);
            }
        });
        // Berkas dihapus setelah barisnya: kegagalan basis data tidak meninggalkan rekaman tanpa berkas.
        if ($berkas) Storage::disk('local')->delete($berkas);
        ActivityLog::write('Hapus tindak lanjut temuan', $temuan->kode_kriteria.' · '.$smkp->namaTampil(), 'smkp');

        return back()->with('ok', 'Kiriman tindak lanjut dihapus.');
    }

    public function unduh(Request $request, SmkpAudit $smkp, SmkpFinding $temuan, int $kiriman, int $berkas)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $l = $temuan->tindakLanjut()->findOrFail($kiriman)->lampiran[$berkas] ?? null;
        abort_unless(is_array($l), 404);

        return BerkasUnggah::kirim($l['path'] ?? null, $l['nama'] ?? null, $l['mime'] ?? null, $request->boolean('lihat'));
    }

    /** Tolak kiriman yang melampaui kuota temuan/harian atau sisa disk server. */
    private function pastikanKuota(Request $request, SmkpFinding $temuan): void
    {
        $baru = BerkasUnggah::ukuranKiriman($request, 'lampiran');
        if (!$baru) return;

        $ukuran = fn ($q) => $q->get(['lampiran'])->sum(fn (SmkpTindakLanjut $k) => array_sum(array_column($k->lampiran ?? [], 'ukuran')));
        $mb = fn (int $b) => BerkasUnggah::mb($b);

        $galat = match (true) {
            $ukuran(SmkpTindakLanjut::where('finding_id', $temuan->id)) + $baru > self::KUOTA_TEMUAN
                => 'Lampiran temuan ini sudah mencapai batas '.$mb(self::KUOTA_TEMUAN).'. Hapus kiriman lama yang tidak diperlukan, atau gabungkan bukti dalam satu PDF.',
            $ukuran(SmkpTindakLanjut::where('user_id', $request->user()->id)->where('created_at', '>=', now()->subDay())) + $baru > self::KUOTA_HARIAN
                => 'Batas unggah harian '.$mb(self::KUOTA_HARIAN).' tercapai. Lanjutkan besok, atau hubungi administrator.',
            !BerkasUnggah::diskCukup($baru)
                => 'Ruang penyimpanan server hampir penuh — unggahan ditunda. Hubungi administrator.',
            default => null,
        };
        if ($galat) throw ValidationException::withMessages(['lampiran' => $galat]);
    }
}
