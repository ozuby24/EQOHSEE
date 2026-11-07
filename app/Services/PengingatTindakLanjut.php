<?php

namespace App\Services;

use App\Mail\PengingatTindakLanjut as SuratPengingat;
use App\Models\{Company, Pengaturan, SmkpFinding, SmkpPengingat, User};
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Pengingat email batas waktu tindak lanjut temuan audit SMKP.
 *
 * Setiap hari (penjadwal, routes/console.php) temuan yang belum ditutup
 * diperiksa terhadap jadwal pengingat — bawaan 2 bulan dan 1 bulan sebelum
 * target penyelesaiannya. Satu email per perusahaan dikirim ke akun auditi
 * aktifnya (PJP tanpa akun auditi: ke auditi IUP induknya), berisi seluruh
 * temuan perusahaan itu yang jatuh pada jadwal hari tersebut.
 *
 * Tiap jadwal tercatat per temuan & per target (smkp_pengingat) sehingga
 * tidak terkirim dua kali; bila target diundur, jadwal berlaku lagi untuk
 * target baru. Penjadwal yang terlewat menyusul pada jalannya berikutnya —
 * beberapa jadwal yang tersusul sekaligus cukup satu email (yang terdekat).
 * Perusahaan demo tidak pernah dikirimi.
 *
 * Penyesuaian EQOHSEE: pengaturan disimpan di app_settings (App\Models\
 * Pengaturan); server email memakai konfigurasi mail Laravel (.env) —
 * EQOHSEE tidak punya halaman Admin → Server → Email; auditi = akun
 * non-admin yang audit_role-nya bukan 'auditor'.
 */
final class PengingatTindakLanjut
{
    /** Jadwal yang dapat dipilih: kode → jarak sebelum target ('b' bulan, 'm' minggu). */
    public const JADWAL = ['3b' => '3 bulan', '2b' => '2 bulan', '1b' => '1 bulan', '2m' => '2 minggu', '1m' => '1 minggu'];
    public const BAWAAN = ['2b', '1b'];

    /* Kunci tabel pengaturan. */
    public const AKTIF = 'pengingat.aktif';
    public const KUNCI_JADWAL = 'pengingat.jadwal';

    /** Jam kirim harian (lihat routes/console.php). */
    public const JAM = '07:15';

    /**
     * Server email siap mengirim: mailer bawaan bukan 'log'/'array'
     * (MAIL_MAILER di .env). Pengganti KonfigurasiSurel::siap() Aurorasys.
     */
    public static function suratSiap(): bool
    {
        return !in_array(config('mail.default'), [null, '', 'log', 'array'], true);
    }

    /** Alamat & nama pengirim yang berlaku. @return array{address:string, name:string} */
    public static function pengirim(): array
    {
        return ['address' => (string) config('mail.from.address'), 'name' => (string) config('mail.from.name')];
    }

    public static function aktif(): bool
    {
        return Pengaturan::ambil(self::AKTIF, '1') === '1';
    }

    /** Jadwal terpilih, terjauh dari target lebih dulu. @return list<string> */
    public static function jadwal(): array
    {
        $j = json_decode((string) Pengaturan::ambil(self::KUNCI_JADWAL, ''), true);

        return is_array($j) ? array_values(array_intersect(array_keys(self::JADWAL), $j)) : self::BAWAAN;
    }

    /** Tanggal kirim pengingat berkode $kode untuk target $target. */
    public static function tanggal(CarbonInterface $target, string $kode): CarbonImmutable
    {
        $t = CarbonImmutable::parse($target->toDateString());
        $n = (int) $kode;

        return str_ends_with($kode, 'b') ? $t->subMonthsNoOverflow($n) : $t->subWeeks($n);
    }

    /**
     * Pengingat yang jatuh tempo pada $hari, dikelompokkan per perusahaan audit.
     *
     * @return list<array{perusahaan: Company, penerima: Collection<int,User>, butir: list<array{temuan: SmkpFinding, kode: string, lewati: list<string>}>}>
     */
    public function jatuhTempo(?CarbonInterface $hari = null): array
    {
        $hari = self::hari($hari);
        $jadwal = self::jadwal();

        $grup = [];
        foreach ($this->terbuka($hari, $jadwal) as $t) {
            $sudah = $this->sudah($t);
            $jatuh = array_values(array_filter($jadwal, fn (string $k) => !in_array($k, $sudah, true) && self::tanggal($t->target_selesai, $k)->lte($hari)));
            if (!$jatuh) continue;

            $c = $t->audit->company;
            $grup[$c->id] ??= ['perusahaan' => $c, 'butir' => []];
            // Beberapa jadwal yang tersusul sekaligus cukup satu email: yang paling dekat ke target.
            $grup[$c->id]['butir'][] = ['temuan' => $t, 'kode' => array_pop($jatuh), 'lewati' => $jatuh];
        }

        return array_values(array_map(fn (array $g) => $g + ['penerima' => $this->penerima($g['perusahaan'])], $grup));
    }

