<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Company, News, Procedure};
use App\Support\Diagnosa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Penetapan pemilik bagi prosedur dan berita yang belum bertuan.
 *
 * Ketika prosedur dan berita mulai melekat perusahaan, migrasinya
 * sengaja TIDAK menebak pemilik baris yang sudah ada. Menebak berarti
 * menyembunyikannya dari yang berhak, dan kegagalan itu tidak
 * menimbulkan galat apa pun — hanya daftar yang mendadak kosong.
 *
 * Akibat yang benar dari keputusan itu: barisnya terbaca oleh SEMUA
 * perusahaan sampai ada yang menetapkannya. Halaman ini tempat
 * menetapkannya, dan sebagian memang boleh dibiarkan kosong — prosedur
 * baku dan pengumuman se-pemasangan memang berlaku untuk semua.
 *
 * Yang dijaga di sini:
 *
 * - Hanya administrator. Menetapkan pemilik berarti memindahkan data
 *   antar perusahaan, dan itu bukan wewenang penghuni salah satunya.
 * - Hanya baris yang BELUM bertuan yang dapat ditetapkan dari sini.
 *   Memindahkan prosedur dari satu perusahaan ke perusahaan lain
 *   adalah tindakan yang berbeda, jauh lebih berbahaya, dan tidak
 *   pernah merupakan pembetulan data lama.
 * - Setiap penetapan tercatat di jejak aktivitas beserta jumlahnya.
 */
class PemilikController extends Controller
{
    /**
     * Jenis yang dapat ditetapkan pemiliknya.
     *
     * @var array<string,array{kelas:class-string,label:string,judul:string,ket:string}>
     */
    private const JENIS = [
        'prosedur' => [
            'kelas' => Procedure::class,
            'label' => 'Prosedur',
            'judul' => 'title',
            'ket'   => 'Prosedur menyebut nama jabatan dan batas kewenangan yang berlaku di satu '
                .'perusahaan. Yang benar-benar baku untuk semua boleh dibiarkan kosong.',
        ],
        'berita' => [
            'kelas' => News::class,
            'label' => 'Berita',
            'judul' => 'title',
            'ket'   => 'Pengumuman menyebut nama orang dan kejadian di satu lokasi kerja. '
                .'Pengumuman se-pemasangan boleh dibiarkan kosong.',
        ],
    ];

    public function index()
    {
        return Inertia::render('Admin/Pemilik', [
            'judul'    => 'Penetapan Pemilik',
            'subjudul' => 'Prosedur dan berita yang belum melekat pada satu perusahaan',

            'jenis' => array_map(fn ($kunci) => [
                'kunci'  => $kunci,
                'label'  => self::JENIS[$kunci]['label'],
                'ket'    => self::JENIS[$kunci]['ket'],
                'baris'  => $this->yatim($kunci),
                'jumlah' => $this->hitungYatim($kunci),
            ], array_keys(self::JENIS)),

            'perusahaan' => Company::query()->withoutGlobalScopes()
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->all(),

            'tautan' => [
                'tetapkan' => route('admin.pemilik.tetapkan'),
                'sistem'   => route('admin.system'),
                'diagnosa' => route('admin.system.diagnosa'),
            ],
        ]);
    }

    /**
     * Tetapkan perusahaan bagi sekumpulan baris sekaligus.
     *
     * Penyaring `whereNull('company_id')` diulang di sini meski
     * daftarnya sudah hanya memuat yang yatim. Daftar itu digambar
     * peramban, dan yang dikirim balik peramban bukan bukti apa pun
     * tentang keadaan baris ketika perintahnya tiba.
     */
    public function tetapkan(Request $request)
    {
        $data = $request->validate([
            'jenis'      => ['required', Rule::in(array_keys(self::JENIS))],
            'id'         => ['required', 'array', 'min:1'],
            'id.*'       => ['integer'],
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $kelas = self::JENIS[$data['jenis']]['kelas'];

        $n = $kelas::withoutGlobalScopes()
            ->whereIn('id', $data['id'])
            ->whereNull('company_id')
            ->update(['company_id' => $data['company_id']]);

        if ($n === 0) {
            return back()->withErrors(['pemilik' =>
                'Tidak ada yang berubah. Baris itu mungkin sudah ditetapkan orang lain '
                .'sementara halaman ini terbuka — muat ulang untuk melihat keadaan terbaru.']);
        }

        $perusahaan = Company::withoutGlobalScopes()->find($data['company_id']);

        ActivityLog::write(
            'Tetapkan pemilik '.self::JENIS[$data['jenis']]['label'],
            $n.' baris → '.($perusahaan?->name ?? 'perusahaan '.$data['company_id']),
            'sistem',
        );

        Diagnosa::lupakanRingkas();

        return back()->with('ok', $n.' '.strtolower(self::JENIS[$data['jenis']]['label'])
            .' kini melekat pada '.($perusahaan?->name ?? 'perusahaan itu').'.');
    }

    /** @return list<array<string,mixed>> */
    private function yatim(string $kunci): array
    {
        $j = self::JENIS[$kunci];

        return $j['kelas']::withoutGlobalScopes()
            ->whereNull('company_id')
            ->orderBy($j['judul'])
            ->limit(200)
            ->get()
            ->map(fn ($b) => [
                'id'    => $b->getKey(),
                'judul' => (string) ($b->{$j['judul']} ?? '(tanpa judul)'),
                'ket'   => $kunci === 'prosedur'
                    ? trim((string) ($b->code ?? '').' · '.(string) ($b->category ?? ''), ' ·')
                    : (string) ($b->published_at?->format('d M Y') ?? 'belum terbit'),
            ])
            ->all();
    }

    private function hitungYatim(string $kunci): int
    {
        return self::JENIS[$kunci]['kelas']::withoutGlobalScopes()->whereNull('company_id')->count();
    }
}
