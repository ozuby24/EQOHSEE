<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Miners\Concerns\TindakAlur;
use App\Models\Miners\{Induksi, InduksiOrang, Mcu, McuOrang, Permit};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Miners\{AksesMiners, Cetakan, Jalur, Laporan, LokasiInduksi, RincianDokumen};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Induksi — induction_registrationsController dan
 * induction_registration_detailsController Safe Track.
 *
 * CALON PESERTA (`calonPesertaInduksi`): MCU-nya SUDAH DISETUJUI dokter,
 * hasilnya LAYAK (Fit / Fit With Note), dan BELUM PERNAH didaftarkan
 * induksi. Bila daftarnya kosong, layarnya MENGATAKAN sebabnya
 * (`sebabKosong`) — "No results found" yang sama untuk empat keadaan yang
 * berbeda tindak lanjutnya sudah pernah menahan pengajuan berhari-hari.
 *
 * PER PESERTA: tanggal, lokasi (kantor IUP penyelenggara), dan Lulus /
 * Tidak Lulus yang ditetapkan HSE yang ditugasi. Sertifikat diterbitkan
 * aplikasi sendiri bagi peserta pendaftaran yang disetujui dan tidak
 * dinyatakan Tidak Lulus, bernomor IND/{kode}/{id}/{mm}/{yyyy} dan ber-QR.
 */
class InduksiController extends Controller
{
    use TindakAlur;

    /* ═══════════════════ daftar ═══════════════════ */

    /** Register Induction (`master_induction_user`) dan History (`master_induction_admin`). */
    public function index(Request $r)
    {
        $riwayat = $r->route('tahap') === 'induksi';
        $u       = $r->user();
        $cari    = trim((string) $r->query('cari', ''));

        $halaman = Induksi::query()->with(['user:id,name', 'company:id,name'])->withCount('orang')
            ->when(! $riwayat, fn ($w) => $w->where('user_id', $u->id))
            ->when($cari !== '', fn ($w) => $w->where(fn ($c) => $c->where('no_registrasi', 'like', "%{$cari}%")
                ->orWhere('perihal', 'like', "%{$cari}%")))
            ->orderByDesc('tanggal')->orderByDesc('id')->paginate(15)->withQueryString();

        $halaman->getCollection()->transform(fn (Induksi $i) => [
            'id'         => $i->id,
            'tanggal'    => $i->tanggal?->toDateString(),
            'nomor'      => $i->no_registrasi,
            'perihal'    => $i->perihal,
            'perusahaan' => $i->company?->name,
            'peserta'    => $i->orang_count,
            'status'     => $i->status,
            'oleh'       => $i->user?->name,
            'permohonan' => Berkas::lampiran($u, $i, 'mni'),
            'bolehHapus' => AksesMiners::ubahDraf($i, $u) || AksesMiners::kelola($u),
        ]);

        return Inertia::render('Miners/Induksi', [
            'judul'  => 'Data Master Induksi',
            'kop'    => false,
            'mode'   => $riwayat ? 'riwayat' : 'register',
            'baris'  => $halaman,
            'saring' => ['cari' => $cari],
            'hariIni' => Waktu::kini()->toDateString(),
        ]);
    }

