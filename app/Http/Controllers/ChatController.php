<?php

namespace App\Http\Controllers;

use App\Models\{Percakapan, Pesan, User};
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Pesan — chat langsung antar pengguna dan grup perusahaan.
 *
 * Memakai tabel yang sama dengan Bantuan (percakapan/peserta/pesan); yang
 * membedakan hanya kolom jenis dan siapa yang terdaftar sebagai peserta.
 * Tidak ada konsep "peran asimetris" di sini seperti pada Bantuan — setiap
 * pengirim adalah sesama pengguna, jadi sisi gelembung ditentukan dengan
 * membandingkan user_id pesan dengan pemirsa yang sedang membuka halaman,
 * bukan dari kolom peran.
 */
class ChatController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();

        // Menjamin grup perusahaan ada dan keanggotaannya mutakhir sebelum
        // didaftar — kalau tidak, pengguna yang baru ditempatkan admin
        // tidak akan melihat grupnya sampai seseorang lain membuka halaman
        // ini lebih dulu. Dan melepas dari grup lama miliknya sendiri,
        // supaya perpindahan perusahaan tidak menyisakan keanggotaan yang
        // tak seorang pun lagi berkepentingan membersihkannya.
        Percakapan::keluarkanDariGrupLama($u);
        if ($u->company_id) {
            Percakapan::grupPerusahaan($u->company);
        }

        $daftar = $this->daftarUntuk($u);

        $pilih = (int) $r->query('percakapan');
        $aktif = $daftar->firstWhere('id', $pilih) ?? $daftar->first();

        if ($aktif) $this->tandaiDibaca($aktif, $u);

        return Inertia::render('Pesan/Kotak', [
            'judul'    => 'Pesan',
            'subjudul' => 'Percakapan langsung dan grup perusahaan',

            'percakapan' => $daftar->map(fn (Percakapan $p) => $this->ringkas($p, $u, $aktif))->values()->all(),
            'terpilih'   => $aktif?->id,
            'pesan'      => $aktif ? $this->pesanUntuk($aktif, $u) : [],
            'penggunaId' => $u->id,
        ]);
    }

    public function kirim(Request $r, Percakapan $percakapan)
    {
        abort_unless(
            in_array($percakapan->jenis, ['langsung', 'grup'], true) && $this->pesertaUtas($percakapan, $r->user()),
            403,
            'Anda bukan peserta percakapan ini.'
        );

        $d = $r->validate(['isi' => ['required', 'string', 'max:4000']], [], ['isi' => 'pesan']);

        $percakapan->pesan()->create(['user_id' => $r->user()->id, 'peran' => 'pengguna', 'isi' => $d['isi']]);
        $percakapan->pesan_terakhir_at = now();
        $percakapan->save();

        $this->tandaiDibaca($percakapan, $r->user());

        return back();
    }

    /**
     * Memulai atau membuka kembali percakapan langsung dengan seseorang.
     *
     * Batasnya sama dengan Direktori: pengguna biasa hanya menjangkau rekan
     * satu perusahaan, admin menjangkau siapa pun. Batas itu tidak diulang
     * dengan aturan sendiri di sini — mengizinkan pesan ke orang yang tidak
     * pernah terlihat di Direktori akan membocorkan keberadaan akun yang
     * seharusnya tersembunyi.
     */
    public function mulai(Request $r)
    {
        $d = $r->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $u    = $r->user();
        $lain = User::findOrFail($d['user_id']);

        abort_if($lain->id === $u->id, 422, 'Tidak dapat memulai percakapan dengan diri sendiri.');
        abort_unless(
            $u->isAdmin() || $lain->company_id === $u->company_id,
            403,
            'Anda hanya dapat mengirim pesan kepada rekan satu perusahaan.'
        );

        $p = Percakapan::langsungAntara($u, $lain);

        return redirect()->route('pesan.index', ['percakapan' => $p->id]);
    }

    /* ══════════════ bantu ══════════════ */

    private function daftarUntuk(User $u)
    {
        return Percakapan::whereIn('jenis', ['langsung', 'grup'])
            ->whereHas('peserta', fn ($q) => $q->where('users.id', $u->id))
            ->with(['peserta', 'company'])
            ->orderByDesc('pesan_terakhir_at')
            ->get();
    }

    private function ringkas(Percakapan $p, User $u, ?Percakapan $aktif): array
    {
        if ($p->jenis === 'grup') {
            $nama   = $p->judul ?? $p->company?->name ?? 'Grup Perusahaan';
            $avatar = $p->company?->effectiveLogo() ? asset('storage/' . $p->company->effectiveLogo()) : null;
        } else {
            $lawan  = $p->peserta->firstWhere('id', '!=', $u->id);
            $nama   = $lawan?->name ?? 'Pengguna terhapus';
            $avatar = $lawan?->avatar ? asset('storage/' . $lawan->avatar) : null;
        }

        return [
            'id'          => $p->id,
            'jenis'       => $p->jenis,
            'nama'        => $nama,
            'avatar'      => $avatar,
            'terakhir'    => $p->pesan_terakhir_at?->diffForHumans(),
            'belumDibaca' => $p->belumDibaca($u),
            'aktif'       => $aktif && $p->id === $aktif->id,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function pesanUntuk(Percakapan $p, User $pemirsa): array
    {
        return $p->pesan()->with('pengirim')->orderBy('id')->get()
            ->map(fn (Pesan $m) => [
                'id'        => $m->id,
                'isi'       => $m->isi,
                'nama'      => $m->pengirim?->name ?? 'Pengguna terhapus',
                'avatar'    => $m->pengirim?->avatar ? asset('storage/' . $m->pengirim->avatar) : null,
                'waktu'     => \App\Support\Waktu::lokal($m->created_at)?->format('d M · H:i'),
                'milikSaya' => $m->user_id === $pemirsa->id,
            ])->all();
    }

    private function pesertaUtas(Percakapan $p, User $u): bool
    {
        return $p->peserta()->where('users.id', $u->id)->exists();
    }

    private function tandaiDibaca(Percakapan $p, User $u): void
    {
        if (!$this->pesertaUtas($p, $u)) return;

        $p->peserta()->updateExistingPivot($u->id, [
            'dibaca_sampai_id' => $p->pesan()->max('id'),
        ]);
    }
}
