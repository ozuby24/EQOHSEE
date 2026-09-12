<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{Absensi, Cuti, JenisCuti, Kontrak, Lembur, Roster, SlipGaji};
use App\Support\Berkas;
use App\Support\Hr\{Ess, KebijakanCuti, KontrakPkwt};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Layanan mandiri: yang dilihat seorang pekerja tentang dirinya.
 *
 * SELURUH MODUL HRIS SEBELUM INI HANYA DAPAT DIOPERASIKAN ADMIN. Rosternya
 * disusun admin, absensinya direkonsiliasi admin, cutinya disetujui admin,
 * dan slipnya dihitung admin — sementara orang yang seluruh angkanya
 * tentang dirinya tidak punya satu pun pintu masuk. Yang terjadi di
 * lapangan bukan orang yang tidak tahu, melainkan orang yang bertanya
 * lewat WhatsApp kepada staf HR satu per satu, dan staf itu membuka
 * layar admin untuk menjawabnya.
 *
 * TIAP KUERI DI SINI MELEWATI Ess. Tidak ada satu pun `where('pekerja_id',
 * ...)` yang ditulis langsung: ditulis ulang di tiap tempat, cepat atau
 * lambat ada satu yang terlewat, dan yang terlewat tidak menimbulkan
 * galat — hanya baris rekan sekerja yang ikut terbaca pada halaman
 * berjudul "Slip Gaji Saya".
 *
 * AKUN TANPA PEKERJA BUKAN GALAT. Admin, auditor, dan pengawas kantor
 * memang bukan pekerja tambang mana pun; halamannya menyebutkan itu apa
 * adanya alih-alih menggambar layar kosong yang terbaca seperti data
 * yang gagal dimuat.
 */
class SayaController extends Controller
{
    /** Berapa hari ke depan jadwal ditampilkan. */
    public const JENDELA_JADWAL = 14;

    /** Berapa bulan riwayat kehadiran ditampilkan. */
    public const BULAN_RIWAYAT = 3;

    public function index(Request $r)
    {
        $p = Ess::pekerja($r->user());

        if ($p === null) return $this->tanpaPekerja('Beranda Saya');

        $hari = Waktu::kini()->startOfDay();

        $jadwal = Ess::milik(Roster::query(), $p)
            ->where('tanggal', '>=', $hari->toDateString())
            ->where('tanggal', '<=', $hari->copy()->addDays(self::JENDELA_JADWAL)->toDateString().' 23:59:59')
            ->orderBy('tanggal')
            ->get();

        $hariIni = $jadwal->firstWhere(fn (Roster $x) => $x->tanggal->isSameDay($hari));

        $saldo = KebijakanCuti::ringkas($p, (int) $hari->format('Y'));

        $kontrak = Ess::milik(Kontrak::query(), $p)->hidup()->orderByDesc('mulai')->first();

        $slip = Ess::milik(SlipGaji::query(), $p)
            ->with('periode')->orderByDesc('id')->first();

        return Inertia::render('Hris/Saya/Beranda', [
            'judul'    => 'Beranda Saya',
            'subjudul' => 'Jadwal, kehadiran, cuti, dan slip gaji — milik Anda sendiri.',

            'saya' => $this->diri($p),

            'kop' => [
                'angka' => [
                    'label'   => 'Jadwal hari ini',
                    'nilai'   => $hariIni?->bekerja()
                        ? ucfirst((string) ($hariIni->shift ?? 'kerja'))
                        : ($hariIni ? 'Libur' : 'Tidak dijadwalkan'),
                    'catatan' => $hari->format('l, d F Y'),
                ],
                'sisi' => [
                    ['Sisa cuti', $saldo['sisa'].' hari'],
                    ['Hari kerja 14 hari ke depan',
                        (string) $jadwal->filter(fn (Roster $x) => $x->bekerja())->count()],
                ],
            ],

            'hariIni' => $hariIni === null ? null : $this->barisRoster($hariIni),

            'jadwal' => $jadwal->map(fn (Roster $x) => $this->barisRoster($x))->values(),

            'saldo' => $saldo,

            'kontrak' => $kontrak === null ? null : [
                'nomor'   => $kontrak->nomor,
                'jenis'   => Kontrak::JENIS[$kontrak->jenis] ?? $kontrak->jenis,
                'mulai'   => $kontrak->mulai?->toDateString(),
                'selesai' => $kontrak->selesai?->toDateString(),
                'sisa'    => $kontrak->selesai
                    ? (int) Waktu::kini()->startOfDay()->diffInDays($kontrak->selesai, false)
                    : null,
            ],

            'slip' => $slip === null ? null : [
                'id'      => $slip->id,
                'periode' => $slip->periode
                    ? (\App\Models\Hr\PeriodeGaji::BULAN[$slip->periode->bulan] ?? '').' '.$slip->periode->tahun
                    : '—',
                'neto'    => (float) $slip->neto,
                'terkunci'=> $slip->periode?->status === 'terkunci',
            ],
        ]);
    }

