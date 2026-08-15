<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\{Ai, AiPenyedia, Diagnosa};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pengaturan asisten AI, dan pendampingnya untuk menafsirkan diagnosa.
 *
 * Kunci API tidak pernah keluar dari server. Halaman ini hanya
 * mengatakan penyedia mana yang sudah terpasang dan empat huruf
 * terakhirnya; tidak ada satu pun jalan yang memulangkan kuncinya utuh,
 * termasuk kepada administrator yang memasukkannya. Yang lupa kuncinya
 * mengambilnya kembali dari penyedia, bukan dari sini.
 */
class AiController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Ai', $this->prop());
    }

    /** @return array<string,mixed> */
    private function prop(): array
    {
        $terpilih = Ai::penyedia();

        return [
            'judul'    => 'Integrasi AI',
            'subjudul' => 'Kunci API milik sendiri, dipakai asisten Bantuan dan pendamping diagnosa',

            'penyedia' => array_map(fn ($kode) => [
                'kode'        => $kode,
                'nama'        => AiPenyedia::satu($kode)['nama'],
                'modelBawaan' => AiPenyedia::satu($kode)['model'],
                'contohModel' => AiPenyedia::satu($kode)['contohModel'],
                'kunciDari'   => AiPenyedia::satu($kode)['kunciDari'],

                // Bukan kuncinya — hanya bukti bahwa ada, dan yang mana.
                'terpasang'   => Ai::punyaKunci($kode),
                'ekor'        => Ai::ekorKunci($kode),
                'dariBerkas'  => Ai::dariBerkas($kode),
            ], array_keys(AiPenyedia::semua())),

            'terpilih'  => $terpilih,
            'model'     => Ai::model(),
            'maksToken' => Ai::maksToken(),
            'aktif'     => Ai::aktif(),

            'tautan' => [
                'simpan'   => route('admin.ai.simpan'),
                'uji'      => route('admin.ai.uji'),
                'hapus'    => route('admin.ai.hapus'),
                'sistem'   => route('admin.system'),
                'diagnosa' => route('admin.system.diagnosa'),
            ],
        ];
    }

    /**
     * Simpan penyedia, model, dan — bila diisi — kuncinya.
     *
     * Kunci kosong berarti "biarkan yang sudah ada", bukan "hapus".
     * Menyamakan keduanya membuat setiap penyuntingan model tanpa
     * sengaja mematikan asistennya.
     */
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'penyedia'   => ['required', Rule::in(array_keys(AiPenyedia::semua()))],
            'model'      => ['nullable', 'string', 'max:120'],
            'maks_token' => ['nullable', 'integer', 'min:100', 'max:4000'],
            'kunci'      => ['nullable', 'string', 'min:8', 'max:400'],
        ]);

        Ai::simpanPengaturan($data['penyedia'], $data['model'] ?? null, $data['maks_token'] ?? null);

        if (filled($data['kunci'] ?? null)) {
            Ai::simpanKunci($data['penyedia'], $data['kunci']);

            // Kuncinya sendiri tidak pernah masuk log aktivitas.
            ActivityLog::write('Simpan kunci AI', AiPenyedia::satu($data['penyedia'])['nama'], 'sistem');
        }

        ActivityLog::write('Ubah pengaturan AI',
            AiPenyedia::satu($data['penyedia'])['nama'].' · '.Ai::model(), 'sistem');

        Diagnosa::lupakanRingkas();

        return back()->with('ok', 'Pengaturan AI disimpan.');
    }

    /**
     * Uji sambungan.
     *
     * Kunci yang baru diketik diuji lebih dulu tanpa disimpan; bila
     * kolomnya dikosongkan, yang diuji kunci yang sudah tersimpan.
     */
    public function uji(Request $request)
    {
        $data = $request->validate([
            'penyedia' => ['required', Rule::in(array_keys(AiPenyedia::semua()))],
            'model'    => ['nullable', 'string', 'max:120'],
            'kunci'    => ['nullable', 'string', 'min:8', 'max:400'],
        ]);

        $kunci = filled($data['kunci'] ?? null) ? $data['kunci'] : Ai::kunci($data['penyedia']);

        if (blank($kunci)) {
            return back()->withErrors(['ai' =>
                'Belum ada kunci untuk '.AiPenyedia::satu($data['penyedia'])['nama']
                .'. Masukkan kuncinya lebih dulu, lalu uji.']);
        }

        $hasil = Ai::uji($data['penyedia'], $kunci, $data['model'] ?? null);

        ActivityLog::write('Uji sambungan AI',
            AiPenyedia::satu($data['penyedia'])['nama'].' · '.($hasil['ok'] ? 'berhasil' : 'gagal'),
            'sistem');

        return $hasil['ok']
            ? back()->with('ok', $hasil['pesan'])
            : back()->withErrors(['ai' => $hasil['pesan']]);
    }

    public function hapus(Request $request)
    {
        $data = $request->validate([
            'penyedia' => ['required', Rule::in(array_keys(AiPenyedia::semua()))],
        ]);

        Ai::hapusKunci($data['penyedia']);
        ActivityLog::write('Hapus kunci AI', AiPenyedia::satu($data['penyedia'])['nama'], 'sistem');
        Diagnosa::lupakanRingkas();

        return back()->with('ok', 'Kunci '.AiPenyedia::satu($data['penyedia'])['nama'].' dihapus.');
    }

    /**
     * Pendamping diagnosa.
     *
     * Temuan yang sedang tampil dikirim apa adanya, dan AI diminta
     * mengurutkan mana yang harus ditangani lebih dulu beserta
     * alasannya. Yang dikirim hanya temuannya — bukan isi basis data,
     * bukan `.env`, bukan potongan kode.
     *
     * Ia MENASIHATI, tidak menjalankan apa pun. Perintah yang
     * disebutnya tetap harus dijalankan orang, dan itu batas yang
     * disengaja: saran yang salah dari model yang percaya diri jauh
     * lebih murah bila masih harus melewati satu orang lebih dulu.
     */
    public function tanyaDiagnosa(Request $request)
    {
        $data = $request->validate([
            'pertanyaan' => ['nullable', 'string', 'max:500'],
        ]);

        if (!Ai::aktif()) {
            return back()->withErrors(['ai' =>
                'Asisten AI belum diaktifkan. Masukkan kunci API di halaman Integrasi AI.']);
        }

        $hasil = Diagnosa::jalankan();

        $ringkas = [];
        foreach ($hasil as $h) {
            if ($h['keadaan'] === Diagnosa::AMAN) continue;

            $ringkas[] = sprintf("- [%s] %s: %s\n  %s",
                strtoupper($h['keadaan']), $h['judul'], $h['nilai'], $h['uraian']);
        }

        if (!$ringkas) {
            return back()->with('ok', 'Tidak ada temuan yang perlu ditafsirkan — semuanya aman.');
        }

        $tanya = trim($data['pertanyaan'] ?? '') ?: 'Mana yang harus saya tangani lebih dulu, dan mengapa?';

        $jawab = Ai::jawab(
            [['peran' => 'pengguna', 'isi' =>
                "Temuan diagnosa sistem saat ini:\n\n".implode("\n", $ringkas)."\n\n".$tanya]],
            self::PERAN_DIAGNOSA,
            1,
        );

        ActivityLog::write('Tanya AI tentang diagnosa',
            count($ringkas).' temuan · '.($jawab['ok'] ? 'terjawab' : 'gagal'), 'sistem');

        return $jawab['ok']
            ? back()->with('aiJawaban', $jawab['isi'])
            : back()->withErrors(['ai' => $jawab['isi'].($jawab['galat'] ?? false ? ' ('.$jawab['galat'].')' : '')]);
    }

    /** Batas lingkup pendamping diagnosa. */
    private const PERAN_DIAGNOSA = <<<'TEKS'
    Anda pendamping teknis untuk administrator EQOHSEE, sebuah aplikasi
    Laravel + Vue untuk keselamatan pertambangan yang berjalan di satu VPS.

    Anda diberi daftar temuan diagnosa sistem. Tugas Anda: mengurutkan mana
    yang harus ditangani lebih dulu, menjelaskan akibatnya bila dibiarkan,
    dan menyebut langkah nyata untuk tiap temuan.

    Aturan:
    - Jawab dalam bahasa Indonesia, ringkas, dan berurut dari yang paling
      mendesak. Sebutkan perintahnya bila memang ada perintahnya.
    - Anda TIDAK dapat menjalankan apa pun. Setiap perintah yang Anda sebut
      dijalankan oleh orang yang membaca, jadi sebutkan juga apa yang perlu
      diperiksa sebelum menjalankannya bila perintah itu mengubah data.
    - Jangan mengarang temuan yang tidak ada di daftar, dan jangan menebak
      isi berkas, basis data, atau konfigurasi yang tidak diberikan.
    - Bila sebuah temuan tidak Anda kenali, katakan begitu.
    TEKS;
}
