<?php

namespace App\Support\Meranti;

use App\Models\Meranti\Log;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use RuntimeException;

/**
 * Impor lembar Google Sheets MERANTI ke EQOHSEE.
 *
 * Sumbernya buku kerja yang sama dengan basis data MERANTI versi Google
 * Apps Script: satu lembar per modul ("MERANTI - Stok Bibit", …), baris
 * pertama nama kolom, kolom `id` dan `waktu_input`. Buku kerja itu tiba
 * sebagai .xlsx yang diunggah atau diambil langsung dari tautan Google
 * Sheets yang dibagikan; CSV hasil tombol Export CSV MERANTI juga diterima.
 *
 * Datanya sengaja TIDAK disimpan di repositori: data operasional
 * perusahaan tempatnya di basis data aplikasi, bukan di riwayat Git.
 *
 * Aman diulang: setiap baris membawa `asal_ref` ("stok_bibit#7"), dan
 * impor berikutnya memperbarui baris yang sama, tidak menggandakannya.
 * Lembar Users TIDAK diimpor — akun dan peran dikelola EQOHSEE sendiri,
 * dan hash sandi aplikasi lama tidak boleh ikut berpindah.
 */
final class Impor
{
    /** Lembar yang sengaja tidak dimuat, beserta alasannya. */
    private const DILEWATI = [
        'users' => 'Akun MERANTI lama tidak dipindahkan — akun dan peran dikelola di EQOHSEE (Data Master → Pengguna & Peran).',
    ];

    /**
     * Buku kerja normal: nama lembar → kepala kolom dan baris.
     *
     * @return array<string, array{kepala: list<string>, baris: list<array<string, mixed>>}>
     */
    public static function bacaXlsx(string $jalur): array
    {
        $pembaca = IOFactory::createReader('Xlsx');
        $pembaca->setReadDataOnly(true);
        $buku = $pembaca->load($jalur);

        $keluar = [];
        foreach ($buku->getWorksheetIterator() as $ws) {
            $data = $ws->toArray(null, true, false, false);
            $kepala = array_map(fn ($h) => trim((string) $h), $data[0] ?? []);
            while ($kepala && end($kepala) === '') array_pop($kepala);

            $baris = [];
            foreach (array_slice($data, 1) as $r) {
                if (!array_filter($r, fn ($v) => $v !== null && $v !== '')) continue;
                $d = [];
                foreach ($kepala as $i => $h) if ($h !== '') $d[$h] = $r[$i] ?? null;
                $baris[] = $d;
            }
            $keluar[$ws->getTitle()] = ['kepala' => $kepala, 'baris' => $baris];
        }
        $buku->disconnectWorksheets();

        return $keluar;
    }

    /**
     * CSV ekspor MERANTI ("Progres_Mingguan.csv"): satu modul per berkas,
     * kepala berupa label, kolom pertama "No". Nama modulnya dari nama
     * berkas. Pemisah koma atau titik koma dikenali sendiri.
     *
     * @return array<string, array{kepala: list<string>, baris: list<array<string, mixed>>}>
     */
    public static function bacaCsv(string $jalur, string $namaBerkas): array
    {
        $isi = (string) file_get_contents($jalur);
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        $baris1 = strtok($isi, "\n") ?: '';
        $pisah = substr_count($baris1, ';') > substr_count($baris1, ',') ? ';' : ',';

        $f = fopen('php://memory', 'r+');
        fwrite($f, $isi);
        rewind($f);
        $kepala = array_map(fn ($h) => trim((string) $h), fgetcsv($f, null, $pisah, '"', '') ?: []);
        $baris = [];
        while (($r = fgetcsv($f, null, $pisah, '"', '')) !== false) {
            if (!array_filter($r, fn ($v) => $v !== null && trim((string) $v) !== '')) continue;
            $d = [];
            foreach ($kepala as $i => $h) if ($h !== '') $d[$h] = $r[$i] ?? null;
            $baris[] = $d;
        }
        fclose($f);

        return [pathinfo($namaBerkas, PATHINFO_FILENAME) => ['kepala' => $kepala, 'baris' => $baris]];
    }

