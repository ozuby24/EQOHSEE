<?php

namespace App\Http\Controllers;

use App\Models\SmkpAudit;
use App\Rules\LampiranBukti;
use App\Services\Asisten\{EkstrakTeks, GagalAsisten, Lampiran, LayananAsisten, Rapi};
use App\Services\Asisten\Tugas\EkstrakRencana;
use App\Support\BerkasUnggah;
use Illuminate\Http\{JsonResponse, Request, Response, UploadedFile};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Pakai ulang Rencana Audit: salin dari audit lain, templat CSV metode &
 * sampel per kriteria, impor CSV/XLSX, dan ekstraksi dokumen DOCX/PDF
 * lama dengan Asisten AI.
 *
 * Tidak ada yang tersimpan di sini — hasilnya diterapkan ke formulir
 * Rencana Audit, diperiksa auditor, lalu disimpan lewat simpanRencana
 * seperti isian biasa. Audit sumber dibatasi global scope MilikPerusahaan,
 * jadi audit di luar jangkauan pengguna tidak terdaftar dan dijawab 404.
 */
class RencanaImporController extends SmkpController
{
    public const MAKS_KB = 10240;

    /** Panjang metode/sampel per kriteria — sama dengan validasi simpanRencana. */
    public const MAKS_SEL = 300;

    /** Isian teks Rencana Audit yang layak dipakai ulang tahun berikutnya. */
    private const TEKS = ['metode', 'sampel', 'tujuan', 'kriteria', 'ruang_lingkup', 'pengecualian', 'dokumentasi', 'sumberdaya', 'distribusi', 'jenis_audit'];

    private const KOLOM_TIM    = ['nama', 'peran', 'registrasi', 'lingkup'];
    private const KOLOM_RISIKO = ['kegiatan', 'risiko', 'nilai'];

    private const PESAN_BERKAS = [
        'berkas.required' => 'Pilih berkas yang akan dibaca.',
        'berkas.file'     => 'Berkas gagal terunggah — periksa koneksi, lalu coba lagi.',
        'berkas.uploaded' => 'Berkas gagal terunggah — periksa koneksi, lalu coba lagi.',
        'berkas.max'      => 'Berkas lebih dari 10 MB.',
    ];

