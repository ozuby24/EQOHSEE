<?php

namespace App\Http\Controllers;

use App\Models\{SmkpAudit, SmkpBukti, SmkpFinding};
use App\Services\Asisten\{GagalAsisten, KonteksAudit, Lampiran, LayananAsisten};
use App\Services\Asisten\Tugas\{AnalisisBukti, AnalisisTemuan, PenilaianButir};
use App\Support\Smkp;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Asisten AI audit — JSON masuk, JSON keluar (routes/asisten.php).
 *
 * Batas perusahaan ditegakkan oleh global scope model: audit dan temuan di
 * luar jangkauan pengguna tidak pernah terbaca sehingga dijawab 404.
 * Saran asisten tidak menyimpan apa pun — auditor yang menerapkan lalu
 * menyimpan lewat formulir biasa. (Tugas PTPKKP milik Aurorasys tidak ikut
 * diporting.)
 */
class AsistenController extends Controller
{
    /** Berkas bukti per analisis — cukup untuk satu butir, dan menjaga ukuran permintaan. */
    public const MAKS_BERKAS_BUKTI = 8;

    /* ---------- Penilaian satu butir kriteria SMKP ---------- */
    public function butir(Request $request, LayananAsisten $asisten, SmkpAudit $smkp): JsonResponse
    {
        $asisten->pastikanAktif();

        $d = $request->validate([
            'kode'  => ['required', 'string', 'max:20'],
            'v'     => ['nullable', 'max:5'],
            'ket'   => ['nullable', 'string', 'max:2000'],
            'bukti' => ['nullable', 'string', 'max:500'],
        ], [
            'kode.required' => 'Kode butir kriteria wajib diisi.',
            'ket.max'       => 'Catatan auditor paling banyak 2.000 karakter.',
            'bukti.max'     => 'Bukti objektif paling banyak 500 karakter.',
        ]);

        $letak = self::letakButir($d['kode']);

        $smkp->load('company');
        $tugas = new PenilaianButir($smkp, $letak, [
            'v'     => self::nilaiIsian($smkp, $letak, $d['v'] ?? null),
            'ket'   => (string) ($d['ket'] ?? ''),
            'bukti' => (string) ($d['bukti'] ?? ''),
        ]);

        $jawaban = $asisten->jalankan($tugas->permintaan(), $smkp->namaTampil().' · butir '.$letak['butir']['kode']);

        return response()->json($tugas->rapikan($jawaban));
    }

    /* ---------- Analisis berkas bukti satu butir kriteria SMKP ---------- */
    public function bukti(Request $request, LayananAsisten $asisten, SmkpAudit $smkp): JsonResponse
    {
        $asisten->pastikanAktif();

        $d = $request->validate([
            'kode'     => ['required', 'string', 'max:20'],
            'berkas'   => ['nullable', 'array', 'max:'.self::MAKS_BERKAS_BUKTI],
            'berkas.*' => ['integer', 'distinct'],
            'v'        => ['nullable', 'max:5'],
            'ket'      => ['nullable', 'string', 'max:2000'],
        ], [
            'kode.required' => 'Kode butir kriteria wajib diisi.',
            'berkas.max'    => 'Pilih paling banyak '.self::MAKS_BERKAS_BUKTI.' berkas untuk sekali analisis.',
            'berkas.*'      => 'Pilihan berkas bukti tidak sah.',
            'ket.max'       => 'Catatan auditor paling banyak 2.000 karakter.',
        ]);

        $letak = self::letakButir($d['kode']);
        $kode  = $letak['butir']['kode'];
        $v     = self::nilaiIsian($smkp, $letak, $d['v'] ?? null);

        // Berkas selalu dicocokkan dengan audit & butirnya. Tanpa pilihan:
        // semua berkas butir yang tidak ditolak verifikator, terbaru dulu.
        $q = SmkpBukti::where('audit_id', $smkp->id)->where('kode', $kode)->with('pengunggah')->orderByDesc('id');
        if (!empty($d['berkas'])) {
            $pilih = $q->whereIn('id', $d['berkas'])->get();
            if ($pilih->count() !== count($d['berkas'])) {
                throw ValidationException::withMessages(['berkas' => 'Berkas bukti yang dipilih tidak ditemukan pada butir ini.']);
            }
        } else {
            $pilih = (clone $q)->where('status', '!=', SmkpBukti::DITOLAK)->limit(self::MAKS_BERKAS_BUKTI)->get();
            if ($pilih->isEmpty() && $q->exists()) {
                throw GagalAsisten::kosong('Semua berkas bukti butir ini berstatus "Perlu perbaikan" sehingga tidak dianalisis. Tunggu unggahan perbaikan dari auditi, atau pilih berkasnya secara khusus.');
            }
        }

        // Biner yang jelas melampaui batas kiriman tidak dibaca ke memori;
        // sisanya disaring Lampiran::muat (batas biner & teks total).
        $terbaca = $milik = $tidakTerbaca = $dilewati = [];
        $biner = 0;
        foreach ($pilih as $b) {
            $path = Storage::disk('local')->path($b->path);
            if (in_array($b->mime, Lampiran::BINER, true)) {
                $n = is_file($path) ? (int) filesize($path) : 0;
                if (str_starts_with((string) $b->mime, 'image/')) $n = min($n, Lampiran::MAKS_GAMBAR);   // diperkecil saat dibaca
                if ($biner + $n > Lampiran::MAKS_BINER_TOTAL) { $dilewati[] = $b->nama; continue; }
                $biner += $n;
            }
            $l = Lampiran::dariBerkas($path, $b->nama, $b->mime);
            if (!$l) { $tidakTerbaca[] = $b->nama; continue; }
            $terbaca[] = $l;
            $milik[spl_object_id($l)] = $b;
        }
        [$ambil, $lewat] = Lampiran::muat($terbaca);
        $dilewati = array_merge($dilewati, $lewat);

        if (!$ambil) {
            throw GagalAsisten::kosong($dilewati && !$tidakTerbaca
                ? 'Berkas bukti butir ini terlalu besar untuk dianalisis AI sekaligus (batas '.intdiv(Lampiran::MAKS_BINER_TOTAL, 1024 * 1024).' MB per analisis; gambar yang tidak dapat diperkecil di bawah '.round(Lampiran::MAKS_GAMBAR / 1_000_000, 1).' MB dilewati). Pilih berkas yang lebih kecil, atau pisahkan PDF-nya.'
                : 'Belum ada berkas bukti yang dapat dibaca AI untuk butir ini — unggah PDF, gambar, atau dokumen Office (DOCX/XLSX/PPTX).');
        }

        $smkp->load('company');
        $tugas = new AnalisisBukti($smkp, $letak,
            ['v' => $v, 'ket' => (string) ($d['ket'] ?? '')],
            array_map(fn (Lampiran $l) => ['bukti' => $milik[spl_object_id($l)], 'lampiran' => $l], $ambil),
            $tidakTerbaca, $dilewati,
        );

        $jawaban = $asisten->jalankan($tugas->permintaan(), $smkp->namaTampil().' · bukti butir '.$kode);

        return response()->json($tugas->rapikan($jawaban));
    }

