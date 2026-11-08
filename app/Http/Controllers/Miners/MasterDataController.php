<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Models\Miners\{Departemen, HasilMcu, Jabatan, JenisUnit, Kendaraan, Pjo, SubKendaraan, Subkontraktor};
use App\Support\Miners\{Acuan, AksesMiners};
use App\Support\Perusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Master Data Miners — layar Master Unit, Type Unit SIMPER, Sub Unit,
 * Jabatan, dan seterusnya dari bilah samping Safe Track.
 *
 * SATU CONTROLLER UNTUK DELAPAN DAFTAR. Kedelapannya berbentuk sama —
 * nama, satu-dua kolom tambahan, aktif atau tidak — dan Safe Track
 * menuliskannya delapan kali dengan delapan tombol simpan yang perlahan
 * berbeda perilakunya. Yang berbeda di sini hanya definisi medannya.
 *
 * TIDAK MENGHAPUS YANG MASIH DIPAKAI. Baris yang masih ditunjuk pekerja,
 * hasil MCU, atau unit SIMPER dinonaktifkan — hilang dari daftar pilih,
 * tetap terbaca pada dokumen lama. Menghapusnya membuat kartu yang sudah
 * terbit kehilangan nama unitnya tanpa satu galat pun.
 *
 * DAFTAR BERSAMA DIJAGA. Baris tanpa pemilik adalah daftar awal yang
 * dipakai setiap perusahaan; hanya administrator yang mengubahnya. HSE
 * sebuah IUP menambah dan menyunting baris milik perusahaannya sendiri.
 */
class MasterDataController extends Controller
{
    /**
     * Definisi tiap daftar.
     *
     * medan: [kunci, label, jenis (teks|angka|pilih|centang|surel|induk), wajib, opsi?]
     */
    private const JENIS = [
        'unit' => [
            'model' => Kendaraan::class, 'judul' => 'Data Master Unit', 'tabel' => 'Unit Table',
            'tombol' => 'add Unit Simper', 'remah' => 'Master Unit',
            'medan' => [['nama', 'Nama Unit', 'teks', true], ['kode', 'Kode', 'teks', false],
                        ['kelas_simpol', 'Kelas SIM Kepolisian', 'pilih', false, 'simpol'],
                        ['wajib_sio', 'Wajib SIO', 'centang', false]],
            'dipakai' => [['mnr_simper_unit', 'kendaraan_id'], ['mnr_simper_ajuan_unit', 'kendaraan_id'], ['mnr_sub_kendaraan', 'kendaraan_id']],
        ],
        'type-unit' => [
            'model' => JenisUnit::class, 'judul' => 'Data Type Unit SIMPER', 'tabel' => 'Type Unit Table',
            'tombol' => 'add Type Unit', 'remah' => 'Type Unit SIMPER',
            'medan' => [['nama', 'Type Unit', 'teks', true]],
            'dipakai' => [['mnr_simper_unit', 'jenis_unit_id'], ['mnr_simper_ajuan_unit', 'jenis_unit_id'],
                          ['mnr_simper_unit_jenis', 'jenis_unit_id'], ['mnr_simper_ajuan_unit_jenis', 'jenis_unit_id']],
        ],
        'sub-unit' => [
            'model' => SubKendaraan::class, 'judul' => 'Data Sub Unit', 'tabel' => 'Sub Unit Table',
            'tombol' => 'add Sub Unit', 'remah' => 'Sub Unit',
            'medan' => [['kendaraan_id', 'Master Unit', 'induk', true], ['nama', 'Nama Sub Unit', 'teks', true]],
            'dipakai' => [],
        ],
        'jabatan' => [
            'model' => Jabatan::class, 'judul' => 'Data Jabatan', 'tabel' => 'Jabatan Table',
            'tombol' => 'add Jabatan', 'remah' => 'Jabatan',
            'medan' => [['nama', 'Jabatan', 'teks', true]],
            'dipakai' => [['mnr_pekerja', 'jabatan_id']],
        ],
        'departemen' => [
            'model' => Departemen::class, 'judul' => 'Data Departemen', 'tabel' => 'Departemen Table',
            'tombol' => 'add Departemen', 'remah' => 'Departemen',
            'medan' => [['nama', 'Departemen', 'teks', true], ['kode', 'Kode', 'teks', false]],
            'dipakai' => [['mnr_pekerja', 'departemen_id'], ['mnr_mcu_orang', 'departemen_id']],
        ],
        'hasil-mcu' => [
            'model' => HasilMcu::class, 'judul' => 'Data Hasil MCU', 'tabel' => 'Hasil MCU Table',
            'tombol' => 'add Hasil MCU', 'remah' => 'Hasil MCU',
            'medan' => [['nama', 'Hasil MCU', 'teks', true], ['nilai', 'Nilai', 'angka', true],
                        ['layak', 'Layak bekerja (Mine Permit boleh terbit)', 'centang', false]],
            'dipakai' => [['mnr_mcu_orang', 'hasil_id']],
        ],
        'pjo' => [
            'model' => Pjo::class, 'judul' => 'Data PJO', 'tabel' => 'PJO Table',
            'tombol' => 'add PJO', 'remah' => 'PJO',
            'medan' => [['nama', 'Nama PJO', 'teks', true], ['jabatan', 'Jabatan', 'teks', false],
                        ['email', 'Email PJO', 'surel', false]],
            'dipakai' => [],
        ],
        'subkontraktor' => [
            'model' => Subkontraktor::class, 'judul' => 'Data Subkontraktor', 'tabel' => 'Subkontraktor Table',
            'tombol' => 'add Subkontraktor', 'remah' => 'Subkontraktor',
            'medan' => [['nama', 'Nama Subkontraktor', 'teks', true], ['kode', 'Kode', 'teks', false]],
            'dipakai' => [['mnr_pekerja', 'subkontraktor_id']],
        ],
    ];

