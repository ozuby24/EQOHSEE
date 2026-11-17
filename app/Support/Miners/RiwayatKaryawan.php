<?php

namespace App\Support\Miners;

use App\Models\{Company, User};
use App\Models\Miners\{Pekerja, Pelanggaran};
use App\Support\Perusahaan;
use Illuminate\Support\Collection;

/**
 * Riwayat Karyawan lintas perusahaan — bahan pertimbangan rekrutmen bagi
 * pemegang IUP dan mitra kerjanya (port DBEST #22, 16 Nov 2026): mengapa
 * seseorang berhenti dari perusahaan sebelumnya di site, apakah ia layak
 * direkrut kembali, dan catatan pelanggarannya.
 *
 * "Lintas perusahaan" di EQOHSEE berarti lintas SATU GRUP IUP — pemegang
 * IUP beserta seluruh mitra (IUJP) di bawahnya — bukan lintas pelanggan.
 * Aplikasi ini dipakai banyak pemegang IUP; riwayat karyawan satu site
 * tidak pernah terbaca pemegang IUP lain.
 *
 * Aturan (mengikuti DBEST):
 *  - Semua pengguna dalam grup dapat mencari dan melihat daftar.
 *  - Status/alasan dan catatan pelanggaran hanya diubah perusahaan pemilik
 *    data (pendaftar pekerja, atau HSE perusahaannya) atau pengelola
 *    (administrator / HSE IUP — AksesMiners::kelola).
 *  - Catatan dari mitra MENUNGGU verifikasi dan baru terlihat perusahaan
 *    lain setelah diverifikasi pengelola; catatan pengelola langsung
 *    terverifikasi. Yang ditolak hanya terlihat pemilik beserta alasannya.
 *    Catatan terverifikasi hanya diubah/dihapus pengelola.
 *  - NIK pekerja perusahaan lain disamarkan; data kesehatan tidak pernah
 *    ditampilkan di sini.
 */
final class RiwayatKaryawan
{
    public const ALASAN = [
        'resign'          => 'Resign (mengundurkan diri)',
        'habis_kontrak'   => 'Habis kontrak',
        'phk_pelanggaran' => 'PHK karena pelanggaran',
        'phk_lain'        => 'PHK non-pelanggaran (efisiensi/kinerja)',
        'mangkir'         => 'Mangkir / meninggalkan pekerjaan',
        'mutasi'          => 'Mutasi / pindah site',
        'pensiun'         => 'Pensiun',
        'meninggal'       => 'Meninggal dunia',
        'lainnya'         => 'Lainnya',
    ];

    /** Alasan yang wajib disertai keterangan agar tidak menyesatkan pembaca. */
    public const ALASAN_WAJIB_KETERANGAN = ['phk_pelanggaran', 'phk_lain', 'mangkir', 'lainnya'];

    public const REKOMENDASI = [
        'layak'         => 'Layak direkrut kembali',
        'pertimbangkan' => 'Pertimbangkan (ada catatan)',
        'tidak'         => 'Tidak disarankan direkrut',
    ];

    public const MAKS_BARIS_ORANG = 25;

    /* ═══════════ grup & hak ═══════════ */

