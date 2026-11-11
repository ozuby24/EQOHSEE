<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Miners\Concerns\TindakAlur;
use App\Models\Miners\{JenisUnit, Kendaraan, Simper, SimperAjuan, SimperAjuanUnit, SimperUnit};
use App\Support\Berkas;
use App\Support\Miners\{AksesMiners, DeteksiMasaSim, Jalur, Laporan, RincianDokumen};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * SIMPER Lanjutan — Penambahan Unit (form_simper_extsController), Upgrade
 * SIMPER (form_upgrade_simpersController) dan Perpanjangan SIMPER
 * (extendsimpersController) Safe Track.
 *
 *   · Penambahan: dari SIMPER disetujui dan aktif; saat dibuat, unit lama
 *     DISALIN ke pengajuan, pengaju menambah baris unit baru; HSE → KTT.
 *     Unit barunya tidak digabung ke SIMPER — kartu membacanya dari
 *     pengajuan yang selesai (Simper::unitTambahan).
 *   · Upgrade: per unit SIMPER yang ada, authority baru + tiga berkas
 *     (simper upgrade, DDT, pemenuhan jam); pengaju → HSE. Safe Track
 *     berhenti pada "kirim" — persetujuannya dilengkapi di sini.
 *   · Perpanjangan: SIMPER disetujui, aktif, tanpa perpanjangan berjalan;
 *     pengaju → dokter → HSE → KTT; dokter menulis balik MCU; tanggal dan
 *     nomor SIM baru pindah ke SIMPER hanya sesudah KTT (SimperAjuan).
 */
class SimperLanjutanController extends Controller
{
    use TindakAlur;

    public const JUDUL = [
        'penambahan'   => ['Penambahan Unit', 'Simper Penambahan Unit Table', 'penambahan-unit'],
        'upgrade'      => ['Upgrade SIMPER', 'Upgrade Authority Table', 'upgrade-simper'],
        'perpanjangan' => ['Perpanjangan SIMPER', 'Perpanjangan SIMPER Table', 'perpanjangan'],
    ];

    /* ═══════════════════ daftar ═══════════════════ */

    public function index(Request $r)
    {
        $jenis = self::jenisDariRute((string) $r->route('jenis'));
        $u     = $r->user();
        $baris = $this->daftar($r, $jenis);

        [$judul, $tabel] = self::JUDUL[$jenis];

        return Inertia::render('Miners/SimperLanjutan', [
            'judul'  => $judul,
            'tabel'  => $tabel,
            'kop'    => false,
            'jenis'  => $jenis,
            'cari'   => $r->query('cari', ''),
            'baris'  => $baris,
            'calon'  => self::calon($jenis),
        ]);
    }

    /** @return Collection<int,array<string,mixed>> daftar tersaring — layar dan unduhannya */
    private function daftar(Request $r, string $jenis): Collection
    {
        $u    = $r->user();
        $cari = mb_strtolower(trim((string) $r->query('cari', '')));

        return SimperAjuan::query()->where('jenis', $jenis)
            ->with(['simper.pekerja.departemen', 'simper.pekerja.company:id,name', 'simper.permit.mcuOrang', 'user:id,name'])
            ->when(! AksesMiners::kelola($u), fn ($q) => $q->where('user_id', $u->id))
            ->orderByDesc('tanggal')->orderByDesc('id')->get()
            ->map(fn (SimperAjuan $a) => [
                'id'         => $a->id,
                'nomor'      => $a->no_registrasi,
                'tanggal'    => $a->tanggal?->toDateString(),
                'nama'       => $a->simper?->pekerja?->nama,
                'noid'       => $a->simper?->pekerja?->no_induk,
                'jabatan'    => $a->simper?->permit?->mcuOrang?->jabatan,
                'departemen' => $a->simper?->pekerja?->departemen?->nama,
                'perusahaan' => $a->simper?->pekerja?->company?->name,
                'jenisSim'   => $a->simper?->jenis_simpol,
                'status'     => $a->status,
                'pembuat'    => $a->user?->name,
                'bolehHapus' => $u->isAdmin() || ($a->status === 'draf' && (int) $a->user_id === (int) $u->id),
            ])
            ->when($cari !== '', fn ($c) => $c->filter(fn ($b) => str_contains(mb_strtolower($b['nama'].' '.$b['noid'].' '.$b['nomor']), $cari)))
            ->values();
    }

