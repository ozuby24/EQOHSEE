<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Miners\Concerns\TindakAlur;
use App\Models\Miners\{Departemen, HasilMcu, InduksiOrang, Jabatan, Mcu, McuOrang, McuRujukan, Pekerja, Permit, Simper};
use App\Models\User;
use App\Notifications\PemberitahuanMiners;
use App\Support\Berkas;
use App\Support\Db;
use App\Support\Miners\{AksesMiners, Cetakan, Jalur, Laporan, Penyetuju, RincianDokumen, StempelValidasi};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * MCU — mcusController, mcu_detailsController, dan rujukan_formsController
 * Safe Track.
 *
 * SATU SURAT MEMUAT BANYAK ORANG, dan orangnya ditambahkan LANGSUNG di
 * suratnya dengan NIK: bila NIK itu sudah dikenal pada perusahaannya,
 * baris manpower yang sama ditautkan; bila belum, manpower baru dibuat.
 * Itulah cara Safe Track membuat "karyawan" — tidak ada layar master
 * terpisah yang harus diisi lebih dulu.
 *
 * NIK YANG SUDAH PUNYA MINE PERMIT ATAU SIMPER AKTIF DITOLAK: MCU ulang
 * karyawan yang sudah bekerja lewat Perpanjangan, yang menulis balik ke
 * MCU yang SUDAH terhubung dengan kartunya — bukan MCU baru yang tidak
 * pernah tersambung ke mana pun (d8d5f9a).
 */
class McuController extends Controller
{
    use TindakAlur;

    /* ═══════════════════ daftar ═══════════════════ */

    /**
     * Register MCU (`master_mcu_user`) dan History MCU (`master_mcu_admin`).
     *
     * Register hanya surat buatan pengguna ini, dengan tombol tambah, ubah,
     * dan hapus; History seluruh surat dalam jangkauannya, hanya baca.
     */
    public function index(Request $r)
    {
        $riwayat = $r->route('tahap') === 'mcu';
        $u       = $r->user();
        $cari    = trim((string) $r->query('cari', ''));

        /* Register: surat buatan sendiri. History: seluruh surat bagi
           pengelola dan paramedis; bagi yang lain hanya yang boleh ia buka
           rinciannya (AksesMiners::saringDokumen). */
        $jangkau = fn ($w) => $riwayat ? AksesMiners::saringDokumen($w, $u, 'mcu') : $w->where('user_id', $u->id);

        $q = Mcu::query()->with(['company:id,name', 'user:id,name'])
            ->tap($jangkau)
            ->when($cari !== '', fn ($w) => $w->where(fn ($c) => $c->where('no_registrasi', Db::like(), "%{$cari}%")
                ->orWhere('perihal', Db::like(), "%{$cari}%")->orWhere('kepada', Db::like(), "%{$cari}%")));

        $halaman = $q->orderByDesc('tanggal')->orderByDesc('id')->paginate(15)->withQueryString();

        $halaman->getCollection()->transform(fn (Mcu $m) => [
            'id'         => $m->id,
            'tanggal'    => $m->tanggal?->toDateString(),
            'nomor'      => $m->no_registrasi,
            'perihal'    => $m->perihal,
            'kepada'     => $m->kepada,
            'catatan'    => $m->catatan,
            'company_id' => $m->company_id,
            'perusahaan' => $m->company?->name,
            'status'     => $m->status,
            'oleh'       => $m->user?->name,
            'bolehUbah'  => AksesMiners::ubahDraf($m, $u) || AksesMiners::kelola($u),
            'bolehHapus' => AksesMiners::ubahDraf($m, $u) || AksesMiners::kelola($u),
        ]);

        /* Kartu ringkas Safe Track: Manpower, Rujukan | Outstanding,
           Open | Total — dihitung atas surat dalam jangkauan layar ini. */
        $surat = Mcu::query()->tap($jangkau)->select('id');
        $orang = fn () => McuOrang::query()->whereIn('mcu_id', $surat);

        return Inertia::render('Miners/Mcu', [
            'judul' => $riwayat ? 'History MCU' : 'Data MCU',
            'kop'   => false,
            'mode'  => $riwayat ? 'riwayat' : 'register',

            'kartu' => [
                'manpower' => $orang()->count(),
                'rujukan'  => ($rujukan = HasilMcu::rujukan()) ? $orang()->where('hasil_id', $rujukan)->count() : 0,
                'open'     => McuOrang::query()->whereIn('mcu_id', Mcu::query()->tap($jangkau)
                    ->whereIn('status', ['diajukan', 'diperiksa'])->select('id'))->count(),
            ],

            'baris'  => $halaman,
            'saring' => ['cari' => $cari],

            'perusahaan'  => AksesMiners::perusahaan($u)->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->values(),
            'dokterSkema' => StempelValidasi::namaDokter(null, Penyetuju::iupDari($u->company_id)),
            'hariIni'     => Waktu::kini()->toDateString(),
            'STATUS'      => Mcu::STATUS,
        ]);
    }

