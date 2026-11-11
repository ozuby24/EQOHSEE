<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Miners\Concerns\TindakAlur;
use App\Models\Miners\{JenisUnit, Kendaraan, Permit, RiwayatPerpanjangan, Simper, SimperAjuan, SimperUnit};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Miners\{AksesMiners, DeteksiMasaSim, Jalur, RincianDokumen, Zona};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * SIMPER — form_simpersController, form_simper_detailsController, dan
 * bagian SIMPER MasaBerlakuController Safe Track.
 *
 * CALON PEMEGANG: pemegang Mine Permit yang sudah TERBIT dan aktif, dan
 * belum punya SIMPER — SATU SIMPER PER ORANG ("Simper sudah ada").
 *
 * MASA BERLAKU = TANGGAL SIM/SIMPOL-nya (`sim_expired`), terbaca otomatis
 * dari berkas SIM bila dapat (DeteksiMasaSim), diisi tangan bila tidak.
 * Admin dapat mengubah tanggalnya dan menonaktifkan SIMPER dengan alasan.
 *
 * UNIT: Master Unit + BANYAK Type Unit, authority F/R/P/I/T, nilai P2H
 * dan praktek, berkas teori/praktek (wajib) dan evaluasi. Pengaju
 * mengubahnya selama draf; admin juga sesudah SIMPER disahkan (8601bfe).
 */
class SimperController extends Controller
{
    use TindakAlur;

    /* ═══════════════════ daftar ═══════════════════ */

    /** Register (`master_simper_user`, v09) dan History (`master_simper_admin`, v05). */
    public function index(Request $r)
    {
        $riwayat = $r->route('tahap') === 'mine-license';
        $u       = $r->user();

        $saring = [
            'cari'       => trim((string) $r->query('cari', '')),
            'perusahaan' => (string) $r->query('perusahaan', ''),
            'zona'       => in_array($r->query('zona'), Zona::KUNCI, true) ? (string) $r->query('zona') : '',
        ];

        $semua = Simper::query()
            ->with(['pekerja.company:id,name', 'pekerja.departemen', 'permit.mcuOrang', 'user:id,name'])
            ->when(! $riwayat, fn ($q) => $q->where('user_id', $u->id))
            ->orderByDesc('tanggal')->orderByDesc('id')->get();

        $baris = $semua->map(fn (Simper $s) => self::baris($s, $u));

        $tersaring = $baris
            ->when($saring['cari'] !== '', fn ($c) => $c->filter(fn ($b) => str_contains($b['cari'], mb_strtolower($saring['cari']))))
            ->when($saring['perusahaan'] !== '', fn ($c) => $c->where('company_id', (int) $saring['perusahaan']));

        $aktif   = $tersaring->where('aktif', true);
        $ringkas = Zona::ringkas($aktif->pluck('zona'));
        $rekap   = Zona::rekapPerusahaan($aktif->all());

        if ($saring['zona'] !== '') $tersaring = $tersaring->where('zona', $saring['zona']);

        $kini = max(1, (int) $r->query('page', 1));

        $halaman = new \Illuminate\Pagination\LengthAwarePaginator(
            $tersaring->values()->forPage($kini, 15)->values()->map(fn ($b) => Arr::except($b, ['cari'])),
            $tersaring->count(), 15, $kini, ['path' => $r->url(), 'query' => $r->except('page')],
        );

        return Inertia::render('Miners/Simper', [
            'judul' => $riwayat ? 'Master SIMPER Admin' : 'Data Master SIMPER',
            'kop'   => false,
            'mode'  => $riwayat ? 'riwayat' : 'register',

            'saring'  => $saring,
            'ringkas' => $ringkas,
            'rekap'   => $rekap,
            'baris'   => $halaman,

            'kelola'     => AksesMiners::kelola($u),
            'perusahaan' => AksesMiners::perusahaan($u)->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->values(),

            'calon'   => $riwayat ? [] : self::calon(),
            'jenisSim' => Simper::JENIS_SIM,
        ]);
    }

