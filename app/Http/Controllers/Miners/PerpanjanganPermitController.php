<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Miners\Concerns\TindakAlur;
use App\Models\Miners\{Alur, Permit, PermitPerpanjangan, RiwayatPerpanjangan};
use App\Notifications\PemberitahuanMiners;
use App\Support\Berkas;
use App\Support\Miners\{AksesMiners, Jalur, Penyetuju, RincianDokumen, TugasMiners};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Perpanjangan Mine Permit — extendcardsController dan
 * workflowextendsController Safe Track.
 *
 * Pengajuannya LANGSUNG BERJALAN (statuspengajuan 0 = open), tidak lewat
 * draf: formulirnya sudah memuat seluruh yang dibutuhkan — tanggal
 * periksa MCU baru, masa berlaku baru, form pengajuan, dan sertifikat MCU.
 * Rantainya pengaju → dokter → HSE → KTT; lihat PermitPerpanjangan.
 */
class PerpanjanganPermitController extends Controller
{
    use TindakAlur;

    /**
     * Daftar perpanjangan — `masterdata-users` / `masterdata-admin` Safe
     * Track. "Perlu Approval Saya" menjadi bawaan bagi yang punya giliran;
     * "Semua" seluruh pengajuan dalam jangkauannya.
     */
    public function index(Request $r)
    {
        $u = $r->user();

        $milikSaya = Alur::query()->giliran()->where('dokumen', 'perpanjangan_permit')
            ->where('ditugaskan_user_id', $u->id)->where('peran', '!=', 'pengaju')->select('dokumen_id');

        $saya = PermitPerpanjangan::withoutGlobalScopes()->whereIn('status', TugasMiners::BERJALAN)->whereIn('id', $milikSaya);

        $jumlahSaya = (clone $saya)->count();
        $aktif = $r->query('semua') !== null ? 'semua' : ($jumlahSaya > 0 ? 'saya' : 'semua');

        $q = $aktif === 'saya' ? $saya : PermitPerpanjangan::query();

        $baris = $q->with(['permit.pekerja.company', 'permit.mcuOrang', 'permit.perpanjanganBerjalan', 'user:id,name', 'alur.ditugaskan'])
            ->orderByDesc('tanggal')->orderByDesc('id')->get()
            ->map(fn (PermitPerpanjangan $x) => [
                'id'         => $x->id,
                'nomor'      => $x->no_registrasi,
                'tanggal'    => $x->tanggal?->toDateString(),
                'nama'       => $x->permit?->pekerja?->nama,
                'noid'       => $x->permit?->no_registrasi,
                'perusahaan' => $x->permit?->pekerja?->company?->name,
                'berlakuBaru' => $x->berlaku_baru?->toDateString(),
                'status'     => $x->status,
                'oleh'       => $x->user?->name,
                'posisi'     => Jalur::posisi($x)['pesan'],
            ]);

        return Inertia::render('Miners/PerpanjanganPermit', [
            'judul' => 'Perpanjangan Mine Permit',
            'kop'   => false,
            'baris' => $baris,
            'pilih' => ['aktif' => $aktif, 'saya' => $jumlahSaya, 'semua' => PermitPerpanjangan::query()->count()],
            'calon' => self::calon(),
            'hariIni' => Waktu::kini()->toDateString(),
        ]);
    }

    /** `sendperpanjangan` Safe Track. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'permit_id'    => ['required', 'integer'],
            'tanggal'      => ['required', 'date'],
            'berlaku_baru' => ['required', 'date'],
            'tanggal_mcu'  => ['required', 'date'],
            'catatan'      => ['nullable', 'string', 'max:2000'],
            'berkas_form'  => ['required', ...Berkas::ATURAN_DOKUMEN],
            'berkas_mcu'   => ['required', ...Berkas::ATURAN_DOKUMEN],
        ], [
            'permit_id.required'    => 'Mine Permit yang diperpanjang wajib dipilih.',
            'berlaku_baru.required' => 'Masa berlaku baru wajib diisi.',
            'tanggal_mcu.required'  => 'Tanggal periksa MCU wajib diisi.',
            'berkas_form.required'  => 'Form pengajuan wajib diunggah.',
            'berkas_mcu.required'   => 'Sertifikat MCU wajib diunggah.',
        ]);

        $permit = Permit::query()->find($data['permit_id']);

        if (! $permit || ! self::calon()->contains('id', $permit->id)) {
            return back()->withErrors(['permit_id' => 'Mine Permit ini tidak dapat diperpanjang: kartunya harus sudah terbit '
                .'atau expired, masih aktif, dan tidak sedang punya perpanjangan yang berjalan.'])->withInput();
        }

        /* Diperiksa SEBELUM apa pun disimpan — langkah dokter, HSE, dan
           KTT harus sudah punya penyetujunya. */
        $iup = $permit->iup_id ? (int) $permit->iup_id : null;