    /**
     * Id perusahaan dalam grup IUP pengguna: induk IUP + seluruh mitranya.
     * Administrator: seluruhnya, atau grup perusahaan yang sedang dilihat.
     *
     * @return array<int,int>|null  null = tanpa batas (administrator)
     */
    public static function grup(?User $u): ?array
    {
        if (! $u) return [];

        $akar = null;
        if ($u->isAdmin()) {
            $lihat = Perusahaan::terpilih($u);
            if (! $lihat) return null;
            $akar = Company::query()->find($lihat);
        } else {
            $akar = $u->company;
        }
        if (! $akar) return [];
        if ($akar->parent_id) $akar = Company::query()->find($akar->parent_id) ?? $akar;

        return Company::query()->where(fn ($w) => $w->whereKey($akar->id)->orWhere('parent_id', $akar->id))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    /** Pengelola ("admin OHSE" DBEST): administrator atau HSE IUP. */
    public static function pengelola(?User $u): bool
    {
        return AksesMiners::kelola($u);
    }

    /** Perusahaan pemilik data pekerja ini: pendaftarnya, atau HSE perusahaannya. */
    public static function pemilik(Pekerja $p, ?User $u): bool
    {
        if (! $u) return false;

        return AksesMiners::pendaftar($p, $u)
            || ($u->company_id && (int) $u->company_id === (int) $p->company_id && $u->isOhse());
    }

    public static function bolehKelola(Pekerja $p, ?User $u): bool
    {
        return self::pengelola($u) || self::pemilik($p, $u);
    }

    /** Pemilik catatan: perusahaannya pemilik data, atau pencatatnya. */
    public static function pemilikCatatan(Pelanggaran $c, ?User $u): bool
    {
        if (! $u) return false;

        return (int) $c->dibuat_oleh === (int) $u->getKey()
            || ($u->company_id && (int) $u->company_id === (int) $c->company_id);
    }

    public static function bolehLihatPelanggaran(Pelanggaran $c, ?User $u): bool
    {
        return self::pengelola($u) || self::pemilikCatatan($c, $u) || $c->status === Pelanggaran::DIVERIFIKASI;
    }

    public static function bolehUbahPelanggaran(Pelanggaran $c, ?User $u): bool
    {
        return self::pengelola($u) || (self::pemilikCatatan($c, $u) && $c->status !== Pelanggaran::DIVERIFIKASI);
    }

    public static function statusAwal(?User $u): string
    {
        return self::pengelola($u) ? Pelanggaran::DIVERIFIKASI : Pelanggaran::DIAJUKAN;
    }

    /* ═══════════ NIK ═══════════ */

    public static function normalNik(?string $nik): string
    {
        return (string) preg_replace('/\D+/', '', (string) $nik);
    }

    /**
     * NIK KTP yang layak dipakai mengenali orang lintas perusahaan: 16 digit,
     * kode wilayah wajar, tanggal lahir (+40 perempuan) dan bulan sah, nomor
     * urut bukan 0000, dan bukan angka seragam/berurutan.
     */
    public static function nikValid(?string $nik): bool
    {
        $d = self::normalNik($nik);
        if (strlen($d) !== 16 || (int) substr($d, 0, 2) < 11) return false;
        if (preg_match('/^(\d)\1+$/', $d) || in_array($d, ['1234567890123456', '0123456789012345'], true)) return false;
        $tgl = (int) substr($d, 6, 2);
        $tgl = $tgl > 40 ? $tgl - 40 : $tgl;
        $bln = (int) substr($d, 8, 2);

        return $tgl >= 1 && $tgl <= 31 && $bln >= 1 && $bln <= 12 && substr($d, 12, 4) !== '0000';
    }

    /** 6407062808970001 → 6407••••••••0001 */
    public static function samarNik(?string $nik): string
    {
        $d = self::normalNik($nik);
        $n = strlen($d);
        if ($n === 0) return '';
        if ($n <= 8) return str_repeat('•', max(0, $n - 2)).substr($d, -2);

        return substr($d, 0, 4).str_repeat('•', $n - 8).substr($d, -4);
    }

    public static function kunciOrang(?string $nik, int $id): string
    {
        return self::nikValid($nik) ? 'nik:'.self::normalNik($nik) : 'id:'.$id;
    }

    public static function sanksiTerberat(array $sanksi): ?string
    {
        $t = null;
        foreach ($sanksi as $s) {
            if ($t === null || (Pelanggaran::BERAT_SANKSI[$s] ?? 0) > (Pelanggaran::BERAT_SANKSI[$t] ?? 0)) $t = $s;
        }

        return $t;
    }

    /* ═══════════ kueri ═══════════ */

    /** Pekerja dalam grup, tanpa global scope (dibatasi grup di sini). */
    public static function dasar(?User $u)
    {
        $g = self::grup($u);

        return Pekerja::withoutGlobalScopes()->with([
                'company' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'parent_id'),
                'jabatan' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nama'),
            ])
            ->when($g !== null, fn ($q) => $q->whereIn('mnr_pekerja.company_id', $g ?: [0]));
    }