    /** @return array<string,mixed> */
    private static function baris(Simper $s, User $u): array
    {
        $habis = $s->habisEfektif();
        $sisa  = Zona::sisa($habis);
        $pk    = $s->pekerja;

        return [
            'id'         => $s->id,
            'nomor'      => $s->no_simper,
            'tanggal'    => $s->tanggal?->toDateString(),
            'nama'       => $pk?->nama,
            'pekerja_id' => $s->pekerja_id,
            'noid'       => $pk?->no_induk ?: $s->permit?->no_registrasi,
            'jabatan'    => $s->permit?->mcuOrang?->jabatan,
            'departemen' => $pk?->departemen?->nama,
            'company_id' => $pk?->company_id ?? $s->company_id,
            'perusahaan' => $pk?->company?->name,
            'jenisSim'   => $s->jenis_simpol,
            'berlaku'    => $habis?->toDateString(),
            'sisa'       => $sisa,
            'zona'       => Zona::dariSisa($sisa),
            'sumber'     => Simper::SUMBER_SIM[$s->simpol_sumber ?? ''] ?? null,
            'status'     => $s->status,
            'aktif'      => (bool) $s->aktif,
            'alasanNonaktif' => $s->alasan_nonaktif,
            'pembuat'    => $s->user?->name,
            'bolehHapus' => $u->isAdmin(),
            'cari'       => mb_strtolower($s->no_simper.' '.$pk?->nama.' '.$pk?->nik.' '.$pk?->no_induk),
        ];
    }

    /* ═══════════════════ pengajuan ═══════════════════ */

    /** `add_simper_user` Safe Track. */
    public function store(Request $r)
    {
        $data   = $this->validasi($r, null);
        $permit = Permit::query()->with('pekerja')->find($data['permit_id']);

        if (! $permit || ! self::calon()->contains('id', $permit->id)) {
            return back()->withErrors(['permit_id' => 'Simper sudah ada, atau Mine Permit karyawan ini belum disetujui/aktif. '
                .'SIMPER hanya dibuat dari Mine Permit yang sudah terbit, satu per karyawan.'])->withInput();
        }

        [$tanggal, $sumber, $meta] = $this->tanggalSim($r, $data);

        if ($tanggal === null) {
            return back()->withErrors(['simpol_berlaku_sampai' => 'Masa berlaku SIM tidak terbaca dari berkasnya. Isi sendiri tanggalnya.'])->withInput();
        }

        $s = Simper::create([
            'company_id'            => $permit->company_id,
            'iup_id'                => $permit->iup_id,
            'permit_id'             => $permit->id,
            'pekerja_id'            => $permit->pekerja_id,
            'tanggal'               => isset($data['tanggal']) ? Waktu::tanggal($data['tanggal']) : Waktu::kini()->startOfDay(),
            'no_simpol'             => $data['no_simpol'],
            'jenis_simpol'          => $data['jenis_simpol'],
            'email_atasan'          => $data['email_atasan'] ?? null,
            'simpol_berlaku_sampai' => $tanggal,
            'simpol_sumber'         => $sumber,
            'simpol_meta'           => $meta,
            'berkas_simpol'         => Berkas::simpan($r->file('berkas_simpol'), 'miners/simper'),
            'berkas_ddt'            => Berkas::simpan($r->file('berkas_ddt'), 'miners/simper'),
            'berkas_permohonan'     => Berkas::simpan($r->file('berkas_permohonan'), 'miners/simper'),
            'berkas_pengalaman'     => Berkas::simpan($r->file('berkas_pengalaman'), 'miners/simper'),
            'user_id'               => $r->user()->id,
        ]);

        $s->terbitkanAlur();

        return redirect()->route('miners.simper.rincian', $s->id)
            ->with('sukses', 'SIMPER '.$s->fresh()->no_simper.' dibuat sebagai draf. Tambahkan unit, lalu kirim.');
    }

