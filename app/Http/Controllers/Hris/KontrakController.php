<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\Kontrak;
use App\Models\Miners\Pekerja;
use App\Support\Berkas;
use App\Support\Hr\{JalurKontrak, KontrakPkwt};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Kontrak kerja: daftar, rantai perpanjangan, dan kepatuhannya.
 *
 * TEMUAN DITAMPILKAN BERSAMA KONTRAKNYA, bukan pada halaman terpisah
 * yang harus dibuka sendiri. Pelanggaran PKWT tidak berbentuk kesalahan
 * yang menghentikan siapa pun: kontraknya berjalan, gajinya terbayar,
 * dan yang berubah hanya jenis hubungan kerjanya — diam-diam, demi
 * hukum. Dipisahkan ke halaman lain, satu-satunya yang membukanya
 * adalah orang yang sudah tahu harus mencari.
 */
class KontrakController extends Controller
{
    public function index(Request $r)
    {
        $company = $r->user()?->company_id;

        $semua = Kontrak::query()
            ->with(['pekerja.jabatan', 'induk'])
            ->orderByDesc('mulai')->orderByDesc('id')
            ->get();

        $temuan = [];
        foreach ($semua as $k) {
            foreach (KontrakPkwt::periksa($k) as $t) {
                $temuan[] = $t + [
                    'kontrak_id' => $k->id,
                    'nomor'      => $k->nomor,
                    'pekerja'    => $k->pekerja?->nama ?? '—',
                ];
            }
        }

        $akanBerakhir = KontrakPkwt::akanBerakhir($company, 60);

        return Inertia::render('Hris/Kontrak/Daftar', [
            'judul'    => 'HRIS — Kontrak Kerja',
            'subjudul' => 'PKWT dan PKWTT, beserta rantai perpanjangan dan batasnya.',

            'baris' => $semua->map(fn (Kontrak $k) => $this->baris($k))->values(),

            'temuan' => $temuan,

            'akanBerakhir' => $akanBerakhir->map(fn (Kontrak $k) => [
                'id'      => $k->id,
                'nomor'   => $k->nomor,
                'pekerja' => $k->pekerja?->nama ?? '—',
                'selesai' => $k->selesai?->toDateString(),
                'sisa'    => (int) Waktu::kini()->startOfDay()->diffInDays($k->selesai, false),
            ])->values(),

            'ringkas' => [
                'berjalan'  => $semua->where('status', 'berjalan')->count(),
                'pkwt'      => $semua->where('status', 'berjalan')->filter(fn ($k) => $k->pkwt())->count(),
                'pkwtt'     => $semua->where('status', 'berjalan')->where('jenis', 'pkwtt')->count(),
                'gawat'     => count(array_filter($temuan, fn ($t) => $t['berat'] === 'gawat')),
                'berakhir'  => $akanBerakhir->count(),
                /* DIHITUNG DARI SUMBER YANG SAMA DENGAN BARISNYA,
                   bukan dari kolom tersimpan. Kolom itu baru terisi
                   ketika kontraknya diakhiri lewat layar; sebelum itu ia
                   null, dan ubinnya berbunyi "Rp 0 belum dibayar" tepat
                   di atas tabel yang menyebut jutaan pada tiap baris.
                   Dua angka yang berselisih pada satu layar membuat
                   pembacanya berhenti memercayai keduanya. */
                'kompensasi' => round($semua
                    ->filter(fn (Kontrak $k) => $k->kompensasi_dibayar_pada === null)
                    ->sum(fn (Kontrak $k) => KontrakPkwt::kompensasi($k)['nilai']), 2),
            ],

            'pekerja' => Pekerja::query()->where('status', 'aktif')
                ->orderBy('nama')->get(['id', 'nama', 'no_registrasi'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->nama.' · '.$p->no_registrasi])
                ->values(),

            'JENIS'  => Kontrak::JENIS,
            'ALASAN' => Kontrak::ALASAN,
            'STATUS' => Kontrak::STATUS,

            'ACUAN' => [
                'maks_bulan'   => KontrakPkwt::MAKS_BULAN,
                'min_bulan'    => KontrakPkwt::MIN_BULAN_KOMPENSASI,
                'harian_hari'  => KontrakPkwt::HARIAN_MAKS_HARI,
                'harian_bulan' => KontrakPkwt::HARIAN_BULAN_BERUNTUN,
                'catat_hari'   => KontrakPkwt::CATAT_HARI_KERJA,
                'percobaan'    => KontrakPkwt::MAKS_PERCOBAAN_HARI,
            ],
        ]);
    }

