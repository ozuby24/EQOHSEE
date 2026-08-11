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
    public function index(Request $r)
    {
        return view('personalia.profil', ['u' => $r->user()]);
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
        if (isset($data['email']) && $data['email'] !== $u->email) {
            $u->email_verified_at = null;
        }

        $u->fill($data)->save();

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

    public function perusahaan(Request $r)
    {
        return view('personalia.perusahaan', [
            'p'      => $r->user()->company,
            'boleh'  => $this->bolehSuntingPerusahaan($r->user()),
            'daftar' => $r->user()->isAdmin() ? Company::orderBy('name')->get() : collect(),
        ]);
    }

    public function simpanPerusahaan(Request $r)
    {
        $u = $r->user();

        abort_unless($this->bolehSuntingPerusahaan($u), 403,
            'Hanya administrator atau PIC perusahaan yang dapat mengubah data ini.');

        $p = $u->company;
        abort_if($p === null, 404, 'Akun Anda belum terhubung ke perusahaan mana pun.');

        $data = $r->validate([
            'name'      => ['required', 'string', 'max:160'],
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

        $p = $u->company;
        abort_if($p === null, 404);

        if ($p->logo) Storage::disk('public')->delete($p->logo);

        $p->logo = null;
        $p->theme_color = null;
        $p->theme_dark = null;
        $p->save();

        return back()->with('sukses', 'Logo dihapus, tampilan kembali ke warna bawaan.');
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

        return view('personalia.direktori', [
            'orang' => $q->with('company')->paginate(24)->withQueryString(),
            'cari'  => $cari ?? '',
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