    /**
     * Kirim seluruh pengingat yang jatuh tempo. Temuan baru dicatat setelah
     * emailnya diterima server SMTP; perusahaan tanpa akun auditi dilewati
     * dan dicoba lagi esok hari (setelah akunnya dibuat).
     *
     * @return array{email:int, temuan:int, tanpaPenerima:list<string>, gagal:list<string>}
     */
    public function kirim(?CarbonInterface $hari = null): array
    {
        $hari = self::hari($hari);
        $hasil = ['email' => 0, 'temuan' => 0, 'tanpaPenerima' => [], 'gagal' => []];

        foreach ($this->jatuhTempo($hari) as $g) {
            $nama = (string) $g['perusahaan']->name;
            if ($g['penerima']->isEmpty()) {
                $hasil['tanpaPenerima'][] = $nama;
                continue;
            }
            try {
                Mail::to($g['penerima'])->send(new SuratPengingat($g['perusahaan'], $this->baris($g['butir'], $hari)));
            } catch (Throwable $e) {
                report($e);
                $hasil['gagal'][] = $nama.': '.mb_substr($e->getMessage(), 0, 200);
                continue;
            }
            $this->catat($g['butir'], $g['penerima']->pluck('email')->implode(', '));
            $hasil['email']++;
            $hasil['temuan'] += count($g['butir']);
        }

        return $hasil;
    }

    /**
     * Pengingat yang akan dikirim dalam $rentang hari ke depan (termasuk yang
     * jatuh tempo tetapi belum terkirim), untuk pratinjau di menu Server.
     *
     * @return list<array{tanggal:string, jadwal:string, temuan:string, perusahaan:string, target:string, penerima:list<string>, url:string}>
     */
    public function mendatang(int $rentang = 90, int $batas = 50, ?CarbonInterface $hari = null): array
    {
        $hari = self::hari($hari);
        $akhir = $hari->addDays($rentang);
        $jadwal = self::jadwal();
        $penerima = [];

        $daftar = [];
        foreach ($this->terbuka($akhir, $jadwal, $hari) as $t) {
            $sudah = $this->sudah($t);
            $tunda = array_values(array_filter($jadwal, fn (string $k) => !in_array($k, $sudah, true)));
            // Jadwal yang sudah jatuh tempo tetapi belum terkirim tersusul bersama pada jalan
            // berikutnya — hanya yang terdekat ke target yang benar-benar dikirim.
            $jatuh = array_values(array_filter($tunda, fn (string $k) => self::tanggal($t->target_selesai, $k)->lte($hari)));
            $berikut = array_values(array_filter($tunda, fn (string $k) => !in_array($k, array_slice($jatuh, 0, -1), true)));

            foreach ($berikut as $k) {
                $tgl = self::tanggal($t->target_selesai, $k);
                if ($tgl->gt($akhir)) continue;
                $c = $t->audit->company;
                $penerima[$c->id] ??= $this->penerima($c)->pluck('email')->all();
                $daftar[] = [
                    'tanggal'    => ($tgl->lt($hari) ? $hari : $tgl)->toDateString(),
                    'jadwal'     => self::JADWAL[$k],
                    'temuan'     => self::nomor($t),
                    'perusahaan' => (string) $c->name,
                    'target'     => $t->target_selesai->toDateString(),
                    'penerima'   => $penerima[$c->id],
                    'url'        => route('smkp.temuan', [$t->audit_id, 'buka' => $t->id]),
                ];
            }
        }
        usort($daftar, fn ($a, $b) => [$a['tanggal'], $a['target']] <=> [$b['tanggal'], $b['target']]);

        return array_slice($daftar, 0, $batas);
    }

    /** Akun auditi aktif perusahaan itu; bila belum ada, auditi IUP induknya. @return Collection<int,User> */
    public function penerima(Company $c): Collection
    {
        $cari = fn (?int $id) => $id === null ? collect() : User::where('company_id', $id)->where('is_admin', false)
            ->where(fn ($q) => $q->whereNull('audit_role')->orWhere('audit_role', '<>', User::ROLE_AUDITOR))
            ->where('active', true)->orderBy('id')->get()
            ->filter(fn (User $u) => filter_var($u->email, FILTER_VALIDATE_EMAIL) !== false)->values();

        $u = $cari($c->id);

        return $u->isNotEmpty() ? $u : $cari($c->parent_id);
    }