    /** Laporan Excel induksi — Report_Induction_Adm Safe Track. */
    public function ekspor(Request $r)
    {
        $u       = $r->user();
        $riwayat = $r->query('mode') === 'riwayat';
        $dari    = $r->date('dari')?->startOfDay();
        $sampai  = $r->date('sampai')?->endOfDay();

        $baris = InduksiOrang::query()->with(['induksi.user', 'pekerja.company', 'pekerja.departemen', 'mcuOrang'])
            ->whereIn('induksi_id', Induksi::query()->when(! $riwayat, fn ($w) => $w->where('user_id', $u->id))->select('id'))
            ->when($dari, fn ($q) => $q->where('created_at', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('created_at', '<=', $sampai))
            ->orderBy('created_at')->get()
            ->map(fn (InduksiOrang $o) => [
                $o->tanggal_induksi?->toDateString() ?? $o->induksi?->tanggal?->toDateString(),
                $o->pekerja?->no_induk ?? $o->mcuOrang?->no_id,
                $o->induksi?->no_registrasi,
                $o->pekerja?->nama ?? $o->mcuOrang?->nama,
                $o->lokasi,
                $o->mcuOrang?->usia,
                $o->mcuOrang?->jabatan,
                $o->pekerja?->departemen?->nama,
                $o->pekerja?->company?->name,
                $o->mcuOrang?->berlaku_sampai?->toDateString(),
                InduksiOrang::STATUS[$o->status] ?? $o->status,
                $o->induksi?->user?->name,
            ]);

        return Laporan::unduh('laporan-induksi-'.Waktu::kini()->format('Ymd-Hi'), [
            'Tanggal', 'No ID', 'No Registrasi', 'Nama Lengkap', 'Lokasi Induksi', 'Umur', 'Jabatan',
            'Departemen', 'Perusahaan', 'Expired Of MCU', 'Status Induksi', 'CreatedBy',
        ], $baris);
    }

    /* ═══════════════════ pendaftaran ═══════════════════ */

    /** `save_induction_emp` Safe Track — tanggal hari ini, judul, form permohonan. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'tanggal'           => ['required', 'date'],
            'perihal'           => ['required', 'string', 'max:500'],
            'berkas_permohonan' => ['required', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'perihal.required'           => 'Title wajib diisi.',
            'berkas_permohonan.required' => 'File permohonan wajib diunggah.',
        ]);

        $induksi = Induksi::create([
            'tanggal'           => Waktu::tanggal($data['tanggal']),
            'perihal'           => $data['perihal'],
            'berkas_permohonan' => Berkas::simpan($r->file('berkas_permohonan'), 'miners/induksi'),
            'user_id'           => $r->user()->id,
        ]);

        $induksi->terbitkanAlur();

        return redirect()->route('miners.induksi.rincian', $induksi->id)->with('sukses', 'Pengajuan induksi '.$induksi->fresh()->no_registrasi.' dibuat.');
    }

    public function update(Request $r, Induksi $induksi)
    {
        AksesMiners::tegakkan(AksesMiners::ubahDraf($induksi, $r->user()) || AksesMiners::kelola($r->user()));

        $data = $r->validate([
            'tanggal'           => ['required', 'date'],
            'perihal'           => ['required', 'string', 'max:500'],
            'catatan'           => ['nullable', 'string', 'max:2000'],
            'berkas_permohonan' => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ]);

        $induksi->update([
            'tanggal'           => Waktu::tanggal($data['tanggal']),
            'perihal'           => $data['perihal'],
            'catatan'           => $data['catatan'] ?? $induksi->catatan,
            'berkas_permohonan' => Berkas::simpan($r->file('berkas_permohonan'), 'miners/induksi') ?? $induksi->berkas_permohonan,
        ]);

        return back()->with('sukses', 'Pengajuan induksi diperbarui.');
    }

    /** Hapus pengajuan — ditolak selama ada peserta yang sudah menjadi dasar Mine Permit. */
    public function destroy(Request $r, Induksi $induksi)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($induksi, $r->user()));

        if (Permit::withoutGlobalScopes()->whereIn('induksi_orang_id', $induksi->orang()->select('id'))->exists()) {
            return back()->withErrors(['hapus' => 'Pengajuan '.$induksi->no_registrasi.' tidak dapat dihapus: '
                .'ada peserta yang sudah menjadi dasar Mine Permit.']);
        }

        $induksi->alur()->delete();
        $induksi->delete();