        if ($pesan = Penyetuju::pesanBelumSiap($iup, Alur::rantai('perpanjangan_permit'))) {
            return back()->withErrors(['permit_id' => $pesan])->withInput();
        }

        /* Tanggal kartu saat ini DIBEKUKAN ke kolomnya sebelum pengajuan
           berjalan: selama itu kolom inilah yang dibaca, sebab langkah
           dokter menulis MCU barunya lebih dulu (Permit::habisEfektif). */
        if ($permit->sumber_berlaku !== 'manual' && ($kini = $permit->habisEfektif())
            && $permit->berlaku_sampai?->toDateString() !== $kini->toDateString()) {
            $permit->forceFill(['berlaku_sampai' => $kini])->save();
        }

        $x = PermitPerpanjangan::create([
            'company_id'   => $permit->company_id,
            'iup_id'       => $iup,
            'permit_id'    => $permit->id,
            'tanggal'      => Waktu::tanggal($data['tanggal']),
            'berlaku_baru' => Waktu::tanggal($data['berlaku_baru']),
            'tanggal_mcu'  => Waktu::tanggal($data['tanggal_mcu']),
            'catatan'      => $data['catatan'] ?? null,
            'berkas_form'  => Berkas::simpan($r->file('berkas_form'), 'miners/perpanjangan'),
            'berkas_mcu'   => Berkas::simpan($r->file('berkas_mcu'), 'miners/perpanjangan'),
            'user_id'      => $r->user()->id,
        ]);

        $x->terbitkanAlur();

        if ($alasan = Jalur::kirim($x, $r->user())) {
            return redirect()->route('miners.perpanjangan-permit.rincian', $x->id)->withErrors(['kirim' => $alasan]);
        }

        $galat = Jalur::pesanSurel();

        /* PerpanjanganSubmit — kotak OHSE ikut dikabari. */
        if ($tujuan = Penyetuju::kotakOhse($iup)) {
            $galat ??= $this->surel(fn () => Notification::route('mail', $tujuan)->notify(new PemberitahuanMiners(
                'Perpanjangan Mine Permit diajukan — '.$permit->pekerja?->nama,
                [
                    'Perpanjangan Mine Permit '.$permit->no_registrasi.' atas nama '.$permit->pekerja?->nama.' diajukan.',
                    'Masa berlaku baru yang diminta: '.$x->berlaku_baru?->format('d-m-Y').'.',
                ],
                route('miners.perpanjangan-permit.rincian', $x->id, false),
                'Buka pengajuan',
            )));
        }

