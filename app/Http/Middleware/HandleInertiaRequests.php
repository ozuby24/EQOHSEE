<?php

namespace App\Http\Middleware;

use App\Support\{IkonNav, Lencana, Media, Menu, RuteInertia, Tema};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use App\Support\Berkas;
use App\Support\Perusahaan;

/**
 * Data yang dibagikan ke seluruh halaman Inertia.
 *
 * Isinya sengaja dibentuk sama dengan yang dipakai bilah samping Blade,
 * dan keduanya membaca App\Support\Menu yang sama. Menyusun menu sendiri
 * di sini berarti dua daftar yang harus diubah bersama setiap kali ada
 * halaman baru — dan yang tertinggal tidak menimbulkan galat, hanya menu
 * yang diam-diam berbeda antara halaman Blade dan halaman Vue.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app-inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $u = $request->user();

        return array_merge(parent::share($request), [
            'pengguna' => $u ? [
                'id'     => $u->id,
                'nama'   => $u->name,
                'peran'  => $u->position ?: ($u->isAdmin() ? 'Administrator' : ucfirst($u->lms_role ?: 'Peserta')),
                'admin'  => $u->isAdmin(),
                'avatar' => $u->avatar ? Berkas::terbuka($u->avatar) : null,

                /* Nama perusahaannya ikut dikirim karena kepala halaman
                   menyebutkannya. Pengguna yang tidak terikat perusahaan
                   mana pun — admin lintas perusahaan — memulangkan null,
                   dan kepala halamannya menuliskan "Semua perusahaan"
                   alih-alih kotak kosong yang terbaca sebagai galat. */
                /* Bagi administrator, null berarti "semua perusahaan"
                   dan harus tetap null — jatuh ke nama perusahaannya
                   sendiri membuat "seluruhnya" tidak dapat dibedakan
                   dari "perusahaan saya", dan pilihan yang tidak dapat
                   dibedakan dari pilihan lain adalah pilihan yang
                   tidak pernah terlihat berpindah. */
                'perusahaan' => $u->isAdmin()
                    ? Perusahaan::namaTerpilih($u)
                    : $u->company?->name,

                /* Daftar KOSONG bagi pengguna biasa, dan bilah atasnya
                   menggambar label biasa alih-alih pemilih. Pemilih
                   yang dapat dibuka tetapi tidak dapat mengubah apa pun
                   lebih membingungkan daripada tulisan. */
                'perusahaanPilihan'  => Perusahaan::dapatDipilih($u),
                'perusahaanDilihat'  => Perusahaan::terpilih($u),
            ] : null,

            'menu' => fn () => $this->menu($u),

            /* Ditutup dalam closure supaya dibaca saat halaman disusun,
               bukan saat middleware dipasang — pesan kilat baru ada
               setelah pengalihan terjadi. */
            'kilat' => fn () => [
                'sukses' => $request->session()->get('sukses') ?? $request->session()->get('ok'),
                'galat'  => $request->session()->get('galat'),

                /* Jawaban asisten AI dibawa terpisah dari 'sukses'.
                   Jawabannya berparagraf, sementara bilah 'sukses'
                   dirancang untuk satu kalimat — dan yang paling perlu
                   dibaca justru akan terpotong di situ. */
                'aiJawaban' => $request->session()->get('aiJawaban'),
            ],

            'status' => fn () => $request->session()->get('status'),

            'pengumuman' => fn () => Schema::hasTable('news')
                ? \App\Models\News::where('created_at', '>=', now()->subDays(30))->count()
                : 0,

            'tema'  => Tema::pilihan($u),
            'warna' => [
                'aksen' => Tema::aksen($u),
                'dasar' => Tema::dasar($u),
            ],

            /* Latar halaman masuk. Dibagikan dari sini, bukan dari tiap
               pengendali autentikasi: masuk, daftar, lupa sandi, dan atur
               ulang sandi semuanya memakai tata letak yang sama, dan yang
               satu terlewat tidak menimbulkan galat — latarnya hanya
               diam-diam kembali menjadi foto lama pada satu halaman. */
            'mediaMasuk' => fn () => [
                'video'  => Media::masukVideo(),
                'poster' => Media::masukPoster(),
            ],
        ]);
    }

    /**
     * Menu disusun menjadi bentuk yang siap digambar.
     *
     * Nama rute diubah jadi URL di sini, bukan di sisi Vue: helper rute
     * Laravel tidak ada di peramban, dan menyalin daftar rutenya ke sana
     * hanya memindahkan masalah yang sama ke tempat yang lebih sulit
     * diperiksa.
     */
    private function menu($u): array
    {
        $kunci = Menu::modulAktif();
        $aktif = Menu::modul($kunci);

        // Sekali per permintaan, bukan sekali per butir — lihat App\Support\Lencana.
        $lencana = Lencana::semua($u);

        $modul = [];
        foreach (Menu::untuk($u) as $k => $m) {
            $rute = Menu::ruteAwal($m);

            $modul[] = [
                'kunci'   => $k,
                'label'   => $m['label'],
                'ikon'    => $m['icon'],
                'url'     => $rute ? route($rute) : '#',
                'aktif'   => $k === $kunci,
                'lencana' => Lencana::modul($m, $lencana),

                /* Menentukan <Link> atau <a> di sisi Vue. Salah menandai
                   di sini bukan sekadar membuat perpindahan lebih lambat —
                   <Link> ke halaman Blade tidak berpindah sama sekali. */
                'inertia' => RuteInertia::ada($rute),
            ];
        }

        $grup = [];
        foreach ($aktif['groups'] as $nama => $butir) {
            $isi = [];
            foreach ($butir as [$label, $rute, $cocok]) {
                $isi[] = [
                    'label'   => $label,
                    'url'     => route($rute),
                    'aktif'   => request()->is($cocok),
                    'ikon'    => IkonNav::JALUR[IkonNav::nama($label)],
                    'inertia' => RuteInertia::ada($rute),
                    'lencana' => $lencana[$rute] ?? null,
                ];
            }
            $grup[] = ['nama' => (string) $nama, 'butir' => $isi];
        }

        return [
            'modul' => $modul,
            'kunci' => $kunci,
            'label' => $aktif['label'],
            'grup'  => $grup,

            /* Tema modul, bila ia menyatakan satu. Dikirim sebagai NAMA,
               bukan sebagai daftar warna: yang menentukan rupa sebuah
               tema adalah lembar gayanya, dan mengirim warnanya lewat
               prop berarti dua tempat yang harus sama selamanya. */
            'tema'  => $aktif['tema'] ?? null,

            /* Semboyan modul, untuk kop halaman. Ditulis sekali di
               tempat modulnya didefinisikan, bukan diulang di tiap
               halaman — diulang, ia akan berselisih antar halaman pada
               modul yang sama, dan selisih itu terbaca sebagai halaman
               yang dikerjakan orang berbeda. */
            'semboyan' => $aktif['semboyan'] ?? null,

            /* Alamat akar modul, untuk remah roti di kop. */
            'akar' => ($r = Menu::ruteAwal($aktif)) ? route($r) : null,
        ];
    }
}