    /**
     * Cari orang: NIK 16 digit (lintas perusahaan), nama (min. 3 huruf),
     * atau nomor induk. Mengembalikan baris pekerja yang cocok, termasuk
     * baris lain dengan NIK sah yang sama (orang yang sama di perusahaan lain).
     */
    public static function cari(string $kata, ?User $u, int $batas = 40): Collection
    {
        $kata = trim($kata);
        $nik = self::normalNik($kata);
        $like = \App\Support\Db::like();

        if (strlen($nik) >= 8 && strlen($nik) === strlen(preg_replace('/[\s.\-]/', '', $kata))) {
            $baris = self::dasar($u)->where('nik', $like, '%'.$nik.'%')->limit($batas)->get();
        } elseif (mb_strlen($kata) >= 3) {
            $baris = self::dasar($u)->where(fn ($w) => $w->where('nama', $like, '%'.$kata.'%')
                ->orWhere('no_induk', $like, '%'.$kata.'%'))->limit($batas)->get();
        } else {
            return collect();
        }

        // Sertakan baris lain dengan NIK sah yang sama: satu orang, banyak perusahaan.
        $nikSah = $baris->pluck('nik')->filter(fn ($n) => self::nikValid($n))->map(fn ($n) => self::normalNik($n))->unique()->values();
        if ($nikSah->isNotEmpty()) {
            $lain = self::dasar($u)->whereNotIn('mnr_pekerja.id', $baris->pluck('id'))
                ->where(fn ($w) => $nikSah->each(fn ($n) => $w->orWhere('nik', $like, '%'.$n.'%')))->limit($batas)->get()
                ->filter(fn ($p) => $nikSah->contains(self::normalNik($p->nik)));
            $baris = $baris->concat($lain);
        }

        return $baris;
    }

    /** Catatan pelanggaran yang boleh dilihat pengguna, per pekerja. */
    public static function pelanggaranUntuk(array $idPekerja, ?User $u): Collection
    {
        if (! $idPekerja) return collect();

        return Pelanggaran::with(['company:id,name', 'pembuat:id,name', 'verifikator:id,name'])
            ->whereIn('pekerja_id', $idPekerja)->orderByDesc('tanggal')->orderByDesc('id')->get()
            ->filter(fn (Pelanggaran $c) => self::bolehLihatPelanggaran($c, $u))
            ->groupBy('pekerja_id');
    }

    /** Satu catatan untuk layar. */
    public static function catatan(Pelanggaran $c, ?User $u): array
    {
        return [
            'id'        => $c->id,
            'tanggal'   => $c->tanggal?->toDateString(),
            'kategori'  => $c->kategori, 'labelKategori' => Pelanggaran::KATEGORI[$c->kategori] ?? $c->kategori,
            'tingkat'   => $c->tingkat, 'labelTingkat' => Pelanggaran::TINGKAT[$c->tingkat] ?? $c->tingkat,
            'sanksi'    => $c->sanksi, 'labelSanksi' => $c->sanksi ? (Pelanggaran::SANKSI[$c->sanksi] ?? $c->sanksi) : null,
            'uraian'    => (string) $c->uraian,
            'lokasi'    => (string) $c->lokasi,
            'noDokumen' => (string) $c->no_dokumen,
            'status'    => $c->status, 'labelStatus' => Pelanggaran::STATUS[$c->status] ?? $c->status,
            'catatanVerifikasi' => (string) $c->catatan_verifikasi,
            'perusahaan' => $c->company?->name,
            'pencatat'  => $c->pembuat?->name,
            'verifikator' => $c->verifikator?->name,
            'bolehUbah' => self::bolehUbahPelanggaran($c, $u),
            'bolehVerifikasi' => self::pengelola($u) && $c->status !== Pelanggaran::DIVERIFIKASI,
        ];
    }