        return redirect()->route('miners.perpanjangan-permit.rincian', $x->id)
            ->with('sukses', 'Perpanjangan Mine Permit diajukan; menunggu validasi dokter.')
            ->with('galat', $galat);
    }

    /** `extendsimper-users/{id}` (detail perpanjangan permit) Safe Track. */
    public function rincian(Request $r, string $id)
    {
        $u = $r->user();

        $x = PermitPerpanjangan::with(['alur.user', 'alur.ditugaskan', 'user', 'iup',
            'permit.pekerja.company', 'permit.pekerja.departemen', 'permit.mcuOrang'])->findOrFail((int) $id);

        $x->terbitkanAlur();
        $x->load('alur.user', 'alur.ditugaskan');

        $p = $x->permit;

        return Inertia::render('Miners/PerpanjanganRincian', [
            'judul' => 'Perpanjangan Mine Permit Detail',
            'kop'   => false,

            'ajuan' => [
                'id'          => $x->id,
                'nomor'       => $x->no_registrasi,
                'tanggal'     => $x->tanggal?->toDateString(),
                'berlakuBaru' => $x->berlaku_baru?->toDateString(),
                'tanggalMcu'  => $x->tanggal_mcu?->toDateString(),
                'status'      => $x->status,
                'catatan'     => $x->catatan,
                'pengaju'     => $x->user?->name,
                'nama'        => $p?->pekerja?->nama,
                'noid'        => $p?->no_registrasi,
                'permit_id'   => $p?->id,
                'jabatan'     => $p?->mcuOrang?->jabatan,
                'departemen'  => $p?->pekerja?->departemen?->nama,
                'perusahaan'  => $p?->pekerja?->company?->name,
                'berlakuKini' => $p?->habisEfektif()?->toDateString(),
                'form'        => Berkas::lampiran($u, $x, 'mpf'),
                'mcu'         => Berkas::lampiran($u, $x, 'mpm'),
                'alasanTolak' => $x->alur->first(fn ($a) => in_array($a->keadaan, ['tolak', 'dikembalikan'], true))?->catatan,
            ],

            'riwayat' => $p ? RiwayatPerpanjangan::query()->with('user:id,name')->where('jenis', 'permit')
                ->where('dokumen_id', $p->id)->orderByDesc('urutan')->get()
                ->map(fn (RiwayatPerpanjangan $h) => [
                    'urutan' => $h->urutan,
                    'lama'   => $h->berlaku_lama?->toDateString(),
                    'baru'   => $h->berlaku_baru?->toDateString(),
                    'oleh'   => $h->user?->name,
                    'pada'   => Waktu::lokal($h->created_at)?->format('d-m-Y H:i'),
                ]) : [],

            'alur'   => RincianDokumen::alur($x),
            'posisi' => Jalur::posisi($x),

            'bolehTindak'      => Jalur::bolehTindak($x, $u),
            'bolehAjukanUlang' => Jalur::bolehKirim($x, $u) && $x->alurTertahan(),
        ]);
    }

    public function tindak(Request $r, PermitPerpanjangan $perpanjangan)
    {
        $balas = $this->tindakAlur($r, $perpanjangan, 'Perpanjangan Mine Permit');

        return $balas;
    }

    public function ajukanUlang(Request $r, PermitPerpanjangan $perpanjangan)
    {
        if ($alasan = Jalur::ajukanUlang($perpanjangan, $r->user())) {
            return back()->withErrors(['ajukanUlang' => $alasan]);
        }

        return $this->balas('Perpanjangan dikirim ulang; alurnya diulang dari awal.');
    }

    /**
     * Mine Permit yang dapat diperpanjang — `formperpanjangan` Safe Track:
     * terbit atau expired, aktif, tanpa perpanjangan lain yang berjalan.
     * Keadaan kartunya ikut dikirim supaya pengaju tinggal memperbarui yang
     * memang berubah.
     *
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    public static function calon()
    {
        $berjalan = PermitPerpanjangan::withoutGlobalScopes()->whereIn('status', PermitPerpanjangan::BERJALAN)->select('permit_id');

        $permit = Permit::query()->with(['pekerja', 'mcuOrang', 'perpanjanganBerjalan'])
            ->whereIn('status', Permit::TERBIT)->where('aktif', true)
            ->whereNotIn('id', $berjalan)
            ->get();

        /* Jumlah perpanjangan terdahulu dihitung SEKALI untuk semua calon,
           bukan satu kueri per baris. */
        $jumlah = RiwayatPerpanjangan::query()->where('jenis', 'permit')->whereIn('dokumen_id', $permit->pluck('id'))
            ->selectRaw('dokumen_id, COUNT(*) as n')->groupBy('dokumen_id')->pluck('n', 'dokumen_id');

        return $permit
            ->sortBy(fn (Permit $p) => $p->pekerja?->nama)
            ->map(fn (Permit $p) => [
                'id'      => $p->id,
                'label'   => ($p->pekerja?->nama ?? '(nama tidak tertaut)').' - '.($p->no_registrasi ?: '-'),
                'berlaku' => $p->habisEfektif()?->toDateString(),
                'mcu'     => $p->mcuOrang?->berlaku_sampai?->toDateString(),
                'tglMcu'  => $p->mcuOrang?->tanggal_periksa?->toDateString(),
                'nik'     => $p->pekerja?->nik,
                'cakupan' => $p->cakupan_area,
                'jumlah'  => (int) ($jumlah[$p->id] ?? 0),
            ])->values();
    }
}
