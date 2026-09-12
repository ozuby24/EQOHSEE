<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{Kebutuhan, PolaRoster, Regu, ReguAnggota, Roster};
use App\Models\Miners\{Blok, Jabatan, Pekerja};
use App\Support\Hr\{Fatigue, Kelayakan, Penyusun};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Roster & shift — pola kerja bergilir di tambang terpencil.
 *
 * Menjawab dua pertanyaan yang tidak dapat dijawab spreadsheet: siapa
 * yang seharusnya berada di site pada tanggal berapa, dan BOLEHKAH ia
 * berada di sana. Yang kedua itu yang membedakannya — jawabannya
 * dibaca dari MCU, induksi, dan Mine Permit yang memang tersimpan di
 * aplikasi yang sama, pada tanggal yang direncanakan, bukan pada hari
 * penyusunnya membuka layar.
 */
class RosterController extends Controller
{
    /* ═══════════════════ kalender ═══════════════════ */

    public function index(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);

        $regu = Regu::query()->aktif()->with(['pola', 'blok'])->orderBy('nama')->get();

        $terpilih = $r->query('regu')
            ? $regu->firstWhere('id', (int) $r->query('regu'))
            : $regu->first();

        $baris = $terpilih ? $this->kalender($terpilih, $dari, $sampai) : [];