    /** Kunci daftar yang punya rute — dipakai routes/web.php. */
    public const DAFTAR = ['unit', 'type-unit', 'sub-unit', 'jabatan', 'departemen', 'hasil-mcu', 'pjo', 'subkontraktor'];

    public function index(Request $r)
    {
        $u = $r->user();
        AksesMiners::tegakkan(AksesMiners::kelola($u), 'Master data hanya dikelola admin atau HSE IUP.');

        $jenis = $this->jenis($r);
        $def   = self::JENIS[$jenis];
        $model = new ($def['model']);
        $tabel = $model->getTable();

        $q = $def['model']::query();
        if (Schema::hasColumn($tabel, 'urutan')) $q->orderBy('urutan');
        $baris = $q->orderBy('nama')->get();

        $pakai = $this->hitungPakai($def, $baris->pluck('id')->all());
        $induk = $jenis === 'sub-unit' ? Kendaraan::query()->orderBy('nama')->pluck('nama', 'id') : collect();
        $milik = \App\Models\Company::query()->whereIn('id', $baris->pluck('company_id')->filter()->unique())->pluck('name', 'id');

        return Inertia::render('Miners/Master', [
            'judul'  => $def['judul'],
            'kop'    => false,
            'jenis'  => $jenis,
            'tabel'  => $def['tabel'],
            'tombol' => $def['tombol'],
            'remah'  => $def['remah'],
            'pakaiAktif' => Schema::hasColumn($tabel, 'aktif'),

            'medan' => array_map(fn ($m) => [
                'kunci' => $m[0], 'label' => $m[1], 'jenis' => $m[2], 'wajib' => $m[3],
                'opsi'  => match ($m[4] ?? null) {
                    'simpol' => array_map(fn ($k) => ['nilai' => $k, 'label' => 'SIM '.$k], Acuan::KELAS_SIMPOL),
                    default  => $m[2] === 'induk'
                        ? $induk->map(fn ($n, $id) => ['nilai' => $id, 'label' => $n])->values()->all()
                        : [],
                },
            ], $def['medan']),

            'baris' => $baris->map(fn (Model $b) => [
                'id'       => $b->id,
                'nilai'    => collect($def['medan'])->mapWithKeys(fn ($m) => [$m[0] => $b->{$m[0]}])->all(),
                'tampil'   => collect($def['medan'])->mapWithKeys(fn ($m) => [$m[0] => match ($m[2]) {
                    'centang' => $b->{$m[0]} ? 'Ya' : '—',
                    'induk'   => $induk[$b->{$m[0]}] ?? '—',
                    'pilih'   => $b->{$m[0]} ? 'SIM '.$b->{$m[0]} : '—',
                    default   => $b->{$m[0]},
                }])->all(),
                'aktif'    => $b->aktif ?? true,
                'bersama'  => $b->company_id === null,
                'milik'    => $b->company_id ? ($milik[$b->company_id] ?? '—') : 'Bersama',
                'dipakai'  => $pakai[$b->id] ?? 0,
                'boleh'    => $this->bolehUbah($u, $b),
            ])->values(),
        ]);
    }

