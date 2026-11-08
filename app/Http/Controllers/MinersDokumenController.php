<?php

namespace App\Http\Controllers;

use App\Models\Miners\{HasilMcu, Induksi, InduksiOrang, JenisUnit, KategoriPermit, Kendaraan,
    Mcu, McuOrang, McuRujukan, Pekerja, Permit, PermitBerkas, Simper, SimperAjuan,
    SimperAjuanUnit, SimperUnit, TipePermit};
use App\Support\Berkas;
use App\Support\Miners\{Acuan, AksesMiners, Jalur, Keadaan, RincianDokumen};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Dokumen Miners: MCU, induksi, Mine Permit, SIMPER, pengajuan lanjutan.
 *
 * DIPISAHKAN DARI MinersController yang menyajikan daftar dan
 * pemantauan, sebab keduanya menjawab pertanyaan berbeda: yang di sana
 * "siapa yang hari ini tidak boleh masuk", yang di sini "bagaimana
 * dokumennya dibuat dan disahkan". Digabung, satu berkas menjadi tiga
 * ribu baris dan tidak ada satu pun bagian yang dapat dibaca utuh —
 * itu keadaan controller lama, dan itu yang sedang diganti.
 */
class MinersDokumenController extends Controller
{
    /* MCU, induksi, dan Mine Permit kini punya controllernya sendiri —
       App\Http\Controllers\Miners\{McuController, InduksiController,
       PermitController} — mengikuti layar Safe Track masing-masing. Yang
       tersisa di sini SIMPER, pengajuan lanjutan, dan pintu alur bersama
       (kirim, ajukan ulang, rincian SIMPER). */

    /* ═══════════════════ SIMPER ═══════════════════ */

    public function simperIndex(Request $r)
    {
        $simper = Simper::query()
            ->with(['pekerja', 'permit.mcuOrang', 'unit.kendaraan', 'unit.jenisUnit', 'ajuan', 'alur'])
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal')->get();

        return Inertia::render('Miners/Simper', [
            'judul'    => 'Miners — SIMPER',
            'subjudul' => 'Izin mengemudikan unit, menempel pada Mine Permit yang mendasarinya.',

            'baris' => $simper->map(fn (Simper $s) => [
                'id'        => $s->id,
                'pekerja_id'=> $s->pekerja_id,
                'pekerja'   => $s->pekerja?->nama,
                'nomor'     => $s->no_simper,
                'tanggal'   => $s->tanggal?->toDateString(),
                'kelas'     => $s->kelas,
                'simpol'    => $s->jenis_simpol,
                'no_simpol' => $s->no_simpol,
                'simpolSampai' => $s->simpol_berlaku_sampai?->toDateString(),
                'status'    => $s->status,
                'sampai'    => $s->berlaku_sampai?->toDateString(),
                'efektif'   => $s->habisEfektif()?->toDateString(),
                'sebab'     => $s->penyebabHabis(),
                'sisa'      => $s->sisaHari(),
                'keadaan'   => Keadaan::simper($s),
                'permit_id' => $s->permit_id,
                'berkas'    => ['simpol' => Berkas::lampiran($r->user(), $s, 'mnp')],
                'unit'      => $s->unit->map(fn (SimperUnit $u) => [
                    'id'         => $u->id,
                    'golongan'   => $u->kendaraan?->nama,
                    'unit'       => $u->jenisUnit?->nama,
                    'kewenangan' => $u->kewenangan,
                    'asal'       => $u->asal,
                    'lulus'      => $u->lulus(),
                    'nilai_p2h'     => $u->nilai_p2h,
                    'nilai_praktek' => $u->nilai_praktek,
                    'nilai_teori'   => $u->nilai_teori,
                    'nilai_rambu'   => $u->nilai_rambu,
                ]),
                'ajuan' => $s->ajuan->map(fn (SimperAjuan $a) => [
                    'id' => $a->id, 'jenis' => $a->jenis, 'nomor' => $a->no_registrasi,
                    'tanggal' => $a->tanggal?->toDateString(), 'status' => $a->status,
                ]),
                'alur' => $this->alur($s),
            ]),

            'saring'    => ['status' => $r->query('status', '')],
            'STATUS'    => Simper::STATUS,
            'KELAS'     => Simper::pilihanKelas(),
            'SIMPOL'    => Simper::JENIS_SIMPOL,
            'KEWENANGAN'=> SimperUnit::KEWENANGAN,
            'ASAL'      => SimperUnit::ASAL,
            'JENIS_AJUAN' => SimperAjuan::JENIS,
            'KEADAAN'   => Keadaan::LABEL,
            'NADA'      => Keadaan::NADA,
            'NILAI_LULUS' => SimperUnit::NILAI_LULUS,
            'golongan'  => Kendaraan::query()->terpakai()->get(['id', 'nama', 'kelas_simpol', 'wajib_sio']),
            'unit'      => JenisUnit::query()->terpakai()->get(['id', 'nama']),
            'permit'    => Permit::query()->where('status', 'terbit')->with('pekerja')
                ->orderByDesc('tanggal')->get()
                ->map(fn (Permit $p) => [
                    'id' => $p->id, 'nomor' => $p->no_registrasi,
                    'pekerja_id' => $p->pekerja_id, 'pekerja' => $p->pekerja?->nama,
                ]),
            'peranSaya' => Jalur::peran($r->user()),
        ]);
    }