    /**
     * Ambil buku kerja dari tautan Google Sheets.
     *
     * Hanya ID lembarnya yang diambil dari isian; alamat unduhannya
     * disusun di sini, jadi isian tidak dapat mengarahkan server ke
     * alamat lain. Lembar yang belum dibagikan "siapa saja yang memiliki
     * tautan" menjawab halaman masuk Google, dan itu dilaporkan begitu.
     */
    public static function unduhTautan(string $tautan): string
    {
        $id = self::idLembar($tautan);
        if (!$id) throw new RuntimeException('Tautan bukan tautan Google Sheets.');

        $jawab = Http::timeout(60)->withHeaders(['Accept' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->get("https://docs.google.com/spreadsheets/d/{$id}/export", ['format' => 'xlsx']);
        $isi = (string) $jawab->body();

        if (!$jawab->successful() || !str_starts_with($isi, 'PK')) {
            throw new RuntimeException('Google Sheets tidak memberikan berkasnya. Bagikan lembarnya sebagai "Siapa saja yang memiliki link — Pelihat", '
                .'atau unduh sebagai .xlsx (File → Download → Microsoft Excel) lalu unggah di sini.');
        }
        if (strlen($isi) > 30 * 1024 * 1024) throw new RuntimeException('Berkas lebih dari 30 MB.');

        $jalur = tempnam(sys_get_temp_dir(), 'meranti').'.xlsx';
        file_put_contents($jalur, $isi);

        return $jalur;
    }

    public static function idLembar(string $tautan): ?string
    {
        $t = trim($tautan);
        if (preg_match('~docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]{20,})~', $t, $m)) return $m[1];

        return preg_match('~^[A-Za-z0-9_-]{25,}$~', $t) ? $t : null;
    }

    /**
     * Pratinjau: lembar mana masuk ke modul mana, berapa barisnya.
     *
     * @return list<array<string, mixed>>
     */
    public static function pratinjau(array $buku): array
    {
        $keluar = [];
        foreach ($buku as $lembar => $isi) {
            $n = count($isi['baris']);
            $tujuan = self::tujuan((string) $lembar);
            if ($n === 0 && $tujuan['jenis'] !== 'modul') continue;
            $keluar[] = ['lembar' => (string) $lembar, 'baris' => $n] + $tujuan;
        }

        return $keluar;
    }

    /** @return array{jenis: string, kunci: ?string, judul: string, ket: ?string} */
    private static function tujuan(string $lembar): array
    {
        $n = Katalog::normalLembar($lembar);
        if (isset(self::DILEWATI[$n])) return ['jenis' => 'lewat', 'kunci' => null, 'judul' => ucfirst($n), 'ket' => self::DILEWATI[$n]];
        if (str_starts_with('log aktivitas', $n) && mb_strlen($n) >= 3) return ['jenis' => 'log', 'kunci' => 'log', 'judul' => 'Log Aktivitas', 'ket' => 'Riwayat aplikasi lama'];
        if ($k = Katalog::modulUntukLembar($lembar)) return ['jenis' => 'modul', 'kunci' => $k, 'judul' => Katalog::modul($k)['judul'], 'ket' => null];

        return ['jenis' => 'asing', 'kunci' => null, 'judul' => $lembar, 'ket' => 'Lembar tidak dikenali — dilewati'];
    }

    /**
     * Muat buku kerja ke satu perusahaan.
     *
     * @return array{lembar: list<array<string, mixed>>, baru: int, diperbarui: int, dilewati: int}
     */
    public static function jalankan(array $buku, ?int $companyId, ?User $oleh, string $sumber): array
    {
        $hasil = [];
        $baru = $diperbarui = $dilewati = 0;

        DB::transaction(function () use ($buku, $companyId, $oleh, &$hasil, &$baru, &$diperbarui, &$dilewati) {
            foreach ($buku as $lembar => $isi) {
                $t = self::tujuan((string) $lembar);
                if ($t['jenis'] !== 'modul' && $t['jenis'] !== 'log') {
                    if ($isi['baris']) {
                        $hasil[] = ['lembar' => (string) $lembar, 'judul' => $t['judul'], 'baru' => 0, 'diperbarui' => 0, 'dilewati' => count($isi['baris']), 'catatan' => [$t['ket']]];
                        $dilewati += count($isi['baris']);
                    }
                    continue;
                }

                $r = $t['jenis'] === 'log'
                    ? self::muatLog($isi['baris'], $companyId)
                    : self::muatModul($t['kunci'], $isi['baris'], $companyId, $oleh);

                $baru += $r['baru'];
                $diperbarui += $r['diperbarui'];
                $dilewati += $r['dilewati'];
                $hasil[] = ['lembar' => (string) $lembar, 'judul' => $t['judul']] + $r;
            }
        });

        Log::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'user_id' => $oleh?->id, 'waktu' => now(), 'pengguna' => $oleh?->name ?? 'sistem', 'aksi' => 'Impor',
            'modul' => 'Impor Google Sheets', 'detail' => "{$sumber} | {$baru} baru, {$diperbarui} diperbarui, {$dilewati} dilewati",
        ]);

        return ['lembar' => $hasil, 'baru' => $baru, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }

    /** @return array{baru: int, diperbarui: int, dilewati: int, catatan: list<string>} */
    private static function muatModul(string $kunci, array $baris, ?int $companyId, ?User $oleh): array
    {
        $m = Katalog::modul($kunci);
        $kelas = $m['kelas'];
        $kolom = collect($m['kolom'])->keyBy('k');
        $peta = [];
        $baru = $diperbarui = $dilewati = 0;
        $catatan = [];

        foreach ($baris as $i => $r) {
            $data = [];
            foreach ($r as $h => $v) {
                $k = $peta[$h] ??= Katalog::kolomUntukKepala($kunci, (string) $h) ?? '';
                if ($k === '' || !$kolom->has($k)) continue;
                $data[$k] = self::nilai($kolom[$k], $v);
            }

            // `id` lembar Apps Script; CSV ekspor MERANTI hanya membawa nomor urut "No".
            $id = Hitung::angkaAtauNull($r['id'] ?? null);
            $no = Hitung::angkaAtauNull($r['No'] ?? null);
            $ref = $kunci.'#'.($id !== null ? (int) $id : 'r'.($no !== null ? (int) $no : $i + 1));

            if ($kunci === 'peta_spasial' && blank($data['nama_fitur'] ?? null)) {
                $dasar = pathinfo((string) ($data['sumber_file'] ?? ''), PATHINFO_FILENAME);
                $data['nama_fitur'] = trim(($dasar !== '' ? $dasar : 'Fitur').' · #'.($id !== null ? (int) $id : $i + 1));
            }
            if ($kunci === 'peta_spasial' && blank($data['luas_ha'] ?? null) && filled($data['geojson'] ?? null)) {
                $g = json_decode((string) $data['geojson'], true);
                if (($g['type'] ?? null) === 'Polygon' && isset($g['coordinates'][0])) $data['luas_ha'] = round(Geo::luasHa($g['coordinates'][0]), 6);
            }
            if (($m['hitung'] ?? null) === 'matrik17' && isset($data['nilai_penatagunaan'])) {
                $data = array_merge($data, Hitung::matrik17($data['nilai_penatagunaan'], $data['nilai_revegetasi'] ?? 0, $data['nilai_penyelesaian'] ?? 0));
            }

            $kurang = $kolom->filter(fn ($k) => ($k['wajib'] ?? false) && $k['t'] !== 'hitung' && blank($data[$k['k']] ?? null))->keys()->all();
            if ($kurang) {
                $dilewati++;
                if (count($catatan) < 5) $catatan[] = "Baris {$ref} dilewati: ".implode(', ', array_map(fn ($k) => $kolom[$k]['l'], $kurang)).' kosong.';
                continue;
            }

            $waktu = self::waktu($r['waktu_input'] ?? null);
            $ada = $kelas::withoutGlobalScopes()->where('company_id', $companyId)->where('asal_ref', $ref)->first();

            if ($ada) {
                $ada->fill($data);
                if ($ada->isDirty()) { $ada->save(); $diperbarui++; }
            } else {
                $b = new $kelas(array_merge($data, ['company_id' => $companyId, 'user_id' => $oleh?->id, 'asal_ref' => $ref]));
                if ($waktu) { $b->created_at = $waktu; $b->updated_at = $waktu; }
                $b->save();
                $baru++;
            }
        }

        return ['baru' => $baru, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati, 'catatan' => $catatan];
    }

    /** @return array{baru: int, diperbarui: int, dilewati: int, catatan: list<string>} */
    private static function muatLog(array $baris, ?int $companyId): array
    {
        $baru = $dilewati = 0;
        foreach ($baris as $i => $r) {
            $waktu = self::waktu($r['waktu'] ?? null);
            if (!$waktu || blank($r['aksi'] ?? null)) { $dilewati++; continue; }

            // Urutan baris, bukan id: lembar log lama memuat id kembar.
            $ref = 'log#r'.($i + 1);
            $ada = Log::withoutGlobalScopes()->where('company_id', $companyId)->where('asal_ref', $ref)->exists();
            if ($ada) continue;

            Log::withoutGlobalScopes()->create([
                'company_id' => $companyId, 'waktu' => $waktu, 'pengguna' => mb_substr((string) ($r['user'] ?? ''), 0, 100) ?: null,
                'aksi' => mb_substr((string) $r['aksi'], 0, 20), 'modul' => mb_substr((string) ($r['modul'] ?? ''), 0, 100) ?: null,
                'detail' => isset($r['detail']) && $r['detail'] !== null ? mb_substr((string) $r['detail'], 0, 5000) : null, 'asal_ref' => $ref,
            ]);
            $baru++;
        }

        return ['baru' => $baru, 'diperbarui' => 0, 'dilewati' => $dilewati, 'catatan' => []];
    }

    /** Nilai sel → nilai kolom menurut tipenya di katalog. */
    public static function nilai(array $kolom, mixed $v): mixed
    {
        if ($v === null) return null;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') return null;
        }

        switch ($kolom['t']) {
            case 'angka':
            case 'hitung':
                if ($kolom['t'] === 'hitung' && !isset($kolom['des'])) return mb_substr((string) $v, 0, 20);
                // Kolom bilangan bulat (stok, jumlah batang): "1.250" pasti seribu dua ratus
                // lima puluh — bibit tidak dihitung pecahan, jadi titiknya pemisah ribuan.
                if (($kolom['des'] ?? 2) === 0 && is_string($v) && preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) $v = str_replace('.', '', $v);
                $a = Hitung::angkaAtauNull($v);

                return $a === null ? null : (($kolom['des'] ?? 2) === 0 ? (int) round($a) : $a);
            case 'tanggal':
                return self::tanggal($v);
            case 'pilih':
                foreach ($kolom['opsi'] as $o) if (mb_strtolower($o) === mb_strtolower((string) $v)) return $o;

                return mb_substr((string) $v, 0, 60);
            default:
                if (is_float($v) && floor($v) === $v) $v = (int) $v;

                return mb_substr((string) $v, 0, $kolom['panjang'] ?? ($kolom['t'] === 'tautan' ? 500 : 190));
        }
    }

    public static function tanggal(mixed $v): ?string
    {
        return self::waktu($v)?->toDateString();
    }

    private static function waktu(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') return null;

        try {
            if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) {
                $n = (float) $v;

                return $n > 20000 && $n < 80000 ? Carbon::instance(TanggalExcel::excelToDateTimeObject($n)) : null;
            }
            $s = trim((string) $v);
            if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})~', $s, $m)) return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]);

            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
