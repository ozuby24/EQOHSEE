<?php

namespace App\Http\Controllers;

use App\Models\{Percakapan, Pesan, User};
use App\Support\AsistenAI;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Bantuan — kotak percakapan dengan asisten AI dan dengan admin.
 *
 * Dua saluran dalam satu utas, bukan dua utas terpisah: pertanyaan yang tidak
 * terjawab asisten hampir selalu berlanjut ke admin, dan admin perlu membaca
 * apa yang sudah dicoba. Memisahkannya memaksa orang mengetik ulang keluhannya
 * dari awal.
 */
class BantuanController extends Controller
{
    /** Saluran tujuan sebuah pertanyaan. */
    public const SALURAN = ['ai', 'admin'];

    public function index(Request $r)
    {
        $u = $r->user();
        $p = $this->utas($u);

        $this->tandaiDibaca($p, $u);

        return Inertia::render('Bantuan/Kotak', [
            'judul'    => 'Bantuan',
            'subjudul' => 'Tanya asisten AI, atau teruskan ke admin',

            'aiAktif' => AsistenAI::aktif(),
            'pesan'   => $this->pesanUntuk($p),
            'status'  => $p->status,
            'admin'   => $u->isAdmin(),
        ]);
    }

    public function kirim(Request $r)
    {
        $u = $r->user();

        $d = $r->validate([
            'isi'     => ['required', 'string', 'max:4000'],
            'saluran' => ['required', 'in:' . implode(',', self::SALURAN)],
        ], [], ['isi' => 'pertanyaan']);

        $p = $this->utas($u);

        $p->pesan()->create(['user_id' => $u->id, 'peran' => 'pengguna', 'isi' => $d['isi']]);

        if ($d['saluran'] === 'ai') {
            /*
             * Jawaban asisten disimpan sebagai pesan biasa, termasuk ketika
             * gagal. Kegagalan yang hanya lewat sebagai notifikasi membuat
             * orang mengira pertanyaannya tidak terkirim, lalu mengetiknya
             * lagi — dan riwayat yang dibaca admin jadi penuh pengulangan.
             */
            $hasil = AsistenAI::jawab($this->riwayat($p));

            $p->pesan()->create([
                'peran' => $hasil['ok'] ? 'asisten' : 'sistem',
                'isi'   => $hasil['isi'],
            ]);
        } else {
            // Diteruskan ke admin: utasnya dibuka kembali supaya muncul lagi
            // di kotak masuk meskipun sebelumnya sudah ditandai selesai.
            $p->pesan()->create([
                'peran' => 'sistem',
                'isi'   => 'Pertanyaan diteruskan ke admin. Anda akan menerima jawabannya di sini.',
            ]);

            $p->status = 'terbuka';
        }

        $p->pesan_terakhir_at = now();
        $p->save();

        $this->tandaiDibaca($p, $u);

        return back();
    }

    /* ══════════════ sisi admin ══════════════ */

    public function masuk(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403, 'Hanya administrator yang dapat membuka kotak masuk bantuan.');

        $daftar = Percakapan::where('jenis', 'bantuan')
            ->with(['peserta', 'company'])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['terbuka'])
            ->orderByDesc('pesan_terakhir_at')
            ->get();

        $pilih = (int) $r->query('utas');
        $aktif = $daftar->firstWhere('id', $pilih) ?? $daftar->first();

        if ($aktif) $this->tandaiDibaca($aktif, $r->user());

        return Inertia::render('Bantuan/Masuk', [
            'judul'    => 'Kotak Masuk Bantuan',
            'subjudul' => 'Pertanyaan pengguna yang diteruskan ke admin',

            'utas' => $daftar->map(fn (Percakapan $p) => [
                'id'         => $p->id,
                // Peserta pertama adalah pemilik utas; utas bantuan selalu
                // dibuat dengan tepat satu pengguna.
                'nama'       => $p->peserta->first()?->name ?? 'Pengguna terhapus',
                'perusahaan' => $p->company?->name,
                'status'     => $p->status,
                'terakhir'   => $p->pesan_terakhir_at?->diffForHumans(),
                'aktif'      => $aktif && $p->id === $aktif->id,
            ])->all(),

            'terpilih' => $aktif?->id,
            'pesan'    => $aktif ? $this->pesanUntuk($aktif) : [],
            'status'   => $aktif?->status,
        ]);
    }

    public function balas(Request $r, Percakapan $percakapan)
    {
        abort_unless($r->user()->isAdmin(), 403);

        $d = $r->validate(['isi' => ['required', 'string', 'max:4000']], [], ['isi' => 'balasan']);

        $percakapan->pesan()->create([
            'user_id' => $r->user()->id,
            'peran'   => 'admin',
            'isi'     => $d['isi'],
        ]);

        $percakapan->pesan_terakhir_at = now();
        $percakapan->status = 'terbuka';
        $percakapan->save();

        $this->tandaiDibaca($percakapan, $r->user());

        return back();
    }

    public function selesai(Request $r, Percakapan $percakapan)
    {
        abort_unless($r->user()->isAdmin(), 403);

        $percakapan->status = 'selesai';
        $percakapan->save();

        // Ditandai di dalam utasnya, bukan hanya di kolom status: pemakai
        // melihat percakapannya sendiri, bukan kotak masuk admin.
        $percakapan->pesan()->create([
            'peran' => 'sistem',
            'isi'   => 'Percakapan ditandai selesai oleh admin. Kirim pesan lagi bila masih ada yang perlu ditanyakan.',
        ]);

        return back()->with('sukses', 'Percakapan ditandai selesai.');
    }

    /* ══════════════ bantu ══════════════ */

    /** Utas bantuan milik seorang pengguna; dibuat saat pertama dibuka. */
    private function utas(User $u): Percakapan
    {
        $p = Percakapan::where('jenis', 'bantuan')
            ->whereHas('peserta', fn ($q) => $q->where('users.id', $u->id))
            ->first();

        if ($p) return $p;

        $p = Percakapan::create([
            'jenis'      => 'bantuan',
            'judul'      => 'Bantuan — ' . $u->name,
            'company_id' => $u->company_id,
        ]);

        $p->peserta()->attach($u->id);

        return $p;
    }

    /** @return array<int,array<string,mixed>> */
    private function pesanUntuk(Percakapan $p): array
    {
        return $p->pesan()->with('pengirim')->orderBy('id')->get()
            ->map(fn (Pesan $m) => [
                'id'      => $m->id,
                'peran'   => $m->peran,
                'isi'     => $m->isi,
                'nama'    => $m->pengirim?->name,
                'waktu'   => \App\Support\Waktu::lokal($m->created_at)?->format('d M · H:i'),
            ])->all();
    }

    /** Riwayat untuk asisten: hanya giliran pengguna dan asisten. */
    private function riwayat(Percakapan $p): array
    {
        return $p->pesan()->whereIn('peran', ['pengguna', 'asisten'])->orderBy('id')->get()
            ->map(fn (Pesan $m) => ['peran' => $m->peran, 'isi' => $m->isi])->all();
    }

    private function tandaiDibaca(Percakapan $p, User $u): void
    {
        if (!$p->peserta()->where('users.id', $u->id)->exists()) return;

        $p->peserta()->updateExistingPivot($u->id, [
            'dibaca_sampai_id' => $p->pesan()->max('id'),
        ]);
    }
}