        return redirect()->route('miners.induksi.index')->with('sukses', 'Pengajuan induksi dihapus.');
    }

    /* ═══════════════════ rincian ═══════════════════ */

    public function rincian(Request $r, string $id)
    {
        $u = $r->user();

        $induksi = Induksi::with(['alur.user', 'alur.ditugaskan', 'user', 'iup', 'company',
            'orang.pekerja.company', 'orang.mcuOrang.mcu.company'])->findOrFail((int) $id);

        $induksi->terbitkanAlur();
        $induksi->load('alur.user', 'alur.ditugaskan');

        $draf  = AksesMiners::ubahDraf($induksi, $u);
        $nilai = AksesMiners::penyetujuLangkah($induksi, $u, 'ohse');
        $calon = $draf ? self::calon() : collect();

        return Inertia::render('Miners/InduksiRincian', [
            'judul' => 'Detail Induction',
            'kop'   => false,

            'induksi' => [
                'id'         => $induksi->id,
                'nomor'      => $induksi->no_registrasi,
                'tanggal'    => $induksi->tanggal?->toDateString(),
                'perihal'    => $induksi->perihal,
                'catatan'    => $induksi->catatan,
                'status'     => $induksi->status,
                'iup'        => $induksi->iup?->name,
                'pengaju'    => $induksi->user?->name,
                'permohonan' => Berkas::lampiran($u, $induksi, 'mni'),
                'alasanTolak' => $induksi->alur->first(fn ($a) => in_array($a->keadaan, ['tolak', 'dikembalikan'], true))?->catatan,
            ],

            'orang' => $induksi->orang->map(function (InduksiOrang $o) use ($induksi, $u, $draf) {
                [$boleh, $alasan] = Cetakan::bolehSertifikatInduksi($o);

                return [
                    'id'         => $o->id,
                    'pekerja_id' => $o->pekerja_id,
                    'nama'       => $o->pekerja?->nama ?? $o->mcuOrang?->nama,
                    'perusahaan' => $o->pekerja?->company?->name ?? $o->mcuOrang?->mcu?->company?->name,
                    'mcuSampai'  => $o->mcuOrang?->berlaku_sampai?->toDateString(),
                    'tanggal'    => $o->tanggal_induksi?->toDateString(),
                    'lokasi'     => $o->lokasi,
                    'status'     => $o->status,
                    'lokasiPilihan' => LokasiInduksi::pilihan($induksi->iup_id ? (int) $induksi->iup_id : null, $o->lokasi),
                    'sertifikat' => $boleh ? route('miners.induksi.sertifikat', $o->id) : null,
                    'sertifikatAlasan' => $alasan,
                    'unggahan'   => Berkas::lampiran($u, $o, 'mns'),
                    'bolehHapus' => $draf,
                ];
            })->values(),

            'calon'       => $calon,
            'sebabKosong' => $draf && $calon->isEmpty() ? self::sebabKosong() : [],

            'alur'   => RincianDokumen::alur($induksi),
            'posisi' => Jalur::posisi($induksi),

            'bolehTambah'      => $draf,
            'bolehNilai'       => $nilai,
            'bolehKirim'       => Jalur::bolehKirim($induksi, $u) && ! $induksi->alurTertahan(),
            'bolehAjukanUlang' => Jalur::bolehKirim($induksi, $u) && $induksi->alurTertahan(),
            'bolehTindak'      => Jalur::bolehTindak($induksi, $u),
            'STATUS_ORANG'     => InduksiOrang::STATUS,
        ]);
    }

    /* ═══════════════════ peserta ═══════════════════ */

    /** `add_employee_induction` Safe Track — dari daftar calon peserta. */
    public function tambahOrang(Request $r, Induksi $induksi)
    {
        AksesMiners::tegakkan(AksesMiners::ubahDraf($induksi, $r->user()));

        $data = $r->validate(['mcu_orang_id' => ['required', 'integer']], [
            'mcu_orang_id.required' => 'Nama karyawan wajib dipilih.',
        ]);

        $mcu = McuOrang::query()->with('mcu')->find($data['mcu_orang_id']);

        if (! $mcu) {
            return back()->withErrors(['mcu_orang_id' => 'Karyawan yang dipilih tidak ada lagi. Muat ulang halaman ini.']);
        }

        /* SATU ORANG SEKALI SAJA — tiap salinannya nanti menghasilkan satu
           Mine Permit sendiri. */
        if ($ada = InduksiOrang::withoutGlobalScopes()->where('mcu_orang_id', $mcu->id)->whereHas('induksi')->first()) {
            return back()->withErrors(['mcu_orang_id' => 'Karyawan "'.$mcu->nama.'" sudah pernah didaftarkan induksi'
                .((int) $ada->induksi_id === (int) $induksi->id ? ' pada pengajuan ini.' : ' pada pengajuan lain.')]);
        }

        if (! self::calon()->contains('id', $mcu->id)) {
            return back()->withErrors(['mcu_orang_id' => 'Karyawan "'.$mcu->nama.'" belum dapat diajukan induksi: '
                .'MCU-nya harus sudah disetujui dokter dengan hasil Fit atau Fit With Note.']);
        }

        InduksiOrang::create([
            'induksi_id'   => $induksi->id,
            'pekerja_id'   => $mcu->pekerja_id,
            'mcu_orang_id' => $mcu->id,
            'status'       => 'belum',
            'percobaan'    => 1,
        ]);

        return back()->with('sukses', 'Karyawan "'.$mcu->nama.'" masuk daftar peserta induksi.');
    }

    public function hapusOrang(Request $r, Induksi $induksi, InduksiOrang $orang)
    {
        abort_unless((int) $orang->induksi_id === (int) $induksi->id, 404);
        AksesMiners::tegakkan(AksesMiners::ubahDraf($induksi, $r->user()));

        if (Permit::withoutGlobalScopes()->where('induksi_orang_id', $orang->id)->exists()) {
            return back()->withErrors(['hapus' => 'Peserta ini sudah menjadi dasar Mine Permit.']);
        }

        $orang->delete();

        return back()->with('sukses', 'Peserta dihapus.');
    }

    /**
     * Jadwal dan kelulusan seluruh peserta sekaligus — `update_schedule_induksi`
     * Safe Track: tanggal, lokasi, Lulus / Tidak Lulus per baris. Hanya
     * HSE yang ditugasi pada pendaftaran ini (atau admin).
     */
    public function jadwal(Request $r, Induksi $induksi)
    {
        AksesMiners::tegakkan(AksesMiners::penyetujuLangkah($induksi, $r->user(), 'ohse'),
            'Jadwal dan kelulusan induksi hanya diisi HSE yang ditugasi pada pendaftaran ini.');

        $data = $r->validate([
            'peserta'                   => ['required', 'array'],
            'peserta.*.id'              => ['required', 'integer'],
            'peserta.*.tanggal_induksi' => ['nullable', 'date'],
            'peserta.*.lokasi'          => ['nullable', 'string', 'max:150'],
            'peserta.*.status'          => ['nullable', Rule::in(array_keys(InduksiOrang::PILIHAN))],
        ]);

        $orang = $induksi->orang()->get()->keyBy('id');

        foreach ($data['peserta'] as $p) {
            if (! $o = $orang[$p['id']] ?? null) continue;

            $o->update([
                'tanggal_induksi' => ! empty($p['tanggal_induksi']) ? Waktu::tanggal($p['tanggal_induksi']) : null,
                'lokasi'          => $p['lokasi'] ?? null,
                'status'          => $p['status'] ?? $o->status,
            ]);
        }

        return back()->with('sukses', 'Jadwal dan kelulusan peserta disimpan.');
    }

    /** Satu peserta — tanggal, lokasi, kelulusan, dan berkas unggahan lama. */
    public function nilai(Request $r, Induksi $induksi, InduksiOrang $orang)
    {
        abort_unless((int) $orang->induksi_id === (int) $induksi->id, 404);

        AksesMiners::tegakkan(AksesMiners::penyetujuLangkah($induksi, $r->user(), 'ohse'),
            'Jadwal dan kelulusan induksi hanya diisi HSE yang ditugasi pada pendaftaran ini.');

        $data = self::tanpaBerkas($r->validate([
            'tanggal_induksi'   => ['nullable', 'date'],
            'lokasi'            => ['nullable', 'string', 'max:150'],
            'status'            => ['nullable', Rule::in(array_keys(InduksiOrang::PILIHAN))],
            'nilai'             => ['nullable', 'integer', 'min:0', 'max:100'],
            'catatan'           => ['nullable', 'string', 'max:2000'],
            'berkas_sertifikat' => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_hadir'      => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ]));

        $ubah = array_intersect_key($data, array_flip(['lokasi', 'status', 'nilai', 'catatan']));

        if (array_key_exists('tanggal_induksi', $data)) {
            $ubah['tanggal_induksi'] = $data['tanggal_induksi'] ? Waktu::tanggal($data['tanggal_induksi']) : null;
        }

        foreach (['berkas_sertifikat', 'berkas_hadir'] as $k) {
            if ($jalur = Berkas::simpan($r->file($k), 'miners/induksi')) $ubah[$k] = $jalur;
        }

        $orang->update($ubah);

        return back()->with('sukses', 'Data peserta disimpan.');
    }

    /** Sertifikat induksi — `sertifikat_induksi/{id}` Safe Track. */
    public function sertifikat(InduksiOrang $orang)
    {
        [$boleh, $alasan] = Cetakan::bolehSertifikatInduksi($orang->load('induksi'));

        return Inertia::render('Print/SertifikatInduksi', [
            'boleh'   => $boleh,
            'alasan'  => $alasan,
            's'       => $boleh ? Cetakan::sertifikatInduksi($orang) : null,
            'kembali' => route('miners.induksi.rincian', $orang->induksi_id, false),
        ]);
    }

    public function tindak(Request $r, Induksi $induksi)
    {
        return $this->tindakAlur($r, $induksi, 'Induksi');
    }

    /* ═══════════════════ kelayakan ═══════════════════ */

    /**
     * Calon peserta induksi — `calonPesertaInduksi` Safe Track.
     *
     * Lingkupnya PERUSAHAAN (scope surat MCU), bukan pembuat MCU-nya:
     * petugas yang mengajukan induksi kerap bukan yang mengetik MCU-nya.
     *
     * @return Collection<int,array{id:int,nama:string,noid:?string,perusahaan:?string}>
     */
    public static function calon(): Collection
    {
        $layak = \App\Models\Miners\HasilMcu::withoutGlobalScopes()->where('layak', true)->pluck('id');

        return McuOrang::query()->with('mcu.company:id,name')
            ->whereIn('mcu_id', Mcu::query()->where('status', 'selesai')->select('id'))
            ->whereIn('hasil_id', $layak)
            ->whereNotIn('id', InduksiOrang::withoutGlobalScopes()->whereNotNull('mcu_orang_id')
                ->whereIn('induksi_id', Induksi::withoutGlobalScopes()->select('id'))->select('mcu_orang_id'))
            ->orderBy('nama')->get()
            ->map(fn (McuOrang $o) => [
                'id'         => $o->id,
                'nama'       => $o->nama,
                'noid'       => $o->no_id,
                'perusahaan' => $o->mcu?->company?->name,
            ])->values();
    }

    /**
     * Kenapa daftar calon kosong — dalam kalimat (`sebabKosong` Safe Track).
     *
     * @return list<string>
     */
    public static function sebabKosong(): array
    {
        $u = auth()->user();
        $sebab = [];

        if ($u && ! $u->isAdmin() && ($u->company?->parent_id || $u->company?->isJasa())) {
            $sebab[] = 'Akun ini hanya melihat karyawan '.$u->company->name.'.';
        }

        $layak = \App\Models\Miners\HasilMcu::withoutGlobalScopes()->where('layak', true)->pluck('id');

        $semua     = McuOrang::query()->count();
        $disetujui = McuOrang::query()->whereIn('mcu_id', Mcu::query()->where('status', 'selesai')->select('id'))->count();
        $fit       = McuOrang::query()->whereIn('mcu_id', Mcu::query()->where('status', 'selesai')->select('id'))
            ->whereIn('hasil_id', $layak)->count();

        if ($semua === 0) {
            $sebab[] = 'Belum ada data MCU sama sekali untuk akun ini.';
        } elseif ($disetujui === 0) {
            $sebab[] = 'Ada '.$semua.' karyawan di data MCU, tetapi belum ada pengajuan MCU yang disetujui dokter.';
        } elseif ($fit === 0) {
            $sebab[] = 'Ada '.$disetujui.' karyawan dengan MCU disetujui, tetapi hasilnya bukan Fit / Fit With Note.';
        } else {
            $terdaftar = InduksiOrang::query()->with(['mcuOrang', 'induksi'])
                ->whereIn('mcu_orang_id', McuOrang::query()->whereIn('hasil_id', $layak)->select('id'))
                ->limit(10)->get();

            if ($terdaftar->isNotEmpty()) {
                $sebab[] = 'Sudah terdaftar di pengajuan induksi: '
                    .$terdaftar->map(fn ($t) => $t->mcuOrang?->nama.' ('.$t->induksi?->no_registrasi.')')->implode(', ')
                    .'. Hapus dulu dari pengajuan itu bila ingin didaftarkan di sini.';
            }
        }

        return $sebab;
    }
}
