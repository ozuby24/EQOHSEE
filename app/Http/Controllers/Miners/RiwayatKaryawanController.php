<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Miners\{Pekerja, Pelanggaran};
use App\Support\Miners\{AksesMiners, RiwayatKaryawan};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Miners → Monitoring → Riwayat Karyawan (port DBEST #22).
 *
 * Tiga tab: Cek Karyawan (cari NIK/nama/nomor induk lintas perusahaan
 * dalam satu grup IUP), Karyawan Nonaktif (alasan, tahun, rekomendasi),
 * dan Rekap Pelanggaran (ringkasan, sebaran, rekap per karyawan, daftar
 * catatan + verifikasi). Aturan siapa melihat dan mengubah apa tinggal di
 * App\Support\Miners\RiwayatKaryawan.
 *
 * Data pribadi (UU No. 27 Tahun 2022): pencarian oleh selain pengelola
 * dan setiap perubahan tercatat di log aktivitas.
 */
class RiwayatKaryawanController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $tab = in_array($r->query('tab'), ['cek', 'nonaktif', 'pelanggaran'], true) ? $r->query('tab') : 'cek';
        $q = trim((string) $r->query('q', ''));

        $data = ['tab' => $tab, 'q' => $q];

        if ($tab === 'cek' && $q !== '') {
            $data['orang'] = RiwayatKaryawan::susunOrang(RiwayatKaryawan::cari($q, $u), $u);
            if (! RiwayatKaryawan::pengelola($u)) {
                ActivityLog::write('Cek riwayat karyawan', mb_substr($q, 0, 4).'…', 'miners');
            }
        }

        $f = [
            'sebab'       => (string) $r->query('sebab', ''),
            'perusahaan'  => (string) $r->query('perusahaan', ''),
            'tahun'       => (string) $r->query('tahun', ''),
            'rekomendasi' => (string) $r->query('rekomendasi', ''),
            'q'           => $tab === 'nonaktif' ? $q : '',
            'kategori'    => (string) $r->query('kategori', ''),
            'tingkat'     => (string) $r->query('tingkat', ''),
            'status'      => (string) $r->query('status', ''),
        ];
        if ($tab === 'nonaktif') $data['nonaktif'] = RiwayatKaryawan::daftarNonaktif($f, $u);
        if ($tab === 'pelanggaran') $data['rekap'] = RiwayatKaryawan::rekap($f, $u);

        $grup = RiwayatKaryawan::grup($u);

        return Inertia::render('Miners/RiwayatKaryawan', $data + [
            'saring'     => $f,
            'pengelola'  => RiwayatKaryawan::pengelola($u),
            'menunggu'   => RiwayatKaryawan::pengelola($u)
                ? Pelanggaran::where('status', Pelanggaran::DIAJUKAN)->when($grup !== null, fn ($w) => $w->whereIn('company_id', $grup ?: [0]))->count()
                : 0,
            'opsi' => [
                'alasan'      => RiwayatKaryawan::ALASAN,
                'wajibKet'    => RiwayatKaryawan::ALASAN_WAJIB_KETERANGAN,
                'rekomendasi' => RiwayatKaryawan::REKOMENDASI,
                'kategori'    => Pelanggaran::KATEGORI,
                'tingkat'     => Pelanggaran::TINGKAT,
                'sanksi'      => Pelanggaran::SANKSI,
                'status'      => Pelanggaran::STATUS,
                'perusahaan'  => AksesMiners::perusahaan($u)->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->values(),
                'tahun'       => range((int) Waktu::kini()->year, (int) Waktu::kini()->year - 6),
            ],
        ]);
    }

    /** Status aktif / nonaktif beserta alasannya (alasan wajib saat dinonaktifkan). */
    public function status(Request $r, Pekerja $pekerja)
    {
        $u = $r->user();
        AksesMiners::tegakkan(RiwayatKaryawan::bolehKelola($pekerja, $u),
            'Status karyawan hanya diubah perusahaan pemiliknya atau pengelola Miners.');

        $d = $r->validate([
            'aktif'       => ['required', 'boolean'],
            'sebab'       => ['required_if:aktif,0', 'nullable', Rule::in(array_keys(RiwayatKaryawan::ALASAN))],
            'tanggal'     => ['required_if:aktif,0', 'nullable', 'date'],
            'keterangan'  => ['nullable', 'string', 'max:200', Rule::requiredIf(! $r->boolean('aktif')
                && in_array($r->input('sebab'), RiwayatKaryawan::ALASAN_WAJIB_KETERANGAN, true))],
            'rekomendasi' => ['nullable', Rule::in(array_keys(RiwayatKaryawan::REKOMENDASI))],
        ], [
            'sebab.required_if'   => 'Pilih alasan tidak aktif.',
            'tanggal.required_if' => 'Isi tanggal berhenti.',
            'keterangan.required' => 'Alasan ini wajib disertai keterangan.',
        ]);

        if ($r->boolean('aktif')) {
            $pekerja->forceFill([
                'status' => 'aktif', 'tanggal_nonaktif' => null, 'alasan_nonaktif' => null,
                'sebab_nonaktif' => null, 'rekomendasi_rekrut' => null, 'nonaktif_oleh' => null,
            ])->save();
            ActivityLog::write('Aktifkan kembali karyawan', $pekerja->nama, 'miners');

            return back()->with('sukses', $pekerja->nama.' kembali aktif.');
        }

        $pekerja->forceFill([
            'status'             => 'nonaktif',
            'tanggal_nonaktif'   => Waktu::tanggal($d['tanggal']),
            'sebab_nonaktif'     => $d['sebab'],
            'alasan_nonaktif'    => $d['keterangan'] ?? null,
            'rekomendasi_rekrut' => $d['rekomendasi'] ?? null,
            'nonaktif_oleh'      => $u->id,
        ])->save();
        ActivityLog::write('Nonaktifkan karyawan', $pekerja->nama.' · '.RiwayatKaryawan::ALASAN[$d['sebab']], 'miners');

        return back()->with('sukses', $pekerja->nama.' dinonaktifkan: '.RiwayatKaryawan::ALASAN[$d['sebab']].'.');
    }

    public function catat(Request $r, Pekerja $pekerja)
    {
        $u = $r->user();
        AksesMiners::tegakkan(RiwayatKaryawan::bolehKelola($pekerja, $u),
            'Catatan pelanggaran hanya dibuat perusahaan pemiliknya atau pengelola Miners.');

        $c = new Pelanggaran($this->isian($r) + [
            'company_id' => $pekerja->company_id,
            'pekerja_id' => $pekerja->id,
            'status'     => RiwayatKaryawan::statusAwal($u),
            'dibuat_oleh'=> $u->id,
        ]);
        if ($c->status === Pelanggaran::DIVERIFIKASI) {
            $c->forceFill(['diverifikasi_oleh' => $u->id, 'diverifikasi_pada' => now()]);
        }
        $c->save();
        ActivityLog::write('Catat pelanggaran', $pekerja->nama.' · '.Pelanggaran::KATEGORI[$c->kategori], 'miners');

        return back()->with('sukses', $c->status === Pelanggaran::DIVERIFIKASI
            ? 'Catatan pelanggaran tersimpan dan terverifikasi.'
            : 'Catatan pelanggaran tersimpan — menunggu verifikasi OHSE sebelum terlihat perusahaan lain.');
    }

    public function ubah(Request $r, int $pelanggaran)
    {
        $c = $this->catatan($pelanggaran, $r);
        AksesMiners::tegakkan(RiwayatKaryawan::bolehUbahPelanggaran($c, $r->user()),
            'Catatan terverifikasi hanya diubah pengelola Miners.');

        $c->fill($this->isian($r));
        // Catatan mitra yang ditolak lalu diperbaiki diajukan kembali.
        if ($c->status === Pelanggaran::DITOLAK && ! RiwayatKaryawan::pengelola($r->user())) {
            $c->status = Pelanggaran::DIAJUKAN;
        }
        $c->save();
        ActivityLog::write('Ubah catatan pelanggaran', (string) $c->pekerja?->nama, 'miners');

        return back()->with('sukses', 'Catatan pelanggaran diperbarui.');
    }

    public function hapus(Request $r, int $pelanggaran)
    {
        $c = $this->catatan($pelanggaran, $r);
        AksesMiners::tegakkan(RiwayatKaryawan::bolehUbahPelanggaran($c, $r->user()),
            'Catatan terverifikasi hanya dihapus pengelola Miners.');

        ActivityLog::write('Hapus catatan pelanggaran', (string) $c->pekerja?->nama, 'miners');
        $c->delete();

        return back()->with('sukses', 'Catatan pelanggaran dihapus.');
    }

    public function verifikasi(Request $r, int $pelanggaran)
    {
        AksesMiners::tegakkan(RiwayatKaryawan::pengelola($r->user()), 'Verifikasi hanya oleh pengelola Miners.');
        $c = $this->catatan($pelanggaran, $r);

        $d = $r->validate([
            'keputusan' => ['required', Rule::in([Pelanggaran::DIVERIFIKASI, Pelanggaran::DITOLAK])],
            'catatan'   => ['nullable', 'string', 'max:1000', Rule::requiredIf($r->input('keputusan') === Pelanggaran::DITOLAK)],
        ], ['catatan.required' => 'Tulis alasan penolakan agar pencatat dapat memperbaikinya.']);

        $c->forceFill([
            'status' => $d['keputusan'], 'catatan_verifikasi' => $d['catatan'] ?? null,
            'diverifikasi_oleh' => $r->user()->id, 'diverifikasi_pada' => now(),
        ])->save();
        ActivityLog::write($d['keputusan'] === Pelanggaran::DIVERIFIKASI ? 'Verifikasi pelanggaran' : 'Tolak pelanggaran',
            (string) $c->pekerja?->nama, 'miners');

        return back()->with('sukses', $d['keputusan'] === Pelanggaran::DIVERIFIKASI ? 'Catatan diverifikasi.' : 'Catatan ditolak.');
    }

    /** Catatan dalam grup pengguna dan boleh dilihatnya — selain itu 404. */
    private function catatan(int $id, Request $r): Pelanggaran
    {
        $g = RiwayatKaryawan::grup($r->user());
        $c = Pelanggaran::with('pekerja')->when($g !== null, fn ($q) => $q->whereIn('company_id', $g ?: [0]))->findOrFail($id);
        abort_unless(RiwayatKaryawan::bolehLihatPelanggaran($c, $r->user()), 404);

        return $c;
    }

    private function isian(Request $r): array
    {
        return $r->validate([
            'tanggal'    => ['required', 'date', 'before_or_equal:'.Waktu::kini()->toDateString()],
            'kategori'   => ['required', Rule::in(array_keys(Pelanggaran::KATEGORI))],
            'tingkat'    => ['required', Rule::in(array_keys(Pelanggaran::TINGKAT))],
            'sanksi'     => ['nullable', Rule::in(array_keys(Pelanggaran::SANKSI))],
            'uraian'     => ['required', 'string', 'max:2000'],
            'lokasi'     => ['nullable', 'string', 'max:150'],
            'no_dokumen' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
