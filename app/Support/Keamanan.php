<?php

namespace App\Support;

use App\Models\{ActivityLog, User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jejak akses dan kendali sesi.
 *
 * Modul ini menjawab tiga pertanyaan yang sebelumnya tidak dapat
 * dijawab sama sekali oleh pemasangan ini:
 *
 *   1. Siapa yang masuk, kapan, dari mana, dan dengan perangkat apa.
 *   2. Siapa yang SEDANG masuk sekarang, dan dapatkah ia diputus.
 *   3. Adakah yang sedang mencoba menebak sandi, dan sandi siapa.
 *
 * Pertanyaan ketiga yang paling sering datang terlambat. Percobaan
 * masuk yang gagal tidak menimbulkan galat, tidak mengisi log, dan
 * tidak mengganggu siapa pun — sampai ada satu yang berhasil. Sejak
 * berkas ini ada, setiap percobaan yang gagal tercatat beserta
 * alamatnya, sehingga tekanan yang sedang berlangsung dapat dilihat
 * ketika masih berlangsung.
 *
 * Yang sengaja TIDAK dicatat: sandi, baik yang benar maupun yang salah.
 * Sandi yang salah pun berharga — orang memakai sandi yang mirip di
 * banyak tempat, dan sandi yang salah di sini sering merupakan sandi
 * yang benar di tempat lain. Yang dicatat hanya surel yang dicoba.
 */
final class Keamanan
{
    public const MODUL = 'keamanan';

    /* Nama peristiwa. Dipakai sebagai `action` pada activity_log. */
    public const MASUK          = 'Masuk';
    public const MASUK_GAGAL    = 'Masuk gagal';
    public const TERKUNCI       = 'Masuk terkunci';
    public const KELUAR         = 'Keluar';
    public const SANDI_BERUBAH  = 'Sandi diubah';
    public const SESI_DIPUTUS   = 'Sesi diputus';

    /** Peristiwa yang menandakan kegagalan — dihitung sebagai tekanan. */
    public const PERISTIWA_GAGAL = [self::MASUK_GAGAL, self::TERKUNCI];

    /**
     * Ambang tekanan dalam satu jam sebelum keadaannya disebut gawat.
     *
     * Dua puluh dipilih bukan dari teori: batas bawaan Laravel adalah
     * lima percobaan per surel per alamat, jadi dua puluh berarti
     * setidaknya empat kunci beruntun — sesuatu yang tidak dihasilkan
     * oleh orang yang sekadar lupa sandinya.
     *
     * Keduanya hanya BAWAAN; nilai yang berlaku dibaca dari config
     * supaya pemasangan dengan banyak pengguna lapangan dapat
     * menaikkannya tanpa mematikan peringatannya.
     */
    public const AMBANG_GAWAT     = 20;
    public const AMBANG_PERHATIAN = 8;

    public static function ambangGawat(): int
    {
        return max(1, (int) config('keamanan.ambang_gagal_gawat', self::AMBANG_GAWAT));
    }

    public static function ambangPerhatian(): int
    {
        return max(1, (int) config('keamanan.ambang_gagal_perhatian', self::AMBANG_PERHATIAN));
    }

    /* ═══════════ pencatatan ═══════════ */

    /**
     * Catat satu peristiwa keamanan.
     *
     * Ditulis lewat ActivityLog supaya jejaknya berada di tempat yang
     * sama dengan jejak modul lain. Yang memeriksa sebuah insiden tidak
     * perlu tahu bahwa peristiwa masuk kebetulan disimpan terpisah.
     */
    public static function catat(
        string $peristiwa,
        ?string $keterangan = null,
        ?User $pengguna = null,
        ?Request $permintaan = null,
    ): void {
        $r = $permintaan ?: request();

        ActivityLog::create([
            'user_id'    => $pengguna?->id ?? auth()->id(),
            'username'   => $pengguna?->name ?? auth()->user()?->name,
            'module'     => self::MODUL,
            'action'     => $peristiwa,
            'detail'     => $keterangan,
            'ip'         => self::alamat($r),
            'user_agent' => self::perangkat($r),
            'company_id' => $pengguna?->company_id ?? auth()->user()?->company_id,
        ]);
    }

    /**
     * Alamat pemanggil.
     *
     * Di belakang nginx, `REMOTE_ADDR` selalu 127.0.0.1 — alamat nginx
     * sendiri. Alamat sebenarnya hanya ada pada X-Forwarded-For, dan
     * Laravel baru mempercayainya bila proksinya memang dipercaya.
     * Karena itu yang dipakai `$r->ip()`, bukan header mentah: header
     * mentah dapat dipalsukan oleh siapa pun yang mengirim permintaan,
     * dan jejak yang alamatnya dapat dipalsukan lebih buruk daripada
     * jejak tanpa alamat — ia terlihat meyakinkan.
     */
    public static function alamat(?Request $r = null): ?string
    {
        return ($r ?: request())?->ip();
    }

    /** Dipotong 255 huruf; user-agent panjang tidak menambah keterangan. */
    public static function perangkat(?Request $r = null): ?string
    {
        $ua = ($r ?: request())?->userAgent();

        return filled($ua) ? mb_substr($ua, 0, 255) : null;
    }

    /**
     * Ringkas user-agent menjadi sesuatu yang dapat dibaca orang.
     *
     * Tujuannya bukan ketepatan, melainkan pengenalan: pemilik akun
     * harus dapat menjawab "apakah ini saya" dalam sekali lihat.
     * "Chrome di Windows" menjawab itu; string user-agent utuh tidak.
     */
    public static function ringkasPerangkat(?string $ua): string
    {
        if (blank($ua)) return 'Tidak diketahui';

        $peramban = match (true) {
            str_contains($ua, 'Edg/')                              => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/')                          => 'Firefox',
            str_contains($ua, 'Chrome/')                           => 'Chrome',
            str_contains($ua, 'Safari/')                           => 'Safari',
            default                                                => 'Peramban lain',
        };

        $sistem = match (true) {
            str_contains($ua, 'Android')                            => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows')                            => 'Windows',
            str_contains($ua, 'Mac OS')                             => 'macOS',
            str_contains($ua, 'Linux')                              => 'Linux',
            default                                                 => 'sistem lain',
        };

        return $peramban.' di '.$sistem;
    }

    /* ═══════════ tekanan ═══════════ */

    /** Jumlah percobaan masuk yang gagal sejak sekian jam lalu. */
    public static function gagalSejak(int $jam = 24): int
    {
        return ActivityLog::query()
            ->where('module', self::MODUL)
            ->whereIn('action', self::PERISTIWA_GAGAL)
            ->where('created_at', '>=', now()->subHours($jam))
            ->count();
    }

    /**
     * Alamat dengan kegagalan terbanyak.
     *
     * @return list<array{ip:string,jumlah:int,sasaran:int,terakhir:string}>
     */
    public static function alamatMenekan(int $jam = 24, int $batas = 8): array
    {
        return ActivityLog::query()
            ->selectRaw('ip, COUNT(*) as jumlah, COUNT(DISTINCT detail) as sasaran, MAX(created_at) as terakhir')
            ->where('module', self::MODUL)
            ->whereIn('action', self::PERISTIWA_GAGAL)
            ->where('created_at', '>=', now()->subHours($jam))
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->orderByDesc('jumlah')
            ->limit($batas)
            ->get()
            ->map(fn ($b) => [
                'ip'       => (string) $b->ip,
                'jumlah'   => (int) $b->jumlah,
                'sasaran'  => (int) $b->sasaran,
                'terakhir' => Carbon::parse($b->terakhir)->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Keadaan tekanan saat ini: aman, perhatian, atau gawat.
     *
     * Dinyatakan dalam kosakata yang sama dengan Diagnosa supaya
     * keduanya dapat dibaca berdampingan tanpa penerjemahan.
     */
    public static function keadaanTekanan(int $gagalSejam): string
    {
        return match (true) {
            $gagalSejam >= self::ambangGawat()     => Diagnosa::GAWAT,
            $gagalSejam >= self::ambangPerhatian() => Diagnosa::PERHATIAN,
            default                                => Diagnosa::AMAN,
        };
    }

    /**
     * Pangkas jejak yang sudah lewat masa simpannya.
     *
     * Hanya jejak modul keamanan. Jejak modul lain punya nilai
     * operasional yang berbeda — riwayat persetujuan dokumen masih
     * ditanyakan bertahun kemudian — dan memangkasnya di sini berarti
     * memutuskan hal yang bukan urusan berkas ini.
     */
    public static function pangkasJejak(?int $hari = null): int
    {
        $hari = $hari ?? (int) config('keamanan.simpan_jejak_hari', 90);

        if ($hari < 1) return 0;

        return ActivityLog::query()
            ->where('module', self::MODUL)
            ->where('created_at', '<', now()->subDays($hari))
            ->delete();
    }

    /* ═══════════ sesi ═══════════ */

    /**
     * Sesi yang masih hidup, terbaru lebih dulu.
     *
     * Dibatasi pada satu pengguna bila `$user` diberikan. Tanpa itu ia
     * memulangkan seluruh sesi — hanya untuk administrator.
     *
     * @return list<array<string,mixed>>
     */
    public static function sesiAktif(?User $user = null, ?string $sesiKini = null, int $batas = 100): array
    {
        if (config('session.driver') !== 'database') return [];

        $kedaluwarsa = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        $q = DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->select([
                'sessions.id', 'sessions.ip_address', 'sessions.user_agent',
                'sessions.last_activity', 'sessions.user_id',
                'users.name as nama', 'users.email', 'users.company_id',
            ])
            ->where('sessions.last_activity', '>=', $kedaluwarsa)
            ->orderByDesc('sessions.last_activity')
            ->limit($batas);

        if ($user) $q->where('sessions.user_id', $user->id);

        return $q->get()->map(fn ($s) => [
            'id'        => (string) $s->id,
            'iniSaya'   => $sesiKini !== null && hash_equals((string) $s->id, $sesiKini),
            'userId'    => $s->user_id,
            'nama'      => $s->nama ?: 'Tanpa nama (belum masuk)',
            'email'     => $s->email,
            'ip'        => $s->ip_address ?: '—',
            'perangkat' => self::ringkasPerangkat($s->user_agent),
            'terakhir'  => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
            'detik'     => now()->getTimestamp() - (int) $s->last_activity,
        ])->all();
    }

    /**
     * Putuskan satu sesi.
     *
     * Menghapus barisnya sudah cukup: pembaca sesi berbasis basis data
     * memuat ulang tiap permintaan, jadi permintaan berikutnya dari
     * perangkat itu datang tanpa sesi dan dialihkan ke halaman masuk.
     *
     * Memulangkan false bila barisnya bukan milik `$pemilik` — penjaga
     * ini ada di sini, bukan hanya di controller, supaya jalan mana pun
     * yang memanggilnya tetap terjaga.
     */
    public static function putusSesi(string $id, ?User $pemilik = null): bool
    {
        if (config('session.driver') !== 'database') return false;

        $q = DB::table('sessions')->where('id', $id);

        if ($pemilik) $q->where('user_id', $pemilik->id);

        return $q->delete() > 0;
    }

    /**
     * Putuskan seluruh sesi seorang pengguna kecuali yang sedang dipakai.
     *
     * Ini tindakan pertama yang harus dapat dilakukan orang yang
     * menduga sandinya bocor, dan ia harus dapat melakukannya sendiri
     * tanpa menunggu administrator. Menunggu berarti penyusupnya
     * memegang sesi hidup selama masa tunggu itu.
     */
    public static function putusSesiLain(User $pemilik, ?string $kecuali = null): int
    {
        if (config('session.driver') !== 'database') return 0;

        $q = DB::table('sessions')->where('user_id', $pemilik->id);

        if (filled($kecuali)) $q->where('id', '!=', $kecuali);

        return $q->delete();
    }

    /** Jumlah sesi hidup, untuk lencana dan diagnosa. */
    public static function jumlahSesiAktif(): int
    {
        if (config('session.driver') !== 'database') return 0;

        return DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
            ->count();
    }

    /**
     * Sesi basi: barisnya masih ada padahal masa berlakunya lewat.
     *
     * Tidak berbahaya dengan sendirinya — pembaca sesi menolaknya —
     * tetapi barisnya menyimpan IP dan user-agent setiap orang yang
     * pernah masuk, dan menyimpan data yang tidak dipakai lagi hanya
     * menambah yang dapat hilang tanpa menambah yang dapat dikerjakan.
     */
    public static function sesiBasi(): int
    {
        if (config('session.driver') !== 'database') return 0;

        return DB::table('sessions')
            ->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
            ->count();
    }

    public static function bersihkanSesiBasi(): int
    {
        if (config('session.driver') !== 'database') return 0;

        return DB::table('sessions')
            ->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
            ->delete();
    }

    /* ═══════════ riwayat ═══════════ */

    /**
     * Peristiwa keamanan terakhir.
     *
     * @return list<array<string,mixed>>
     */
    public static function riwayat(int $batas = 40, ?User $hanya = null): array
    {
        $q = ActivityLog::query()
            ->where('module', self::MODUL)
            ->latest('created_at')
            ->limit($batas);

        if ($hanya) $q->where('user_id', $hanya->id);

        return $q->get()->map(fn ($b) => [
            'id'        => $b->id,
            'peristiwa' => $b->action,
            'siapa'     => $b->username ?: ($b->detail ?: 'tidak dikenal'),
            'detail'    => $b->detail,
            'ip'        => $b->ip ?: '—',
            'perangkat' => self::ringkasPerangkat($b->user_agent),
            'kapan'     => $b->created_at?->diffForHumans(),
            'gagal'     => in_array($b->action, self::PERISTIWA_GAGAL, true),
        ])->all();
    }
}
