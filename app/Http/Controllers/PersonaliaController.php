<?php

namespace App\Http\Controllers;

use App\Models\{Company, User};
use App\Support\WarnaLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Personalia — data diri, kontak, dan identitas perusahaan.
 *
 * Berdiri sebagai modul tersendiri di depan Learning Center: yang diurus
 * di sini bukan pembelajaran melainkan siapa penggunanya dan di bawah
 * perusahaan mana ia bekerja, dan hampir seluruh modul lain bergantung
 * pada jawaban itu.
 */
class PersonaliaController extends Controller
{
    /** Medan data diri; dipakai halaman untuk menggambar isian. */
    public const MEDAN = [
        ['name',        'Nama Lengkap',      'text',  true],
        ['email',       'Surel',             'email', true],
        ['employee_id', 'NIK / Nomor Induk', 'text',  false],
        ['position',    'Jabatan',           'text',  false],
        ['department',  'Departemen',        'text',  false],
        ['phone',       'Telepon',           'text',  false],
        ['whatsapp',    'WhatsApp',          'text',  false],
    ];

    public function index(Request $r)
    {
        $u = $r->user();
        $p = $u->company;

        $isian = [];
        foreach (self::MEDAN as [$k]) $isian[$k] = (string) ($u->{$k} ?? '');
        $isian['bio'] = (string) ($u->bio ?? '');

        return \Inertia\Inertia::render('Personalia/Profil', [
            'judul'    => 'Data Diri',
            'subjudul' => 'Kontak, jabatan, dan foto yang melekat pada akun Anda',

            'medan' => array_map(fn ($m) => [
                'nama' => $m[0], 'label' => $m[1], 'tipe' => $m[2], 'wajib' => $m[3],
            ], self::MEDAN),
            'isian'  => $isian,
            'avatar' => $u->avatar ? asset('storage/' . $u->avatar) : null,
            'inisial' => mb_strtoupper(mb_substr($u->name, 0, 1)),

            'perusahaan' => $p ? [
                'nama'     => $p->name,
                'jenis'    => $p->izin_type ?: 'Perusahaan',
                'lokasi'   => $p->location ?: null,
                'logo'     => $p->effectiveLogo() ? asset('storage/' . $p->effectiveLogo()) : null,
            ] : null,
            'urlPerusahaan' => route('personalia.perusahaan'),
        ]);
    }

    public function simpanProfil(Request $r)
    {
        $u = $r->user();

        $data = $r->validate([
            'name'        => ['required', 'string', 'max:120'],
            'email'       => ['required', 'email', 'max:160', Rule::unique('users')->ignore($u->id)],
            'employee_id' => ['nullable', 'string', 'max:40'],
            'position'    => ['nullable', 'string', 'max:120'],
            'department'  => ['nullable', 'string', 'max:120'],
            'phone'       => ['nullable', 'string', 'max:32'],
            'whatsapp'    => ['nullable', 'string', 'max:32'],
            'bio'         => ['nullable', 'string', 'max:300'],
            'avatar'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], [
            'name' => 'nama', 'email' => 'surel', 'employee_id' => 'NIK',
            'position' => 'jabatan', 'department' => 'departemen',
            'phone' => 'telepon', 'bio' => 'keterangan singkat',
        ]);

        if ($r->hasFile('avatar')) {
            // Foto lama dihapus supaya penyimpanan tidak menumpuk berkas
            // yang sudah tidak dirujuk siapa pun.
            if ($u->avatar) Storage::disk('public')->delete($u->avatar);
            $data['avatar'] = $r->file('avatar')->store('avatar', 'public');
        } else {
            unset($data['avatar']);
        }

        // Mengubah surel membatalkan verifikasinya — alamat baru belum
        // terbukti milik orang yang sama.
        $gantiSurel = isset($data['email']) && $data['email'] !== $u->email;

        if ($gantiSurel) $u->email_verified_at = null;

        $u->fill($data)->save();

        /* Kode dikirim ke alamat BARU begitu disimpan. Tanpa ini orang
           terlempar ke halaman verifikasi tanpa pernah menerima apa pun,
           dan satu-satunya jalan keluar adalah menebak bahwa ia harus
           menekan "kirim ulang". */
        if ($gantiSurel) {
            $u->sendEmailVerificationNotification();

            return back()->with('sukses', 'Surel diganti. Kode verifikasi dikirim ke alamat baru.');
        }

        return back()->with('sukses', 'Data diri tersimpan.');
    }

