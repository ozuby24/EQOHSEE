<?php

namespace App\Http\Controllers;

use App\Models\TindakLanjut;
use App\Support\Temuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Register temuan seluruh modul dalam satu halaman.
 *
 * Halaman ini menjawab satu pertanyaan yang selama ini tidak dapat
 * dijawab tanpa membuka lima modul satu per satu: apa saja yang masih
 * terbuka di seluruh site, dan mana yang sudah lewat tenggat.
 *
 * Penyaringnya sengaja hanya tiga — terbuka, terlambat, tak bertuan —
 * dan ketiganya menjawab pertanyaan yang berbeda. "Terlambat" menagih
 * yang sudah dijanjikan; "tak bertuan" menagih yang belum pernah
 * dijanjikan siapa pun. Yang kedua lebih mudah terlewat justru karena
 * ia tidak pernah tampak merah di modul asalnya.
 */
class TemuanController extends Controller
{
    public function index(Request $request)
    {
        /* Register dibaca dalam batas perusahaan pengguna. Administrator
           EQOHSEE tidak dibatasi — sama seperti scope MilikPerusahaan,
           supaya keduanya tidak pernah menyebut jumlah yang berbeda. */
        $pengguna = $request->user();
        $batas    = ($pengguna && !$pengguna->isAdmin()) ? $pengguna->company : null;

        $semua  = Temuan::semua($batas);
        $saring = $request->string('saring')->toString() ?: 'terbuka';

        $terlihat = match ($saring) {
            'terlambat'   => array_values(array_filter($semua, fn ($t) => $t['terbuka'] && $t['terlambat'])),
            'tak-bertuan' => array_values(array_filter($semua, fn ($t) => $t['terbuka'] && !$t['bertuan'])),
            'semua'       => $semua,
            default       => array_values(array_filter($semua, fn ($t) => $t['terbuka'])),
        };

        return Inertia::render('Temuan/Register', [
            'judul'    => 'Register Temuan',
            'subjudul' => 'Seluruh temuan dan tindak lanjut dari semua modul',

            'temuan'   => $terlihat,
            'ringkas'  => Temuan::ringkas($semua),
            'perModul' => array_map(
                fn ($modul, $jumlah) => ['modul' => $modul, 'jumlah' => $jumlah],
                array_keys(Temuan::perModul($semua)),
                array_values(Temuan::perModul($semua)),
            ),

            'saring' => $saring,
            'opsi'   => [
                ['nilai' => 'terbuka',     'label' => 'Masih terbuka'],
                ['nilai' => 'terlambat',   'label' => 'Lewat tenggat'],
                ['nilai' => 'tak-bertuan', 'label' => 'Tanpa penanggung jawab'],
                ['nilai' => 'semua',       'label' => 'Semua'],
            ],

            'tautan' => ['register' => route('temuan.index')],
        ]);
    }