    /** Satu baris pekerja (satu perusahaan) untuk layar. */
    public static function baris(Pekerja $p, ?User $u, bool $utuh): array
    {
        return [
            'id'         => $p->id,
            'nama'       => $p->nama,
            'perusahaan' => $p->company?->name,
            'jabatan'    => $p->jabatan?->nama,
            'noInduk'    => $utuh ? (string) $p->no_induk : '',
            'aktif'      => $p->status === 'aktif',
            'masuk'      => $p->tanggal_masuk?->toDateString(),
            'berhenti'   => ($p->tanggal_nonaktif ?? $p->tanggal_resign)?->toDateString(),
            'sebab'      => $p->sebab_nonaktif,
            'labelSebab' => $p->status === 'aktif' ? null : ($p->sebab_nonaktif ? (self::ALASAN[$p->sebab_nonaktif] ?? $p->sebab_nonaktif) : 'Alasan belum diisi'),
            'keterangan' => (string) $p->alasan_nonaktif,
            'rekomendasi' => $p->rekomendasi_rekrut,
            'labelRekomendasi' => $p->rekomendasi_rekrut ? (self::REKOMENDASI[$p->rekomendasi_rekrut] ?? null) : null,
            'bolehKelola' => self::bolehKelola($p, $u),
        ];
    }

    /**
     * Kelompokkan baris pekerja menjadi ORANG (dikenali lewat NIK sah),
     * beserta pelanggaran yang boleh dilihat.
     */
    public static function susunOrang(Collection $baris, ?User $u): array
    {
        if ($baris->isEmpty()) return [];
        $pel = self::pelanggaranUntuk($baris->pluck('id')->all(), $u);
        $kelola = self::pengelola($u);

        $orang = [];
        foreach ($baris->sortByDesc('created_at') as $p) {
            $k = self::kunciOrang($p->nik, (int) $p->id);
            $milik = self::pemilik($p, $u) || ($u?->company_id && (int) $u->company_id === (int) $p->company_id);
            $orang[$k] ??= [
                'kunci' => $k, 'nama' => $p->nama, 'nikMentah' => (string) $p->nik, 'nikValid' => self::nikValid($p->nik),
                'utuh' => $kelola, 'namaLain' => [], 'baris' => [], 'pelanggaran' => [], 'aktifDi' => [],
                'nonaktif' => 0, 'phkPelanggaran' => false, 'tidakDisarankan' => false, 'terpotong' => 0,
            ];
            $o = &$orang[$k];
            if ($milik) $o['utuh'] = true;
            if (mb_strtolower(trim($p->nama)) !== mb_strtolower(trim($o['nama']))) $o['namaLain'][] = trim($p->nama);
            if ($p->status === 'aktif') {
                $o['aktifDi'][] = (string) $p->company?->name;
            } else {
                $o['nonaktif']++;
                $o['phkPelanggaran'] = $o['phkPelanggaran'] || $p->sebab_nonaktif === 'phk_pelanggaran';
                $o['tidakDisarankan'] = $o['tidakDisarankan'] || $p->rekomendasi_rekrut === 'tidak';
            }
            if (count($o['baris']) < self::MAKS_BARIS_ORANG) $o['baris'][] = $p;
            else $o['terpotong']++;
            foreach ($pel->get($p->id, []) as $c) $o['pelanggaran'][] = $c;
            unset($o);
        }

        $out = [];
        foreach ($orang as $o) {
            $catatan = collect($o['pelanggaran'])->sortByDesc(fn ($c) => $c->tanggal?->toDateString().sprintf('%09d', $c->id))->values();
            $tampak = $catatan->where('status', '!=', Pelanggaran::DITOLAK);
            $out[] = [
                'kunci'    => $o['kunci'],
                'nama'     => $o['nama'],
                'namaLain' => array_values(array_unique($o['namaLain'])),
                'nik'      => $o['nikMentah'] === '' ? '' : ($o['utuh'] ? self::normalNik($o['nikMentah']) : self::samarNik($o['nikMentah'])),
                'nikValid' => $o['nikValid'],
                'aktifDi'  => array_values(array_unique(array_filter($o['aktifDi']))),
                'nonaktif' => $o['nonaktif'],
                'phkPelanggaran'  => $o['phkPelanggaran'],
                'tidakDisarankan' => $o['tidakDisarankan'],
                'jumlahPelanggaran' => $tampak->count(),
                'berat'    => $tampak->where('tingkat', 'berat')->count(),
                'sanksiTerberat' => ($s = self::sanksiTerberat($tampak->pluck('sanksi')->filter()->all())) ? Pelanggaran::SANKSI[$s] : null,
                'baris'    => array_map(fn (Pekerja $p) => self::baris($p, $u, $o['utuh']), $o['baris']),
                'terpotong'=> $o['terpotong'],
                'pelanggaran' => $catatan->map(fn ($c) => self::catatan($c, $u))->all(),
            ];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));