    public function hapusAvatar(Request $r)
    {
        $u = $r->user();

        if ($u->avatar) {
            Storage::disk('public')->delete($u->avatar);
            $u->avatar = null;
            $u->save();
        }

        return back()->with('sukses', 'Foto profil dihapus.');
    }

    /** Tema tersimpan di akun supaya ikut berpindah antar perangkat. */
    public function tema(Request $r)
    {
        $data = $r->validate(['tema' => ['nullable', Rule::in(['terang', 'gelap'])]]);

        $u = $r->user();
        $u->tema = $data['tema'] ?? null;
        $u->save();

        return $r->wantsJson()
            ? response()->json(['tema' => $u->tema])
            : back();
    }

    /**
     * Medan identitas perusahaan.
     *
     * [nama, label, wajib, lebar, khususAdmin]. 'lebar' menandai isian
     * selebar dua kolom; 'khususAdmin' menandai medan yang hanya boleh
     * disentuh administrator.
     *
     * Nama dan kode adalah identitas perusahaan itu sendiri — dipakai
     * modul lain untuk mengenalinya dan tercetak pada kop dokumen. PIC
     * merawat isi datanya, tetapi tidak menamai ulang perusahaan tempat
     * orang lain juga bernaung.
     */
    public const MEDAN_PERUSAHAAN = [
        ['name',      'Nama Perusahaan',               true,  true,  true],
        ['code',      'Kode',                          false, false, true],
        ['izin_type', 'Jenis Izin (IUP / IUJP)',       false, false, false],
        ['commodity', 'Komoditas',                     false, false, false],
        ['location',  'Lokasi',                        false, false, false],
        ['ktt',       'Kepala Teknik Tambang',         false, false, false],
        ['pjo',       'Penanggung Jawab Operasional',  false, false, false],
        ['pic_name',  'Nama PIC',                      false, false, false],
        ['pic_email', 'Surel PIC',                     false, false, false],
        ['pic_phone', 'Telepon PIC',                   false, false, false],
    ];

    /** Medan yang hanya administrator boleh ubah. */
    public static function medanAdmin(): array
    {
        return array_values(array_column(array_filter(self::MEDAN_PERUSAHAAN, fn ($m) => $m[4]), 0));
    }

    /**
     * Perusahaan yang sedang dibuka.
     *
     * Administrator boleh berpindah antar perusahaan lewat ?perusahaan=,
     * dan pilihannya diingat sepanjang sesi. Selain admin, tidak ada yang
     * bisa keluar dari perusahaannya sendiri — parameternya diabaikan,
     * bukan ditolak, sebab tautan yang dibagikan admin tidak boleh
     * menjatuhkan orang lain ke halaman galat.
     */
    private function perusahaanAktif(Request $r): ?Company
    {
        $u = $r->user();

        if (!$u->isAdmin()) return $u->company;

        $id = $r->query('perusahaan') ?? session('personalia_perusahaan');
        $p  = $id ? Company::find($id) : null;
        $p ??= $u->company ?? Company::orderBy('name')->first();

        if ($p) session(['personalia_perusahaan' => $p->id]);

        return $p;
    }

    public function perusahaan(Request $r)
    {
        $u = $r->user();
        $p = $this->perusahaanAktif($r);

        $isian = [];
        foreach (self::MEDAN_PERUSAHAAN as [$k]) $isian[$k] = (string) ($p->{$k} ?? '');
        $isian['address'] = (string) ($p->address ?? '');

        return \Inertia\Inertia::render('Personalia/Perusahaan', [
            'judul'    => 'Data Perusahaan',
            'subjudul' => 'Identitas, kontak, dan logo yang mewarnai tampilan aplikasi',

            'medan' => array_map(fn ($m) => [
                'nama' => $m[0], 'label' => $m[1], 'wajib' => $m[2],
                'lebar' => $m[3], 'khususAdmin' => $m[4],
            ], self::MEDAN_PERUSAHAAN),

            'ada'   => $p !== null,
            'nama'  => $p?->name,
            'isian' => $p ? $isian : null,

            /* Administrator memilih perusahaan mana yang dibuka, dan hanya
               dia yang boleh menambah perusahaan baru. */
            'admin'  => $u->isAdmin(),
            'aktif'  => $p?->id,
            'daftar' => $u->isAdmin()
                ? Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all()
                : [],

            'logo'      => $p?->effectiveLogo() ? asset('storage/' . $p->effectiveLogo()) : null,
            // Hanya logo milik perusahaan ini yang boleh dihapus; effectiveLogo()
            // bisa memulangkan logo bawaan yang bukan miliknya.
            'logoSendiri' => (bool) $p?->logo,
            'warna'     => $p?->theme_color ? [
                ['nama' => 'Aksen', 'hex' => $p->theme_color],
                ['nama' => 'Dasar', 'hex' => $p->theme_dark],
            ] : [],

            'bisaSunting' => $this->bolehSuntingPerusahaan($u),
        ]);
    }