    /** `update_simper_user` — admin kapan saja, pengaju selama draf. */
    public function update(Request $r, Simper $simper)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()) || AksesMiners::ubahDraf($simper, $r->user()));

        $data = $this->validasi($r, $simper);

        $ubah = Arr::only($data, ['no_simpol', 'jenis_simpol', 'email_atasan']);
        if (! empty($data['tanggal'])) $ubah['tanggal'] = Waktu::tanggal($data['tanggal']);

        foreach (['berkas_simpol', 'berkas_ddt', 'berkas_permohonan', 'berkas_pengalaman'] as $k) {
            if ($r->hasFile($k)) $ubah[$k] = Berkas::simpan($r->file($k), 'miners/simper');
        }

        if (! empty($data['simpol_berlaku_sampai']) || $r->hasFile('berkas_simpol')) {
            [$tanggal, $sumber, $meta] = $this->tanggalSim($r, $data);
            if ($tanggal) $ubah += ['simpol_berlaku_sampai' => $tanggal, 'simpol_sumber' => $sumber, 'simpol_meta' => $meta];
        }

        $simper->update($ubah);

        return back()->with('sukses', 'SIMPER '.$simper->no_simper.' diperbarui.');
    }

    public function destroy(Simper $simper)
    {
        $simper->alur()->delete();
        $simper->delete();

        return redirect()->route('miners.simper.index')->with('sukses', 'SIMPER dihapus.');
    }

    public function tindak(Request $r, Simper $simper)
    {
        return $this->tindakAlur($r, $simper, 'SIMPER');
    }

    /* ═══════════════════ unit ═══════════════════ */

    /** `add_attachment_simper`. */
    public function unit(Request $r, Simper $simper)
    {
        $this->bolehUnit($r->user(), $simper);

        $data = $this->validasiUnit($r, true);

        $u = SimperUnit::create($this->isiUnit($r, $data) + ['simper_id' => $simper->id, 'asal' => 'baru']);
        $u->jenisUnitBanyak()->sync($data['jenis_unit_ids']);

        return back()->with('sukses', 'Unit ditambahkan.');
    }

    /** `update_line_simper` — admin juga sesudah SIMPER disahkan penuh. */
    public function unitUbah(Request $r, Simper $simper, SimperUnit $unit)
    {
        abort_unless((int) $unit->simper_id === (int) $simper->id, 404);
        $this->bolehUnit($r->user(), $simper);

        $data = $this->validasiUnit($r, false);

        $unit->update($this->isiUnit($r, $data, $unit));
        $unit->jenisUnitBanyak()->sync($data['jenis_unit_ids']);

        return back()->with('sukses', 'Unit diperbarui.');
    }

    /** `delete_detail_data` — hanya selama belum disahkan, kecuali admin. */
    public function unitHapus(Request $r, Simper $simper, SimperUnit $unit)
    {
        abort_unless((int) $unit->simper_id === (int) $simper->id, 404);
        $this->bolehUnit($r->user(), $simper);

        $unit->jenisUnitBanyak()->detach();
        $unit->delete();

        return back()->with('sukses', 'Unit dihapus.');
    }

    private function bolehUnit(User $u, Simper $s): void
    {
        AksesMiners::tegakkan(AksesMiners::kelola($u) || AksesMiners::ubahDraf($s, $u),
            'Unit SIMPER hanya diubah pengaju selama draf, atau admin/HSE IUP.');
    }

    /** @return array<string,mixed> */
    private function isiUnit(Request $r, array $data, ?SimperUnit $lama = null): array
    {
        $isi = [
            'kendaraan_id'  => $data['kendaraan_id'],
            'jenis_unit_id' => $data['jenis_unit_ids'][0] ?? null,
            'authority'     => $data['authority'],
            'nilai_p2h'     => $data['nilai_p2h'] ?? null,
            'nilai_praktek' => $data['nilai_praktek'] ?? null,
        ];

        foreach (['berkas_teori', 'berkas_praktek', 'berkas_evaluasi'] as $k) {
            $isi[$k] = Berkas::simpan($r->file($k), 'miners/simper/unit') ?? $lama?->$k;
        }

        return $isi;
    }

    /* ═══════════════════ masa berlaku & status ═══════════════════ */

    /** `masa-berlaku/simper/{id}` — tanggal SIM manual atau baca ulang dari berkasnya. */
    public function masaBerlaku(Request $r, Simper $simper)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Masa berlaku SIMPER hanya diubah admin atau HSE IUP.');

        $data = $r->validate([
            'sumber'  => ['required', Rule::in(['manual', 'auto'])],
            'tanggal' => ['required_if:sumber,manual', 'nullable', 'date'],
        ], ['tanggal.required_if' => 'Tanggal masa berlaku SIM wajib diisi.']);

        if ($data['sumber'] === 'auto') {
            $hasil = DeteksiMasaSim::dariJalur($simper->berkas_simpol);

            if (! $hasil['tanggal']) {
                return back()->withErrors(['sumber' => 'Masa berlaku tidak terbaca dari berkas SIM-nya. Isi tanggalnya secara manual.']);
            }

            $simper->update(['simpol_berlaku_sampai' => Waktu::tanggal($hasil['tanggal']), 'simpol_sumber' => 'auto', 'simpol_meta' => $hasil]);
        } else {
            $simper->update(['simpol_berlaku_sampai' => Waktu::tanggal($data['tanggal']), 'simpol_sumber' => 'manual']);
        }

        return back()->with('sukses', 'Masa berlaku SIMPER: '.$simper->simpol_berlaku_sampai->format('d-m-Y').'.');
    }

    /** Aktif / tidak aktif dengan alasan. */
    public function status(Request $r, Simper $simper)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Status SIMPER hanya diubah admin atau HSE IUP.');

        $data   = $r->validate(['aktif' => ['required', 'boolean'], 'alasan' => ['nullable', 'string', 'max:200']]);
        $aktif  = $r->boolean('aktif');
        $alasan = trim((string) ($data['alasan'] ?? ''));

        if (! $aktif && $alasan === '') {
            return back()->withErrors(['alasan' => 'Alasan menonaktifkan SIMPER wajib diisi.']);
        }

        $simper->update([
            'aktif'            => $aktif,
            'tanggal_nonaktif' => $aktif ? null : Waktu::kini()->startOfDay(),
            'alasan_nonaktif'  => $aktif ? null : $alasan,
        ]);

        return back()->with('sukses', 'SIMPER '.$simper->no_simper.' ditandai '.($aktif ? 'AKTIF' : 'TIDAK AKTIF').'.');
    }

    /**
     * `POST masa-berlaku/deteksi-sim` — usulan tanggal dari berkas SIM
     * yang baru dipilih, untuk petunjuk di formulir (v14). Tidak menyimpan
     * apa pun dan tidak pernah mengganti tanggal yang sudah diketik.
     */
    public function deteksi(Request $r)
    {
        $r->validate(['berkas' => ['required', ...Berkas::ATURAN_DOKUMEN]]);

        return response()->json(DeteksiMasaSim::dariBerkas($r->file('berkas')) + ['ocr' => DeteksiMasaSim::adaOcr()]);
    }

    /* ═══════════════════ rincian ═══════════════════ */

    public function rincian(Request $r, string $id)
    {
        $u = $r->user();

        $s = Simper::with(['alur.user', 'alur.ditugaskan', 'user', 'iup', 'pekerja.departemen', 'pekerja.company',
            'permit.mcuOrang', 'unit.kendaraan', 'unit.jenisUnitBanyak', 'unit.jenisUnit', 'ajuan'])->findOrFail((int) $id);

        $s->terbitkanAlur();
        $s->load('alur.user', 'alur.ditugaskan');

        $kelola = AksesMiners::kelola($u);
        $pk     = $s->pekerja;
        $habis  = $s->habisEfektif();
        $simKendaraan = fn (?Kendaraan $k) => $k?->kelas_simpol && ! str_contains((string) $s->jenis_simpol, $k->kelas_simpol)
            ? $k->nama.' menuntut SIM '.$k->kelas_simpol.', SIMPER ini tercatat '.($s->jenis_simpol ?: 'tanpa SIM').'.' : null;

        $unit = fn ($x, string $asal) => [
            'id'         => $x->id,
            'asal'       => $asal,
            'kendaraan_id' => $x->kendaraan_id,
            'kendaraan'  => $x->kendaraan?->nama,
            'kode'       => $x->kendaraan?->kode,
            'jenis'      => $x->jenisUnitBanyak->pluck('nama')->whenEmpty(fn ($c) => $c->push($x->jenisUnit?->nama))->filter()->values(),
            'jenis_ids'  => $x->jenisUnitBanyak->pluck('id')->whenEmpty(fn ($c) => $c->push($x->jenis_unit_id))->filter()->values(),
            'authority'  => $x->authority,
            'p2h'        => $x->nilai_p2h,
            'praktek'    => $x->nilai_praktek,
            'teori_b'    => Berkas::lampiran($u, $x, $asal === 'simper' ? 'sut' : 'aut'),
            'praktek_b'  => Berkas::lampiran($u, $x, $asal === 'simper' ? 'sup' : 'aup'),
            'evaluasi_b' => Berkas::lampiran($u, $x, $asal === 'simper' ? 'sue' : 'aue'),
            'peringatan' => $simKendaraan($x->kendaraan),
        ];

        return Inertia::render('Miners/SimperRincian', [
            'judul' => 'Detail SIMPER',
            'kop'   => false,

            'simper' => [
                'id'          => $s->id,
                'nomor'       => $s->no_simper,
                'tanggal'     => $s->tanggal?->toDateString(),
                'status'      => $s->status,
                'aktif'       => (bool) $s->aktif,
                'nonaktifSejak'  => $s->tanggal_nonaktif?->toDateString(),
                'alasanNonaktif' => $s->alasan_nonaktif,
                'nama'        => $pk?->nama,
                'pekerja_id'  => $s->pekerja_id,
                'nik'         => $pk?->nik,
                'noid'        => $pk?->no_induk ?: $s->permit?->no_registrasi,
                'jabatan'     => $s->permit?->mcuOrang?->jabatan,
                'departemen'  => $pk?->departemen?->nama,
                'perusahaan'  => $pk?->company?->name,
                'iup'         => $s->iup?->name,
                'foto'        => $s->permit ? Berkas::lampiran($u, $s->permit, 'mnf') : null,
                'permit'      => $s->permit?->no_registrasi,
                'permit_id'   => $s->permit_id,
                'noSim'       => $s->no_simpol,
                'jenisSim'    => $s->jenis_simpol,
                'kelasLama'   => $s->kelas ? (Simper::KELAS[$s->kelas]['label'] ?? $s->kelas) : null,
                'emailAtasan' => $s->email_atasan,
                'berlaku'     => $habis?->toDateString(),
                'sisa'        => Zona::sisa($habis),
                'sumber'      => Simper::SUMBER_SIM[$s->simpol_sumber ?? ''] ?? null,
                'sumberKode'  => $s->simpol_sumber,
                /* Usulan `miners:deteksi-sim` untuk SIMPER lama — hanya
                   tampil selama tanggal SIM-nya belum diisi. */
                'usulanSim'   => ! $s->simpol_berlaku_sampai && ! empty($s->simpol_meta['usulan']['tanggal'])
                    ? ['tanggal' => $s->simpol_meta['usulan']['tanggal'], 'pesan' => $s->simpol_meta['usulan']['pesan'] ?? null]
                    : null,
                'catatan'     => $s->catatan,
                'pengaju'     => $s->user?->name,
                'berkas' => [
                    ['label' => 'Berkas SIM',        'kunci' => 'berkas_simpol',     'wajib' => true] + Berkas::lampiran($u, $s, 'mnp'),
                    ['label' => 'File DDT',          'kunci' => 'berkas_ddt',        'wajib' => true] + Berkas::lampiran($u, $s, 'sdd'),
                    ['label' => 'Permohonan Simper', 'kunci' => 'berkas_permohonan', 'wajib' => true] + Berkas::lampiran($u, $s, 'sdm'),
                    ['label' => 'Pengalaman Kerja',  'kunci' => 'berkas_pengalaman', 'wajib' => true] + Berkas::lampiran($u, $s, 'sdg'),
                ],
            ],

            'unit'         => $s->unit->map(fn ($x) => $unit($x, 'simper'))->values(),
            'unitTambahan' => $s->unitTambahan()->map(fn ($x) => $unit($x, 'penambahan'))->values(),
            'ajuan'        => $s->ajuan->map(fn (SimperAjuan $a) => [
                'id' => $a->id, 'jenis' => $a->jenisLabel(), 'nomor' => $a->no_registrasi,
                'tanggal' => $a->tanggal?->toDateString(), 'status' => $a->status,
            ])->values(),
            'kurang' => $s->syaratKirim(),

            'riwayat' => RiwayatPerpanjangan::query()->with('user:id,name')->where('jenis', 'simper')
                ->where('dokumen_id', $s->id)->orderByDesc('urutan')->get()
                ->map(fn (RiwayatPerpanjangan $h) => [
                    'urutan' => $h->urutan,
                    'lama'   => $h->berlaku_lama?->toDateString(),
                    'baru'   => $h->berlaku_baru?->toDateString(),
                    'simLama' => $h->data_lama['simpol'] ?? null,
                    'simBaru' => $h->data_baru['simpol'] ?? null,
                    'oleh'   => $h->user?->name,
                    'pada'   => Waktu::lokal($h->created_at)?->format('d-m-Y H:i'),
                ]),

            'alur'   => RincianDokumen::alur($s),
            'posisi' => Jalur::posisi($s),

            'kelola'           => $kelola,
            'bolehUbah'        => $kelola || AksesMiners::ubahDraf($s, $u),
            'bolehKirim'       => Jalur::bolehKirim($s, $u) && ! $s->alurTertahan(),
            'bolehAjukanUlang' => Jalur::bolehKirim($s, $u) && $s->alurTertahan(),
            'bolehTindak'      => Jalur::bolehTindak($s, $u),
            'jenisSim'         => Simper::JENIS_SIM,
            'authority'        => SimperUnit::AUTHORITY,
            'kendaraan'        => Kendaraan::query()->terpakai()->get(['id', 'nama', 'kode']),
            'typeUnit'         => JenisUnit::query()->terpakai()->get(['id', 'nama']),
        ]);
    }

    /* ═══════════════════ kelayakan & perkakas ═══════════════════ */

    /**
     * Calon pemegang SIMPER — pemegang Mine Permit terbit dan aktif yang
     * belum punya SIMPER (satu SIMPER per orang).
     *
     * @return Collection<int,array<string,mixed>>
     */
    public static function calon(): Collection
    {
        $sudah = Simper::withoutGlobalScopes()->select('pekerja_id');

        return Permit::query()->with(['pekerja.company', 'mcuOrang'])
            ->where('status', 'terbit')->where('aktif', true)
            ->whereNotIn('pekerja_id', $sudah)
            ->get()
            ->sortBy(fn (Permit $p) => $p->pekerja?->nama)
            ->map(fn (Permit $p) => [
                'id'         => $p->id,
                'nama'       => $p->pekerja?->nama,
                'nomor'      => $p->no_registrasi,
                'perusahaan' => $p->pekerja?->company?->name,
                'jabatan'    => $p->mcuOrang?->jabatan,
            ])->values();
    }

    /**
     * Tanggal SIM: yang diketik pengguna MENANG; bila kosong, dibaca dari
     * berkas SIM-nya (terapkanMasaBerlakuSim Safe Track).
     *
     * @return array{0:?\Illuminate\Support\Carbon,1:?string,2:?array}
     */
    private function tanggalSim(Request $r, array $data): array
    {
        if (! empty($data['simpol_berlaku_sampai'])) {
            return [Waktu::tanggal($data['simpol_berlaku_sampai']), $r->input('simpol_sumber') === 'auto' ? 'auto' : 'manual', null];
        }

        if ($r->hasFile('berkas_simpol')) {
            $hasil = DeteksiMasaSim::dariBerkas($r->file('berkas_simpol'));
            if ($hasil['tanggal']) return [Waktu::tanggal($hasil['tanggal']), 'auto', $hasil];
        }

        return [null, null, null];
    }

    /** @return array<string,mixed> */
    private function validasi(Request $r, ?Simper $lama): array
    {
        $baru = $lama === null;

        return self::tanpaBerkas($r->validate([
            'permit_id'             => [$baru ? 'required' : 'nullable', 'integer'],
            'tanggal'               => ['nullable', 'date'],
            'email_atasan'          => ['nullable', 'email', 'max:150'],
            'no_simpol'             => [$baru ? 'required' : 'nullable', 'string', 'max:40'],
            'jenis_simpol'          => [$baru ? 'required' : 'nullable', Rule::in(array_merge(Simper::JENIS_SIM, Simper::JENIS_SIMPOL))],
            'simpol_berlaku_sampai' => ['nullable', 'date'],
            'berkas_simpol'         => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_ddt'            => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_permohonan'     => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_pengalaman'     => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'permit_id.required'         => 'Employee wajib dipilih.',
            'no_simpol.required'         => 'Nomor SIM/Simpol wajib diisi.',
            'jenis_simpol.required'      => 'Jenis SIM wajib dipilih.',
            'berkas_simpol.required'     => 'Berkas SIM wajib diunggah.',
            'berkas_ddt.required'        => 'File DDT wajib diunggah.',
            'berkas_permohonan.required' => 'Permohonan Simper wajib diunggah.',
            'berkas_pengalaman.required' => 'Pengalaman Kerja wajib diunggah.',
        ]));
    }

    /** @return array<string,mixed> */
    private function validasiUnit(Request $r, bool $baru): array
    {
        return $r->validate([
            'kendaraan_id'     => ['required', 'exists:mnr_kendaraan,id'],
            'jenis_unit_ids'   => ['required', 'array', 'min:1'],
            'jenis_unit_ids.*' => ['integer', 'exists:mnr_jenis_unit,id'],
            'authority'        => ['required', Rule::in(array_keys(SimperUnit::AUTHORITY))],
            'nilai_p2h'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_praktek'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'berkas_teori'     => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_praktek'   => [$baru ? 'required' : 'nullable', ...Berkas::ATURAN_DOKUMEN],
            'berkas_evaluasi'  => ['nullable', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'kendaraan_id.required'   => 'Master Unit wajib dipilih.',
            'jenis_unit_ids.required' => 'Pilih sedikitnya satu Type Unit.',
            'authority.required'      => 'Authority wajib dipilih.',
            'berkas_teori.required'   => 'Berkas teori wajib diunggah.',
            'berkas_praktek.required' => 'Hasil praktek wajib diunggah.',
        ]);
    }
}