    /** Laporan Excel MCU — Report_MCU / Report_MCU_User Safe Track. */
    public function ekspor(Request $r)
    {
        $u       = $r->user();
        $riwayat = $r->query('mode') === 'riwayat';

        $dari   = $r->date('dari')?->startOfDay();
        $sampai = $r->date('sampai')?->endOfDay();

        $baris = McuOrang::query()->with(['mcu.company', 'mcu.user', 'hasil', 'departemen'])
            ->whereIn('mcu_id', Mcu::query()->tap(fn ($w) => $riwayat ? AksesMiners::saringDokumen($w, $u, 'mcu') : $w->where('user_id', $u->id))->select('id'))
            ->when($dari, fn ($q) => $q->where('created_at', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('created_at', '<=', $sampai))
            ->orderBy('created_at')->get()
            ->map(function (McuOrang $o) use ($u) {
                /* Hasil dan catatan dokter hanya bagi yang boleh membaca isi medis. */
                $medis = AksesMiners::medis($u, $o->mcu);

                return [
                    Waktu::lokal($o->created_at)?->format('Y-m-d H:i'),
                    $o->id, $o->nik, $o->no_id, $o->nama, $o->usia, $o->jabatan, $o->departemen?->nama,
                    $o->mcu?->company?->name, $o->tanggal_periksa?->toDateString(), $o->berlaku_sampai?->toDateString(),
                    $medis ? $o->hasil?->nama : null, $o->jenis_mcu, $o->catatan_mitra, $medis ? $o->catatan : null,
                    Mcu::STATUS[$o->mcu?->status] ?? $o->mcu?->status, $o->mcu?->user?->name,
                ];
            });

        return Laporan::unduh('laporan-mcu-'.Waktu::kini()->format('Ymd-Hi'), [
            'Tanggal', 'ID System', 'NIK', 'Noid', 'Nama Karyawan', 'Umur', 'Jabatan', 'Departemen', 'Perusahaan',
            'Date Of MCU', 'Next Of MCU', 'Hasil MCU', 'Status MCU', 'Catatan', 'Catatan Dokter', 'Status Pengajuan', 'CreatedBy',
        ], $baris);
    }

    /* ═══════════════════ surat ═══════════════════ */

    /**
     * Surat MCU baru — `add_mcu_user` Safe Track: tanggal hari ini, kepada
     * dokter IUP, judul "Permohonan Sertifikat MCU", dan perusahaan
     * pemberi kerja. Akun mitra selalu perusahaannya sendiri.
     */
    public function store(Request $r)
    {
        $data = $this->validasiSurat($r);

        $mcu = Mcu::create($data + [
            'tanggal' => Waktu::tanggal($data['tanggal']),
            'user_id' => $r->user()->id,
        ]);

        $mcu->terbitkanAlur();

        return redirect()->route('miners.mcu.rincian', $mcu->id)->with('sukses', 'Surat MCU '.$mcu->fresh()->no_registrasi.' dibuat.');
    }

    /**
     * Ubah surat — `update_mcu` Safe Track.
     *
     * Bila TANGGAL SURAT berubah, masa berlaku manpower yang MASIH BAWAAN
     * (tanggal lama + 1 tahun) ikut bergeser; yang sudah ditetapkan
     * tersendiri — dipersingkat dokter, misalnya — dibiarkan (e9c281c).
     */
    public function update(Request $r, Mcu $mcu)
    {
        AksesMiners::tegakkan(AksesMiners::ubahDraf($mcu, $r->user()) || AksesMiners::kelola($r->user()));

        $data = $this->validasiSurat($r, $mcu);

        $lama = $mcu->tanggal?->copy();
        $baru = Waktu::tanggal($data['tanggal']);

        $mcu->update($data + ['tanggal' => $baru]);

        if ($lama && $baru && ! $lama->isSameDay($baru)) {
            McuOrang::query()->where('mcu_id', $mcu->id)
                ->whereDate('berlaku_sampai', $lama->copy()->addYear()->toDateString())
                ->update(['berlaku_sampai' => $baru->copy()->addYear()->toDateString(),
                          'tanggal_berikut' => $baru->copy()->addYear()->toDateString()]);
        }

        return back()->with('sukses', 'Surat MCU '.$mcu->no_registrasi.' diperbarui.');
    }

    /**
     * Hapus surat. Safe Track menghapus berantai sampai ke induksi dan
     * Mine Permit-nya; di sini penghapusan DITOLAK selama ada nama di
     * dalamnya yang sudah menjadi dasar induksi atau kartu — riwayat yang
     * sudah dipakai dokumen lain tidak dihapus diam-diam.
     */
    public function destroy(Request $r, Mcu $mcu)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($mcu, $r->user()));

