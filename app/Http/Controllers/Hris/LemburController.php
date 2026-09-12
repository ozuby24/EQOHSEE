<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{Lembur, Upah};
use App\Models\Miners\Pekerja;
use App\Support\Hr\{JalurLembur, UpahLembur};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Lembur (SPL) dan upah dasarnya.
 *
 * LEMBUR DIUSULKAN DARI JAM YANG BENAR-BENAR TERCATAT, bukan diketik
 * pengawas. Absensi sudah menyimpan berapa jam seseorang ada di site
 * hari itu dan roster sudah menyimpan berapa jam ia dijadwalkan;
 * selisihnya adalah lemburnya. Diketik tangan, lembur yang
 * benar-benar dikerjakan terlewat karena tidak ada yang
 * mengajukannya — dan lembur yang tidak dikerjakan terbayar karena
 * tidak ada yang dapat membantahnya.
 */
class LemburController extends Controller
{
    public function index(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);

        $baris = Lembur::query()
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->with(['pekerja.jabatan', 'penindak'])
            ->orderByDesc('tanggal')
            ->get();

        return Inertia::render('Hris/Lembur/Daftar', [
            'judul'    => 'HRIS — Lembur (SPL)',
            'subjudul' => 'Diusulkan dari jam yang tercatat, dihitung menurut PP 35/2021.',

            'rentang' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],

            'antrean' => $baris->where('status', 'menunggu')
                ->map(fn (Lembur $l) => $this->baris($l, true))->values(),

            'riwayat' => $baris->where('status', '<>', 'menunggu')
                ->map(fn (Lembur $l) => $this->baris($l))->values(),

            'ringkas' => [
                'jam'   => round((float) $baris->where('status', 'disetujui')->sum('jam'), 2),
                'nilai' => round((float) $baris->where('status', 'disetujui')->sum('nilai'), 2),
                'menunggu' => $baris->where('status', 'menunggu')->count(),
            ],

            'STATUS'     => Lembur::STATUS,
            'JENIS_HARI' => Lembur::JENIS_HARI,

            'ACUAN' => [
                'pembagi'      => UpahLembur::PEMBAGI_JAM,
                'maks_hari'    => UpahLembur::MAKS_JAM_HARI,
                'maks_minggu'  => UpahLembur::MAKS_JAM_MINGGU,
                'min_usul'     => JalurLembur::MIN_JAM,
            ],
        ]);
    }

    /** Usulkan lembur dari catatan absensi satu rentang. */
    public function usulkan(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);

        $ids = Pekerja::query()->aktif()->pluck('id')->all();

        $n = JalurLembur::usulkan($ids, $dari, $sampai, $r->user()?->id);

        $pesan = sprintf(
            '%d perintah lembur diusulkan, %d dilewati.',
            $n['dibuat'], $n['dilewati'],
        );

        if ($n['tanpa_upah'] > 0) {
            $pesan .= ' '.$n['tanpa_upah'].' di antaranya bernilai nol karena upahnya belum tercatat.';
        }

        return back()->with('sukses', $pesan);
    }

    public function setujui(Request $r, Lembur $lembur)
    {
        return $this->tindak(
            JalurLembur::setujui($lembur, $r->user(), $r->input('catatan')),
            'Perintah lembur disetujui.',
        );
    }

    public function tolak(Request $r, Lembur $lembur)
    {
        $r->validate(['catatan' => ['required', 'string', 'min:5', 'max:300']]);

        return $this->tindak(
            JalurLembur::tolak($lembur, $r->user(), $r->input('catatan')),
            'Perintah lembur ditolak.',
        );
    }

    public function batalkan(Request $r, Lembur $lembur)
    {
        return $this->tindak(
            JalurLembur::batalkan($lembur, $r->user(), $r->input('catatan')),
            'Perintah lembur dibatalkan.',
        );
    }

    /* ═══════════════════ upah ═══════════════════ */

    public function upah()
    {
        $pekerja = Pekerja::query()->aktif()->with('jabatan')->orderBy('nama')->get();

        return Inertia::render('Hris/Lembur/Upah', [
            'judul'    => 'HRIS — Upah Dasar',
            'subjudul' => 'Dasar perhitungan lembur, bertanggal berlaku.',

            'baris' => $pekerja->map(function (Pekerja $p) {
                $u = Upah::kini($p);

                $dasar = $u
                    ? UpahLembur::dasar($u->pokok, $u->tunjangan_tetap, $u->tunjangan_tidak_tetap)
                    : null;

                return [
                    'id'        => $p->id,
                    'nama'      => $p->nama,
                    'nik'       => $p->nik,
                    'jabatan'   => $p->jabatan?->nama,
                    'kecuali'   => (bool) $p->jabatan?->kecuali_lembur,

                    'berlaku'   => $u?->berlaku_mulai?->toDateString(),
                    'pokok'     => $u?->pokok,
                    'tetap'     => $u?->tunjangan_tetap,
                    'tidakTetap'=> $u?->tunjangan_tidak_tetap,

                    'dasar'     => $dasar ? round($dasar['upah'], 2) : null,
                    'persen'    => $dasar['persen'] ?? null,
                    'sejam'     => $dasar ? round(UpahLembur::upahSejam($dasar['upah']), 2) : null,
                ];
            }),

            'ACUAN' => [
                'pembagi' => UpahLembur::PEMBAGI_JAM,
                'ambang'  => (int) round(UpahLembur::AMBANG_DASAR * 100),
            ],
        ]);
    }

    public function upahSimpan(Request $r)
    {
        $data = $r->validate([
            'pekerja_id'            => ['required', 'exists:mnr_pekerja,id'],
            'berlaku_mulai'         => ['required', 'date'],
            'pokok'                 => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'tunjangan_tetap'       => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'tunjangan_tidak_tetap' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'catatan'               => ['nullable', 'string', 'max:300'],
        ]);

        $p = Pekerja::findOrFail($data['pekerja_id']);

        /* Baris upah TIDAK ditimpa melainkan ditambah, kecuali tanggal
           berlakunya persis sama. Ditimpa, riwayat upah hilang — dan
           bersamanya hilang pula kemampuan menjelaskan nilai lembur
           bulan lalu. */
        Upah::updateOrCreate(
            ['pekerja_id' => $p->id, 'berlaku_mulai' => Waktu::tanggal($data['berlaku_mulai'])],
            [
                'company_id'            => $p->company_id,
                'pokok'                 => $data['pokok'],
                'tunjangan_tetap'       => $data['tunjangan_tetap'] ?? 0,
                'tunjangan_tidak_tetap' => $data['tunjangan_tidak_tetap'] ?? 0,
                'catatan'               => $data['catatan'] ?? null,
            ],
        );

        return back()->with('sukses', 'Upah dasar '.$p->nama.' disimpan.');
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function tindak(?string $alasan, string $sukses)
    {
        return $alasan === null
            ? back()->with('sukses', $sukses)
            : back()->withErrors(['tindak' => $alasan]);
    }

    /** @return array<string,mixed> */
    private function baris(Lembur $l, bool $denganPeringatan = false): array
    {
        $isi = [
            'id'        => $l->id,
            'pekerja'   => $l->pekerja?->nama,
            'nik'       => $l->pekerja?->nik,
            'jabatan'   => $l->pekerja?->jabatan?->nama,
            'tanggal'   => $l->tanggal?->toDateString(),
            'jenisHari' => $l->jenis_hari,
            'hariMinggu'=> $l->hari_seminggu,
            'jam'       => (float) $l->jam,
            'sebulan'   => (float) $l->upah_sebulan,
            'persen'    => $l->dasar_persen,
            'sejam'     => (float) $l->upah_sejam,
            'rincian'   => $l->rincian ?? [],
            'nilai'     => (float) $l->nilai,
            'alasan'    => $l->alasan,
            'status'    => $l->status,
            'penindak'  => $l->penindak?->name,
            'catatan'   => $l->catatan_tindak,
        ];

        return $denganPeringatan ? $isi + ['peringatan' => JalurLembur::peringatan($l)] : $isi;
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $r): array
    {
        $dari = $r->query('dari') ? Waktu::tanggal($r->query('dari')) : Waktu::kini()->startOfMonth();

        $sampai = $r->query('sampai') ? Waktu::tanggal($r->query('sampai')) : $dari->copy()->endOfMonth();

        if ($sampai->lt($dari)) $sampai = $dari->copy()->endOfMonth();

        /* Dibatasi seperti pada roster dan absensi, dan karena alasan
           yang sama: rekap setahun untuk site berisi tiga ratus orang
           membaca puluhan ribu baris untuk satu halaman. */
        if ($dari->diffInDays($sampai) > 92) $sampai = $dari->copy()->addDays(92);

        return [$dari, $sampai];
    }
}
