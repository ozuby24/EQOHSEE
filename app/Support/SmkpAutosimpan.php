<?php

namespace App\Support;

use App\Models\{ActivityLog, SmkpAudit};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

/**
 * Simpan otomatis formulir Audit SMKP — port DBEST (Services\Smkp\Autosimpan).
 *
 * Formulir isian panjang (Tahap I, Rencana Audit, Penilaian, Isian Laporan,
 * Formulir) dikirim peramban ke alamat simpan yang SAMA beberapa detik
 * setelah auditor berhenti mengetik, ditandai kepala X-Autosimpan: 1.
 * Bedanya dengan menekan Simpan hanya jawabannya (JSON, tanpa pesan kilat
 * dan tanpa pindah halaman) dan pemeriksaan versi di bawah.
 *
 * Versi = sidik isi yang ditulis formulir itu. Peramban mengirim versi yang
 * dilihatnya saat halaman dibuka (_versi); bila isi di server sudah berbeda
 * — disimpan akun lain atau dari tab lain — simpan otomatis DITOLAK (409)
 * supaya tidak menimpa pekerjaan orang lain tanpa sadar. Menekan Simpan
 * sendiri tetap menimpa, seperti sebelumnya.
 */
final class SmkpAutosimpan
{
    public const KEPALA = 'X-Autosimpan';

    /** Formulir yang disimpan otomatis ⇒ kolom smkp_audits yang ditulisnya. */
    public const KOLOM = [
        'tahap1'   => ['permulaan', 'kinerja', 'kecukupan'],
        'rencana'  => ['rencana', 'risiko'],
        'laporan'  => ['laporan'],
        'formulir' => ['kesesuaian', 'rekomendasi'],
        'nilai'    => ['hasil'],
    ];

    public const LABEL = [
        'tahap1' => 'Tahap I', 'rencana' => 'Rencana Audit', 'laporan' => 'Isian Laporan Audit',
        'formulir' => 'Formulir kesesuaian & rekomendasi', 'nilai' => 'Penilaian',
    ];

    /** Lama catatan penulis terakhir disimpan (detik). */
    private const INGAT = 172800;

    /** Jeda antarcatatan log simpan otomatis per akun & formulir (detik). */
    private const JEDA_LOG = 1800;

    public static function otomatis(?Request $r = null): bool
    {
        return ($r ?? request())->header(self::KEPALA) === '1';
    }

    /**
     * Sidik isi satu formulir, dari isi yang sudah diurai dan diurutkan
     * kuncinya — basis data dapat menata ulang kolom JSON tanpa mengubah isi.
     */
    public static function versi(SmkpAudit $a, string $formulir, ?string $elemen = null): string
    {
        $mentah = $a->getAttributes();
        $isi = [];
        foreach (self::KOLOM[$formulir] ?? [] as $k) {
            $v = $mentah[$k] ?? null;
            $isi[$k] = is_string($v) ? json_decode($v, true) : $v;
        }
        if ($formulir === 'nilai') {
            // Penilaian disimpan per elemen: elemen lain yang dinilai rekan tidak dianggap bentrok.
            $awalan = (string) $elemen.'.';
            $isi = array_filter((array) ($isi['hasil'] ?? []), fn ($k) => str_starts_with((string) $k, $awalan), ARRAY_FILTER_USE_KEY);
        }

        return self::sidik($isi);
    }

    public static function sidik(mixed $data): string
    {
        return substr(sha1((string) json_encode(self::kanon($data))), 0, 24);
    }

    private static function kanon(mixed $v): mixed
    {
        if (!is_array($v)) return $v;
        if ($v && !array_is_list($v)) ksort($v, SORT_STRING);
        foreach ($v as $k => $x) $v[$k] = self::kanon($x);

        return $v;
    }

    private static function kunci(SmkpAudit $a, string $formulir, ?string $elemen): string
    {
        return 'autosimpan:smkp:'.$a->id.':'.$formulir.($elemen ? ':'.$elemen : '');
    }

    /** Tolak simpan otomatis yang akan menimpa isi yang lebih baru: jawaban 409, atau null bila aman. */
    public static function bentrok(Request $r, SmkpAudit $a, string $formulir, ?string $elemen = null): ?JsonResponse
    {
        if (!self::otomatis($r) || $r->input('_paksa') === '1') return null;

        $klien = (string) $r->input('_versi', '');
        $kini  = self::versi($a, $formulir, $elemen);
        if ($klien === '' || hash_equals($kini, $klien)) return null;

        // Tulisan terakhir milik sesi sunting ini juga: jawaban simpan sebelumnya
        // tidak sampai ke peramban (sambungan putus) — versinya tertinggal, bukan bentrok.
        $akhir   = Cache::get(self::kunci($a, $formulir, $elemen));
        $sunting = (string) $r->input('_sunting', '');
        $milik   = is_array($akhir) && ($akhir['versi'] ?? '') === $kini;
        if ($milik && $sunting !== '' && ($akhir['sunting'] ?? '') === $sunting) return null;

        return response()->json([
            'pesan' => 'Isian ini sudah berubah di server'
                .($milik && ($akhir['nama'] ?? '') !== '' ? ' (disimpan '.$akhir['nama'].' pukul '.$akhir['pukul'].')' : '')
                .'. Simpan otomatis dihentikan agar tidak saling menimpa — muat ulang halaman, atau tekan Simpan untuk menimpanya.',
            'jenis' => 'bentrok',
            'versi' => $kini,
        ], 409);
    }

    /**
     * Sesudah isi tersimpan. Simpan otomatis mendapat JSON (versi baru);
     * tombol Simpan mendapat null dan melanjutkan alurnya sendiri.
     */
    public static function selesai(Request $r, SmkpAudit $a, string $formulir, ?string $elemen = null): ?JsonResponse
    {
        $segar = $a->fresh() ?? $a;
        $versi = self::versi($segar, $formulir, $elemen);
        $pukul = Waktu::kini()->format('H.i');
        Cache::put(self::kunci($a, $formulir, $elemen), [
            'versi' => $versi, 'sunting' => (string) $r->input('_sunting', ''),
            'nama' => (string) ($r->user()?->name ?? ''), 'pukul' => $pukul,
        ], self::INGAT);

        if (!self::otomatis($r)) return null;

        // Log cukup sekali per sesi mengetik, bukan setiap beberapa detik.
        if (Cache::add(self::kunci($a, $formulir, $elemen).':log:'.$r->user()?->id, 1, self::JEDA_LOG)) {
            ActivityLog::write('Simpan otomatis '.self::LABEL[$formulir].($elemen ? ' elemen '.$elemen : ''), $a->namaTampil(), 'smkp');
        }

        return response()->json(['ok' => true, 'versi' => $versi, 'pukul' => $pukul]);
    }
}