    public function kehadiran(Request $r)
    {
        $p = Ess::pekerja($r->user());

        if ($p === null) return $this->tanpaPekerja('Kehadiran Saya');

        $sampai = Waktu::kini()->startOfDay();
        $dari   = $sampai->copy()->subMonths(self::BULAN_RIWAYAT)->startOfMonth();

        $baris = Ess::milik(Absensi::query(), $p)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->orderByDesc('tanggal')
            ->get();

        $lembur = Ess::milik(Lembur::query(), $p)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->where('status', 'disetujui')
            ->get();

        $bekerja = $baris->filter(fn (Absensi $a) => $a->bekerja());

        return Inertia::render('Hris/Saya/Kehadiran', [
            'judul'    => 'Kehadiran Saya',
            'subjudul' => 'Yang tercatat mesin lapangan atas nama Anda, tiga bulan terakhir.',

            'saya' => $this->diri($p),

            'kop' => [
                'angka' => [
                    'label'   => 'Hari bekerja',
                    'nilai'   => (string) $bekerja->count(),
                    'catatan' => $dari->format('d M').' – '.$sampai->format('d M Y'),
                ],
                'sisi' => [
                    ['Jam tercatat', number_format((float) $baris->sum('jam'), 1, ',', '.')],
                    ['Terlambat', (string) $baris->where('keadaan', 'terlambat')->count(),
                        $baris->where('keadaan', 'terlambat')->count() > 0 ? 'ingat' : null],
                    ['Lembur disetujui', number_format((float) $lembur->sum('jam'), 1, ',', '.').' jam'],
                ],
            ],

            'rentang' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],

            'baris' => $baris->map(fn (Absensi $a) => [
                'tanggal' => $a->tanggal?->toDateString(),
                'keadaan' => $a->keadaan,
                'masuk'   => $a->masuk ? Waktu::lokal($a->masuk)->format('H:i') : null,
                'keluar'  => $a->keluar ? Waktu::lokal($a->keluar)->format('H:i') : null,
                'jam'     => (float) $a->jam,
                'telat'   => (int) $a->telat_menit,
                'dalamArea' => $a->dalam_area,
            ])->values(),

            'KEADAAN' => Absensi::KEADAAN,
        ]);
    }

    public function cuti(Request $r)
    {
        $p = Ess::pekerja($r->user());

        if ($p === null) return $this->tanpaPekerja('Cuti Saya');

        $tahun = (int) Waktu::kini()->format('Y');
        $saldo = KebijakanCuti::ringkas($p, $tahun);

        $riwayat = Ess::milik(Cuti::query(), $p)
            ->with('jenis')->orderByDesc('mulai')->get();

        return Inertia::render('Hris/Saya/Cuti', [
            'judul'    => 'Cuti Saya',
            'subjudul' => 'Saldo, pengajuan, dan riwayatnya.',

            'saya' => $this->diri($p),

            'kop' => [
                'angka' => [
                    'label'   => 'Dapat diajukan',
                    'nilai'   => $saldo['tersedia'].' hari',
                    'catatan' => 'cuti tahunan '.$tahun,
                ],
                'sisi' => [
                    ['Hak setahun', $saldo['hak'].' hari'],
                    ['Terpakai', $saldo['terpakai'].' hari'],
                    ['Menunggu persetujuan', $saldo['tertunda'].' hari',
                        $saldo['tertunda'] > 0 ? 'ingat' : null],
                ],
            ],

            'saldo' => $saldo,
            'tahun' => $tahun,

            'riwayat' => $riwayat->map(fn (Cuti $c) => [
                'id'      => $c->id,
                'jenis'   => $c->jenis?->nama ?? '—',
                'mulai'   => $c->mulai?->toDateString(),
                'selesai' => $c->selesai?->toDateString(),
                'hari'    => $c->hari,
                'status'  => $c->status,
                'alasan'  => $c->alasan,
                'catatan' => $c->catatan_tindakan,
            ])->values(),

            'jenis' => JenisCuti::query()->orderBy('nama')
                ->get(['id', 'nama', 'kunci', 'perlu_bukti'])
                ->map(fn ($j) => [
                    'id'         => $j->id,
                    'nama'       => $j->nama,
                    'perluBukti' => (bool) $j->perlu_bukti,
                ])->values(),

            'STATUS' => Cuti::STATUS,
        ]);
    }

    public function gaji(Request $r)
    {
        $p = Ess::pekerja($r->user());

        if ($p === null) return $this->tanpaPekerja('Slip Gaji Saya');

        /* HANYA PERIODE YANG SUDAH TERKUNCI. Periode yang belum dikunci
           masih pratinjau: angkanya dapat berubah saat dihitung ulang,
           dan angka gaji yang berubah sesudah dilihat orangnya adalah
           cara tercepat kehilangan kepercayaan atas seluruh sistem. */
        $slip = Ess::milik(SlipGaji::query(), $p)
            ->whereHas('periode', fn ($q) => $q->where('status', 'terkunci'))
            ->with('periode')
            ->orderByDesc('id')
            ->get();

        $terakhir = $slip->first();

        return Inertia::render('Hris/Saya/Gaji', [
            'judul'    => 'Slip Gaji Saya',
            'subjudul' => 'Hanya periode yang sudah dikunci — yang belum masih dapat berubah.',

            'saya' => $this->diri($p),

            'kop' => $terakhir === null ? null : [
                'angka' => [
                    'label'   => 'Dibawa pulang',
                    'nilai'   => 'Rp '.number_format((float) $terakhir->neto, 0, ',', '.'),
                    'catatan' => $this->namaPeriode($terakhir),
                ],
                'sisi' => [
                    ['Bruto pajak', 'Rp '.number_format((float) $terakhir->bruto, 0, ',', '.')],
                    ['PPh 21', 'Rp '.number_format((float) $terakhir->pph21, 0, ',', '.'), 'ingat'],
                ],
            ],

            'slip' => $slip->map(fn (SlipGaji $s) => [
                'id'        => $s->id,
                'periode'   => $this->namaPeriode($s),
                'pokok'     => (float) $s->pokok,
                'site'      => (float) $s->tunjangan_site,
                'hariSite'  => (int) $s->hari_site,
                'lembur'    => (float) $s->lembur,
                'bruto'     => (float) $s->bruto,
                'bpjs'      => (float) $s->bpjs_karyawan,
                'pph21'     => (float) $s->pph21,
                'neto'      => (float) $s->neto,
                'statusPtkp'=> $s->status_ptkp,
            ])->values(),
        ]);
    }

    public function kontrak(Request $r)
    {
        $p = Ess::pekerja($r->user());

        if ($p === null) return $this->tanpaPekerja('Kontrak Saya');

        $semua = Ess::milik(Kontrak::query(), $p)
            ->orderByDesc('mulai')->orderByDesc('id')->get();

        $aktif = $semua->firstWhere(fn (Kontrak $k) => $k->hidup());

        return Inertia::render('Hris/Saya/Kontrak', [
            'judul'    => 'Kontrak Saya',
            'subjudul' => 'Perjanjian kerja yang berlaku, beserta riwayat perpanjangannya.',

            'saya' => $this->diri($p),

            'kop' => $aktif === null ? null : [
                'angka' => [
                    'label'   => 'Kontrak berlaku',
                    'nilai'   => Kontrak::JENIS[$aktif->jenis] ?? $aktif->jenis,
                    'catatan' => $aktif->nomor,
                ],
                'sisi' => array_values(array_filter([
                    $aktif->selesai ? ['Berakhir', $aktif->selesai->toDateString(),
                        Waktu::kini()->startOfDay()->diffInDays($aktif->selesai, false) <= 60 ? 'ingat' : null] : null,
                    ['Masa kerja kontrak ini',
                        number_format(KontrakPkwt::bulan($aktif->mulai, KontrakPkwt::akhirNyata($aktif)), 1, ',', '.').' bln'],
                ])),
            ],

            'baris' => $semua->map(fn (Kontrak $k) => [
                'nomor'   => $k->nomor,
                'jenis'   => Kontrak::JENIS[$k->jenis] ?? $k->jenis,
                'mulai'   => $k->mulai?->toDateString(),
                'selesai' => $k->selesai?->toDateString(),
                'status'  => Kontrak::STATUS[$k->status] ?? $k->status,
                'urutan'  => $k->urutan,
                'bulan'   => KontrakPkwt::bulan($k->mulai, KontrakPkwt::akhirNyata($k)),

                /* Kompensasi yang SUDAH dibayar saja. Yang belum adalah
                   taksiran, dan taksiran uang yang ditampilkan kepada
                   orang yang akan menerimanya akan dibaca sebagai janji. */
                'kompensasi' => $k->kompensasi_dibayar_pada ? (float) $k->kompensasi_nilai : null,
                'dibayar'    => $k->kompensasi_dibayar_pada?->toDateString(),
            ])->values(),
        ]);
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function tanpaPekerja(string $judul)
    {
        return Inertia::render('Hris/Saya/Kosong', [
            'judul'    => $judul,
            'subjudul' => 'Akun ini belum tertaut ke data pekerja mana pun.',
        ]);
    }

    /** @return array<string,mixed> */
    private function diri(\App\Models\Miners\Pekerja $p): array
    {
        return [
            'nama'       => $p->nama,
            'registrasi' => $p->no_registrasi,
            'jabatan'    => $p->jabatan?->nama,
            'departemen' => $p->departemen?->nama,
            'blok'       => $p->blok?->nama,
            'masuk'      => $p->tanggal_masuk?->toDateString(),
            'foto'       => $p->foto ? Berkas::terbuka($p->foto) : null,
        ];
    }

    private function namaPeriode(SlipGaji $s): string
    {
        if (! $s->periode) return '—';

        return (\App\Models\Hr\PeriodeGaji::BULAN[$s->periode->bulan] ?? '').' '.$s->periode->tahun;
    }

    /** @return array{tanggal:?string,keadaan:string,shift:?string,jam:float,cuti:bool} */
    private function barisRoster(Roster $x): array
    {
        return [
            'tanggal' => $x->tanggal?->toDateString(),
            'keadaan' => $x->keadaan,
            'shift'   => $x->shift,
            'jam'     => (float) $x->jam,
            'cuti'    => $x->cuti_id !== null,
        ];
    }
}