    /**
     * Tetapkan penanggung jawab dan tenggat pada satu temuan.
     *
     * Register ini sebelumnya hanya dapat MELAPORKAN. Ia memperlihatkan
     * temuan tak bertuan — keadaan yang paling perlu terlihat, sebab
     * temuan tanpa tenggat tidak pernah terhitung terlambat dan karena itu
     * tidak pernah menyalakan peringatan apa pun — lalu berhenti di situ.
     * Diagnosa tanpa jalan keluar berhenti dibaca dalam beberapa minggu.
     *
     * Dua jalan, menurut asalnya:
     *
     *   Yang punya kolomnya sendiri — tindak lanjut, temuan SMKP, aksi KO
     *   — ditugaskan di tempat. Membuatkan baris terpisah bagi mereka akan
     *   melahirkan dua tempat yang sama-sama mengaku tahu siapa
     *   penanggung jawabnya.
     *
     *   Yang tidak punya kolomnya sama sekali — laporan bahaya dan butir
     *   inspeksi — memperoleh sebuah tindak lanjut yang MELEKAT lewat
     *   relasi morph `sumber`. Menambahkan kolom penanggung jawab ke kedua
     *   tabel itu tampak paling lurus, dan justru itu yang dihindari:
     *   tabel tindak_lanjut sudah punya daur hidupnya sendiri, dan
     *   docblock relasinya menyebut niat ini sejak sebelum halaman ini
     *   ada. Menambah kolom berarti membangun daur hidup kedua di sebelah
     *   yang sudah jalan.
     */
    public function tugaskan(Request $request, string $sumber, int $id)
    {
        $data = $request->validate([
            'penanggung_jawab' => ['required', 'string', 'max:150'],
            'target_selesai'   => ['required', 'date'],
        ], [], [
            'penanggung_jawab' => 'penanggung jawab',
            'target_selesai'   => 'tenggat',
        ]);

        /* KEDUANYA wajib, bukan salah satu.

           Nama tanpa tanggal tidak pernah jatuh tempo; tanggal tanpa nama
           tidak pernah ada yang ditagih. Membolehkan salah satu diisi
           menghasilkan baris yang lolos dari saringan "tak bertuan" tanpa
           benar-benar ditangani siapa pun — persis keadaan yang halaman
           ini dibuat untuk memperlihatkannya. */

        if (isset(Temuan::DITUGASKAN_DI_TEMPAT[$sumber])) {
            [$kelas, $kolomNama, $kolomTenggat] = Temuan::DITUGASKAN_DI_TEMPAT[$sumber];

            /* findOrFail, bukan pemeriksaan perusahaan yang ditulis ulang:
               seluruh model ini memakai scope perusahaan, sehingga baris
               milik perusahaan lain memang tidak dapat ditemukan. */
            $baris = $kelas::findOrFail($id);
            $baris->forceFill([
                $kolomNama    => $data['penanggung_jawab'],
                $kolomTenggat => $data['target_selesai'],
            ])->save();

            return back()->with('ok', 'Penanggung jawab dan tenggat ditetapkan.');
        }

        if (!isset(Temuan::DITUGASKAN_LEWAT_TINDAK_LANJUT[$sumber])) abort(404);

        [$kelas, $modul] = Temuan::DITUGASKAN_LEWAT_TINDAK_LANJUT[$sumber];
        $baris = $kelas::findOrFail($id);

        DB::transaction(function () use ($baris, $kelas, $modul, $data, $request) {
            /* updateOrCreate pada pasangan morph-nya: menugaskan ulang
               temuan yang sama mengubah tugas yang ada, tidak menumpuk
               tugas kedua di atasnya. Dua tugas pada satu temuan berarti
               dua tenggat, dan tidak ada cara memilih mana yang berlaku. */
            TindakLanjut::updateOrCreate(
                ['sumber_type' => $kelas, 'sumber_id' => $baris->getKey()],
                [
                    'company_id'       => $baris->company_id ?? $request->user()?->company_id,
                    'user_id'          => $request->user()?->getKey(),
                    'modul'            => $modul,
                    'kode_pemicu'      => $baris->kode ?? ('#'.$baris->getKey()),
                    'judul'            => $this->judul($baris),
                    'prioritas'        => 'sedang',
                    'status'           => 'berjalan',
                    'penanggung_jawab' => $data['penanggung_jawab'],
                    'target_selesai'   => $data['target_selesai'],
                    'uraian'           => 'Ditetapkan dari Register Temuan.',
                ],
            );
        });

        return back()->with('ok', 'Penanggung jawab dan tenggat ditetapkan.');
    }

    /**
     * Judul tindak lanjut, diambil dari barisnya sendiri.
     *
     * Tiap sumber menamai kolom uraiannya berbeda; yang pertama ada
     * dipakai. Judul kosong membuat register menampilkan baris tanpa
     * keterangan apa pun, dan baris semacam itu tidak dapat diputuskan
     * nasibnya oleh siapa pun yang membacanya kemudian.
     */
    private function judul(object $baris): string
    {
        foreach (['deskripsi', 'temuan', 'uraian'] as $kolom) {
            $nilai = trim((string) ($baris->{$kolom} ?? ''));
            if ($nilai !== '') return mb_substr($nilai, 0, 200);
        }

        return 'Temuan '.($baris->kode ?? '#'.$baris->getKey());
    }
}
