<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\KirimPengingatTindakLanjut;
use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Pengaturan};
use App\Services\PengingatTindakLanjut;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Pengingat batas waktu tindak lanjut temuan audit SMKP (porting bagian
 * "Pengingat" dari Aurorasys Admin → Server → Email & Pengingat).
 *
 * Yang dipindahkan hanya pengingatnya: sakelar, jadwal, pratinjau 90 hari
 * ke depan, kirim-sekarang, dan riwayatnya. Server email EQOHSEE tetap
 * dari MAIL_* di .env — EQOHSEE tidak menyimpan sandi SMTP di basis data,
 * sehingga halaman ini hanya MELAPORKAN apakah pengiriman siap.
 *
 * Disimpan lewat App\Models\Pengaturan (tabel app_settings), kunci yang
 * sama dengan yang dibaca perintah terjadwal smkp:pengingat-tindak-lanjut.
 * Setiap perubahan dan pengiriman manual tercatat di log aktivitas modul
 * "pengingat" — sama dengan catatan pengiriman harian penjadwal.
 *
 * Penyesuaian EQOHSEE: tidak meminta sandi admin lagi (Aurorasys memintanya
 * di seluruh menu Server); halaman administrasi EQOHSEE lain — Integrasi
 * AI, Keamanan — pun tidak, dan gerbang `can:admin` sudah membatasinya.
 */
class PengingatController extends Controller
{
    public function index(PengingatTindakLanjut $pengingat)
    {
        return Inertia::render('Admin/Pengingat', [
            'judul'    => 'Pengingat Tindak Lanjut',
            'subjudul' => 'Email batas waktu tindak lanjut temuan audit SMKP kepada akun auditi',

            'pengingat' => [
                'aktif'   => PengingatTindakLanjut::aktif(),
                'jadwal'  => PengingatTindakLanjut::jadwal(),
                'pilihan' => PengingatTindakLanjut::JADWAL,
                'jam'     => str_replace(':', '.', PengingatTindakLanjut::JAM),
            ],

            // Server email dari .env — dilaporkan, tidak diubah dari sini.
            'surel' => [
                'siap'    => PengingatTindakLanjut::suratSiap(),
                'mailer'  => (string) config('mail.default'),
                'dari'    => PengingatTindakLanjut::pengirim()['address'],
            ],

            'mendatang' => fn () => $pengingat->mendatang(),

            'riwayat' => fn () => ActivityLog::query()->where('module', 'pengingat')
                ->latest('id')->limit(10)->get(['id', 'username', 'action', 'detail', 'created_at'])
                ->map(fn (ActivityLog $l) => [
                    'id'     => $l->id,
                    'oleh'   => $l->username ?: null,
                    'aksi'   => $l->action,
                    'detail' => $l->detail,
                    'waktu'  => Waktu::lokal($l->created_at)?->translatedFormat('d M Y · H:i'),
                ])->all(),

            'tautan' => [
                'simpan'   => route('admin.pengingat.simpan'),
                'kirim'    => route('admin.pengingat.kirim'),
                'pengguna' => route('admin.users.index'),
                'sistem'   => route('admin.system'),
            ],
        ]);
    }

    /** Sakelar & jadwal pengingat (Aurorasys SurelController::pengingat). */
    public function simpan(Request $request)
    {
        $d = $request->validate([
            'aktif'  => ['required', 'boolean'],
            'jadwal' => ['nullable', 'string', 'max:40'],
        ]);
        $aktif = filter_var($d['aktif'], FILTER_VALIDATE_BOOL);
        $jadwal = array_values(array_intersect(array_keys(PengingatTindakLanjut::JADWAL), explode(',', (string) ($d['jadwal'] ?? ''))));
        if ($aktif && !$jadwal) throw ValidationException::withMessages(['jadwal' => 'Pilih paling sedikit satu jadwal pengingat.']);

        Pengaturan::simpan([
            PengingatTindakLanjut::AKTIF        => $aktif ? '1' : '0',
            PengingatTindakLanjut::KUNCI_JADWAL => json_encode($jadwal),
        ]);
        $label = implode(' & ', array_map(fn (string $k) => PengingatTindakLanjut::JADWAL[$k], $jadwal));
        ActivityLog::write('Pengaturan pengingat', ($aktif ? 'nyala' : 'mati').($label !== '' ? " · {$label} sebelum batas waktu" : ''), 'pengingat');

        return back()->with('ok', $aktif ? "Pengingat aktif: {$label} sebelum batas waktu tindak lanjut." : 'Pengingat tindak lanjut dimatikan.');
    }

    /** Jalankan pengiriman harian sekarang — sama dengan penjadwal, aman diulang. */
    public function kirim(PengingatTindakLanjut $pengingat)
    {
        // Galat lewat withErrors, bukan kilat 'galat': kerangka EQOHSEE hanya
        // menggambar bilah sukses, halaman administrasi menggambar galatnya.
        if (!PengingatTindakLanjut::aktif()) return back()->withErrors(['pengingat' => 'Pengingat tindak lanjut sedang dimatikan.']);
        if (!PengingatTindakLanjut::suratSiap()) return back()->withErrors(['pengingat' => 'Server email belum diatur (MAIL_* di .env) — pengingat tidak dapat dikirim.']);

        $h = $pengingat->kirim();
        $ringkas = KirimPengingatTindakLanjut::ringkasan($h);
        if (!$h['email'] && !$h['gagal'] && !$h['tanpaPenerima']) {
            return back()->with('ok', 'Tidak ada pengingat yang jatuh tempo hari ini.');
        }
        ActivityLog::write('Pengingat tindak lanjut (manual)', $ringkas, 'pengingat');

        return $h['gagal']
            ? back()->withErrors(['pengingat' => ucfirst($ringkas).'.'])
            : back()->with('ok', ucfirst($ringkas).'.');
    }
}