    /**
     * Temuan belum ditutup yang targetnya belum lewat ($sejak) dan masuk
     * rentang jadwal terjauh dari $hari. Batas atasnya dilebihkan beberapa
     * hari karena pengurangan bulan di akhir bulan tidak simetris; ketepatan
     * jadwal diperiksa per temuan oleh pemanggil.
     *
     * @param list<string> $jadwal
     * @return Collection<int,SmkpFinding>
     */
    private function terbuka(CarbonImmutable $hari, array $jadwal, ?CarbonImmutable $sejak = null): Collection
    {
        if (!$jadwal) return collect();
        $maks = collect($jadwal)->map(fn (string $k) => str_ends_with($k, 'b') ? $hari->addMonthsNoOverflow((int) $k) : $hari->addWeeks((int) $k))
            ->max()->addDays(4);

        return SmkpFinding::withoutGlobalScopes()
            ->where('status', '<>', 'Closed')
            ->whereNotNull('target_selesai')
            ->whereDate('target_selesai', '>=', ($sejak ?? $hari)->toDateString())
            ->whereDate('target_selesai', '<=', $maks->toDateString())
            ->whereHas('audit', fn ($a) => $a->withoutGlobalScopes()->whereHas('company', fn ($c) => $c->where('demo', false)))
            ->with(['audit' => fn ($a) => $a->withoutGlobalScopes()->with('company'), 'pengingat'])
            ->orderBy('target_selesai')->orderBy('id')
            ->get();
    }

    /** Kode jadwal yang sudah diproses untuk target temuan saat ini. @return list<string> */
    private function sudah(SmkpFinding $t): array
    {
        $target = $t->target_selesai->toDateString();

        return $t->pengingat->filter(fn (SmkpPengingat $p) => substr((string) $p->target, 0, 10) === $target)->pluck('jadwal')->values()->all();
    }

    /** @param list<array{temuan: SmkpFinding, kode: string, lewati: list<string>}> $butir */
    private function catat(array $butir, string $penerima): void
    {
        $kini = now();
        foreach ($butir as $b) {
            $t = $b['temuan'];
            foreach ([...$b['lewati'], $b['kode']] as $k) {
                $dikirim = $k === $b['kode'];
                SmkpPengingat::firstOrCreate(
                    ['finding_id' => $t->id, 'target' => $t->target_selesai->toDateString(), 'jadwal' => $k],
                    ['penerima' => $dikirim ? $penerima : null, 'dikirim_pada' => $dikirim ? $kini : null],
                );
            }
        }
    }

    /**
     * Baris email, urut target terdekat.
     *
     * @param list<array{temuan: SmkpFinding, kode: string, lewati: list<string>}> $butir
     * @return list<array{nomor:string, kode:string, kategori:string, uraian:string, target:string, sisa:int, jadwal:string, status:string, audit:string, url:string}>
     */
    private function baris(array $butir, CarbonImmutable $hari): array
    {
        return collect($butir)->sortBy(fn (array $b) => [$b['temuan']->target_selesai->toDateString(), $b['temuan']->id])
            ->map(fn (array $b) => [
                'nomor'    => self::nomor($b['temuan']),
                'kode'     => (string) $b['temuan']->kode_kriteria,
                'kategori' => $b['temuan']->labelKategori(),
                'uraian'   => (string) $b['temuan']->uraian,
                'target'   => $b['temuan']->target_selesai->translatedFormat('d F Y'),
                'sisa'     => (int) $hari->diffInDays($b['temuan']->target_selesai->toDateString()),
                'jadwal'   => self::JADWAL[$b['kode']],
                'status'   => (string) $b['temuan']->status,
                'audit'    => $b['temuan']->audit->namaTampil(),
                'url'      => route('smkp.temuan', [$b['temuan']->audit_id, 'buka' => $b['temuan']->id]),
            ])->values()->all();
    }

    private static function nomor(SmkpFinding $t): string
    {
        return trim((string) $t->nomor) ?: 'Temuan '.$t->kode_kriteria;
    }

    private static function hari(?CarbonInterface $hari): CarbonImmutable
    {
        return CarbonImmutable::parse(($hari ?? now())->toDateString());
    }
}