    /* ---------- Akar masalah & RTP satu temuan SMKP ---------- */
    public function temuan(Request $request, LayananAsisten $asisten, SmkpAudit $smkp, SmkpFinding $temuan): JsonResponse
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $asisten->pastikanAktif();

        $d = $request->validate([
            'akar_masalah' => ['nullable', 'string', 'max:2000'],
            'tindakan'     => ['nullable', 'string', 'max:2000'],
        ], [
            'akar_masalah.max' => 'Akar masalah paling banyak 2.000 karakter.',
            'tindakan.max'     => 'Tindakan perbaikan paling banyak 2.000 karakter.',
        ]);

        $smkp->load('company');
        $tugas = new AnalisisTemuan($smkp, $temuan, array_filter(
            ['akar_masalah' => $d['akar_masalah'] ?? null, 'tindakan' => $d['tindakan'] ?? null],
            fn ($x) => $x !== null,
        ));

        $jawaban = $asisten->jalankan($tugas->permintaan(), $smkp->namaTampil().' · temuan '.$temuan->kode_kriteria);

        return response()->json($tugas->rapikan($jawaban));
    }

    /* ---------- bantu ---------- */

    /** @return array{elemen:array,sub:array,butir:array} */
    private static function letakButir(string $kode): array
    {
        return KonteksAudit::butir($kode)
            ?? throw ValidationException::withMessages(['kode' => 'Butir kriteria tidak dikenal.']);
    }

    /**
     * Nilai yang sedang terisi di formulir (boleh belum disimpan): kosong,
     * N/A, atau bilangan bulat 0..maks butir.
     *
     * Nilai tersimpan yang perlu dinilai ulang (skala lama / di atas
     * maksimum) dan masih tampil di formulir dibaca "belum dinilai ulang";
     * prompt menyebut nilai lamanya tersendiri.
     */
    private static function nilaiIsian(SmkpAudit $smkp, array $letak, mixed $v): string
    {
        $kode = $letak['butir']['kode'];
        $maks = (int) $letak['butir']['maks'];
        $v = trim((string) $v);

        $usang = Smkp::perluDinilaiUlang([$kode => $smkp->hasil[$kode] ?? null])[$kode] ?? null;
        if ($usang && is_numeric($v) && (float) $v === $usang['v']) $v = '';
        if ($v !== '' && strcasecmp($v, Smkp::NA) !== 0
            && !(ctype_digit($v) && (int) $v <= $maks)) {
            throw ValidationException::withMessages(['v' => "Nilai butir harus 0 sampai {$maks} atau N/A."]);
        }

        return strcasecmp($v, Smkp::NA) === 0 ? Smkp::NA : $v;
    }
}
