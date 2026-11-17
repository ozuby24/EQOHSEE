<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, User};
use App\Support\{Diagnosa, Keamanan};
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Pusat kendali keamanan.
 *
 * Halaman ini menjawab pertanyaan yang sebelumnya hanya dapat dijawab
 * dengan membuka basis data lewat SSH: siapa yang sedang masuk, dari
 * mana, dan adakah yang sedang menekan pintu masuknya.
 *
 * Yang membedakannya dari sekadar tampilan log: setiap angka di sini
 * bersambung dengan sebuah TINDAKAN. Sesi yang mencurigakan dapat
 * diputus dari barisnya sendiri; tekanan yang sedang berlangsung
 * menyebut alamatnya, bukan hanya jumlahnya. Angka yang tidak dapat
 * ditindaklanjuti hanya melatih orang untuk berhenti melihatnya.
 */
class KeamananController extends Controller
{
    public function index(Request $request)
    {
        $gagal24 = Keamanan::gagalSejak(24);
        $gagal1  = Keamanan::gagalSejak(1);

        return Inertia::render('Admin/Keamanan', [
            'judul'    => 'Keamanan & Jaringan',
            'subjudul' => 'Jejak akses, perangkat yang sedang masuk, dan tekanan pada pintu masuk',

            'ringkas' => [
                'gagal24'    => $gagal24,
                'gagal1'     => $gagal1,
                'keadaan'    => Keamanan::keadaanTekanan($gagal1),
                'sesiAktif'  => Keamanan::jumlahSesiAktif(),
                'sesiBasi'   => Keamanan::sesiBasi(),
                'ambang'     => Keamanan::ambangPerhatian(),
                'ambangGawat'=> Keamanan::ambangGawat(),
            ],

            'menekan' => Keamanan::alamatMenekan(24),
            'sesi'    => Keamanan::sesiAktif(null, $request->session()->getId()),
            'riwayat' => Keamanan::riwayat(40),
            'tajuk'   => $this->tajuk($request),

            'tidurAn' => $this->akunTidur(),

            'tautan' => [
                'putusSesi'    => route('admin.keamanan.sesi.putus'),
                'bersihSesi'   => route('admin.keamanan.sesi.bersih'),
                'pangkasJejak' => route('admin.keamanan.jejak.pangkas'),
                'sistem'       => route('admin.system'),
                'diagnosa'     => route('admin.system.diagnosa'),
                'perangkatSaya'=> route('keamanan.perangkat'),
            ],
        ]);
    }

    /**
     * Keadaan tajuk keamanan pada permintaan yang SEDANG berjalan.
     *
     * Dibaca dari permintaan nyata, bukan dari daftar yang seharusnya
     * dipasang. Perbedaannya menentukan: nginx dapat memasang tajuk
     * yang tidak diketahui aplikasi, dan aplikasi dapat memasang tajuk
     * yang kemudian dibuang proksi di depannya. Yang berlaku adalah
     * yang sampai ke peramban.
     *
     * @return list<array{nama:string,ada:bool,nilai:?string,guna:string}>
     */
    private function tajuk(Request $request): array
    {
        /* Middleware tajuk berjalan SESUDAH controller, jadi tanggapan
           yang sedang dibangun belum memuatnya. Yang diperiksa di sini
           adalah keputusan konfigurasinya, ditambah keadaan sambungan
           yang memang hanya dapat diketahui dari permintaan. */
        $https = $request->isSecure();

        return [
            ['nama' => 'X-Content-Type-Options', 'ada' => true, 'nilai' => 'nosniff',
             'guna' => 'Peramban tidak menebak jenis berkas; unggahan berisi HTML tidak dijalankan sebagai halaman.'],

            ['nama' => 'X-Frame-Options', 'ada' => true, 'nilai' => 'SAMEORIGIN',
             'guna' => 'Halaman tidak dapat dibingkai situs lain lalu ditumpangi tombol palsu.'],

            ['nama' => 'Referrer-Policy', 'ada' => true, 'nilai' => 'strict-origin-when-cross-origin',
             'guna' => 'Alamat halaman — yang memuat nomor dokumen dan izin kerja — tidak ikut keluar ke situs yang ditautkan.'],

            ['nama' => 'Permissions-Policy', 'ada' => true, 'nilai' => 'kamera, mikrofon, lokasi: mati',
             'guna' => 'Perangkat keras yang tidak pernah dipakai aplikasi ini ditutup seluruhnya.'],

            ['nama' => 'Strict-Transport-Security', 'ada' => (bool) config('keamanan.hsts') && $https,
             'nilai' => config('keamanan.hsts')
                 ? ($https ? 'max-age='.config('keamanan.hsts_umur') : 'menunggu https')
                 : 'sengaja dimatikan',
             'guna' => 'Memaksa peramban tetap di https. Sengaja mati sampai beberapa deploy '
                 .'membuktikan blok 443 bertahan — HSTS pada situs yang kehilangan https '
                 .'membuatnya tidak dapat dibuka sama sekali, bukan sekadar tidak terenkripsi. '
                 .'Nyalakan dengan KEAMANAN_HSTS=true.'],
        ];
    }