    /** Unduhan Excel Penambahan Unit / Upgrade / Perpanjangan SIMPER — kolom layar, pencarian layar. */
    public function ekspor(Request $r)
    {
        $jenis = self::jenisDariRute((string) $r->query('jenis', 'penambahan'));

        return Laporan::unduh('laporan-'.self::JUDUL[$jenis][2].'-'.Waktu::kini()->format('Ymd-Hi'),
            ['No Registrasi', 'Tanggal', 'Nama Employee', 'Noid', 'Jabatan', 'Departemen', 'Perusahaan', 'Jenis SIM', 'Status', 'Creator'],
            $this->daftar($r, $jenis)->map(fn ($b) => [
                $b['nomor'], $b['tanggal'], $b['nama'], $b['noid'], $b['jabatan'], $b['departemen'], $b['perusahaan'],
                $b['jenisSim'], SimperAjuan::STATUS[$b['status']] ?? $b['status'], $b['pembuat'],
            ]));
    }

    /* ═══════════════════ pengajuan ═══════════════════ */

    public function store(Request $r)
    {
        $jenis = (string) $r->input('jenis');
        abort_unless(isset(self::JUDUL[$jenis]), 404);

        $data = self::tanpaBerkas($r->validate([
            'simper_id'             => ['required', 'integer'],
            'tanggal'               => ['nullable', 'date'],
            'berkas_evaluasi'       => [$jenis === 'penambahan' ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_pengalaman'     => [$jenis === 'penambahan' ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'no_simpol_baru'        => [$jenis === 'perpanjangan' ? 'required' : 'nullable', 'string', 'max:40'],
            'simpol_berlaku_sampai' => ['nullable', 'date'],
            'tanggal_mcu'           => [$jenis === 'perpanjangan' ? 'required' : 'nullable', 'date'],
            'berkas_simpol'         => [$jenis === 'perpanjangan' ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_mcu'            => [$jenis === 'perpanjangan' ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_form'           => [$jenis === 'perpanjangan' ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'simper_id.required'         => 'SIMPER wajib dipilih.',
            'berkas_evaluasi.required'   => 'Berkas evaluasi wajib diunggah.',
            'berkas_pengalaman.required' => 'Berkas pengalaman wajib diunggah.',
            'no_simpol_baru.required'    => 'Nomor SIM baru wajib diisi.',
            'tanggal_mcu.required'       => 'Tanggal MCU wajib diisi.',
            'berkas_simpol.required'     => 'Berkas SIM baru wajib diunggah.',
            'berkas_mcu.required'        => 'Sertifikat MCU wajib diunggah.',
            'berkas_form.required'       => 'Form perpanjangan wajib diunggah.',
        ]));

        if (! self::calon($jenis)->contains('id', (int) $data['simper_id'])) {
            return back()->withErrors(['simper_id' => match ($jenis) {
                'perpanjangan' => 'SIMPER ini belum dapat diperpanjang: harus disetujui, aktif, dan tidak sedang punya perpanjangan berjalan.',
                default        => 'SIMPER ini belum dapat diajukan: harus disetujui, aktif, dan tidak sedang punya '.self::JUDUL[$jenis][0].' berjalan.',
            }])->withInput();
        }

        $tanggalSim = null;
        if ($jenis === 'perpanjangan') {
            $tanggalSim = ! empty($data['simpol_berlaku_sampai'])
                ? Waktu::tanggal($data['simpol_berlaku_sampai'])
                : (($h = DeteksiMasaSim::dariBerkas($r->file('berkas_simpol')))['tanggal'] ? Waktu::tanggal($h['tanggal']) : null);

            if (! $tanggalSim) {
                return back()->withErrors(['simpol_berlaku_sampai' => 'Masa berlaku SIM baru tidak terbaca dari berkasnya. Isi sendiri tanggalnya.'])->withInput();
            }
        }

        $simper = Simper::query()->with('unit.jenisUnitBanyak')->findOrFail((int) $data['simper_id']);

        $a = SimperAjuan::create([
            'simper_id'             => $simper->id,
            'iup_id'                => $simper->iup_id,
            'jenis'                 => $jenis,
            'tanggal'               => ! empty($data['tanggal']) ? Waktu::tanggal($data['tanggal']) : Waktu::kini()->startOfDay(),
            'no_simpol_baru'        => $data['no_simpol_baru'] ?? null,
            'simpol_berlaku_sampai' => $tanggalSim,
            'tanggal_mcu'           => ! empty($data['tanggal_mcu']) ? Waktu::tanggal($data['tanggal_mcu']) : null,
            'berkas_evaluasi'       => Berkas::simpan($r->file('berkas_evaluasi'), 'miners/simper/ajuan'),
            'berkas_pengalaman'     => Berkas::simpan($r->file('berkas_pengalaman'), 'miners/simper/ajuan'),
            'berkas_simpol'         => Berkas::simpan($r->file('berkas_simpol'), 'miners/simper/ajuan'),
            'berkas_mcu'            => Berkas::simpan($r->file('berkas_mcu'), 'miners/simper/ajuan'),
            'berkas_form'           => Berkas::simpan($r->file('berkas_form'), 'miners/simper/ajuan'),
            'user_id'               => $r->user()->id,
        ]);

        /* Penambahan MENYALIN unit lama ke pengajuannya (Safe Track):
           penyetuju melihat seluruh unit orang ini di satu layar. */
        if ($jenis === 'penambahan') {
            foreach ($simper->unit as $u) {
                $salin = SimperAjuanUnit::create([
                    'ajuan_id' => $a->id, 'simper_unit_id' => $u->id, 'kendaraan_id' => $u->kendaraan_id,
                    'jenis_unit_id' => $u->jenis_unit_id, 'authority' => $u->authority,
                    'nilai_p2h' => $u->nilai_p2h, 'nilai_praktek' => $u->nilai_praktek,
                ]);
                $salin->jenisUnitBanyak()->sync($u->jenisUnitBanyak->pluck('id'));
            }
        }

        $a->terbitkanAlur();

        return redirect()->route('miners.lanjutan.rincian', $a->id)
            ->with('sukses', $a->jenisLabel().' '.$a->fresh()->no_registrasi.' dibuat sebagai draf.');
    }

    /** Baris unit: unit BARU (penambahan) atau perubahan authority (upgrade). */
    public function unit(Request $r, SimperAjuan $ajuan)
    {
        $this->bolehUbah($r, $ajuan);
        abort_if($ajuan->jenis === 'perpanjangan', 404);

        if ($ajuan->jenis === 'upgrade') {
            $data = $r->validate([
                'simper_unit_id' => ['required', 'integer', Rule::exists('mnr_simper_unit', 'id')->where('simper_id', $ajuan->simper_id)],
                'authority'      => ['required', Rule::in(array_keys(SimperUnit::AUTHORITY))],
                'berkas_upgrade' => ['required', ...Berkas::ATURAN_DOKUMEN],
                'berkas_ddt'     => ['required', ...Berkas::ATURAN_DOKUMEN],
                'berkas_jam'     => ['required', ...Berkas::ATURAN_DOKUMEN],
            ], [
                'simper_unit_id.required' => 'Pilih unit yang di-upgrade.',
                'berkas_upgrade.required' => 'Form Simper Upgrade wajib diunggah.',
                'berkas_ddt.required'     => 'DDT Upgrade wajib diunggah.',
                'berkas_jam.required'     => 'Pemenuhan jam wajib diunggah.',
            ]);

            $lama = SimperUnit::withoutGlobalScopes()->with('jenisUnitBanyak')->findOrFail($data['simper_unit_id']);

            if ($lama->authority === $data['authority']) {
                return back()->withErrors(['authority' => 'Authority baru sama dengan yang sekarang ('.$lama->authority.').']);
            }

            $baris = SimperAjuanUnit::updateOrCreate(['ajuan_id' => $ajuan->id, 'simper_unit_id' => $lama->id], [
                'kendaraan_id'   => $lama->kendaraan_id, 'jenis_unit_id' => $lama->jenis_unit_id, 'authority' => $data['authority'],
                'berkas_upgrade' => Berkas::simpan($r->file('berkas_upgrade'), 'miners/simper/ajuan'),
                'berkas_ddt'     => Berkas::simpan($r->file('berkas_ddt'), 'miners/simper/ajuan'),
                'berkas_jam'     => Berkas::simpan($r->file('berkas_jam'), 'miners/simper/ajuan'),
            ]);
            $baris->jenisUnitBanyak()->sync($lama->jenisUnitBanyak->pluck('id'));

            return back()->with('sukses', 'Upgrade unit ditambahkan.');
        }

        $data = $r->validate([
            'kendaraan_id'     => ['required', 'exists:mnr_kendaraan,id'],
            'jenis_unit_ids'   => ['required', 'array', 'min:1'],
            'jenis_unit_ids.*' => ['integer', 'exists:mnr_jenis_unit,id'],
            'authority'        => ['required', Rule::in(array_keys(SimperUnit::AUTHORITY))],
            'nilai_p2h'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_praktek'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'berkas_teori'     => ['required', ...Berkas::ATURAN_DOKUMEN],
            'berkas_praktek'   => ['required', ...Berkas::ATURAN_DOKUMEN],
            'berkas_evaluasi'  => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'kendaraan_id.required'   => 'Master Unit wajib dipilih.',
            'jenis_unit_ids.required' => 'Pilih sedikitnya satu Type Unit.',
            'berkas_teori.required'   => 'Berkas teori wajib diunggah.',
            'berkas_praktek.required' => 'Hasil praktek wajib diunggah.',
        ]);

        $baris = SimperAjuanUnit::create([
            'ajuan_id' => $ajuan->id, 'kendaraan_id' => $data['kendaraan_id'], 'jenis_unit_id' => $data['jenis_unit_ids'][0],
            'authority' => $data['authority'], 'nilai_p2h' => $data['nilai_p2h'] ?? null, 'nilai_praktek' => $data['nilai_praktek'] ?? null,
            'berkas_teori'    => Berkas::simpan($r->file('berkas_teori'), 'miners/simper/ajuan'),
            'berkas_praktek'  => Berkas::simpan($r->file('berkas_praktek'), 'miners/simper/ajuan'),
            'berkas_evaluasi' => Berkas::simpan($r->file('berkas_evaluasi'), 'miners/simper/ajuan'),
        ]);
        $baris->jenisUnitBanyak()->sync($data['jenis_unit_ids']);

        return back()->with('sukses', 'Unit baru ditambahkan.');
    }

    public function unitHapus(Request $r, SimperAjuan $ajuan, SimperAjuanUnit $unit)
    {
        abort_unless((int) $unit->ajuan_id === (int) $ajuan->id, 404);
        $this->bolehUbah($r, $ajuan);

        /* Salinan unit lama pada penambahan bukan milik pengajuan ini. */
        if ($ajuan->jenis === 'penambahan' && $unit->simper_unit_id) {
            return back()->withErrors(['unit' => 'Unit lama tidak dapat dihapus dari pengajuan penambahan.']);
        }

        $unit->jenisUnitBanyak()->detach();
        $unit->delete();

        return back()->with('sukses', 'Baris unit dihapus.');
    }

    public function kirim(Request $r, SimperAjuan $ajuan)
    {
        if ($alasan = Jalur::kirim($ajuan, $r->user())) {
            return back()->withErrors(['kirim' => $alasan]);
        }

        return $this->balas($ajuan->jenisLabel().' terkirim ke penyetuju.');
    }

    public function ajukanUlang(Request $r, SimperAjuan $ajuan)
    {
        if ($alasan = Jalur::ajukanUlang($ajuan, $r->user())) {
            return back()->withErrors(['ajukanUlang' => $alasan]);
        }

        return $this->balas('Pengajuan dikirim ulang; alurnya diulang dari awal.');
    }

    public function tindak(Request $r, SimperAjuan $ajuan)
    {
        return $this->tindakAlur($r, $ajuan, $ajuan->jenisLabel());
    }

    public function destroy(Request $r, SimperAjuan $ajuan)
    {
        AksesMiners::tegakkan($r->user()->isAdmin()
            || ($ajuan->status === 'draf' && (int) $ajuan->user_id === (int) $r->user()->id));

        $jenis = $ajuan->jenis;
        foreach ($ajuan->unit as $u) $u->jenisUnitBanyak()->detach();
        $ajuan->unit()->delete();
        $ajuan->alur()->delete();
        $ajuan->delete();

        return redirect()->route('miners.daftar.'.self::JUDUL[$jenis][2])->with('sukses', 'Pengajuan dihapus.');
    }

    /* ═══════════════════ rincian ═══════════════════ */

    public function rincian(Request $r, string $id)
    {
        $u = $r->user();

        $a = SimperAjuan::with(['alur.user', 'alur.ditugaskan', 'user', 'simper.pekerja.departemen', 'simper.permit.mcuOrang',
            'simper.unit.kendaraan', 'simper.unit.jenisUnitBanyak', 'unit.kendaraan', 'unit.jenisUnitBanyak', 'unit.simperUnit'])
            ->findOrFail((int) $id);

        $a->terbitkanAlur();
        $a->load('alur.user', 'alur.ditugaskan');

        $s  = $a->simper;
        $pk = $s?->pekerja;

        $berkas = match ($a->jenis) {
            'penambahan' => [
                ['label' => 'Evaluasi', 'wajib' => true] + Berkas::lampiran($u, $a, 'sae'),
                ['label' => 'Pengalaman', 'wajib' => true] + Berkas::lampiran($u, $a, 'sag'),
            ],
            'perpanjangan' => [
                ['label' => 'Form Perpanjangan SIMPER', 'wajib' => true] + Berkas::lampiran($u, $a, 'saf'),
                ['label' => 'Sertifikat MCU', 'wajib' => true] + Berkas::lampiran($u, $a, 'sam'),
                ['label' => 'Berkas SIM Pol baru', 'wajib' => true] + Berkas::lampiran($u, $a, 'sas'),
            ],
            default => [],
        };

        return Inertia::render('Miners/SimperLanjutanRincian', [
            'judul' => 'Detail '.$a->jenisLabel(),
            'kop'   => false,

            'ajuan' => [
                'id'        => $a->id,
                'jenis'     => $a->jenis,
                'label'     => $a->jenisLabel(),
                'daftar'    => '/miners/daftar/'.self::JUDUL[$a->jenis][2],
                'nomor'     => $a->no_registrasi,
                'tanggal'   => $a->tanggal?->toDateString(),
                'status'    => $a->status,
                'nama'      => $pk?->nama,
                'noid'      => $pk?->no_induk,
                'jabatan'   => $s?->permit?->mcuOrang?->jabatan,
                'departemen'=> $pk?->departemen?->nama,
                'simper_id' => $s?->id,
                'simper'    => $s?->no_simper,
                'jenisSim'  => $s?->jenis_simpol,
                'simLama'   => $s?->no_simpol,
                'berlakuLama' => $s?->habisEfektif()?->toDateString(),
                'simBaru'   => $a->no_simpol_baru,
                'berlakuBaru' => $a->simpol_berlaku_sampai?->toDateString(),
                'tanggalMcu'  => $a->tanggal_mcu?->toDateString(),
                'pengaju'   => $a->user?->name,
                'berkas'    => $berkas,
            ],

            'unit' => $a->unit->map(fn (SimperAjuanUnit $x) => [
                'id'        => $x->id,
                'lama'      => (bool) $x->simper_unit_id,
                'kendaraan' => $x->kendaraan?->nama,
                'jenis'     => $x->jenisUnitBanyak->pluck('nama')->values(),
                'authority' => $x->authority,
                'authorityLama' => $x->simperUnit?->authority,
                'p2h'       => $x->nilai_p2h,
                'praktek'   => $x->nilai_praktek,
                'berkas'    => array_values(array_filter([
                    ['nama' => 'Teori'] + Berkas::lampiran($u, $x, 'aut'),
                    ['nama' => 'Praktek'] + Berkas::lampiran($u, $x, 'aup'),
                    ['nama' => 'Evaluasi'] + Berkas::lampiran($u, $x, 'aue'),
                    ['nama' => 'Simper Upgrade'] + Berkas::lampiran($u, $x, 'auu'),
                    ['nama' => 'DDT Upgrade'] + Berkas::lampiran($u, $x, 'aud'),
                    ['nama' => 'Pemenuhan Jam'] + Berkas::lampiran($u, $x, 'auj'),
                ], fn ($b) => $b['ada'])),
            ])->values(),

            'unitSimper' => $s ? $s->unit->map(fn (SimperUnit $x) => [
                'id' => $x->id, 'label' => trim($x->kendaraan?->nama.' — '.$x->jenisUnitBanyak->pluck('nama')->join(', ').' ('.$x->authority.')'),
                'authority' => $x->authority,
            ])->values() : [],

            'kurang' => $a->syaratKirim(),
            'alur'   => RincianDokumen::alur($a),
            'posisi' => Jalur::posisi($a),

            'bolehUbah'        => AksesMiners::kelola($u) || AksesMiners::ubahDraf($a, $u),
            'bolehKirim'       => Jalur::bolehKirim($a, $u) && ! $a->alurTertahan(),
            'bolehAjukanUlang' => Jalur::bolehKirim($a, $u) && $a->alurTertahan(),
            'bolehTindak'      => Jalur::bolehTindak($a, $u),
            'authority'        => SimperUnit::AUTHORITY,
            'kendaraan'        => Kendaraan::query()->terpakai()->get(['id', 'nama', 'kode']),
            'typeUnit'         => JenisUnit::query()->terpakai()->get(['id', 'nama']),
        ]);
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /**
     * SIMPER yang dapat diajukan: disetujui, aktif, tanpa pengajuan sejenis
     * yang masih berjalan (`masterdatauserext` dkk. Safe Track).
     *
     * @return Collection<int,array<string,mixed>>
     */
    public static function calon(string $jenis): Collection
    {
        $berjalan = SimperAjuan::withoutGlobalScopes()->where('jenis', $jenis)
            ->whereIn('status', SimperAjuan::BERJALAN)->select('simper_id');

        return Simper::query()->with('pekerja:id,nama,no_induk')
            ->where('status', 'terbit')->where('aktif', true)
            ->whereNotIn('id', $berjalan)
            ->get()
            ->sortBy(fn (Simper $s) => $s->pekerja?->nama)
            ->map(fn (Simper $s) => [
                'id'      => $s->id,
                'nama'    => $s->pekerja?->nama,
                'nomor'   => $s->no_simper,
                'noid'    => $s->pekerja?->no_induk,
                'simpol'  => $s->no_simpol,
                'berlaku' => $s->habisEfektif()?->toDateString(),
            ])->values();
    }

    private static function jenisDariRute(string $rute): string
    {
        foreach (self::JUDUL as $jenis => $j) {
            if ($j[2] === $rute || $jenis === $rute) return $jenis;
        }

        abort(404);
    }

    private function bolehUbah(Request $r, SimperAjuan $a): void
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($a, $r->user()),
            'Pengajuan ini hanya diubah pengajunya selama draf, atau admin/HSE IUP.');
    }
}
