<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Miners\Concerns\TindakAlur;
use App\Models\Company;
use App\Models\Miners\{Departemen, Induksi, InduksiOrang, Pekerja, Permit, PermitBerkas, RiwayatPerpanjangan};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Miners\{Acuan, AksesMiners, Jalur, Kelengkapan, Laporan, Penyetuju, RincianDokumen, SinkronMasaBerlaku, Zona};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Mine Permit — mine_permitsController, mine_permit_detailsController,
 * dan bagian Mine Permit MasaBerlakuController Safe Track.
 *
 * CALON PEMEGANG (`master_permit_users`): peserta induksi yang
 * pendaftarannya DISETUJUI, tidak dinyatakan Tidak Lulus, dan belum punya
 * Mine Permit.
 *
 * PERUSAHAAN (IUP) DAN CONTRACTOR (IUJP) DIPISAH DAN DIPERIKSA
 * (`periksaPasanganPerusahaan`): Perusahaan harus pemegang IUP;
 * Contractor harus IUJP mitra IUP itu dan sama dengan perusahaan
 * karyawannya; karyawan IUP sendiri tanpa Contractor. Nomornya dirakit
 * `{IUP}.{IUJP}.{Ymd}00{id}` dan sekaligus menjadi nomor induknya.
 *
 * MASA BERLAKU MENGIKUTI MCU, dapat dikunci admin, disinkronkan tiap
 * malam; kartu dinonaktifkan dengan alasan, tidak dihapus.
 */
class PermitController extends Controller
{
    use TindakAlur;

    /* ═══════════════════ daftar ═══════════════════ */

    /**
     * Register Mine Permit (`master_permit_users`, v11) dan History
     * (`master_permit_admin`, v04): kartu zona masa berlaku, saringan,
     * rekap per perusahaan (History), lalu daftar berhalaman.
     */
    public function index(Request $r)
    {
        $riwayat = $r->route('tahap') === 'mine-permit';
        $u       = $r->user();

        $saring = [
            'cari'       => trim((string) $r->query('cari', '')),
            'perusahaan' => (string) $r->query('perusahaan', ''),
            'zona'       => in_array($r->query('zona'), Zona::KUNCI, true) ? (string) $r->query('zona') : '',
            'aktif'      => in_array($r->query('aktif'), ['1', '0'], true) ? (string) $r->query('aktif') : '',
        ];

        $semua = Permit::query()
            ->with(['pekerja.company:id,name', 'mcuOrang', 'company:id,name', 'iup:id,name', 'perpanjanganBerjalan'])
            ->tap(fn ($q) => $riwayat ? AksesMiners::saringDokumen($q, $u, 'permit') : $q->where('user_id', $u->id))
            ->orderByDesc('tanggal')->orderByDesc('id')->get();

        $baris = $semua->map(function (Permit $p) use ($u) {
            $habis = $p->habisEfektif();
            $sisa  = Zona::sisa($habis);

            return [
                'id'         => $p->id,
                'nomor'      => $p->no_registrasi,
                'tanggal'    => $p->tanggal?->toDateString(),
                'noid'       => $p->pekerja?->nik ?? $p->mcuOrang?->nik,
                'nama'       => $p->pekerja?->nama ?? $p->mcuOrang?->nama,
                'pekerja_id' => $p->pekerja_id,
                'jabatan'    => $p->mcuOrang?->jabatan,
                'company_id' => $p->company_id,
                'perusahaan' => $p->company?->name ?? $p->pekerja?->company?->name,
                'berlaku'    => $habis?->toDateString(),
                'sisa'       => $sisa,
                'zona'       => Zona::dariSisa($sisa),
                'sumber'     => Permit::SUMBER[$p->sumber_berlaku ?? 'mcu'] ?? $p->sumber_berlaku,
                'sumberKode' => $p->sumber_berlaku ?? 'mcu',
                'status'     => $p->status,
                'aktif'      => (bool) $p->aktif,
                'nonaktifSejak' => $p->tanggal_nonaktif?->toDateString(),
                'alasanNonaktif' => $p->alasan_nonaktif,
                'bolehHapus' => $u->isAdmin(),
                'cari'       => mb_strtolower($p->no_registrasi.' '.$p->pekerja?->nama.' '.$p->pekerja?->nik.' '.$p->mcuOrang?->nama),
            ];
        });

        $tersaring = $baris
            ->when($saring['cari'] !== '', fn ($c) => $c->filter(fn ($b) => str_contains($b['cari'], mb_strtolower($saring['cari']))))
            ->when($saring['perusahaan'] !== '', fn ($c) => $c->where('company_id', (int) $saring['perusahaan']))
            ->when($saring['aktif'] !== '', fn ($c) => $c->where('aktif', $saring['aktif'] === '1'));

        /* Kartu zona dan rekap menghitung kartu AKTIF — kartu yang sudah
           dinonaktifkan tidak lagi ikut zona mana pun (Safe Track). */
        $aktif   = $tersaring->where('aktif', true);
        $ringkas = Zona::ringkas($aktif->pluck('zona'));
        $rekap   = Zona::rekapPerusahaan($aktif->all());

        if ($saring['zona'] !== '') {
            $tersaring = $tersaring->where('zona', $saring['zona']);
        }

        $kini = max(1, (int) $r->query('page', 1));

        $halaman = new \Illuminate\Pagination\LengthAwarePaginator(
            $tersaring->values()->forPage($kini, 15)->values()->map(fn ($b) => \Illuminate\Support\Arr::except($b, ['cari'])),
            $tersaring->count(), 15, $kini, ['path' => $r->url(), 'query' => $r->except('page')],
        );

        return Inertia::render('Miners/Permit', [
            'judul' => $riwayat ? 'Master Permit Admin' : 'Data Master Permit',
            'kop'   => false,
            'mode'  => $riwayat ? 'riwayat' : 'register',

            'saring'  => $saring,
            'ringkas' => $ringkas,
            'rekap'   => $rekap,
            'baris'   => $halaman,

            'kelola'     => AksesMiners::kelola($u),
            'perusahaan' => AksesMiners::perusahaan($u)->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->values(),

            /* Formulir tambah (Register) — calon dan pilihannya. */
            'calon'   => $riwayat ? [] : self::calon(),
            'pilihan' => $riwayat ? null : $this->pilihan($u),
        ]);
    }