    public function simpanPerusahaan(Request $r)
    {
        $u = $r->user();

        abort_unless($this->bolehSuntingPerusahaan($u), 403,
            'Hanya administrator atau PIC perusahaan yang dapat mengubah data ini.');

        $p = $this->perusahaanAktif($r);
        abort_if($p === null, 404, 'Akun Anda belum terhubung ke perusahaan mana pun.');

        $data = $r->validate([
            'name'      => [$u->isAdmin() ? 'required' : 'nullable', 'string', 'max:160'],
            'code'      => ['nullable', 'string', 'max:40'],
            'izin_type' => ['nullable', 'string', 'max:40'],
            'commodity' => ['nullable', 'string', 'max:80'],
            'location'  => ['nullable', 'string', 'max:160'],
            'address'   => ['nullable', 'string', 'max:400'],
            'ktt'       => ['nullable', 'string', 'max:120'],
            'pjo'       => ['nullable', 'string', 'max:120'],
            'pic_name'  => ['nullable', 'string', 'max:120'],
            'pic_email' => ['nullable', 'email', 'max:160'],
            'pic_phone' => ['nullable', 'string', 'max:32'],
            'logo'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ], [], ['name' => 'nama perusahaan']);

        /*
         * Medan identitas dibuang bagi yang bukan administrator.
         *
         * Layar sudah menggambarnya sebagai isian mati, tetapi layar mati
         * bukan penjagaan — kiriman tetap bisa disusun tangan. Dibuang di
         * sini, bukan ditolak, supaya PIC yang menyimpan perubahan sah pada
         * medan lain tidak terhalang oleh medan yang tidak pernah ia sentuh.
         */
        if (!$u->isAdmin()) {
            foreach (self::medanAdmin() as $k) unset($data[$k]);
        }

        if ($r->hasFile('logo')) {
            if ($p->logo) Storage::disk('public')->delete($p->logo);
            $data['logo'] = $r->file('logo')->store('logo', 'public');

            // Warna diambil sekali saat logonya berganti, lalu disimpan.
            // Mencacah piksel pada tiap pemuatan halaman berarti seluruh
            // aplikasi menunggu pekerjaan yang hasilnya tidak berubah.
            $warna = WarnaLogo::dari(Storage::disk('public')->path($data['logo']));

            // SVG tidak dapat dibaca GD, dan logo yang seluruhnya abu-abu
            // tidak punya warna khas. Keduanya wajar — warna lama
            // dikosongkan supaya tampilannya kembali ke bawaan EQOHSEE
            // alih-alih menyisakan warna milik logo sebelumnya.
            $data['theme_color'] = $warna['terang'] ?? null;
            $data['theme_dark']  = $warna['gelap'] ?? null;
        } else {
            unset($data['logo']);
        }

        $p->fill($data)->save();

        return back()->with('sukses', 'Data perusahaan tersimpan.');
    }

    public function hapusLogo(Request $r)
    {
        $u = $r->user();
        abort_unless($this->bolehSuntingPerusahaan($u), 403);

        $p = $this->perusahaanAktif($r);
        abort_if($p === null, 404);

        if ($p->logo) Storage::disk('public')->delete($p->logo);

        $p->logo = null;
        $p->theme_color = null;
        $p->theme_dark = null;
        $p->save();

        return back()->with('sukses', 'Logo dihapus, tampilan kembali ke warna bawaan.');
    }

    /**
     * Menambah perusahaan baru — administrator saja.
     *
     * Perusahaan adalah batas pemisah data antar penyewa: siapa pun yang
     * bisa membuatnya bisa membuat wadah baru dan memindahkan orang ke
     * dalamnya. Karena itu tidak diserahkan kepada PIC.
     */
    public function tambahPerusahaan(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403, 'Hanya administrator yang dapat menambah perusahaan.');