    public function simperStore(Request $r)
    {
        $data = $this->validasiSimper($r);

        $permit = Permit::findOrFail($data['permit_id']);
        $terbit = Waktu::tanggal($data['tanggal']);

        $simper = Simper::create($data + [
            'pekerja_id'     => $permit->pekerja_id,
            'tanggal'        => $terbit,
            'berlaku_sampai' => $terbit->copy()->endOfYear()->startOfDay(),
            'sumber_berlaku' => 'tahunan',
            'simpol_berlaku_sampai' => isset($data['simpol_berlaku_sampai'])
                ? Waktu::tanggal($data['simpol_berlaku_sampai']) : null,
            'berkas_simpol'  => Berkas::simpan($r->file('berkas_simpol'), 'miners/simper'),
            'user_id'        => $r->user()?->id,
        ]);

        $simper->terbitkanAlur();

        return back()->with('sukses', 'SIMPER dibuat sebagai draf.');
    }

    public function simperUpdate(Request $r, Simper $simper)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($simper, $r->user()));

        $data = $this->validasiSimper($r, $simper);

        $simper->update($data + [
            'tanggal' => Waktu::tanggal($data['tanggal']),
            'simpol_berlaku_sampai' => isset($data['simpol_berlaku_sampai'])
                ? Waktu::tanggal($data['simpol_berlaku_sampai']) : null,
            'berkas_simpol' => Berkas::simpan($r->file('berkas_simpol'), 'miners/simper') ?? $simper->berkas_simpol,
        ]);

        return back()->with('sukses', 'SIMPER diperbarui.');
    }

    public function simperDestroy(Simper $simper)
    {
        $this->buangAlur($simper);
        $simper->delete();

        return back()->with('sukses', 'SIMPER dihapus.');
    }

    public function simperUnit(Request $r, Simper $simper)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($simper, $r->user()));

        $data = $this->validasiUnit($r);

        /* Kelas SIM yang dituntut golongan unitnya DIPERIKSA, bukan
           sekadar ditampilkan. SOP 007-SOP-OHSE mencocokkan unit dengan
           kelas SIM Kepolisian, dan pemeriksaan yang hanya ada di layar
           dapat dilewati dengan mengirim permintaannya langsung. */
        if ($salah = $this->simpolTakCocok($simper, $data['kendaraan_id'] ?? null)) {
            return back()->withErrors(['kendaraan_id' => $salah]);
        }

        SimperUnit::create($data + ['simper_id' => $simper->id, 'asal' => 'baru']);

        return back()->with('sukses', 'Unit ditambahkan.');
    }

    public function simperUnitUbah(Request $r, Simper $simper, SimperUnit $unit)
    {
        abort_unless((int) $unit->simper_id === (int) $simper->id, 404);

        /* Admin boleh menyunting baris unit walau SIMPER-nya sudah
           disahkan penuh (Safe Track 8601bfe); pengaju hanya pada draf. */
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($simper, $r->user()));

        $unit->update($this->validasiUnit($r));

        return back()->with('sukses', 'Nilai uji unit disimpan.');
    }

    public function simperUnitHapus(Simper $simper, SimperUnit $unit)
    {
        abort_unless((int) $unit->simper_id === (int) $simper->id, 404);

        $unit->delete();

        return back()->with('sukses', 'Unit dihapus.');
    }

    public function simperTindak(Request $r, Simper $simper)
    {
        return $this->tindak($r, $simper, 'SIMPER');
    }

    /* ═══════════════════ PENGAJUAN LANJUTAN ═══════════════════ */

    public function ajuanStore(Request $r, Simper $simper)
    {
        $data = $r->validate([
            'jenis'         => ['required', 'in:'.implode(',', array_keys(SimperAjuan::JENIS))],
            'no_registrasi' => ['nullable', 'string', 'max:40'],
            'tanggal'       => ['required', 'date'],
            'pengalaman_kerja' => ['nullable', 'string', 'max:100'],
            'simpol_berlaku_sampai' => ['nullable', 'date'],
            'catatan'       => ['nullable', 'string'],
        ]);

        /* Perpanjangan tidak boleh diajukan terlalu awal. SOP membuka
           jendelanya sebulan sebelum berakhir; di Project1 ambang itu
           hanya mewarnai layar pemantauan dan tidak pernah membatasi
           apa pun, sehingga antrean OHSE terisi berkas yang belum
           waktunya sepanjang tahun. */
        if ($data['jenis'] === 'perpanjangan' && ! $simper->bolehDiperpanjang()) {
            return back()->withErrors([
                'jenis' => 'Perpanjangan baru dapat diajukan '
                    .SimperAjuan::HARI_BOLEH_PERPANJANG.' hari sebelum masa berlakunya berakhir.',
            ]);
        }

        $ajuan = SimperAjuan::create($data + [
            'simper_id' => $simper->id,
            'tanggal'   => Waktu::tanggal($data['tanggal']),
            'simpol_berlaku_sampai' => isset($data['simpol_berlaku_sampai'])
                ? Waktu::tanggal($data['simpol_berlaku_sampai']) : null,
            'user_id'   => $r->user()?->id,
        ]);

        $ajuan->terbitkanAlur();

        return back()->with('sukses', 'Pengajuan '.(SimperAjuan::JENIS[$data['jenis']]).' dibuat.');
    }

    public function ajuanUnit(Request $r, SimperAjuan $ajuan)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($ajuan, $r->user()));

        SimperAjuanUnit::create($this->validasiUnit($r) + ['ajuan_id' => $ajuan->id]);

        return back()->with('sukses', 'Unit ditambahkan ke pengajuan.');
    }

    public function ajuanTindak(Request $r, SimperAjuan $ajuan)
    {
        $hasil = $this->tindak($r, $ajuan, 'Pengajuan');

        /* Unit yang pengajuannya TUNTAS ikut masuk ke kartunya,
           bertanda asal pengajuan. Disimpan hanya pada pengajuannya,
           kartu yang unitnya bertambah tidak pernah menunjukkan unit
           barunya — dan yang dibaca petugas pos adalah kartu, bukan
           arsip pengajuan. */
        $ajuan->refresh()->load(['unit', 'simper']);

        if ($ajuan->status === 'selesai' && $ajuan->simper) {
            foreach ($ajuan->unit as $u) {
                SimperUnit::firstOrCreate(
                    [
                        'simper_id'     => $ajuan->simper_id,
                        'kendaraan_id'  => $u->kendaraan_id,
                        'jenis_unit_id' => $u->jenis_unit_id,
                    ],
                    [
                        'kewenangan'    => $u->kewenangan,
                        'nilai_p2h'     => $u->nilai_p2h,
                        'nilai_praktek' => $u->nilai_praktek,
                        'nilai_teori'   => $u->nilai_teori,
                        'nilai_rambu'   => $u->nilai_rambu,
                        'asal'          => $ajuan->jenis,
                    ],
                );
            }

            if ($ajuan->jenis === 'perpanjangan' && $ajuan->simpol_berlaku_sampai) {
                $ajuan->simper->update([
                    'simpol_berlaku_sampai' => $ajuan->simpol_berlaku_sampai,
                    'berlaku_sampai' => Waktu::kini()->endOfYear()->startOfDay(),
                ]);
            }
        }

        return $hasil;
    }

    public function ajuanDestroy(SimperAjuan $ajuan)
    {
        $this->buangAlur($ajuan);
        $ajuan->delete();

        return back()->with('sukses', 'Pengajuan dihapus.');
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /**
     * Satu pintu bagi seluruh tindakan alur.
     *
     * Ditulis sekali, dipakai lima jenis dokumen. Ditulis lima kali,
     * pemeriksaan "pengaju tidak menyetujui pengajuannya sendiri" akan
     * tertinggal di salah satunya — dan yang tertinggal tidak
     * menimbulkan galat, hanya dokumen yang disahkan orang yang
     * membuatnya.
     */
    private function tindak(Request $r, object $dokumen, string $sebutan)
    {
        $data = $r->validate([
            'keadaan' => ['required', 'in:setuju,tolak,dikembalikan'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $salah = Jalur::tindak($dokumen, $r->user(), $data['keadaan'], $data['catatan'] ?? null);

        if ($salah !== null) {
            return back()->withErrors(['keadaan' => $salah]);
        }

        return $this->balas($sebutan.' — '.match ($data['keadaan']) {
            'setuju'       => 'disetujui.',
            'tolak'        => 'ditolak.',
            default        => 'dikembalikan ke pengaju.',
        });
    }

    /**
     * Pesan sukses beserta peringatan pemberitahuan, bila ada.
     *
     * Dua pesan, bukan satu kalimat panjang — cara Safe Track
     * (st_sukses dan st_gagal): yang hijau mengatakan pekerjaannya
     * selesai, yang merah mengatakan apa yang masih perlu ditindaklanjuti.
     * Digabung, bagian keduanya terbaca sebagai catatan kaki dan
     * diabaikan — padahal justru itu yang menahan dokumennya.
     */
    private function balas(string $sukses)
    {
        $balas = back()->with('sukses', $sukses);

        return ($pesan = Jalur::pesanSurel()) ? $balas->with('galat', $pesan) : $balas;
    }

    /**
     * Kirim berkas ke penyetuju pertama — tombol "Kirim" / "Sending".
     *
     * Jenisnya dibaca dari default rute, sama seperti rincian().
     */
    public function kirim(Request $r, string $id)
    {
        $jenis   = (string) ($r->route()?->defaults['jenis'] ?? '');
        $dokumen = $this->dokumen($jenis, (int) $id);

        if ($alasan = Jalur::kirim($dokumen, $r->user())) {
            return back()->withErrors(['kirim' => $alasan]);
        }

        return $this->balas((RincianDokumen::LABEL[$jenis] ?? 'Berkas').' terkirim ke penyetuju.');
    }

    public function ajuanKirim(Request $r, SimperAjuan $ajuan)
    {
        if ($alasan = Jalur::kirim($ajuan, $r->user())) {
            return back()->withErrors(['kirim' => $alasan]);
        }

        return $this->balas('Pengajuan '.$ajuan->jenisLabel().' terkirim ke penyetuju.');
    }

    /**
     * Buang langkah alurnya sebelum dokumennya dihapus.
     *
     * `mnr_alur` berelasi polimorfik lewat sepasang kolom, jadi tidak
     * ada kunci asing yang membuangnya berkaskade. Dilewatkan, tiap
     * penghapusan meninggalkan langkah yatim yang menunjuk dokumen yang
     * sudah tidak ada — dan id yang kelak dipakai ulang membuatnya
     * muncul kembali pada dokumen yang sama sekali lain.
     */
    private function buangAlur(object $dokumen): void
    {
        $dokumen->alur()->delete();
    }

    /** @return list<array<string,mixed>> */
    /* ══════════════ halaman rincian per dokumen ══════════════ */

    /**
     * Rincian satu dokumen Miners.
     *
     * SATU METODE UNTUK EMPAT JENIS, dan itu bukan penghematan baris:
     * empat metode yang menggambar bingkai yang sama akan berbeda satu
     * per satu seiring waktu, dan yang tertinggal tidak menimbulkan
     * galat — hanya satu jenis dokumen yang diam-diam berhenti
     * menampilkan lampirannya. Persis begitu yang terjadi di Project1
     * pada langkah pengesahan KTT.
     */
    public function rincian(Request $r, string $id)
    {
        /* Jenisnya dibaca dari DEFAULT RUTE, bukan dari tanda tangan.
           Keduanya tampak setara, dan yang kedua salah diam-diam:
           Laravel mengisi parameter controller menurut URUTAN, bukan
           menurut nama, sehingga `rincian($r, $jenis, $id)` pada rute
           `miners/mcu/{id}` membuat $jenis menerima "1" dan $id
           menerima 0. Tidak ada galat — hanya 404 pada setiap dokumen
           yang sah, sebab tidak ada jenis bernama "1".

           Parameter rute juga selalu STRING, termasuk yang sudah
           disaring whereNumber; menuliskannya `int` di tanda tangan
           membuat PHP menolaknya sebagai TypeError. */
        $jenis = (string) ($r->route()?->defaults['jenis'] ?? '');
        $id    = (int) $id;

        /* Dokumen milik perusahaan lain memang tidak dapat ditemukan
           oleh pengguna ini, sehingga jawabannya 404 dan bukan 403 —
           dan itu memang lebih tepat, sebab 403 mengakui bahwa
           dokumennya ada. */
        $dokumen = $this->dokumen($jenis, $id, RincianDokumen::relasi($jenis));

        $dokumen->terbitkanAlur();
        $dokumen->load('alur.user');

        return Inertia::render('Miners/Dokumen',
            RincianDokumen::susun($jenis, $dokumen, $r->user()));
    }

    /**
     * Ajukan ulang pengajuan yang dikembalikan.
     *
     * Jenisnya dibaca dari default rute, sama seperti rincian(): lihat
     * alasannya di sana — Laravel mengisi parameter menurut urutan,
     * bukan menurut nama.
     */
    public function ajukanUlang(Request $r, string $id)
    {
        $jenis   = (string) ($r->route()?->defaults['jenis'] ?? '');
        $dokumen = $this->dokumen($jenis, (int) $id);

        if ($alasan = Jalur::ajukanUlang($dokumen, $r->user())) {
            return back()->withErrors(['ajukanUlang' => $alasan]);
        }

        return $this->balas('Pengajuan dikirim ulang; alurnya diulang dari awal.');
    }

    /**
     * Satu dokumen Miners menurut jenisnya, di bawah batas perusahaannya.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function dokumen(string $jenis, int $id, array $relasi = [])
    {
        $kelas = [
            'mcu'     => Mcu::class,
            'induksi' => Induksi::class,
            'permit'  => Permit::class,
            'simper'  => Simper::class,
        ][$jenis] ?? abort(404);

        /* findOrFail DI BAWAH SCOPE-nya, bukan diikuti pemeriksaan
           kepemilikan tersendiri: dokumen milik perusahaan lain memang
           tidak dapat ditemukan oleh pengguna ini. */
        return $kelas::with($relasi ?: ['alur'])->findOrFail($id);
    }

    private function alur(object $dokumen): array
    {
        return RincianDokumen::alur($dokumen);
    }

    /**
     * Golongan unit yang dipilih menuntut kelas SIM yang tidak dipegang.
     *
     * @return string|null alasan penolakan, atau null bila cocok
     */
    private function simpolTakCocok(Simper $simper, ?int $kendaraanId): ?string
    {
        if (! $kendaraanId) return null;

        $golongan = Kendaraan::find($kendaraanId);
        $dituntut = $golongan?->kelas_simpol;

        if (! $dituntut) return null;

        if ($simper->jenis_simpol !== $dituntut) {
            return $golongan->nama.' menuntut SIM '.$dituntut
                .', sedangkan SIMPER ini tercatat '.($simper->jenis_simpol ?: 'tanpa SIM').'.';
        }

        return null;
    }

    /** @return array<string,mixed> */
    /**
     * Buang medan berkas dari data yang sudah tervalidasi.
     *
     * Aturan unggahan HARUS ikut di larik validasi yang sama supaya
     * pesan galatnya muncul pada form yang sama dengan medan lain.
     * Tetapi NILAINYA tidak boleh ikut terbawa ke create()/update():
     * yang tervalidasi berupa objek UploadedFile, dan pada bentuk
     * `$data + [...]` ruas KIRI yang menang — objek unggahan itu akan
     * menimpa jalur hasil Berkas::simpan() dan disimpan sebagai isi
     * kolom. Kolomnya lalu memuat sesuatu yang bukan jalur, dan
     * berkasnya tetap tidak dapat dibuka meski sudah terunggah.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private static function tanpaBerkas(array $data): array
    {
        foreach (array_keys($data) as $k) {
            if (str_starts_with($k, 'berkas')) unset($data[$k]);
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function validasiSimper(Request $r, ?Simper $simper = null): array
    {
        return self::tanpaBerkas($r->validate([
            'permit_id'    => [$simper ? 'nullable' : 'required', 'exists:mnr_permit,id'],
            'no_simper'    => ['nullable', 'string', 'max:40'],
            'tanggal'      => ['required', 'date'],
            'kelas'        => ['required', 'in:'.implode(',', array_keys(Simper::KELAS))],
            'no_simpol'    => ['nullable', 'string', 'max:40'],
            'jenis_simpol' => ['nullable', 'in:'.implode(',', Simper::JENIS_SIMPOL)],
            'simpol_berlaku_sampai' => ['nullable', 'date'],
            'pengalaman_kerja' => ['nullable', 'string', 'max:100'],
            'email_atasan' => ['nullable', 'email', 'max:150'],
            'catatan'      => ['nullable', 'string'],
            'berkas_simpol'=> ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ]));
    }

    /** @return array<string,mixed> */
    private function validasiUnit(Request $r): array
    {
        return $r->validate([
            'kendaraan_id'  => ['nullable', 'exists:mnr_kendaraan,id'],
            'jenis_unit_id' => ['nullable', 'exists:mnr_jenis_unit,id'],
            'kewenangan'    => ['nullable', 'in:'.implode(',', array_keys(SimperUnit::KEWENANGAN))],
            'nilai_p2h'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_praktek' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_teori'   => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_rambu'   => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
    }
}