        $ids = $mcu->orang()->pluck('id');

        $dipakai = InduksiOrang::withoutGlobalScopes()->whereIn('mcu_orang_id', $ids)->exists()
            || Permit::withoutGlobalScopes()->whereIn('mcu_orang_id', $ids)->exists();

        if ($dipakai) {
            return back()->withErrors(['hapus' => 'Surat '.$mcu->no_registrasi.' tidak dapat dihapus: ada nama di dalamnya '
                .'yang sudah menjadi dasar induksi atau Mine Permit.']);
        }

        $mcu->alur()->delete();
        $mcu->delete();

        return redirect()->route('miners.mcu.index')->with('sukses', 'Surat MCU dihapus.');
    }

    /* ═══════════════════ rincian ═══════════════════ */

    /** Rincian surat MCU — `detail_mcu_user` / `detail_mcu_admin` Safe Track. */
    public function rincian(Request $r, string $id)
    {
        $u   = $r->user();
        $mcu = Mcu::with(['alur.user', 'alur.ditugaskan', 'user', 'iup', 'company',
            'orang.hasil', 'orang.departemen', 'orang.rujukan.user', 'orang.mcu'])->findOrFail((int) $id);

        AksesMiners::tegakkan(AksesMiners::lihatDokumen($mcu, $u), 'Anda hanya dapat membuka MCU yang Anda ajukan atau ditugaskan kepada Anda.');

        $mcu->terbitkanAlur();
        $mcu->load('alur.user', 'alur.ditugaskan');

        $dokter  = AksesMiners::penyetujuLangkah($mcu, $u, 'dokter');
        $draf    = AksesMiners::ubahDraf($mcu, $u);
        $kelola  = AksesMiners::kelola($u);
        $giliran = Jalur::bolehTindak($mcu, $u);
        $milik   = (int) $mcu->user_id === (int) $u->id || $u->isAdmin();
        $rujuk   = $kelola || $dokter || $u->isParamedis();

        /* Hasil, kategori, catatan dokter, rujukan, stempel, dan sertifikat
           tidak dikirim sama sekali kepada yang bukan pembaca isi medis —
           menyembunyikannya di layar saja tidak cukup. */
        $medis   = AksesMiners::medis($u, $mcu);

        $langkah = $mcu->langkahBerjalan();

        return Inertia::render('Miners/McuRincian', [
            'judul' => 'Detail MCU',
            'kop'   => false,

            'mcu' => [
                'id'         => $mcu->id,
                'nomor'      => $mcu->no_registrasi,
                'tanggal'    => $mcu->tanggal?->toDateString(),
                'kepada'     => $mcu->kepada,
                'perihal'    => $mcu->perihal,
                'catatan'    => $mcu->catatan,
                'status'     => $mcu->status,
                'company_id' => $mcu->company_id,
                'perusahaan' => $mcu->company?->name,
                'iup'        => $mcu->iup?->name,
                'pengaju'    => $mcu->user?->name,
                'bulan'      => $mcu->bulan_berlaku,
                'alasanTolak' => $mcu->alur->first(fn ($a) => in_array($a->keadaan, ['tolak', 'dikembalikan'], true))?->catatan,
            ],

            'orang' => $mcu->orang->map(fn (McuOrang $o) => [
                'id'          => $o->id,
                'pekerja_id'  => $o->pekerja_id,
                'nama'        => $o->nama,
                'nik'         => $o->nik,
                'noid'        => $o->no_id,
                'usia'        => $o->usia,
                'jabatan'     => $o->jabatan,
                'departemen_id' => $o->departemen_id,
                'departemen'  => $o->departemen?->nama,
                'periksa'     => $o->tanggal_periksa?->toDateString(),
                'sampai'      => $o->berlaku_sampai?->toDateString(),
                'jenis'       => $o->jenis_mcu,
                'risiko'      => $o->level_risiko,
                'hasil_id'    => $medis ? $o->hasil_id : null,
                'hasil'       => $medis && ! $o->belumDinilai() ? $o->hasil?->nama : null,
                'layak'       => $medis ? $o->layak() : null,
                'nilaiHasil'  => ! $medis ? 'terjaga' : ($o->belumDinilai() ? 'belum' : ($o->layak() ? ($o->hasil?->nilai >= 100 ? 'fit' : 'catatan') : ($o->dirujuk() ? 'rujukan' : 'tidak'))),
                'catatan'     => $medis ? $o->catatan : null,
                'catatanMitra' => $o->catatan_mitra,
                'perbaikan'   => $o->catatan_perbaikan,
                'berkas'      => [
                    'hasil'   => Berkas::lampiran($u, $o, 'mnh'),
                    'rujukan' => $medis ? Berkas::lampiran($u, $o, 'mnr') : ['ada' => false, 'url' => null],
                ],
                'rujukan' => ! $medis ? [] : $o->rujukan->map(fn (McuRujukan $j) => [
                    'id'         => $j->id,
                    'tanggal'    => $j->tanggal_surat?->toDateString(),
                    'dokter'     => $j->dokter,
                    'poliklinik' => $j->poliklinik,
                    'rumahSakit' => $j->rumah_sakit,
                    'oleh'       => $j->user?->name,
                    'surat'      => $this->bolehSurat($j, $u) ? route('miners.mcu.rujukan.surat', $j->id) : null,
                ])->values(),
                'stempel' => $medis ? StempelValidasi::untuk($o) : null,

                'bolehUbah'   => $draf || $kelola || ($milik && filled($o->catatan_perbaikan)),
                'bolehHapus'  => $draf,
                'bolehNilai'  => $dokter,
                'bolehPerbaiki' => $giliran,
                'bolehRujuk'  => $rujuk,
                'cetak'       => $medis && in_array($mcu->status, StempelValidasi::DISETUJUI, true)
                    ? route('miners.mcu.sertifikat', $o->id) : null,
            ])->values(),

            'alur'   => RincianDokumen::alur($mcu),
            'posisi' => Jalur::posisi($mcu),

            'bolehTambah'      => $draf,
            'bolehUbahSurat'   => $draf || $kelola,
            'bolehKirim'       => Jalur::bolehKirim($mcu, $u) && ! $mcu->alurTertahan(),
            'bolehAjukanUlang' => Jalur::bolehKirim($mcu, $u) && $mcu->alurTertahan(),
            'bolehTindak'      => $giliran,
            'langkahDokter'    => $langkah?->peran === 'dokter',
            'belumDinilai'     => $mcu->orang->filter(fn (McuOrang $o) => $o->belumDinilai())->count(),
            'medis'            => $medis,

            'pilihan' => [
                'departemen'  => collect(Departemen::pilihan())->map(fn ($n, $id) => ['nilai' => $id, 'label' => $n])->values(),
                'jabatan'     => array_values(Jabatan::pilihan()),
                'jenis'       => McuOrang::JENIS_MCU,
                'risiko'      => McuOrang::LEVEL_RISIKO,
                'hasil'       => HasilMcu::pilihanDokter()->map(fn ($h) => ['nilai' => $h->id, 'label' => $h->nama])->values(),
                'perusahaan'  => AksesMiners::perusahaan($u)->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->values(),
                'bulan'       => Mcu::BULAN_BERLAKU,
            ],
            'batasKb' => Berkas::MAKS_DOKUMEN_KB,
        ]);
    }

    /* ═══════════════════ manpower ═══════════════════ */

    /**
     * Tambah manpower LANGSUNG di surat — `add_detail_manpower` Safe Track.
     */
    public function tambahOrang(Request $r, Mcu $mcu)
    {
        AksesMiners::tegakkan(AksesMiners::ubahDraf($mcu, $r->user()),
            'Manpower hanya dapat ditambahkan pengaju selama surat MCU masih draf.');

        $data = $this->validasiOrang($r, wajibBerkas: true);

        $nik = trim($data['nik']);

        if ($salah = $this->nikPunyaDokumen($mcu, $nik)) {
            return back()->withErrors(['nik' => $salah])->withInput();
        }

        if ($mcu->orang()->where('nik', $nik)->exists()) {
            return back()->withErrors(['nik' => 'NIK '.$nik.' sudah ada pada surat MCU ini.'])->withInput();
        }

        $pekerja = $this->pekerjaDariNik($mcu, $data);
        $periksa = Waktu::tanggal($data['tanggal_periksa']);
        $habis   = McuOrang::masaBerlaku($periksa);

        $o = McuOrang::create([
            'mcu_id'          => $mcu->id,
            'pekerja_id'      => $pekerja->id,
            'nama'            => $data['nama'],
            'nik'             => $nik,
            'no_id'           => $pekerja->no_induk ?: McuOrang::nomorSementara($nik),
            'usia'            => $data['usia'],
            'jabatan'         => $data['jabatan'],
            'departemen_id'   => $data['departemen_id'],
            'jenis_mcu'       => $data['jenis_mcu'],
            'level_risiko'    => $data['level_risiko'],
            'catatan_mitra'   => $data['catatan_mitra'] ?? null,
            'tanggal_periksa' => $periksa,
            'berlaku_sampai'  => $habis,
            'tanggal_berikut' => $habis,
            'hasil_id'        => HasilMcu::belumDinilai(),
            'aktif'           => true,
            'berkas_hasil'    => Berkas::simpan($r->file('berkas_hasil'), 'miners/mcu'),
            'berkas_rujukan'  => Berkas::simpan($r->file('berkas_rujukan'), 'miners/mcu'),
        ]);

        return back()->with('sukses', 'Manpower "'.$o->nama.'" tersimpan'
            .($habis ? ', masa berlaku MCU sampai '.$habis->format('d-m-Y') : '').'.');
    }

    /**
     * Ubah satu manpower. Pengaju selama surat draf — atau kapan pun
     * namanya sedang DIKEMBALIKAN untuk diperbaiki; menyimpan perbaikan
     * itu menghapus tandanya (Safe Track 31d7171). Admin/HSE IUP boleh
     * menyunting kapan pun, termasuk menetapkan "Expired MCU".
     */
    public function ubahOrang(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $u      = $r->user();
        $kelola = AksesMiners::kelola($u);
        $milik  = (int) $mcu->user_id === (int) $u->id || $u->isAdmin();

        AksesMiners::tegakkan(AksesMiners::ubahDraf($mcu, $u) || $kelola || ($milik && filled($orang->catatan_perbaikan)));

        $data = $this->validasiOrang($r, wajibBerkas: false, orang: $orang);

        $nik = trim($data['nik']);

        if ($nik !== $orang->nik && ($salah = $this->nikPunyaDokumen($mcu, $nik))) {
            return back()->withErrors(['nik' => $salah]);
        }

        $periksa = Waktu::tanggal($data['tanggal_periksa']);

        $habis = $kelola && ! empty($data['berlaku_sampai'])
            ? McuOrang::masaBerlaku($periksa, Waktu::tanggal($data['berlaku_sampai']))
            : ($periksa && ! $periksa->isSameDay($orang->tanggal_periksa ?? $periksa->copy()->subDay())
                ? McuOrang::masaBerlaku($periksa) : $orang->berlaku_sampai);

        $orang->update([
            'nama'            => $data['nama'],
            'nik'             => $nik,
            'usia'            => $data['usia'],
            'jabatan'         => $data['jabatan'],
            'departemen_id'   => $data['departemen_id'],
            'jenis_mcu'       => $data['jenis_mcu'],
            'level_risiko'    => $data['level_risiko'],
            'catatan_mitra'   => $data['catatan_mitra'] ?? $orang->catatan_mitra,
            'tanggal_periksa' => $periksa,
            'berlaku_sampai'  => $habis,
            'tanggal_berikut' => $habis,
            'catatan_perbaikan' => null,
            'berkas_hasil'    => Berkas::simpan($r->file('berkas_hasil'), 'miners/mcu') ?? $orang->berkas_hasil,
            'berkas_rujukan'  => Berkas::simpan($r->file('berkas_rujukan'), 'miners/mcu') ?? $orang->berkas_rujukan,
        ]);

        return back()->with('sukses', 'Data '.$orang->nama.' diperbarui.');
    }

    public function hapusOrang(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);
        AksesMiners::tegakkan(AksesMiners::ubahDraf($mcu, $r->user()));

        if (InduksiOrang::withoutGlobalScopes()->where('mcu_orang_id', $orang->id)->exists()
            || Permit::withoutGlobalScopes()->where('mcu_orang_id', $orang->id)->exists()) {
            return back()->withErrors(['hapus' => $orang->nama.' sudah menjadi dasar induksi atau Mine Permit.']);
        }

        $orang->delete();

        return back()->with('sukses', 'Nama dihapus dari surat.');
    }

    /**
     * Hasil MCU satu nama — hanya DOKTER yang ditugasi pada surat ini (atau
     * admin), `bolehNilai` Safe Track: penilaian medis. Pengaju masih boleh
     * melengkapi tanggal pemeriksaan dan berkasnya selama suratnya draf,
     * tetapi tidak menetapkan hasilnya.
     */
    public function hasil(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $dokter = AksesMiners::penyetujuLangkah($mcu, $r->user(), 'dokter');
        AksesMiners::tegakkan($dokter || AksesMiners::ubahDraf($mcu, $r->user()));

        if (! $dokter && $r->filled('hasil_id')) {
            return back()->withErrors(['hasil_id' => 'Hasil MCU hanya dapat ditetapkan dokter pemeriksa surat ini.']);
        }

        $data = self::tanpaBerkas($r->validate([
            'hasil_id'        => ['nullable', 'exists:mnr_hasil_mcu,id'],
            'tanggal_periksa' => ['nullable', 'date'],
            'hasil_napza'     => ['nullable', 'in:negatif,positif'],
            'catatan'         => ['nullable', 'string', 'max:2000'],
            'berkas_hasil'    => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_rekomendasi' => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_napza'    => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ], ['hasil_id.exists' => 'Hasil MCU yang dipilih tidak ada di master. Muat ulang halaman ini.']));

        /* Tanggal yang TIDAK dikirim dipertahankan; tanggal yang berubah
           menghitung ulang masa berlaku bawaan (satu tahun). */
        $ubah = [];

        if (array_key_exists('tanggal_periksa', $data)) {
            $periksa = $data['tanggal_periksa'] ? Waktu::tanggal($data['tanggal_periksa']) : null;
            $ubah['tanggal_periksa'] = $periksa;

            if ($periksa?->toDateString() !== $orang->tanggal_periksa?->toDateString()) {
                $ubah['berlaku_sampai']  = McuOrang::masaBerlaku($periksa);
                $ubah['tanggal_berikut'] = $ubah['berlaku_sampai'];
            }
        }

        foreach (['hasil_id', 'hasil_napza', 'catatan'] as $k) {
            if (array_key_exists($k, $data)) $ubah[$k] = $data[$k];
        }

        foreach (['berkas_hasil', 'berkas_rekomendasi', 'berkas_napza'] as $k) {
            if ($jalur = Berkas::simpan($r->file($k), 'miners/mcu')) $ubah[$k] = $jalur;
        }

        $orang->update($ubah);

        return back()->with('sukses', 'Hasil MCU '.$orang->nama.' disimpan.');
    }

    /**
     * Kembalikan SATU nama untuk diperbaiki — `kembalikan_perbaikan`
     * Safe Track (31d7171). Bukan hasil medis, bukan penolakan surat: nama
     * lain di surat yang sama tidak ikut tertunda. Hanya yang sedang
     * giliran pada surat ini (atau admin).
     */
    public function perbaiki(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $mcu->load('alur');
        AksesMiners::tegakkan(Jalur::bolehTindak($mcu, $r->user()), 'Bukan giliran Anda untuk pengajuan ini.');

        $data = $r->validate(['catatan_perbaikan' => ['required', 'string', 'max:1000']], [
            'catatan_perbaikan.required' => 'Catatan wajib diisi — pengaju perlu tahu apa yang harus diperbaiki.',
        ]);

        $orang->update(['catatan_perbaikan' => trim($data['catatan_perbaikan'])]);

        $galat = null;

        if ($pengaju = User::find($mcu->user_id)) {
            $galat = $this->surel(fn () => $pengaju->notify(new PemberitahuanMiners(
                'MCU '.$mcu->no_registrasi.' — '.$orang->nama.' perlu diperbaiki',
                [
                    'Data MCU atas nama '.$orang->nama.' pada surat '.$mcu->no_registrasi.' dikembalikan untuk diperbaiki.',
                    'Yang perlu diperbaiki: '.$orang->catatan_perbaikan,
                    'Nama lain pada surat yang sama tidak terpengaruh.',
                ],
                route('miners.mcu.rincian', $mcu->id, false),
                'Buka surat MCU',
            )));
        }

        return $this->balas('Pengaju sudah diberi tahu khusus untuk '.$orang->nama
            .'. Nama lain di pengajuan ini tidak terpengaruh.', $galat);
    }

    /* ═══════════════════ rujukan ═══════════════════ */

    /** Surat rujukan baru — `add_rujukan` Safe Track, dengan surel ke OHSE. */
    public function rujukan(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $u = $r->user();
        AksesMiners::tegakkan(AksesMiners::kelola($u) || $u->isParamedis()
            || AksesMiners::penyetujuLangkah($mcu, $u, 'dokter'), 'Surat rujukan hanya dibuat dokter atau HSE IUP.');

        $data = self::tanpaBerkas($r->validate([
            'tanggal_surat'  => ['required', 'date'],
            'dokter'         => ['required', 'string', 'max:150'],
            'rumah_sakit'    => ['required', 'string', 'max:200'],
            'poliklinik'     => ['required', 'string', 'max:150'],
            'diagnosis_awal' => ['required', 'string', 'max:2000'],
            'keterangan'     => ['required', 'string', 'max:2000'],
            'berkas'         => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ]));

        $j = McuRujukan::create($data + [
            'mcu_orang_id'  => $orang->id,
            'tanggal_surat' => Waktu::tanggal($data['tanggal_surat']),
            'berkas'        => Berkas::simpan($r->file('berkas'), 'miners/rujukan'),
            'user_id'       => $u->id,
        ]);

        $tujuan = Penyetuju::kotakOhse($mcu->iup_id ? (int) $mcu->iup_id : null);

        $galat = $tujuan === [] ? null : $this->surel(fn () => Notification::route('mail', $tujuan)->notify(new PemberitahuanMiners(
            'Rujukan MCU baru — '.$orang->nama,
            [
                'Dokter membuat surat rujukan atas nama '.$orang->nama.' (surat MCU '.$mcu->no_registrasi.').',
                'Dirujuk ke '.$j->dokter.', poliklinik '.$j->poliklinik.', '.$j->rumah_sakit.'.',
            ],
            route('miners.mcu.rincian', $mcu->id, false),
            'Buka surat MCU',
            Penyetuju::pecahEmail((string) config('miners.surel.tembusan')),
        )));

        return $this->balas('Surat rujukan '.$orang->nama.' dicatat.', $galat);
    }

    public function hapusRujukan(Request $r, McuRujukan $rujukan)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()));

        $rujukan->delete();

        return back()->with('sukses', 'Surat rujukan dihapus.');
    }

    /**
     * Cetak surat rujukan — `surat_rujukan/{id}` Safe Track. Yang boleh:
     * lihat bolehSurat() — pembaca isi medis yang boleh membuka MCU-nya.
     */
    public function suratRujukan(Request $r, McuRujukan $rujukan)
    {
        AksesMiners::tegakkan($this->bolehSurat($rujukan, $r->user()), 'Surat rujukan hanya dibuka admin, tim OHSE, paramedis, atau dokter surat MCU-nya.');

        return Inertia::render('Print/SuratRujukan', [
            's'       => Cetakan::suratRujukan($rujukan),
            'kembali' => route('miners.mcu.rincian', $rujukan->orang->mcu_id, false),
        ]);
    }

    /* ═══════════════════ sertifikat ═══════════════════ */

    /** Sertifikat MCU — `print_sertifikat/{id}` Safe Track. */
    public function sertifikat(Request $r, McuOrang $orang)
    {
        /* Sertifikat menyatakan hasil dan catatan dokter: boleh dibuka
           dokumennya (lihatDokumen) DAN boleh membaca isi medis. */
        $mcu = $orang->mcu;
        abort_unless($mcu, 404);
        AksesMiners::tegakkan(AksesMiners::lihatDokumen($mcu, $r->user()) && AksesMiners::medis($r->user(), $mcu),
            'Sertifikat MCU hanya dibuka admin, tim OHSE, paramedis, atau dokter surat ini.');

        return Inertia::render('Print/SertifikatMcu', [
            's'       => Cetakan::sertifikatMcu($orang),
            'kembali' => route('miners.mcu.rincian', $orang->mcu_id, false),
        ]);
    }

    /* ═══════════════════ alur ═══════════════════ */

    /**
     * Setujui / tolak / kembalikan. Persetujuan DOKTER membawa pilihan
     * masa berlaku 3, 6, atau 12 bulan (`sending_mcu_dokter` Safe Track);
     * syarat "tidak ada yang Belum Dinilai" dijaga Mcu::syaratSetuju().
     */
    public function tindak(Request $r, Mcu $mcu)
    {
        $mcu->load('alur');

        if ($r->input('keadaan') === 'setuju' && $mcu->langkahBerjalan()?->peran === 'dokter') {
            $data = $r->validate(['bulan_berlaku' => ['required', Rule::in(Mcu::BULAN_BERLAKU)]], [
                'bulan_berlaku.required' => 'Pilih masa berlaku MCU: 3 bulan, 6 bulan, atau 1 tahun.',
            ]);

            $mcu->bulan_berlaku = (int) $data['bulan_berlaku'];
        }

        return $this->tindakAlur($r, $mcu, 'Surat MCU');
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /** @return array<string,mixed> */
    private function validasiSurat(Request $r, ?Mcu $lama = null): array
    {
        $boleh = AksesMiners::perusahaan($r->user())->pluck('id')->all();

        $data = $r->validate([
            'no_registrasi' => ['nullable', 'string', 'max:40'],
            'tanggal'       => ['required', 'date'],
            'kepada'        => ['required', 'string', 'max:200'],
            'perihal'       => ['nullable', 'string', 'max:500'],
            'catatan'       => ['nullable', 'string', 'max:2000'],
            'company_id'    => ['nullable', 'integer', Rule::in($boleh ?: [0])],
        ], ['company_id.in' => 'Perusahaan di luar jangkauan Anda.']);

        /* Akun mitra SELALU perusahaannya sendiri — perusahaanKaryawan()
           Safe Track. MCU yang tersimpan atas nama IUP membuat karyawan
           mitra hilang dari layar mitranya sendiri. */
        if (count($boleh) === 1) $data['company_id'] = $boleh[0];

        if (empty($data['company_id'])) unset($data['company_id']);
        if (blank($data['no_registrasi'] ?? null)) unset($data['no_registrasi']);

        $data['perihal'] = $data['perihal'] ?? ($lama?->perihal ?? 'Permohonan Sertifikat MCU');

        return $data;
    }

    /** @return array<string,mixed> */
    private function validasiOrang(Request $r, bool $wajibBerkas, ?McuOrang $orang = null): array
    {
        return self::tanpaBerkas($r->validate([
            'nama'            => ['required', 'string', 'max:150'],
            'nik'             => ['required', 'string', 'max:40'],
            'usia'            => ['required', 'integer', 'min:15', 'max:80'],
            'tanggal_periksa' => ['required', 'date'],
            'jabatan'         => ['required', 'string', 'max:120'],
            'departemen_id'   => ['required', 'exists:mnr_departemen,id'],
            'jenis_mcu'       => ['required', Rule::in(McuOrang::JENIS_MCU)],
            'level_risiko'    => ['required', Rule::in(McuOrang::LEVEL_RISIKO)],
            'catatan_mitra'   => ['nullable', 'string', 'max:2000'],
            'berlaku_sampai'  => ['nullable', 'date'],
            'berkas_hasil'    => [$wajibBerkas ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_rujukan'  => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'nama.required'            => 'Nama lengkap wajib diisi.',
            'nik.required'             => 'NIK wajib diisi.',
            'usia.required'            => 'Umur wajib diisi.',
            'tanggal_periksa.required' => 'Tanggal MCU wajib diisi.',
            'jabatan.required'         => 'Jabatan wajib diisi.',
            'departemen_id.required'   => 'Departemen wajib dipilih. Bila daftarnya kosong, isi dulu lewat Master Data → Departemen.',
            'berkas_hasil.required'    => 'Berkas Hasil MCU wajib diunggah.',
        ]));
    }

    /**
     * NIK yang sudah punya Mine Permit atau SIMPER AKTIF di keluarga IUP
     * surat ini — alasan penolakannya, atau null.
     *
     * Dibatasi pada IUP surat ini dan mitra-mitranya: EQOHSEE dipakai
     * banyak perusahaan sekaligus, dan menolak berdasarkan dokumen di
     * tambang lain akan membocorkan bahwa orang itu bekerja di sana.
     */
    private function nikPunyaDokumen(Mcu $mcu, string $nik): ?string
    {
        $iup = $mcu->iup_id ?: Penyetuju::iupDari($mcu->company_id);

        $keluarga = \App\Models\Company::query()->where('id', $iup)->orWhere('parent_id', $iup)->pluck('id');

        $pekerja = Pekerja::withoutGlobalScopes()->where('nik', $nik)
            ->where(fn ($q) => $q->whereIn('company_id', $keluarga)->orWhereNull('company_id'))->pluck('id');

        if ($pekerja->isEmpty()) return null;

        $aktif = Permit::withoutGlobalScopes()->whereIn('pekerja_id', $pekerja)->where('aktif', true)->exists()
            || Simper::withoutGlobalScopes()->whereIn('pekerja_id', $pekerja)->where('aktif', true)->exists();

        return $aktif
            ? 'Karyawan dengan NIK ini sudah punya Mine Permit/SIMPER aktif. Gunakan menu Perpanjangan, '
              .'bukan tambah MCU, supaya datanya tetap tersambung ke dokumen yang sudah ada.'
            : null;
    }

    /**
     * Manpower dengan NIK ini pada perusahaan pemberi kerja surat — dipakai
     * bila sudah ada, dibuat bila belum. Jabatan dicocokkan ke master
     * menurut namanya; yang tidak ada di master tetap tercatat sebagai
     * teks pada baris MCU-nya.
     *
     * @param  array<string,mixed>  $data
     */
    private function pekerjaDariNik(Mcu $mcu, array $data): Pekerja
    {
        $nik = trim($data['nik']);

        $ada = Pekerja::withoutGlobalScopes()->where('nik', $nik)
            ->where(fn ($q) => $mcu->company_id ? $q->where('company_id', $mcu->company_id) : $q->whereNull('company_id'))
            ->first();

        if ($ada) return $ada;

        $jabatan = Jabatan::query()->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim($data['jabatan']))])->value('id');

        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $mcu->company_id,
            'nama'          => $data['nama'],
            'nik'           => $nik,
            'departemen_id' => $data['departemen_id'],
            'jabatan_id'    => $jabatan,
            'status'        => 'aktif',
            'user_id'       => null,
        ]);
    }

    /**
     * Surat rujukan adalah isi medis: yang boleh membuka surat MCU-nya
     * (lihatDokumen) DAN boleh membaca isi medis — bukan lagi setiap
     * pengguna perusahaan pemberi kerja pasiennya.
     */
    private function bolehSurat(McuRujukan $j, ?User $u): bool
    {
        $mcu = $j->orang?->mcu;

        return $mcu !== null && AksesMiners::lihatDokumen($mcu, $u) && AksesMiners::medis($u, $mcu);
    }
}