        return Inertia::render('Hris/Roster/Kalender', [
            'judul'    => 'Roster — Kalender Regu',
            'subjudul' => 'Pola kerja bergilir, beserta berkas yang menghalangi penjadwalannya.',

            'regu' => $regu->map(fn (Regu $g) => [
                'id'    => $g->id,
                'nama'  => $g->nama,
                'pola'  => $g->pola?->kode,
                'blok'  => $g->blok?->nama,
                'mulai' => $g->mulai?->toDateString(),
            ]),

            'terpilih' => $terpilih?->id,
            'baris'    => $baris,
            'hari'     => $this->deretHari($dari, $sampai),

            'rentang' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],

            /* Pemeriksaan fatigue dijalankan atas apa yang TERSUSUN,
               bukan atas apa yang akan diterbitkan — supaya penyusunnya
               melihat pelanggarannya sebelum menekan terbit, bukan
               sesudah ditolak. */
            'temuan'  => $terpilih ? $this->temuan($terpilih, $dari, $sampai) : [],
            'ringkas' => $terpilih ? $this->ringkas($terpilih, $dari, $sampai) : null,

            'KEADAAN'     => Roster::KEADAAN,
            'SHIFT'       => Roster::SHIFT,
            'BATAS'       => [
                'jam_hari'      => Fatigue::MAKS_JAM_HARI,
                'jam_14_hari'   => Fatigue::MAKS_JAM_14_HARI,
                'hari_beruntun' => Fatigue::MAKS_HARI_BERUNTUN,
                'istirahat'     => Fatigue::MIN_HARI_ISTIRAHAT,
                'jam_minggu'    => Fatigue::MAKS_JAM_MINGGU,
            ],
        ]);
    }

    public function susun(Request $r, Regu $regu)
    {
        [$dari, $sampai] = $this->rentang($r);

        $n = Penyusun::susun($regu, $dari, $sampai, $r->user()?->id);

        $pesan = sprintf(
            '%d baris dibuat, %d diperbarui, %d dilewati karena sudah terbit.',
            $n['dibuat'], $n['diperbarui'], $n['dilewati'],
        );

        if ($n['halangan'] > 0) {
            $pesan .= ' '.$n['halangan'].' hari kerja tertahan berkas yang tidak berlaku.';
        }

        return back()->with('sukses', $pesan);
    }

    public function terbitkan(Request $r, Regu $regu)
    {
        [$dari, $sampai] = $this->rentang($r);

        $hasil = Penyusun::terbitkan($regu, $dari, $sampai);

        if ($hasil['ditolak'] !== []) {
            $pertama = $hasil['ditolak'][0];

            return back()->withErrors([
                'terbit' => 'Penerbitan ditolak: '.$pertama['label']
                    .' pada '.$pertama['tanggal'].'. '
                    .count($hasil['ditolak']).' pelanggaran ditemukan.',
            ]);
        }

        return back()->with('sukses', $hasil['terbit'].' baris roster diterbitkan.');
    }

    public function ubah(Request $r, Roster $roster)
    {
        $data = $r->validate([
            'keadaan' => ['required', 'in:'.implode(',', array_keys(Roster::KEADAAN))],
            'shift'   => ['nullable', 'in:'.implode(',', array_keys(Roster::SHIFT))],
            'jam'     => ['nullable', 'integer', 'min:0', 'max:24'],
            'catatan' => ['nullable', 'string', 'max:200'],
        ]);

        $bekerja = in_array($data['keadaan'], Roster::BEKERJA, true);

        /* Halangan DIHITUNG ULANG saat barisnya disunting menjadi hari
           kerja. Dibiarkan apa adanya, seseorang dapat memindahkan hari
           liburnya menjadi hari kerja dan lolos dari pemeriksaan berkas
           sepenuhnya — dan baris itulah yang berakhir di pos jaga. */
        $halangan = null;

        if ($bekerja) {
            $p = Pekerja::withoutGlobalScopes()->with(Kelayakan::relasi())->find($roster->pekerja_id);

            $halangan = $p ? Kelayakan::periksa($p, $roster->tanggal)['halangan'] : null;
        }

        $roster->update($data + [
            'shift'    => $bekerja ? ($data['shift'] ?? 'siang') : null,
            'jam'      => $bekerja ? ($data['jam'] ?? $roster->pola?->jam ?? Fatigue::MAKS_JAM_HARI) : 0,
            'halangan' => $halangan,
        ]);

        return back()->with('sukses', 'Baris roster diperbarui.');
    }

    /* ═══════════════════ pola ═══════════════════ */

    public function pola(Request $r)
    {
        return Inertia::render('Hris/Roster/Pola', [
            'judul'    => 'Roster — Pola & Regu',
            'subjudul' => 'Pola bergilir beserta regu yang memakainya.',

            'pola' => PolaRoster::query()->orderBy('urutan')->orderBy('kode')->withCount('regu')->get()
                ->map(fn (PolaRoster $p) => [
                    'id'       => $p->id,
                    'kode'     => $p->kode,
                    'nama'     => $p->nama,
                    'kerja'    => $p->kerja,
                    'libur'    => $p->libur,
                    'satuan'   => $p->satuan,
                    'jam'      => $p->jam,
                    'shift'    => $p->shift,
                    'aktif'    => $p->aktif,
                    'regu'     => $p->regu_count,
                    'siklus'   => $p->siklus(),
                    'hariKerja'=> $p->hariKerja(),
                    'beruntun' => $p->maksBeruntun(),
                    'liburMingguan' => (int) ($p->libur_mingguan ?? 0),
                    'mulaiSiang'    => $p->mulaiShift('siang'),
                    'mulaiMalam'    => $p->mulaiShift('malam'),
                    'toleransi'     => $p->toleransi(),
                    'jamSiklus'=> $p->jamPerSiklus(),

                    /* Pola yang melampaui batas ditandai SEJAK DI SINI,
                       bukan hanya saat roster disusun. Pola 15:6 berjam
                       11 melanggar batas empat belas hari pada tiap
                       siklusnya — dan menemukannya baru saat penerbitan
                       berarti seluruh regu sudah terlanjur disusun. */
                    'langgar'  => $this->langgarPola($p),
                ]),

            'regu' => Regu::query()->with(['pola', 'blok'])->withCount('anggota')->orderBy('nama')->get()
                ->map(fn (Regu $g) => [
                    'id'      => $g->id,
                    'nama'    => $g->nama,
                    'pola'    => $g->pola?->kode,
                    'pola_id' => $g->pola_roster_id,
                    'blok'    => $g->blok?->nama,
                    'blok_id' => $g->blok_id,
                    'mulai'   => $g->mulai?->toDateString(),
                    'shift'   => $g->shift,
                    'anggota' => $g->anggota_count,
                    'aktif'   => $g->aktif,
                ]),

            'SATUAN'  => PolaRoster::SATUAN,
            'SHIFT'   => PolaRoster::SHIFT,
            'blok'    => Blok::pilihan(),
            'pekerja' => Pekerja::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'nik']),
            'BATAS'   => [
                'jam_hari'      => Fatigue::MAKS_JAM_HARI,
                'hari_beruntun' => Fatigue::MAKS_HARI_BERUNTUN,
                'istirahat'     => Fatigue::MIN_HARI_ISTIRAHAT,
                'jam_14_hari'   => Fatigue::MAKS_JAM_14_HARI,
            ],
        ]);
    }

    public function polaSimpan(Request $r)
    {
        PolaRoster::create($this->validasiPola($r));

        return back()->with('sukses', 'Pola roster ditambahkan.');
    }

    public function polaUbah(Request $r, PolaRoster $pola)
    {
        $pola->update($this->validasiPola($r));

        return back()->with('sukses', 'Pola roster diperbarui.');
    }

    public function polaHapus(PolaRoster $pola)
    {
        $pola->delete();

        return back()->with('sukses', 'Pola roster dihapus.');
    }

    /* ═══════════════════ regu ═══════════════════ */

    public function reguSimpan(Request $r)
    {
        $data = $this->validasiRegu($r);

        Regu::create($data + ['mulai' => Waktu::tanggal($data['mulai'])]);

        return back()->with('sukses', 'Regu ditambahkan.');
    }

    public function reguUbah(Request $r, Regu $regu)
    {
        $data = $this->validasiRegu($r);

        $regu->update($data + ['mulai' => Waktu::tanggal($data['mulai'])]);

        return back()->with('sukses', 'Regu diperbarui.');
    }

    public function reguHapus(Regu $regu)
    {
        $regu->delete();

        return back()->with('sukses', 'Regu dihapus.');
    }

    public function anggotaTambah(Request $r, Regu $regu)
    {
        $data = $r->validate([
            'pekerja_id' => ['required', 'exists:mnr_pekerja,id'],
            'mulai'      => ['required', 'date'],
            'selesai'    => ['nullable', 'date', 'after_or_equal:mulai'],
        ]);

        ReguAnggota::create([
            'regu_id'    => $regu->id,
            'pekerja_id' => $data['pekerja_id'],
            'mulai'      => Waktu::tanggal($data['mulai']),
            'selesai'    => isset($data['selesai']) ? Waktu::tanggal($data['selesai']) : null,
        ]);

        return back()->with('sukses', 'Anggota ditambahkan ke regu.');
    }

    public function anggotaHapus(Regu $regu, ReguAnggota $anggota)
    {
        abort_unless((int) $anggota->regu_id === (int) $regu->id, 404);

        $anggota->delete();

        return back()->with('sukses', 'Anggota dikeluarkan dari regu.');
    }

    /* ═══════════════════ kebutuhan ═══════════════════ */

    public function kebutuhan(Request $r)
    {
        $tanggal = $r->query('tanggal') ?: Waktu::kini()->toDateString();

        $rencana = Kebutuhan::query()->pada($tanggal)->with(['blok', 'jabatan'])->get();

        /* Yang benar-benar terjadwal pada tanggal itu, dihitung per
           jabatan. Dihitung sebagai satu angka per site, kekurangan dua
           operator excavator tertutup kelebihan tiga admin — dan layar
           menyatakan "cukup" pada hari front berhenti menggali. */
        $aktual = Roster::query()->bekerja()->whereDate('tanggal', $tanggal)
            ->with('pekerja')->get()
            ->groupBy(fn (Roster $x) => ($x->blok_id ?: 0).'|'.($x->pekerja?->jabatan_id ?: 0))
            ->map->count();

        return Inertia::render('Hris/Roster/Kebutuhan', [
            'judul'    => 'Roster — Manpower Plan vs Actual',
            'subjudul' => 'Kebutuhan per area dan jabatan, dibandingkan dengan yang terjadwal.',

            'tanggal' => $tanggal,

            'baris' => $rencana->map(function (Kebutuhan $k) use ($aktual) {
                $n = (int) ($aktual[($k->blok_id ?: 0).'|'.($k->jabatan_id ?: 0)] ?? 0);

                return [
                    'id'       => $k->id,
                    'blok'     => $k->blok?->nama,
                    'jabatan'  => $k->jabatan?->nama,
                    'shift'    => $k->shift,
                    'butuh'    => $k->jumlah,
                    'ada'      => $n,
                    'selisih'  => $n - $k->jumlah,
                    'mulai'    => $k->mulai?->toDateString(),
                    'selesai'  => $k->selesai?->toDateString(),
                ];
            })->values(),

            'blok'    => Blok::pilihan(),
            'jabatan' => Jabatan::pilihan(),
            'SHIFT'   => Roster::SHIFT,
        ]);
    }

    public function kebutuhanSimpan(Request $r)
    {
        $data = $r->validate([
            'blok_id'    => ['nullable', 'exists:mnr_blok,id'],
            'jabatan_id' => ['nullable', 'exists:mnr_jabatan,id'],
            'mulai'      => ['required', 'date'],
            'selesai'    => ['nullable', 'date', 'after_or_equal:mulai'],
            'jumlah'     => ['required', 'integer', 'min:0', 'max:9999'],
            'shift'      => ['nullable', 'in:'.implode(',', array_keys(Roster::SHIFT))],
            'catatan'    => ['nullable', 'string', 'max:200'],
        ]);

        Kebutuhan::create($data + [
            'mulai'   => Waktu::tanggal($data['mulai']),
            'selesai' => isset($data['selesai']) ? Waktu::tanggal($data['selesai']) : null,
        ]);

        return back()->with('sukses', 'Kebutuhan tenaga kerja disimpan.');
    }

    public function kebutuhanHapus(Kebutuhan $kebutuhan)
    {
        $kebutuhan->delete();

        return back()->with('sukses', 'Kebutuhan dihapus.');
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $r): array
    {
        $dari = $r->query('dari')
            ? Waktu::tanggal($r->query('dari'))
            : Waktu::kini()->startOfMonth();

        $sampai = $r->query('sampai')
            ? Waktu::tanggal($r->query('sampai'))
            : $dari->copy()->endOfMonth();

        /* Rentangnya DIBATASI, dan bukan demi kerapian: kalender
           crew × tanggal menggambar satu sel per orang per hari, dan
           rentang setahun untuk regu berisi lima puluh orang
           menghasilkan delapan belas ribu sel dalam satu tanggapan.
           Yang terkirim bukan lagi halaman melainkan berkas. */
        if ($sampai->lt($dari)) $sampai = $dari->copy()->endOfMonth();
        if ($dari->diffInDays($sampai) > 92) $sampai = $dari->copy()->addDays(92);

        return [$dari, $sampai];
    }

    /** @return list<array{tanggal:string,hari:int,akhirPekan:bool}> */
    private function deretHari(Carbon $dari, Carbon $sampai): array
    {
        $hari = [];

        for ($t = $dari->copy(); $t->lte($sampai); $t->addDay()) {
            $hari[] = [
                'tanggal'    => $t->toDateString(),
                'hari'       => (int) $t->day,
                'akhirPekan' => $t->isWeekend(),
            ];
        }

        return $hari;
    }

    /** @return list<array<string,mixed>> */
    private function kalender(Regu $regu, Carbon $dari, Carbon $sampai): array
    {
        $anggota = $regu->anggota()
            ->with(['pekerja' => fn ($q) => $q->withoutGlobalScopes()->with('jabatan')])
            ->get();

        if ($anggota->isEmpty()) return [];

        $roster = Roster::withoutGlobalScopes()
            ->whereIn('pekerja_id', $anggota->pluck('pekerja_id'))
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->get()
            ->groupBy('pekerja_id');

        return $anggota->map(function (ReguAnggota $a) use ($roster, $dari, $sampai) {
            $milik = ($roster[$a->pekerja_id] ?? collect())
                ->keyBy(fn (Roster $r) => $r->tanggal->toDateString());

            $sel = [];

            for ($t = $dari->copy(); $t->lte($sampai); $t->addDay()) {
                $r = $milik->get($t->toDateString());

                $sel[] = $r === null ? null : [
                    'id'       => $r->id,
                    'keadaan'  => $r->keadaan,
                    'shift'    => $r->shift,
                    'jam'      => $r->jam,
                    'halangan' => $r->halangan,
                    'terbit'   => $r->terbit,
                ];
            }

            return [
                'pekerja_id' => $a->pekerja_id,
                'nama'       => $a->pekerja?->nama ?? '—',
                'jabatan'    => $a->pekerja?->jabatan?->nama,
                'sel'        => $sel,
            ];
        })->values()->all();
    }

    /** @return list<array<string,mixed>> */
    private function temuan(Regu $regu, Carbon $dari, Carbon $sampai): array
    {
        $anggota = $regu->anggota()->with('pekerja')->get();

        $semua = [];

        foreach ($anggota as $a) {
            $baris = Roster::withoutGlobalScopes()
                ->where('pekerja_id', $a->pekerja_id)
                ->antara(
                    $dari->copy()->subDays(Fatigue::MAKS_HARI_BERUNTUN + Fatigue::MIN_HARI_ISTIRAHAT)->toDateString(),
                    $sampai->copy()->addDays(Fatigue::MAKS_HARI_BERUNTUN)->toDateString(),
                )
                ->orderBy('tanggal')->get();

            foreach (Fatigue::periksa($baris) as $t) {
                $semua[] = $t + ['nama' => $a->pekerja?->nama ?? '—'];
            }
        }

        return $semua;
    }

    /** @return array<string,int> */
    private function ringkas(Regu $regu, Carbon $dari, Carbon $sampai): array
    {
        $baris = Roster::withoutGlobalScopes()
            ->where('regu_id', $regu->id)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->get();

        return [
            'baris'    => $baris->count(),
            'kerja'    => $baris->where('keadaan', 'kerja')->count(),
            'jam'      => (int) $baris->sum('jam'),
            'halangan' => $baris->whereNotNull('halangan')->count(),
            'terbit'   => $baris->where('terbit', true)->count(),
        ];
    }

    /**
     * Pola yang melanggar batasnya sendiri.
     *
     * @return list<string>
     */
    private function langgarPola(PolaRoster $p): array
    {
        $langgar = [];

        if ($p->jam > Fatigue::MAKS_JAM_HARI) {
            $langgar[] = Fatigue::PELANGGARAN['jam_harian'];
        }

        /* Yang dibandingkan HARI BERTURUT-TURUT TERPANJANG, bukan
           panjang periodenya: pola sepuluh minggu yang berlibur sekali
           seminggu bekerja paling lama enam hari beruntun. */
        if ($p->maksBeruntun() > Fatigue::MAKS_HARI_BERUNTUN) {
            $langgar[] = Fatigue::PELANGGARAN['hari_beruntun'];
        }

        /* Aturan istirahat lima hari melekat pada rezim periode kerja
           panjang — lihat Fatigue::AMBANG_PERIODE_PANJANG. Diterapkan
           ke semua pola, jadwal kantor 5:2 dan pola 4:1 ditandai
           melanggar padahal keduanya sah. */
        if ($p->maksBeruntun() > Fatigue::AMBANG_PERIODE_PANJANG
            && $p->hariLibur() > 0
            && $p->hariLibur() < Fatigue::MIN_HARI_ISTIRAHAT) {
            $langgar[] = Fatigue::PELANGGARAN['istirahat'];
        }

        /* Batas 154 jam diperiksa atas 14 hari PERTAMA periode kerjanya,
           bukan atas seluruh siklus: siklus yang lebih panjang daripada
           empat belas hari sudah tertangkap batas hari beruntun di
           atas, dan menjumlahkan seluruhnya akan melaporkan dua
           pelanggaran untuk satu sebab. */
        $jam14 = min($p->maksBeruntun(), 14) * $p->jam;

        if ($jam14 > Fatigue::MAKS_JAM_14_HARI) {
            $langgar[] = Fatigue::PELANGGARAN['jam_14_hari'];
        }

        return $langgar;
    }

    /** @return array<string,mixed> */
    private function validasiPola(Request $r): array
    {
        return $r->validate([
            'kode'       => ['required', 'string', 'max:20'],
            'nama'       => ['required', 'string', 'max:120'],
            'kerja'      => ['required', 'integer', 'min:1', 'max:365'],
            'libur'      => ['required', 'integer', 'min:0', 'max:365'],
            'satuan'     => ['required', 'in:'.implode(',', array_keys(PolaRoster::SATUAN))],
            'jam'        => ['required', 'integer', 'min:1', 'max:24'],
            'shift'      => ['required', 'in:'.implode(',', array_keys(PolaRoster::SHIFT))],

            /* Jam mulai shift dan toleransinya diatur DI SINI, bukan
               pada layar absensi. Keterlambatan dihitung terhadap
               keduanya; ditaruh di layar yang lain, yang mengubah pola
               kerja tidak pernah melihat bahwa ia sekaligus mengubah
               siapa yang tercatat terlambat. */
            'mulai_siang'     => ['nullable', 'date_format:H:i'],
            'mulai_malam'     => ['nullable', 'date_format:H:i'],
            'toleransi_menit' => ['nullable', 'integer', 'min:0', 'max:180'],

            'keterangan' => ['nullable', 'string', 'max:300'],
            'aktif'      => ['boolean'],
        ]);
    }

    /** @return array<string,mixed> */
    private function validasiRegu(Request $r): array
    {
        return $r->validate([
            'nama'           => ['required', 'string', 'max:80'],
            'pola_roster_id' => ['required', 'exists:hr_pola_roster,id'],
            'blok_id'        => ['nullable', 'exists:mnr_blok,id'],
            'mulai'          => ['required', 'date'],
            'shift'          => ['nullable', 'in:'.implode(',', array_keys(Roster::SHIFT))],
            'catatan'        => ['nullable', 'string', 'max:300'],
            'aktif'          => ['boolean'],
        ]);
    }
}