    public function simpan(Request $r)
    {
        $data = $r->validate([
            'pekerja_id'          => ['required', 'integer'],
            'nomor'               => ['required', 'string', 'max:60'],
            'jenis'               => ['required', Rule::in(array_keys(Kontrak::JENIS))],
            'alasan'              => ['nullable', Rule::in(array_keys(Kontrak::ALASAN))],
            'mulai'               => ['required', 'date'],
            'selesai'             => ['nullable', 'date', 'after_or_equal:mulai'],
            'batasan_selesai'     => ['nullable', 'string', 'max:2000'],
            'masa_percobaan_hari' => ['nullable', 'integer', 'min:0', 'max:365'],
            'ditandatangani_pada' => ['nullable', 'date'],
            'catatan'             => ['nullable', 'string', 'max:2000'],
            'berkas'              => Berkas::ATURAN_BUKTI,
        ]);

        $company = $r->user()?->company_id;

        // Batas perusahaan diterapkan dengan tangan: pekerja_id datang
        // dari formulir, dan lingkup global tidak menjaga nilai yang
        // dikirim — hanya yang dibaca.
        $pekerja = Pekerja::withoutGlobalScopes()
            ->where('company_id', $company)->find($data['pekerja_id']);

        if ($pekerja === null) {
            return back()->withErrors(['pekerja_id' => 'Pekerja tidak dikenal.']);
        }

        $ada = Kontrak::withoutGlobalScopes()
            ->where('company_id', $company)->where('nomor', $data['nomor'])->exists();

        if ($ada) {
            return back()->withErrors(['nomor' => 'Nomor kontrak ini sudah dipakai.']);
        }

        Kontrak::create([
            'company_id'          => $company,
            'pekerja_id'          => $pekerja->id,
            'nomor'               => $data['nomor'],
            'jenis'               => $data['jenis'],
            'alasan'              => $data['jenis'] === 'pkwt_jangka' ? ($data['alasan'] ?? null) : null,
            'mulai'               => $data['mulai'],
            'selesai'             => $data['jenis'] === 'pkwtt' ? null : ($data['selesai'] ?? null),
            'batasan_selesai'     => $data['batasan_selesai'] ?? null,
            'masa_percobaan_hari' => (int) ($data['masa_percobaan_hari'] ?? 0),
            'ditandatangani_pada' => $data['ditandatangani_pada'] ?? null,
            'catatan'             => $data['catatan'] ?? null,
            'berkas_naskah'       => $r->file('berkas') ? Berkas::simpan($r->file('berkas'), 'kontrak') : null,
            'status'              => 'draft',
            'dibuat_oleh'         => $r->user()?->id,
        ]);

        return back()->with('sukses', 'Kontrak dicatat sebagai draf.');
    }

    public function terbitkan(Request $r, Kontrak $kontrak)
    {
        $galat = JalurKontrak::terbitkan($kontrak, $r->user());

        return $galat === null
            ? back()->with('sukses', 'Kontrak diterbitkan.')
            : back()->withErrors(['kontrak' => $galat]);
    }

    public function perpanjang(Request $r, Kontrak $kontrak)
    {
        $data = $r->validate([
            'nomor'   => ['required', 'string', 'max:60'],
            'mulai'   => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        [$galat] = JalurKontrak::perpanjang($kontrak, $data, $r->user());

        return $galat === null
            ? back()->with('sukses', 'Perpanjangan dicatat sebagai draf.')
            : back()->withErrors(['kontrak' => $galat]);
    }

    public function akhiri(Request $r, Kontrak $kontrak)
    {
        $data = $r->validate([
            'status' => ['required', Rule::in(['selesai', 'diputus'])],
            'pada'   => ['nullable', 'date'],
        ]);

        $galat = JalurKontrak::akhiri($kontrak, $data['status'], $data['pada'] ?? null);

        return $galat === null
            ? back()->with('sukses', 'Kontrak diakhiri dan kompensasinya dihitung.')
            : back()->withErrors(['kontrak' => $galat]);
    }

    public function jadikanPkwtt(Request $r, Kontrak $kontrak)
    {
        $data = $r->validate(['sebab' => ['required', 'string', 'max:2000']]);

        [$galat] = JalurKontrak::jadikanPkwtt($kontrak, $data['sebab'], $r->user());

        return $galat === null
            ? back()->with('sukses', 'Perubahan menjadi PKWTT dicatat.')
            : back()->withErrors(['kontrak' => $galat]);
    }

    /** @return array<string,mixed> */
    private function baris(Kontrak $k): array
    {
        $kompensasi = KontrakPkwt::kompensasi($k);

        return [
            'id'         => $k->id,
            'nomor'      => $k->nomor,
            'pekerja'    => $k->pekerja?->nama ?? '—',
            'jabatan'    => $k->pekerja?->jabatan?->nama,
            'jenis'      => $k->jenis,
            'alasan'     => $k->alasan,
            'mulai'      => $k->mulai?->toDateString(),
            'selesai'    => $k->selesai?->toDateString(),
            'status'     => $k->status,
            'urutan'     => $k->urutan,
            'induk'      => $k->induk?->nomor,
            'percobaan'  => $k->masa_percobaan_hari,

            'ditandatangani' => $k->ditandatangani_pada?->toDateString(),
            'dicatatkan'     => $k->dicatatkan_pada?->toDateString(),

            'bulan'       => KontrakPkwt::bulan($k->mulai, KontrakPkwt::akhirNyata($k)),
            'bulanRantai' => $k->pkwt() ? KontrakPkwt::bulanRantai($k) : null,

            'kompensasi'       => $kompensasi['berhak'] ? $kompensasi['nilai'] : null,
            'kompensasiAlasan' => $kompensasi['alasan'],
            'dibayar'          => $k->kompensasi_dibayar_pada?->toDateString(),

            'berkas' => Berkas::url($k, 'knt'),

            'temuan' => count(KontrakPkwt::periksa($k)),
        ];
    }
}