    /**
     * Akun yang lama tidak dipakai, dan akun yang belum pernah masuk.
     *
     * Akun tidur adalah pintu yang tidak dijaga siapa pun, sebab tidak
     * ada yang merasa memilikinya lagi. Yang paling sering terlewat
     * bukan akun mantan pegawai, melainkan akun yang dibuat untuk
     * pelatihan lalu tidak pernah ditutup.
     *
     * @return list<array<string,mixed>>
     */
    private function akunTidur(int $hari = 90): array
    {
        return User::query()
            ->where('active', true)
            ->where(fn ($q) => $q
                ->whereNull('masuk_terakhir_at')
                ->orWhere('masuk_terakhir_at', '<', now()->subDays($hari)))
            ->orderByRaw('masuk_terakhir_at IS NULL DESC')
            ->orderBy('masuk_terakhir_at')
            ->limit(15)
            ->get(['id', 'name', 'email', 'is_admin', 'masuk_terakhir_at'])
            ->map(fn ($u) => [
                'id'      => $u->id,
                'nama'    => $u->name,
                'email'   => $u->email,
                'admin'   => (bool) $u->is_admin,
                'terakhir'=> $u->masuk_terakhir_at?->diffForHumans() ?? 'belum pernah masuk',
                'url'     => route('admin.users.edit', $u->id),
            ])
            ->all();
    }

    /* ═══════════ tindakan ═══════════ */

    /**
     * Putuskan satu sesi.
     *
     * Administrator boleh memutus sesi siapa pun, termasuk sesinya
     * sendiri — memutus sesi sendiri dari perangkat yang tertinggal di
     * tempat lain justru salah satu alasan halaman ini ada.
     */
    public function putusSesi(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'string', 'max:255']]);

        $sesi = collect(Keamanan::sesiAktif())->firstWhere('id', $data['id']);

        if (!Keamanan::putusSesi($data['id'])) {
            return back()->withErrors(['keamanan' => 'Sesi itu sudah tidak ada — mungkin baru saja berakhir sendiri.']);
        }

        Keamanan::catat(Keamanan::SESI_DIPUTUS,
            'sesi '.($sesi['nama'] ?? 'tidak dikenal').' dari '.($sesi['ip'] ?? '—'));

        Diagnosa::lupakanRingkas();

        return back()->with('ok', 'Sesi diputus. Perangkat itu harus masuk lagi.');
    }

    public function bersihSesi()
    {
        $n = Keamanan::bersihkanSesiBasi();

        Keamanan::catat(Keamanan::SESI_DIPUTUS, $n.' sesi basi dibersihkan');
        Diagnosa::lupakanRingkas();

        return back()->with('ok', $n.' sesi kedaluwarsa dibersihkan.');
    }

    public function pangkasJejak()
    {
        $hari = (int) config('keamanan.simpan_jejak_hari', 90);
        $n    = Keamanan::pangkasJejak();

        ActivityLog::write('Pangkas jejak keamanan', $n.' baris lebih tua dari '.$hari.' hari', 'sistem');
        Diagnosa::lupakanRingkas();

        return back()->with('ok', $n.' baris jejak lebih tua dari '.$hari.' hari dihapus.');
    }
}