    /** Laporan Excel Mine Permit — Report_MinePermit_Adm Safe Track. */
    public function ekspor(Request $r)
    {
        $u       = $r->user();
        $riwayat = $r->query('mode') === 'riwayat';
        $dari    = $r->date('dari')?->startOfDay();
        $sampai  = $r->date('sampai')?->endOfDay();

        $baris = Permit::query()->with(['pekerja.departemen', 'pekerja.company', 'kontraktor', 'iup', 'mcuOrang', 'user', 'perpanjanganBerjalan'])
            ->tap(fn ($q) => $riwayat ? AksesMiners::saringDokumen($q, $u, 'permit') : $q->where('user_id', $u->id))
            ->when($dari, fn ($q) => $q->where('created_at', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('created_at', '<=', $sampai))
            ->orderBy('created_at')->get()
            ->map(fn (Permit $p) => [
                $p->tanggal?->toDateString(), $p->pekerja?->nik, $p->no_registrasi, $p->pekerja?->no_induk,
                $p->pekerja?->nama, $p->pekerja?->tanggal_lahir?->toDateString(), $p->mcuOrang?->jabatan,
                $p->pekerja?->departemen?->nama, $p->iup?->name, $p->kontraktor?->name, $p->pekerja?->usia(),
                $p->pekerja?->gol_darah, $p->jenis_kartu, $p->pekerja?->telepon, $p->pekerja?->telepon_darurat,
                Permit::STATUS[$p->status] ?? $p->status, $p->habisEfektif()?->toDateString(), $p->user?->name, $p->catatan,
            ]);

        return Laporan::unduh('laporan-mine-permit-'.Waktu::kini()->format('Ymd-Hi'), [
            'Tanggal', 'NIK', 'No Registrasi', 'Noid', 'Nama Lengkap', 'Tanggal Lahir', 'Jabatan', 'Departemen',
            'Perusahaan', 'Subcontractor', 'Umur', 'Golongan Darah', 'Type Card', 'Phone', 'Phone2', 'Status',
            'Expired Permit', 'CreatedBy', 'Remarks',
        ], $baris);
    }

    /* ═══════════════════ pengajuan ═══════════════════ */

    /** `add_permit` Safe Track. */
    public function store(Request $r)
    {
        $data = $this->validasi($r, null);

        $io = InduksiOrang::query()->with(['induksi', 'mcuOrang', 'pekerja.company'])->find($data['induksi_orang_id']);

        if (! $io || ! self::calon()->contains('id', $io->id)) {
            return back()->withErrors(['induksi_orang_id' => 'Karyawan ini belum dapat dibuatkan Mine Permit: induksinya '
                .'harus sudah disetujui, tidak dinyatakan Tidak Lulus, dan ia belum punya Mine Permit.'])->withInput();
        }

        if ($galat = $this->periksaPasangan($data, $io->pekerja)) {
            return back()->withErrors($galat)->withInput();
        }

        $pekerja = $io->pekerja;
        $this->isiPekerja($pekerja, $data);

        $mcuSampai = $io->mcuOrang?->berlaku_sampai;

        $p = Permit::create([
            'company_id'       => $data['kontraktor_id'] ?? $pekerja?->company_id ?? $data['iup_id'],
            'iup_id'           => $data['iup_id'],
            'kontraktor_id'    => $data['kontraktor_id'] ?? null,
            'pekerja_id'       => $pekerja->id,
            'mcu_orang_id'     => $io->mcu_orang_id,
            'induksi_orang_id' => $io->id,
            'tanggal'          => Waktu::kini()->startOfDay(),
            'jenis_kartu'      => $data['jenis_kartu'],
            'cakupan_area'     => $data['cakupan_area'],
            'kode_warna'       => $data['kode_warna'] ?? null,
            'berlaku_sampai'   => $mcuSampai,
            'sumber_berlaku'   => 'mcu',
            'foto'             => Berkas::simpan($r->file('foto'), 'miners/permit/foto'),
            'berkas_ktp'       => Berkas::simpan($r->file('ktp'), 'miners/permit'),
            'berkas_permohonan'=> Berkas::simpan($r->file('permohonan'), 'miners/permit'),
            'catatan'          => $data['catatan'] ?? null,
            'user_id'          => $r->user()->id,
        ]);

        $this->beriNomor($p);
        $p->terbitkanAlur();

        return redirect()->route('miners.permit.rincian', $p->id)->with('sukses', 'Mine Permit '.$p->no_registrasi.' dibuat sebagai draf. Unggah lampiran SPDK, lalu kirim.');
    }

    /**
     * Ubah Mine Permit — HANYA ADMIN (admin atau HSE IUP) sejak Safe Track
     * 22 Okt 2026. Pasangan IUP/Contractor diperiksa ulang dan nomornya
     * dirakit ulang; cakupan area yang tidak dikirim dipertahankan.
     */
    public function update(Request $r, Permit $permit)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Mine Permit hanya disunting admin atau HSE IUP.');

        $data = $this->validasi($r, $permit);
        $permit->load('pekerja.company', 'mcuOrang');

        if ($galat = $this->periksaPasangan($data, $permit->pekerja)) {
            return back()->withErrors($galat)->withInput();
        }

        $this->isiPekerja($permit->pekerja, $data);

        if (filled($data['jabatan'] ?? null) && $permit->mcuOrang) {
            $permit->mcuOrang->update(['jabatan' => $data['jabatan']]);
        }

        $permit->update([
            'iup_id'        => $data['iup_id'],
            'kontraktor_id' => $data['kontraktor_id'] ?? null,
            'company_id'    => $data['kontraktor_id'] ?? $permit->pekerja?->company_id ?? $data['iup_id'],
            'jenis_kartu'   => $data['jenis_kartu'],
            'cakupan_area'  => $data['cakupan_area'] ?? $permit->cakupan_area,
            'kode_warna'    => $data['kode_warna'] ?? null,
            'catatan'       => $data['catatan'] ?? $permit->catatan,
            'berkas_ktp'    => Berkas::simpan($r->file('ktp'), 'miners/permit') ?? $permit->berkas_ktp,
            'berkas_permohonan' => Berkas::simpan($r->file('permohonan'), 'miners/permit') ?? $permit->berkas_permohonan,
            'foto'          => Berkas::simpan($r->file('foto'), 'miners/permit/foto') ?? $permit->foto,
        ]);

        $this->beriNomor($permit);

        return back()->with('sukses', 'Mine Permit '.$permit->no_registrasi.' diperbarui.');
    }