        return $out;
    }

    /**
     * Daftar pekerja nonaktif dalam grup dengan saringan alasan, perusahaan,
     * tahun berhenti, rekomendasi, dan nama/NIK; disertai jumlah per alasan.
     */
    public static function daftarNonaktif(array $f, ?User $u, int $perHalaman = 25)
    {
        $like = \App\Support\Db::like();
        $q = self::dasar($u)->whereIn('status', ['nonaktif', 'resign']);

        $saring = function ($q, bool $denganSebab = true) use ($f, $like) {
            return $q
                ->when($denganSebab && ($f['sebab'] ?? '') !== '', fn ($w) => ($f['sebab'] === 'kosong')
                    ? $w->whereNull('sebab_nonaktif') : $w->where('sebab_nonaktif', $f['sebab']))
                ->when(($f['perusahaan'] ?? '') !== '', fn ($w) => $w->where('mnr_pekerja.company_id', (int) $f['perusahaan']))
                ->when(($f['rekomendasi'] ?? '') !== '', fn ($w) => $w->where('rekomendasi_rekrut', $f['rekomendasi']))
                ->when(($f['tahun'] ?? '') !== '', fn ($w) => $w->where(fn ($t) => $t
                    ->whereYear('tanggal_nonaktif', (int) $f['tahun'])
                    ->orWhere(fn ($r) => $r->whereNull('tanggal_nonaktif')->whereYear('tanggal_resign', (int) $f['tahun']))))
                ->when(trim((string) ($f['q'] ?? '')) !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->where('nama', $like, '%'.trim($f['q']).'%')
                    ->orWhere('nik', $like, '%'.self::normalNik($f['q']).'%')));
        };

        $jumlah = $saring(clone $q, false)->selectRaw('sebab_nonaktif, count(*) as n')->groupBy('sebab_nonaktif')
            ->pluck('n', 'sebab_nonaktif')->mapWithKeys(fn ($n, $k) => [$k === '' || $k === null ? 'kosong' : $k => (int) $n])->all();

        $hal = $saring($q)->orderByDesc('tanggal_nonaktif')->orderBy('nama')->paginate($perHalaman)->withQueryString();
        $pel = self::pelanggaranUntuk($hal->getCollection()->pluck('id')->all(), $u);

        $hal->setCollection($hal->getCollection()->map(function (Pekerja $p) use ($u, $pel) {
            $utuh = self::pengelola($u) || ($u?->company_id && (int) $u->company_id === (int) $p->company_id);
            $c = collect($pel->get($p->id, []))->where('status', '!=', Pelanggaran::DITOLAK);

            return self::baris($p, $u, $utuh) + [
                'nik' => $utuh ? self::normalNik($p->nik) : self::samarNik($p->nik),
                'pelanggaran' => $c->count(),
                'berat' => $c->where('tingkat', 'berat')->count(),
            ];
        }));

        return ['halaman' => $hal, 'jumlah' => $jumlah];
    }

    /**
     * Rekap pelanggaran: kartu ringkas, sebaran per kategori (dan per
     * perusahaan bagi pengelola), rekap per karyawan, dan daftar catatan.
     */
    public static function rekap(array $f, ?User $u): array
    {
        $g = self::grup($u);
        $semua = Pelanggaran::with(['pekerja' => fn ($q) => $q->select('id', 'nama', 'nik', 'company_id'), 'company:id,name', 'pembuat:id,name', 'verifikator:id,name'])
            ->when($g !== null, fn ($q) => $q->whereIn('company_id', $g ?: [0]))
            ->orderByDesc('tanggal')->orderByDesc('id')->get()
            ->filter(fn (Pelanggaran $c) => self::bolehLihatPelanggaran($c, $u))->values();

        $tampak = $semua->where('status', '!=', Pelanggaran::DITOLAK);
        $perOrang = $tampak->groupBy('pekerja_id');

        $saring = $semua
            ->when(($f['kategori'] ?? '') !== '', fn ($c) => $c->where('kategori', $f['kategori']))
            ->when(($f['tingkat'] ?? '') !== '', fn ($c) => $c->where('tingkat', $f['tingkat']))
            ->when(($f['status'] ?? '') !== '', fn ($c) => $c->where('status', $f['status']))
            ->values();

        return [
            'kartu' => [
                'total'    => $tampak->count(),
                'berat'    => $tampak->where('tingkat', 'berat')->count(),
                'sedang'   => $tampak->where('tingkat', 'sedang')->count(),
                'ringan'   => $tampak->where('tingkat', 'ringan')->count(),
                'berulang' => $perOrang->filter(fn ($c) => $c->count() > 1)->count(),
                'menunggu' => $semua->where('status', Pelanggaran::DIAJUKAN)->count(),
            ],
            'kategori' => collect(Pelanggaran::KATEGORI)->map(fn ($l, $k) => ['k' => $k, 'n' => $l, 'jumlah' => $tampak->where('kategori', $k)->count()])
                ->filter(fn ($r) => $r['jumlah'] > 0)->sortByDesc('jumlah')->values()->all(),
            'perusahaan' => self::pengelola($u) ? $tampak->groupBy(fn ($c) => (string) $c->company?->name)
                ->map(fn ($c, $n) => ['n' => $n ?: '—', 'jumlah' => $c->count(), 'berat' => $c->where('tingkat', 'berat')->count()])
                ->sortByDesc('jumlah')->values()->all() : [],
            'orang' => $perOrang->map(function ($c) use ($u) {
                $p = $c->first()->pekerja;
                $utuh = self::pengelola($u) || ($u?->company_id && (int) $u->company_id === (int) $p?->company_id);

                return [
                    'nama' => $p?->nama, 'nik' => $utuh ? self::normalNik($p?->nik) : self::samarNik($p?->nik),
                    'perusahaan' => $c->first()->company?->name,
                    'jumlah' => $c->count(), 'berat' => $c->where('tingkat', 'berat')->count(),
                    'sanksi' => ($s = self::sanksiTerberat($c->pluck('sanksi')->filter()->all())) ? Pelanggaran::SANKSI[$s] : null,
                    'terakhir' => $c->max(fn ($x) => $x->tanggal?->toDateString()),
                ];
            })->sortByDesc('jumlah')->values()->take(50)->all(),
            'daftar' => $saring->take(100)->map(fn ($c) => self::catatan($c, $u) + [
                'pekerja' => $c->pekerja?->nama,
            ])->all(),
        ];
    }
}