        $d = $r->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
        ], [], ['name' => 'nama perusahaan']);

        $p = Company::create($d);

        session(['personalia_perusahaan' => $p->id]);

        /*
         * Dialihkan ke perusahaan barunya, bukan back().
         *
         * back() memulangkan alamat asal yang masih membawa ?perusahaan=
         * milik perusahaan sebelumnya, dan kueri itu mengalahkan pilihan
         * yang baru saja disimpan di sesi: perusahaannya benar-benar
         * dibuat, tetapi layar tetap memperlihatkan yang lama seolah
         * tombolnya tidak bekerja.
         */
        return redirect()
            ->route('personalia.perusahaan', ['perusahaan' => $p->id])
            ->with('sukses', "Perusahaan {$p->name} ditambahkan.");
    }

    /**
     * Menetapkan perusahaan seorang pengguna — administrator saja.
     *
     * Ini yang menentukan data siapa yang boleh dilihat orang itu, jadi
     * bukan sekadar isian identitas. Menyerahkannya kepada pemakai berarti
     * siapa pun bisa memindahkan dirinya ke perusahaan mana pun dan ikut
     * membaca isinya.
     */
    public function tetapkanPerusahaan(Request $r, User $pengguna)
    {
        abort_unless($r->user()->isAdmin(), 403,
            'Hanya administrator yang dapat menetapkan perusahaan seorang pengguna.');

        $d = $r->validate(['company_id' => ['nullable', 'exists:companies,id']]);

        $pengguna->company_id = $d['company_id'] ?: null;
        $pengguna->save();

        $nama = $pengguna->fresh()->company?->name ?? 'tanpa perusahaan';

        return back()->with('sukses', "{$pengguna->name} kini terdaftar di {$nama}.");
    }

    /** Direktori rekan satu perusahaan. */
    public function direktori(Request $r)
    {
        $u = $r->user();

        $q = User::query()->orderBy('name');

        // Pengguna biasa hanya melihat rekan satu perusahaan; admin melihat
        // seluruhnya. Direktori yang membocorkan kontak lintas perusahaan
        // adalah kebocoran data, bukan fitur.
        if (!$u->isAdmin()) {
            $q->where('company_id', $u->company_id);
        }

        if ($cari = trim((string) $r->query('cari'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$cari}%")
                                   ->orWhere('position', 'like', "%{$cari}%")
                                   ->orWhere('department', 'like', "%{$cari}%"));
        }

        $hal = $q->with('company')->paginate(24)->withQueryString();

        return \Inertia\Inertia::render('Personalia/Direktori', [
            'judul'    => 'Direktori',
            'subjudul' => 'Kontak rekan kerja di perusahaan Anda',

            'cari'  => (string) $cari,

            /* Hanya administrator yang boleh menetapkan perusahaan seorang
               pengguna; bagi yang lain daftarnya tidak dikirim sama sekali,
               bukan sekadar tidak digambar. */
            'admin'  => $u->isAdmin(),
            'daftar' => $u->isAdmin()
                ? Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all()
                : [],

            'orang' => array_map(fn (User $o) => [
                'id'         => $o->id,
                'nama'       => $o->name,
                'inisial'    => mb_strtoupper(mb_substr($o->name, 0, 1)),
                'jabatan'    => $o->position ?: null,
                'departemen' => $o->department ?: null,
                'email'      => $o->email ?: null,
                'telepon'    => $o->phone ?: null,
                'avatar'     => $o->avatar ? asset('storage/' . $o->avatar) : null,
                // Nama perusahaan hanya berarti bagi admin, sebab hanya dia
                // yang melihat lintas perusahaan.
                'perusahaan' => $u->isAdmin() ? $o->company?->name : null,
                'perusahaanId' => $u->isAdmin() ? $o->company_id : null,
            ], $hal->items()),

            'halaman' => [
                'kini'   => $hal->currentPage(),
                'akhir'  => $hal->lastPage(),
                'total'  => $hal->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'],
                    'url'   => $t['url'],
                    'aktif' => (bool) $t['active'],
                ], $hal->linkCollection()->all()),
            ],
        ]);
    }

    private function bolehSuntingPerusahaan(User $u): bool
    {
        if ($u->isAdmin()) return true;

        // PIC perusahaan boleh merawat datanya sendiri tanpa harus jadi
        // administrator seluruh aplikasi.
        $p = $u->company;

        return $p !== null
            && $p->pic_email !== null
            && strcasecmp($p->pic_email, $u->email) === 0;
    }
}