    /** Ganti foto — pemiliknya atau admin (`upload_foto_permit` Safe Track). */
    public function foto(Request $r, Permit $permit)
    {
        $u = $r->user();
        AksesMiners::tegakkan(AksesMiners::kelola($u) || (int) $permit->user_id === (int) $u->id);

        $r->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048']], [
            'foto.required' => 'Pilih foto lebih dulu.',
            'foto.mimes'    => 'Foto harus JPG atau PNG.',
            'foto.max'      => 'Foto melebihi 2 MB. Perkecil dulu berkasnya.',
        ]);

        $lama = $permit->foto;
        $permit->update(['foto' => Berkas::simpan($r->file('foto'), 'miners/permit/foto')]);
        Berkas::buang($lama);

        return back()->with('sukses', 'Foto Mine Permit diperbarui.');
    }

    /**
     * Lampiran — SPDK (wajib), Induksi Khusus dan LOTO / Sertifikat Welder
     * (opsional). Satu baris per jenis; menyimpan ulang tanpa berkas baru
     * mempertahankan berkas lamanya.
     */
    public function berkas(Request $r, Permit $permit)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($permit, $r->user()));

        $r->validate([
            'spdk'           => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            'induksi-khusus' => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            'loto'           => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            /* Bentuk lama: satu lampiran per permintaan. */
            'jenis'          => ['nullable', 'string', 'max:100'],
            'berkas'         => ['nullable', ...Berkas::ATURAN_DOKUMEN],
            'nomor'          => ['nullable', 'string', 'max:100'],
            'tanggal'        => ['nullable', 'date'],
            'catatan'        => ['nullable', 'string'],
        ]);

        $simpan = function (string $jenis, $file, array $lain = []) use ($permit) {
            $lama = PermitBerkas::where('permit_id', $permit->id)->where('jenis', $jenis)->first();

            PermitBerkas::updateOrCreate(['permit_id' => $permit->id, 'jenis' => $jenis], $lain + [
                'catatan' => $lain['catatan'] ?? $lama?->catatan ?? (Acuan::LAMPIRAN_PERMIT[$jenis]['label'] ?? null),
                'berkas'  => Berkas::simpan($file, 'miners/permit') ?? $lama?->berkas,
            ]);
        };

        $ada = false;

        foreach (array_keys(Acuan::LAMPIRAN_PERMIT) as $jenis) {
            if ($r->hasFile($jenis)) {
                $simpan($jenis, $r->file($jenis));
                $ada = true;
            }
        }

        if ($r->filled('jenis')) {
            $simpan($r->input('jenis'), $r->file('berkas'), [
                'nomor'   => $r->input('nomor'),
                'tanggal' => $r->filled('tanggal') ? Waktu::tanggal($r->input('tanggal')) : null,
                'catatan' => $r->input('catatan'),
            ]);
            $ada = true;
        }

        if (! $ada) {
            return back()->withErrors(['spdk' => 'Pilih sedikitnya satu berkas lampiran.']);
        }

        return back()->with('sukses', 'Lampiran Mine Permit disimpan.');
    }

    /**
     * Masa berlaku — `simpanPermit` MasaBerlakuController Safe Track: ikuti
     * MCU, atau kunci pada tanggal tertentu. Hanya admin (b39614d).
     */
    public function masaBerlaku(Request $r, Permit $permit)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Masa berlaku Mine Permit hanya diubah admin atau HSE IUP.');

        $data = $r->validate([
            'sumber'  => ['required', Rule::in(['mcu', 'manual'])],
            'tanggal' => ['required_if:sumber,manual', 'nullable', 'date'],
        ], ['tanggal.required_if' => 'Tanggal masa berlaku permit wajib diisi.']);

        if ($data['sumber'] === 'mcu') {
            $mcu = $permit->mcuOrang?->berlaku_sampai;

            if (! $mcu) {
                return back()->withErrors(['sumber' => 'MCU karyawan ini belum punya masa berlaku. Isi dulu masa berlaku MCU-nya, '
                    .'atau kunci tanggal permit secara manual.']);
            }

            $permit->update(['berlaku_sampai' => $mcu, 'sumber_berlaku' => 'mcu']);

            return back()->with('sukses', 'Masa berlaku permit mengikuti MCU: '.$mcu->format('d-m-Y').'.');
        }

        $permit->update(['berlaku_sampai' => Waktu::tanggal($data['tanggal']), 'sumber_berlaku' => 'manual']);

        return back()->with('sukses', 'Masa berlaku permit dikunci pada '.$permit->berlaku_sampai->format('d-m-Y').'.');
    }

    /** Selaraskan seluruh permit yang mengikuti MCU — `sinkronPermitMassal`. */
    public function sinkron(Request $r)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()));

        $n = SinkronMasaBerlaku::permitDariMcu();
        SinkronMasaBerlaku::tandaiPermit();

        return back()->with('sukses', $n.' permit diperbarui mengikuti masa berlaku MCU.');
    }

    /**
     * Aktif / tidak aktif — `ubahStatusPermit` Safe Track: kartu dicabut
     * atau hilang ditutup dengan alasan, datanya tidak dihapus.
     */
    public function status(Request $r, Permit $permit)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Status Mine Permit hanya diubah admin atau HSE IUP.');

        $data = $r->validate([
            'aktif'  => ['required', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:200'],
        ]);

        $aktif  = $r->boolean('aktif');
        $alasan = trim((string) ($data['alasan'] ?? ''));

        $permit->update([
            'aktif'            => $aktif,
            'tanggal_nonaktif' => $aktif ? null : Waktu::kini()->startOfDay(),
            'alasan_nonaktif'  => $aktif || $alasan === '' ? null : $alasan,
        ]);

        return back()->with('sukses', 'Mine Permit '.($permit->pekerja?->nama ?? $permit->no_registrasi)
            .' ditandai '.($aktif ? 'AKTIF' : 'TIDAK AKTIF').'.');
    }

    /** Status lama "cabut" — kini penonaktifan dengan alasan. */
    public function cabut(Request $r, Permit $permit)
    {
        $r->merge(['aktif' => 0, 'alasan' => $r->input('alasan_cabut', $r->input('alasan'))]);

        return $this->status($r, $permit);
    }

    public function destroy(Permit $permit)
    {
        $permit->alur()->delete();
        $permit->delete();

        return redirect()->route('miners.permit.index')->with('sukses', 'Mine Permit dihapus.');
    }

    public function tindak(Request $r, Permit $permit)
    {
        return $this->tindakAlur($r, $permit, 'Mine Permit');
    }

    /* ═══════════════════ rincian ═══════════════════ */

    /** `detail_permit` Safe Track. */
    public function rincian(Request $r, string $id)
    {
        $u = $r->user();

        $p = Permit::with(['alur.user', 'alur.ditugaskan', 'user', 'iup', 'kontraktor', 'company', 'berkas',
            'pekerja.departemen', 'pekerja.company', 'mcuOrang.mcu', 'mcuOrang.hasil', 'induksiOrang.induksi'])->findOrFail((int) $id);

        AksesMiners::tegakkan(AksesMiners::lihatDokumen($p, $u), 'Anda hanya dapat membuka Mine Permit yang Anda ajukan atau ditugaskan kepada Anda.');

        $p->terbitkanAlur();
        $p->load('alur.user', 'alur.ditugaskan');

        $kelola = AksesMiners::kelola($u);
        $berkas = $p->berkas->keyBy('jenis');

        $lampiran = [];
        foreach (Acuan::LAMPIRAN_PERMIT as $jenis => $l) {
            $b = $berkas[$jenis] ?? collect($l['setara'])->map(fn ($s) => $berkas[$s] ?? null)->filter()->first();

            $lampiran[] = ['kunci' => $jenis, 'label' => $l['label'], 'wajib' => $l['wajib']]
                + ($b ? Berkas::lampiran($u, $b, 'mnl') : ['ada' => false, 'url' => null]);
        }

        /* Lampiran lama (daftar SOP sebelumnya) tetap terbaca. */
        $lain = $p->berkas->reject(fn ($b) => isset(Acuan::LAMPIRAN_PERMIT[$b->jenis])
            || in_array($b->jenis, Acuan::LAMPIRAN_PERMIT['spdk']['setara'], true))
            ->map(fn ($b) => ['label' => $b->catatan ?: $b->jenis, 'nomor' => $b->nomor] + Berkas::lampiran($u, $b, 'mnl'))
            ->values();

        $pk     = $p->pekerja;
        $habis  = $p->habisEfektif();

        /* Hasil MCU dan sertifikatnya hanya bagi pembaca isi medis. */
        $mcuP   = $p->mcuOrang?->mcu;
        $medis  = AksesMiners::medis($u, $mcuP);

        return Inertia::render('Miners/PermitRincian', [
            'judul' => 'Detail Permit',
            'kop'   => false,

            'permit' => [
                'id'          => $p->id,
                'nomor'       => $p->no_registrasi,
                'tanggal'     => $p->tanggal?->toDateString(),
                'status'      => $p->status,
                'aktif'       => (bool) $p->aktif,
                'nonaktifSejak' => $p->tanggal_nonaktif?->toDateString(),
                'alasanNonaktif' => $p->alasan_nonaktif,
                'nama'        => $pk?->nama ?? $p->mcuOrang?->nama,
                'pekerja_id'  => $p->pekerja_id,
                'nik'         => $pk?->nik ?? $p->mcuOrang?->nik,
                'noid'        => $p->no_registrasi,
                'jabatan'     => $p->mcuOrang?->jabatan ?? $pk?->jabatan?->nama,
                'lahir'       => $pk?->tanggal_lahir?->toDateString(),
                'darah'       => $pk?->gol_darah,
                'telepon'     => $pk?->telepon,
                'darurat'     => $pk?->telepon_darurat,
                'departemen'  => $pk?->departemen?->nama,
                'departemen_id' => $pk?->departemen_id,
                'jenisKartu'  => $p->jenis_kartu,
                'cakupan'     => $p->cakupan_area,
                'warna'       => $p->kode_warna,
                'iup_id'      => $p->iup_id,
                'iup'         => $p->iup?->name,
                'kontraktor_id' => $p->kontraktor_id,
                'kontraktor'  => $p->kontraktor?->name,
                'catatan'     => $p->catatan,
                'berlaku'     => $habis?->toDateString(),
                'sisa'        => Zona::sisa($habis),
                'sumber'      => Permit::SUMBER[$p->sumber_berlaku ?? 'mcu'] ?? $p->sumber_berlaku,
                'sumberKode'  => $p->sumber_berlaku ?? 'mcu',
                'mcuSampai'   => $p->mcuOrang?->berlaku_sampai?->toDateString(),
                'mcuHasil'    => $medis ? $p->mcuOrang?->hasil?->nama : null,
                'induksi'     => $p->induksiOrang?->induksi?->no_registrasi,
                'pengaju'     => $p->user?->name,
                'foto'        => Berkas::lampiran($u, $p, 'mnf'),
                'ktp'         => Berkas::lampiran($u, $p, 'mnt'),
                'permohonan'  => Berkas::lampiran($u, $p, 'mnq'),
                'hasilMcu'    => Berkas::lampiran($u, $p->mcuOrang, 'mnh'),
                'sertifikatMcu' => $mcuP && $medis && in_array($mcuP->status, ['selesai', 'kedaluwarsa'], true)
                    && AksesMiners::lihatDokumen($mcuP, $u)
                    ? route('miners.mcu.sertifikat', $p->mcu_orang_id) : null,
            ],

            'lampiran'     => $lampiran,
            'lampiranLama' => $lain,
            'kurang'       => Kelengkapan::kurang($p),

            'riwayat' => RiwayatPerpanjangan::query()->with('user:id,name')->where('jenis', 'permit')
                ->where('dokumen_id', $p->id)->orderByDesc('urutan')->get()
                ->map(fn (RiwayatPerpanjangan $h) => [
                    'urutan' => $h->urutan,
                    'lama'   => $h->berlaku_lama?->toDateString(),
                    'baru'   => $h->berlaku_baru?->toDateString(),
                    'oleh'   => $h->user?->name,
                    'pada'   => Waktu::lokal($h->created_at)?->format('d-m-Y H:i'),
                ]),

            'alur'   => RincianDokumen::alur($p),
            'posisi' => Jalur::posisi($p),

            'kelola'           => $kelola,
            'medis'            => $medis,
            'bolehLampiran'    => $kelola || AksesMiners::ubahDraf($p, $u),
            'bolehFoto'        => $kelola || (int) $p->user_id === (int) $u->id,
            'bolehKirim'       => Jalur::bolehKirim($p, $u) && ! $p->alurTertahan(),
            'bolehAjukanUlang' => Jalur::bolehKirim($p, $u) && $p->alurTertahan(),
            'bolehTindak'      => Jalur::bolehTindak($p, $u),
            'pilihan'          => $kelola ? $this->pilihan($u, $p) : null,
        ]);
    }

    /* ═══════════════════ kelayakan & perkakas ═══════════════════ */

    /**
     * Calon pemegang Mine Permit — `master_permit_users` Safe Track.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public static function calon(): Collection
    {
        $sudah = Permit::withoutGlobalScopes();

        return InduksiOrang::query()->with(['induksi', 'pekerja.company', 'mcuOrang'])
            ->whereIn('induksi_id', Induksi::query()->where('status', 'selesai')->select('id'))
            ->where('status', '!=', 'gagal')
            ->whereNotIn('id', (clone $sudah)->whereNotNull('induksi_orang_id')->select('induksi_orang_id'))
            ->where(fn ($q) => $q->whereNull('mcu_orang_id')
                ->orWhereNotIn('mcu_orang_id', (clone $sudah)->whereNotNull('mcu_orang_id')->select('mcu_orang_id')))
            ->get()
            ->sortBy(fn (InduksiOrang $o) => $o->pekerja?->nama ?? $o->mcuOrang?->nama)
            ->map(fn (InduksiOrang $o) => [
                'id'         => $o->id,
                'nama'       => $o->pekerja?->nama ?? $o->mcuOrang?->nama,
                'noDaftar'   => $o->induksi?->no_registrasi,
                'jabatan'    => $o->mcuOrang?->jabatan,
                'perusahaan' => $o->pekerja?->company?->name,
                'company_id' => $o->pekerja?->company_id,
                'mitra'      => (bool) ($o->pekerja?->company?->parent_id || $o->pekerja?->company?->isJasa()),
                'lahir'      => $o->pekerja?->tanggal_lahir?->toDateString(),
                'darah'      => $o->pekerja?->gol_darah,
                'telepon'    => $o->pekerja?->telepon,
                'darurat'    => $o->pekerja?->telepon_darurat,
                'departemen_id' => $o->pekerja?->departemen_id ?? $o->mcuOrang?->departemen_id,
            ])->values();
    }

    /**
     * Pilihan formulir: IUP (pemegang izin) dan Contractor (IUJP) DUA
     * daftar terpisah — `PilihanPerusahaan::iup()` dan `::kontraktor()`.
     *
     * @return array<string,mixed>
     */
    private function pilihan(User $u, ?Permit $p = null): array
    {
        $jangkau = AksesMiners::perusahaan($u);

        $iupId = $jangkau->map(fn (Company $c) => $c->parent_id ?: $c->id)->unique()->values();

        if ($p?->iup_id) $iupId->push($p->iup_id);

        $iup = Company::query()->whereIn('id', $iupId->unique())->whereNull('parent_id')->orderBy('name')->get()
            ->reject(fn (Company $c) => $c->isJasa());

        $kontraktor = $jangkau->filter(fn (Company $c) => $c->parent_id || $c->isJasa());

        if ($p?->kontraktor_id && ! $kontraktor->contains('id', $p->kontraktor_id)) {
            $kontraktor->push(Company::query()->find($p->kontraktor_id));
        }

        return [
            'iup'        => $iup->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->values(),
            'kontraktor' => $kontraktor->filter()->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name, 'induk' => $c->parent_id])->values(),
            'departemen' => collect(Departemen::pilihan())->map(fn ($n, $id) => ['nilai' => $id, 'label' => $n])->values(),
            'jenisKartu' => Permit::JENIS_KARTU,
            'cakupan'    => collect(Acuan::ZONA_AKSES)->map(fn ($z, $k) => ['nilai' => $k, 'label' => $z['label']])->values(),
            'warna'      => collect(Acuan::WARNA_KARTU)->map(fn ($w, $k) => ['nilai' => $k, 'label' => strtoupper($k).' — '.$w['label']])->values(),
            'golDarah'   => Pekerja::GOL_DARAH,
        ];
    }

    /** @return array<string,mixed> */
    private function validasi(Request $r, ?Permit $lama): array
    {
        $baru = $lama === null;

        return self::tanpaBerkas($r->validate([
            'induksi_orang_id' => [$baru ? 'required' : 'nullable', 'integer'],
            'iup_id'           => ['required', 'exists:companies,id'],
            'kontraktor_id'    => ['nullable', 'exists:companies,id'],
            'jenis_kartu'      => ['required', Rule::in(Permit::JENIS_KARTU)],
            'cakupan_area'     => [$baru ? 'required' : 'nullable', Rule::in(array_keys(Acuan::ZONA_AKSES))],
            'kode_warna'       => ['nullable', Rule::in(array_keys(Acuan::WARNA_KARTU))],
            'departemen_id'    => ['required', 'exists:mnr_departemen,id'],
            'tanggal_lahir'    => [$baru ? 'required' : 'nullable', 'date'],
            'gol_darah'        => ['nullable', Rule::in(Pekerja::GOL_DARAH)],
            'telepon'          => ['nullable', 'string', 'max:30'],
            'telepon_darurat'  => ['nullable', 'string', 'max:30'],
            'jabatan'          => ['nullable', 'string', 'max:120'],
            'catatan'          => ['nullable', 'string', 'max:2000'],
            'foto'             => [$baru ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'ktp'              => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'permohonan'       => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'induksi_orang_id.required' => 'Karyawan wajib dipilih.',
            'iup_id.required'           => 'Perusahaan (IUP) wajib dipilih.',
            'departemen_id.required'    => 'Departemen wajib dipilih. Bila daftarnya kosong, isi dulu lewat Master Data → Departemen.',
            'tanggal_lahir.required'    => 'Tanggal lahir wajib diisi.',
            'foto.required'             => 'Foto karyawan wajib diunggah.',
            'foto.max'                  => 'Foto melebihi 2 MB. Perkecil dulu berkasnya.',
            'ktp.required'              => 'Foto KTP wajib diunggah.',
            'permohonan.required'       => 'Permohonan permit wajib diunggah.',
        ]));
    }

    /**
     * Apakah pasangan Perusahaan–Contractor masuk akal bagi karyawan ini —
     * `periksaPasanganPerusahaan` Safe Track, dengan hubungan IUP–IUJP
     * dibaca dari `companies.parent_id`.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,string> kosong bila tidak ada yang salah
     */
    private function periksaPasangan(array $data, ?Pekerja $pekerja): array
    {
        $galat = [];

        $iup        = Company::query()->find($data['iup_id']);
        $karyawan   = $pekerja?->company;
        $mitra      = (bool) ($karyawan?->parent_id || $karyawan?->isJasa());
        $kontraktor = ! empty($data['kontraktor_id']) ? Company::query()->find($data['kontraktor_id']) : null;

        if ($iup && ($iup->parent_id || $iup->isJasa())) {
            $galat['iup_id'] = 'Perusahaan harus pemegang IUP. "'.$iup->name.'" terdaftar sebagai perusahaan jasa '
                .'pertambangan (IUJP) — isikan sebagai Contractor, bukan sebagai Perusahaan.';
        }

        if ($kontraktor) {
            if (! $kontraktor->parent_id && ! $kontraktor->isJasa()) {
                $galat['kontraktor_id'] = 'Contractor harus perusahaan jasa pertambangan (IUJP). "'.$kontraktor->name.'" adalah pemegang IUP.';
            } elseif (! $mitra && $karyawan) {
                $galat['kontraktor_id'] = 'Karyawan ini tercatat sebagai karyawan '.$karyawan->name.' (IUP), jadi Contractor harus dikosongkan.';
            } elseif ($karyawan && (int) $kontraktor->id !== (int) $karyawan->id) {
                $galat['kontraktor_id'] = 'Contractor tidak cocok: karyawan ini tercatat sebagai karyawan '.$karyawan->name
                    .'. Pilih perusahaan itu, atau perbaiki dulu data MCU-nya bila memang keliru.';
            } elseif ($iup && $kontraktor->parent_id && (int) $kontraktor->parent_id !== (int) $iup->id) {
                $galat['kontraktor_id'] = '"'.$kontraktor->name.'" bukan mitra '.$iup->name
                    .'. Pasangkan dulu di Master Data → Perusahaan bila memang bekerja untuk IUP ini.';
            }
        } elseif ($mitra) {
            $galat['kontraktor_id'] = 'Contractor wajib diisi: karyawan ini tercatat sebagai karyawan '.$karyawan->name.' (IUJP).';
        }

        return $galat;
    }

    /**
     * Data diri pemegang kartu — tanggal lahir, golongan darah, telepon,
     * departemen. Safe Track menyimpannya pada baris permit; di EQOHSEE
     * pada manpower-nya, sumber yang dibaca HRIS dan seluruh layar lain.
     *
     * @param  array<string,mixed>  $data
     */
    private function isiPekerja(?Pekerja $pekerja, array $data): void
    {
        if (! $pekerja) return;

        $ubah = ['departemen_id' => $data['departemen_id']];

        foreach (['tanggal_lahir', 'gol_darah', 'telepon', 'telepon_darurat'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null) {
                $ubah[$k] = $k === 'tanggal_lahir' ? Waktu::tanggal($data[$k]) : $data[$k];
            }
        }

        $pekerja->forceFill($ubah)->save();
    }

    /**
     * Nomor `{IUP}.{IUJP}.{Ymd}00{id}` — sekaligus nomor induk pemegang
     * kartu (DataKartu::nomorIndukPermit), supaya kartu dan halaman
     * verifikasinya menyebut nomor yang sama.
     */
    private function beriNomor(Permit $p): void
    {
        $nomor = $p->susunNomor();

        $p->forceFill(['no_registrasi' => $nomor])->saveQuietly();

        $pk = $p->pekerja;

        if ($pk && (blank($pk->no_induk) || str_starts_with((string) $pk->no_induk, 'MCU-') || $pk->no_induk === $p->getOriginal('no_registrasi'))) {
            $pk->forceFill(['no_induk' => $nomor])->save();
        }
    }
}
