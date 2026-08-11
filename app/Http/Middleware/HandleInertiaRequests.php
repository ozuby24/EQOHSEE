<?php

namespace App\Http\Middleware;

use App\Support\{IkonNav, Menu, Tema};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

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
                'avatar' => $u->avatar ? asset('storage/'.$u->avatar) : null,
            ] : null,

            'menu' => fn () => $this->menu($u),

            /* Ditutup dalam closure supaya dibaca saat halaman disusun,
               bukan saat middleware dipasang — pesan kilat baru ada
               setelah pengalihan terjadi. */
            'kilat' => fn () => [
                'sukses' => $request->session()->get('sukses') ?? $request->session()->get('ok'),
                'galat'  => $request->session()->get('galat'),
            ],

            'pengumuman' => fn () => Schema::hasTable('news')
                ? \App\Models\News::where('created_at', '>=', now()->subDays(30))->count()
                : 0,

            'tema'  => Tema::pilihan($u),
            'warna' => [
                'aksen' => Tema::aksen($u),
                'dasar' => Tema::dasar($u),
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

        $modul = [];
        foreach (Menu::untuk($u) as $k => $m) {
            $rute = Menu::ruteAwal($m);

            $modul[] = [
                'kunci' => $k,
                'label' => $m['label'],
                'ikon'  => $m['icon'],
                'url'   => $rute ? route($rute) : '#',
                'aktif' => $k === $kunci,
            ];
        }

        $grup = [];
        foreach ($aktif['groups'] as $nama => $butir) {
            $isi = [];
            foreach ($butir as [$label, $rute, $cocok]) {
                $isi[] = [
                    'label' => $label,
                    'url'   => route($rute),
                    'aktif' => request()->is($cocok),
                    'ikon'  => IkonNav::JALUR[IkonNav::nama($label)],
                ];
            }
            $grup[] = ['nama' => (string) $nama, 'butir' => $isi];
        }

        return ['modul' => $modul, 'label' => $aktif['label'], 'grup' => $grup];
    }
}