    public function store(Request $r)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()));

        $jenis = $this->jenis($r);
        $def   = self::JENIS[$jenis];
        $data  = $this->validasi($r, $def);

        /* Admin yang sedang melihat satu perusahaan menambah ke daftar
           perusahaan itu; tanpa pilihan, ke daftar bersama. Pengguna
           lain selalu ke daftar perusahaannya sendiri. */
        $milik = $r->user()->isAdmin() ? Perusahaan::terpilih($r->user()) : $r->user()->company_id;

        $baris = new ($def['model'])($data);
        $baris->company_id = $milik;
        $baris->save();

        return back()->with('sukses', $def['remah'].' "'.$baris->nama.'" ditambahkan.');
    }

    public function update(Request $r, string $id)
    {
        $jenis = $this->jenis($r);
        $def   = self::JENIS[$jenis];
        $baris = $def['model']::query()->findOrFail((int) $id);

        AksesMiners::tegakkan($this->bolehUbah($r->user(), $baris),
            'Daftar bersama hanya dapat diubah administrator.');

        $data = $this->validasi($r, $def);

        if ($r->has('aktif') && Schema::hasColumn($baris->getTable(), 'aktif')) {
            $data['aktif'] = $r->boolean('aktif');
        }

        $baris->update($data);

        return back()->with('sukses', $def['remah'].' "'.$baris->nama.'" diperbarui.');
    }

    public function destroy(Request $r, string $id)
    {
        $jenis = $this->jenis($r);
        $def   = self::JENIS[$jenis];
        $baris = $def['model']::query()->findOrFail((int) $id);

        AksesMiners::tegakkan($this->bolehUbah($r->user(), $baris),
            'Daftar bersama hanya dapat diubah administrator.');

        $dipakai = $this->hitungPakai($def, [$baris->id])[$baris->id] ?? 0;

        if ($dipakai > 0) {
            if (! Schema::hasColumn($baris->getTable(), 'aktif')) {
                return back()->withErrors(['hapus' => $def['remah'].' "'.$baris->nama.'" masih dipakai '.$dipakai.' data.']);
            }

            $baris->update(['aktif' => false]);

            return back()->with('sukses', $def['remah'].' "'.$baris->nama.'" masih dipakai '.$dipakai
                .' data — dinonaktifkan, tidak dihapus.');
        }

        $baris->delete();

        return back()->with('sukses', $def['remah'].' "'.$baris->nama.'" dihapus.');
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function jenis(Request $r): string
    {
        $j = (string) ($r->route()?->defaults['jenis'] ?? '');

        abort_unless(isset(self::JENIS[$j]), 404);

        return $j;
    }

    private function bolehUbah($u, Model $b): bool
    {
        if (! AksesMiners::kelola($u)) return false;
        if ($u->isAdmin()) return true;

        return $b->company_id !== null && (int) $b->company_id === (int) $u->company_id;
    }

    /** @return array<string,mixed> */
    private function validasi(Request $r, array $def): array
    {
        $aturan = [];

        foreach ($def['medan'] as [$k, $label, $jenis, $wajib]) {
            $aturan[$k] = match ($jenis) {
                'angka'   => [$wajib ? 'required' : 'nullable', 'integer', 'min:0', 'max:255'],
                'centang' => ['nullable', 'boolean'],
                'surel'   => [$wajib ? 'required' : 'nullable', 'email', 'max:150'],
                'pilih'   => ['nullable', Rule::in(Acuan::KELAS_SIMPOL)],
                'induk'   => ['required', 'exists:mnr_kendaraan,id'],
                default   => [$wajib ? 'required' : 'nullable', 'string', 'max:'.($k === 'kode' ? 20 : 120)],
            };
        }

        $data = $r->validate($aturan);

        foreach ($def['medan'] as [$k, , $jenis]) {
            if ($jenis === 'centang') $data[$k] = $r->boolean($k);
        }

        return $data;
    }

    /**
     * Berapa data yang masih menunjuk tiap baris.
     *
     * @param  list<int>  $ids
     * @return array<int,int>
     */
    private function hitungPakai(array $def, array $ids): array
    {
        $n = [];

        if ($ids === []) return $n;

        foreach ($def['dipakai'] as [$tabel, $kolom]) {
            if (! Schema::hasTable($tabel)) continue;

            foreach (DB::table($tabel)->whereIn($kolom, $ids)
                         ->selectRaw($kolom.' as k, count(*) as c')->groupBy($kolom)->get() as $b) {
                $n[(int) $b->k] = ($n[(int) $b->k] ?? 0) + (int) $b->c;
            }
        }

        return $n;
    }
}