    /* ---------- Daftar audit lain yang Rencana Audit-nya dapat disalin ---------- */
    public function sumber(Request $request, SmkpAudit $smkp): JsonResponse
    {
        $daftar = SmkpAudit::query()
            ->whereKeyNot($smkp->id)
            ->where(fn ($q) => $q->whereNotNull('rencana')->orWhereNotNull('risiko'))
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$smkp->company_id])
            ->orderByDesc('tahun')->orderByDesc('id')
            ->limit(200)
            ->with('company:id,name')
            ->get()
            ->filter(fn (SmkpAudit $a) => $this->adaIsi($a))
            ->take(50)
            ->map(fn (SmkpAudit $a) => $this->ringkasSumber($a, $smkp))
            ->values();

        return response()->json(['sumber' => $daftar, 'total' => count($this->kodeSah())]);
    }

    /* ---------- Isi Rencana Audit lain yang dipakai ulang ---------- */
    public function salin(Request $request, SmkpAudit $smkp, SmkpAudit $sumber): JsonResponse
    {
        abort_if($sumber->is($smkp), 404);
        $sumber->load('company:id,name');

        $r  = (array) ($sumber->rencana ?? []);
        $rk = (array) ($sumber->risiko ?? []);

        // Nomor formulir, tanggal, susunan acara, alamat, dan pengesahan
        // melekat pada pelaksanaan audit sumber — sengaja tidak ikut.
        $isi = ['metode_sampel' => (object) $this->metodeSampel($r)];
        foreach (self::TEKS as $k) $isi[$k] = trim((string) ($r[$k] ?? ''));
        $isi['tugas']  = $this->tabelBersih($r['tugas'] ?? [], self::KOLOM_TIM);
        $isi['risiko'] = [
            'present' => $this->tabelBersih($rk['present'] ?? [], self::KOLOM_RISIKO),
            'future'  => $this->tabelBersih($rk['future'] ?? [], self::KOLOM_RISIKO),
        ];

        return response()->json(['sumber' => $this->ringkasSumber($sumber, $smkp), 'isi' => $isi]);
    }

    /* ---------- Templat CSV metode & sampel, berisi nilai tersimpan ---------- */
    public function templat(Request $request, SmkpAudit $smkp): Response
    {
        $ms = $this->metodeSampel((array) ($smkp->rencana ?? []));

        $h = fopen('php://temp', 'r+');
        // BOM agar Excel membaca UTF-8; titik koma = pemisah daftar Excel berlokal Indonesia.
        fwrite($h, "\xEF\xBB\xBFKode;Kriteria;Metode Audit;Sampel\r\n");
        foreach ($this->daftarSubElemen() as $e) {
            foreach ($e['sub'] as $s) {
                fputcsv($h, [
                    $s['kode'], $s['nama'],
                    self::selAman($ms[$s['kode']]['metode'] ?? ''),
                    self::selAman($ms[$s['kode']]['sampel'] ?? ''),
                ], ';', '"', '', "\r\n");
            }
        }
        rewind($h);
        $isi = (string) stream_get_contents($h);
        fclose($h);

        $nama = 'metode-sampel-'.(Str::slug($smkp->namaTampil()) ?: 'audit').'.csv';

        return response($isi, 200, [
            'Content-Type'           => 'text/csv; charset=UTF-8',
            'Content-Disposition'    => HeaderUtils::makeDisposition('attachment', $nama, Str::ascii($nama)),
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ---------- Impor tabel CSV/XLSX → pratinjau metode & sampel ---------- */
    public function impor(Request $request, SmkpAudit $smkp): JsonResponse
    {
        $request->validate(['berkas' => ['required', 'file', 'max:'.self::MAKS_KB]], self::PESAN_BERKAS);

        /** @var UploadedFile $f */
        $f = $request->file('berkas');
        $ext = strtolower($f->getClientOriginalExtension());

        // Dokumen naratif tidak punya tabel yang dapat dibaca pasti — itu tugas Asisten AI.
        if (in_array($ext, ['pdf', 'docx'], true)) {
            return response()->json([
                'pesan' => 'Dokumen DOCX/PDF dibaca dengan Asisten AI. Pilih berkasnya lagi saat Asisten AI aktif, atau simpan tabel metode & sampelnya sebagai CSV/XLSX.',
                'jenis' => 'ai',
            ], 422);
        }

        $request->validate([
            'berkas' => ['extensions:csv,txt,xlsx', ...($ext === 'xlsx' ? [new LampiranBukti()] : [])],
        ], self::PESAN_BERKAS + ['berkas.extensions' => 'Berkas impor harus CSV, TXT, atau XLSX.']);

        $tabel = EkstrakTeks::tabel((string) $f->getRealPath(), $ext);
        if (!$tabel) {
            return response()->json(['pesan' => 'Isi berkas tidak dapat dibaca. Pastikan berkas tidak berkata sandi, atau pakai templat CSV dari tombol Unduh templat.', 'jenis' => 'kosong'], 422);
        }

        ['baris' => $baris, 'dilewati' => $dilewati, 'kosong' => $kosong] = $this->uraiTabel($tabel);
        if (!$baris) {
            return response()->json([
                'pesan' => 'Tidak ada metode atau sampel dengan kode sub-elemen yang dikenali (mis. I.1, II.3). Pakai templat CSV dari tombol Unduh templat.',
                'jenis' => 'kosong',
            ], 422);
        }

        return response()->json([
            'baris'    => $baris,
            'dilewati' => $dilewati,
            'kosong'   => $kosong,
            'sumber'   => 'tabel',
            'berkas'   => BerkasUnggah::namaAman($f->getClientOriginalName(), $ext, 'impor'),
        ]);
    }

    /* ---------- Susunan kegiatan lewat Excel (port DBEST #17) ---------- */

    /** Kolom templat susunan kegiatan, urut seperti formulir. */
    private const KEPALA_SUSUNAN = [
        'tanggal'  => 'Tanggal (YYYY-MM-DD)',
        'waktu'    => 'Waktu',
        'metode'   => 'Metode',
        'area'     => 'Area',
        'auditi'   => 'Fungsi / Jabatan / Departemen',
        'kriteria' => 'Kriteria (mis. I.1, III.2.1)',
        'kegiatan' => 'Kegiatan lain',
        'auditor'  => 'Auditor',
    ];

    /** Kata kunci kepala kolom → kolom susunan (urutan bebas pada berkas unggahan). */
    private const KENAL_SUSUNAN = [
        'tanggal' => '/tanggal|tgl|hari/i', 'waktu' => '/waktu|jam/i', 'metode' => '/metode/i', 'area' => '/area|lokasi/i',
        'auditi' => '/fungsi|jabatan|departemen|auditi/i', 'kriteria' => '/kriteria|elemen/i', 'kegiatan' => '/kegiatan|agenda/i',
        'auditor' => '/auditor/i',
    ];

    public function templatSusunan(Request $request, SmkpAudit $smkp)
    {
        $buku = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $buku->getActiveSheet()->setTitle('Susunan Kegiatan');
        $ws->fromArray([array_values(self::KEPALA_SUSUNAN)], null, 'A1');

        $isi = (array) ($smkp->rencana['susunan'] ?? []);
        $baris = $isi ? array_map(fn ($b) => array_map(fn ($k) => (string) (((array) $b)[$k] ?? ''), array_keys(self::KEPALA_SUSUNAN)), $isi)
            : [[(string) ($smkp->rencana['tanggal_mulai'] ?? ''), '08.00–10.00', 'Peninjauan Dokumen, Rekaman, Wawancara',
                'Kantor', 'Kepala Teknik Tambang (KTT)', 'I.1, I.5, III.3', '', '']];
        $ws->fromArray($baris, null, 'A2');
        $ws->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A', 'H') as $c) $ws->getColumnDimension($c)->setWidth($c === 'G' ? 40 : 26);

        $nama = 'susunan-kegiatan-'.(Str::slug($smkp->namaTampil()) ?: 'audit').'.xlsx';

        return response()->streamDownload(fn () => (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($buku))->save('php://output'), $nama, [
            'Content-Type'           => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Unggahan CSV/XLSX → baris susunan kegiatan (belum disimpan; diterapkan
     * ke formulir lalu disimpan auditor). Kepala kolom dikenali dari
     * namanya; tanpa kepala, urutan templat yang dipakai.
     */
    public function imporSusunan(Request $request, SmkpAudit $smkp): JsonResponse
    {
        $request->validate(['berkas' => ['required', 'file', 'max:'.self::MAKS_KB]], self::PESAN_BERKAS);
        $f = $request->file('berkas');
        $ext = strtolower($f->getClientOriginalExtension());
        $request->validate([
            'berkas' => ['extensions:csv,txt,xlsx', ...($ext === 'xlsx' ? [new LampiranBukti()] : [])],
        ], self::PESAN_BERKAS + ['berkas.extensions' => 'Berkas susunan kegiatan harus XLSX atau CSV.']);

        $tabel = EkstrakTeks::tabel((string) $f->getRealPath(), $ext);
        if (!$tabel) {
            return response()->json(['pesan' => 'Isi berkas tidak dapat dibaca. Pakai templat dari tombol Unduh templat.', 'jenis' => 'kosong'], 422);
        }

        $kolom = null;
        $mulai = 0;
        foreach (array_slice($tabel, 0, 10) as $i => $row) {
            $peta = [];
            foreach ($row as $j => $sel) {
                foreach (self::KENAL_SUSUNAN as $k => $pola) {
                    if (!isset($peta[$k]) && preg_match($pola, (string) $sel)) { $peta[$k] = $j; break; }
                }
            }
            if (count($peta) >= 3) { [$kolom, $mulai] = [$peta, $i + 1]; break; }
        }
        $kolom ??= array_flip(array_keys(self::KEPALA_SUSUNAN));

        $rows = [];
        foreach (array_slice($tabel, $mulai) as $row) {
            $b = [];
            foreach (array_keys(self::KEPALA_SUSUNAN) as $k) $b[$k] = isset($kolom[$k]) ? trim((string) ($row[$kolom[$k]] ?? '')) : '';
            $b['tanggal'] = self::tanggalSel($b['tanggal']);
            $rows[] = $b;
        }
        $baris = \App\Support\SmkpSusunan::bersih($rows);
        if (!$baris) {
            return response()->json(['pesan' => 'Tidak ada baris susunan kegiatan yang terisi. Pakai templat dari tombol Unduh templat.', 'jenis' => 'kosong'], 422);
        }

        return response()->json(['baris' => $baris, 'berkas' => BerkasUnggah::namaAman($f->getClientOriginalName(), $ext, 'susunan')]);
    }

    /** Tanggal sel Excel: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, atau nomor seri Excel. */
    private static function tanggalSel(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }

        return '';
    }

    /* ---------- Ekstraksi DOCX/PDF/XLSX/CSV dengan Asisten AI (routes/asisten.php) ---------- */
    public function ekstrak(Request $request, LayananAsisten $asisten, SmkpAudit $smkp): JsonResponse
    {
        $asisten->pastikanAktif();

        $f = $request->file('berkas');
        $ext = $f instanceof UploadedFile ? strtolower($f->getClientOriginalExtension()) : '';
        $request->validate([
            'berkas' => ['required', 'file', 'max:'.self::MAKS_KB, 'extensions:pdf,docx,xlsx,csv,txt',
                ...(in_array($ext, ['pdf', 'docx', 'xlsx'], true) ? [new LampiranBukti()] : [])],
        ], self::PESAN_BERKAS + ['berkas.extensions' => 'Asisten AI membaca berkas PDF, DOCX, XLSX, CSV, atau TXT.']);

        /** @var UploadedFile $f */
        $nama = BerkasUnggah::namaAman($f->getClientOriginalName(), $ext, 'rencana-audit');
        $lampiran = Lampiran::dariBerkas((string) $f->getRealPath(), $nama, (string) $f->getMimeType());
        if (!$lampiran) {
            throw GagalAsisten::kosong('Isi berkas tidak dapat dibaca. Pastikan dokumen tidak berkata sandi, atau simpan tabel metode & sampelnya sebagai CSV/XLSX.');
        }

        $smkp->load('company');
        $tugas = new EkstrakRencana($smkp, $lampiran);
        $jawaban = $asisten->jalankan($tugas->permintaan(), 'Rencana Audit '.$smkp->namaTampil());

        return response()->json($tugas->rapikan($jawaban));
    }

    /* ---------- bantu ---------- */

    /** @return list<string> kode sub-elemen sah, urut Lampiran II */
    private function kodeSah(): array
    {
        return array_column(array_merge(...array_column($this->daftarSubElemen(), 'sub')), 'kode');
    }

    /** Metode & sampel per sub-elemen yang sah dan berisi. @return array<string,array{metode:string,sampel:string}> */
    private function metodeSampel(array $r): array
    {
        $sah = array_flip($this->kodeSah());
        $out = [];
        foreach ((array) ($r['metode_sampel'] ?? []) as $kode => $v) {
            $m  = trim((string) (((array) $v)['metode'] ?? ''));
            $sp = trim((string) (((array) $v)['sampel'] ?? ''));
            if (isset($sah[(string) $kode]) && ($m !== '' || $sp !== '')) $out[(string) $kode] = ['metode' => $m, 'sampel' => $sp];
        }

        return $out;
    }

    /** Rencana yang pernah diisi — kosong tidak berguna sebagai acuan. */
    private function adaIsi(SmkpAudit $a): bool
    {
        $r = (array) ($a->rencana ?? []);
        if ($this->metodeSampel($r) || $this->tabelBersih($r['tugas'] ?? [], self::KOLOM_TIM)) return true;
        foreach (self::TEKS as $k) {
            if (trim((string) ($r[$k] ?? '')) !== '') return true;
        }
        $rk = (array) ($a->risiko ?? []);

        return (bool) ($this->tabelBersih($rk['present'] ?? [], self::KOLOM_RISIKO) || $this->tabelBersih($rk['future'] ?? [], self::KOLOM_RISIKO));
    }

    private function ringkasSumber(SmkpAudit $a, SmkpAudit $tujuan): array
    {
        $r  = (array) ($a->rencana ?? []);
        $rk = (array) ($a->risiko ?? []);

        return [
            'id'             => $a->id,
            'judul'          => $a->namaTampil(),
            'perusahaan'     => $a->company?->name ?? '—',
            'tahun'          => $a->tahun,
            'status'         => $a->status,
            'labelStatus'    => SmkpAudit::STATUS[$a->status] ?? $a->status,
            'terisi'         => count($this->metodeSampel($r)),
            'risiko'         => count($this->tabelBersih($rk['present'] ?? [], self::KOLOM_RISIKO)) + count($this->tabelBersih($rk['future'] ?? [], self::KOLOM_RISIKO)),
            'tim'            => count($this->tabelBersih($r['tugas'] ?? [], self::KOLOM_TIM)),
            'samaPerusahaan' => $a->company_id === $tujuan->company_id,
        ];
    }

    /** Baris tabel dengan kolom tetap, dirapikan; baris yang seluruhnya kosong dibuang. */
    private function tabelBersih(mixed $rows, array $kolom): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $b = [];
            foreach ($kolom as $k) $b[$k] = trim((string) (is_array($row) && is_scalar($row[$k] ?? null) ? $row[$k] : ''));
            if (implode('', $b) !== '') $out[] = $b;
        }

        return $out;
    }

    /**
     * Baris metode & sampel dari tabel unggahan. Baris kepala dikenali dari
     * namanya (Kode/Kriteria/Metode/Sampel, urutan bebas); tanpa kepala,
     * kolom pertama kode lalu metode dan sampel — atau kode, kriteria,
     * metode, sampel bila tabelnya empat kolom.
     *
     * @param  list<list<string>> $tabel
     * @return array{baris: list<array{kode:string,metode:string,sampel:string}>, dilewati:int, kosong:int}
     */
    private function uraiTabel(array $tabel): array
    {
        $urutan = array_flip($this->kodeSah());

        $kolom = null;
        $mulai = 0;
        foreach (array_slice($tabel, 0, 10) as $i => $row) {
            if ($k = self::kolomKepala($row, $urutan)) {
                [$kolom, $mulai] = [$k, $i + 1];
                break;
            }
        }
        if (!$kolom) {
            $lebar = 0;
            foreach ($tabel as $row) {
                foreach ($row as $i => $x) if (trim((string) $x) !== '') $lebar = max($lebar, $i + 1);
            }
            $kolom = $lebar >= 4
                ? ['kode' => 0, 'kriteria' => 1, 'metode' => 2, 'sampel' => 3]
                : ['kode' => 0, 'kriteria' => null, 'metode' => 1, 'sampel' => 2];
        }

        $hasil = [];
        $dilewati = $kosong = 0;
        foreach (array_slice($tabel, $mulai) as $row) {
            $m  = $kolom['metode'] !== null ? self::selBersih($row[$kolom['metode']] ?? '') : '';
            $sp = $kolom['sampel'] !== null ? self::selBersih($row[$kolom['sampel']] ?? '') : '';
            $kode = ($kolom['kode'] !== null ? self::kodeDari($row[$kolom['kode']] ?? '') : null)
                ?? ($kolom['kriteria'] !== null ? self::kodeDari($row[$kolom['kriteria']] ?? '') : null);

            // Baris judul elemen atau baris kosong tidak dihitung; yang berisi
            // metode/sampel tetapi kodenya tak dikenal atau ganda dilaporkan.
            if ($kode === null || !isset($urutan[$kode]) || isset($hasil[$kode])) {
                if ($m !== '' || $sp !== '') $dilewati++;
                continue;
            }
            if ($m === '' && $sp === '') {
                $kosong++;
                continue;
            }
            $hasil[$kode] = ['kode' => $kode, 'metode' => $m, 'sampel' => $sp];
        }

        uksort($hasil, fn ($a, $b) => $urutan[$a] <=> $urutan[$b]);

        return ['baris' => array_values($hasil), 'dilewati' => $dilewati, 'kosong' => $kosong];
    }

    /**
     * Indeks kolom bila $row adalah baris kepala: perlu kolom kode atau
     * kriteria, dan kolom metode atau sampel — serta tidak berisi kode
     * sub-elemen (agar baris data tidak terbaca sebagai kepala).
     *
     * @return array{kode:?int,kriteria:?int,metode:?int,sampel:?int}|null
     */
    private static function kolomKepala(array $row, array $urutan): ?array
    {
        $k = ['kode' => null, 'kriteria' => null, 'metode' => null, 'sampel' => null];
        foreach ($row as $i => $sel) {
            if (isset($urutan[(string) self::kodeDari($sel)])) return null;
            $t = mb_strtolower(trim((string) $sel));
            $kunci = match (true) {
                $t === ''                                                 => null,
                str_contains($t, 'metode') || str_contains($t, 'method') => 'metode',
                str_contains($t, 'sampel') || str_contains($t, 'sample') => 'sampel',
                str_starts_with($t, 'kode') || $t === 'code'              => 'kode',
                str_contains($t, 'kriteria') || str_contains($t, 'elemen') => 'kriteria',
                default                                                    => null,
            };
            if ($kunci !== null && $k[$kunci] === null) $k[$kunci] = $i;
        }

        $adaKode = $k['kode'] !== null || $k['kriteria'] !== null;
        $adaIsi  = $k['metode'] !== null || $k['sampel'] !== null;

        return $adaKode && $adaIsi ? $k : null;
    }

    /**
     * Kode sub-elemen dari isi sel: "I.1", " ii . 3 ", "I.01", "Sub-elemen
     * IV.10", atau kode yang diikuti nama ("I.1 Penyusunan Kebijakan").
     * Kode butir (II.2.1) bukan kode sub-elemen.
     */
    private static function kodeDari(mixed $sel): ?string
    {
        if (!is_scalar($sel)) return null;
        $t = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $sel)));
        $t = (string) preg_replace('/^(SUB[- ]?ELEMEN|ELEMEN|KRITERIA)\s*/u', '', $t);
        if (!preg_match('/^([IVX]+)\s*\.\s*(\d{1,2})(?!\d)(?!\s*\.\s*\d)/', $t, $m)) return null;

        return $m[1].'.'.(int) $m[2];
    }

    /** Isi sel menjadi satu baris yang muat di kolom metode/sampel. */
    private static function selBersih(mixed $v): string
    {
        $t = is_scalar($v) ? (string) preg_replace('/\s*[\r\n]+\s*/u', '; ', trim((string) $v)) : '';
        // Penanda "'" dari templat (pencegah rumus spreadsheet) dibuang lagi.
        if (preg_match("/^'[=+\-@]/", $t)) $t = substr($t, 1);

        return Rapi::teks($t, self::MAKS_SEL);
    }

    /** Sel yang diawali = + - @ diberi "'" agar tidak dijalankan sebagai rumus oleh spreadsheet. */
    private static function selAman(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
