<?php

namespace App\Http\Controllers;

use App\Models\Miners\{HasilMcu, Induksi, InduksiOrang, JenisUnit, KategoriPermit, Kendaraan,
    Mcu, McuOrang, McuRujukan, Pekerja, Permit, PermitBerkas, Simper, SimperAjuan,
    SimperAjuanUnit, SimperUnit, TipePermit};
use App\Support\Berkas;
use App\Support\Miners\{Acuan, Jalur, Keadaan};
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
    /* ═══════════════════ MCU ═══════════════════ */

    public function mcuIndex(Request $r)
    {
        $surat = Mcu::query()->with(['orang.hasil', 'orang.pekerja', 'alur'])
            ->when(trim((string) $r->query('cari')), fn ($q, $c) => $q->where(
                fn ($w) => $w->where('no_registrasi', 'like', "%{$c}%")
                    ->orWhere('kepada', 'like', "%{$c}%")))
            ->orderByDesc('tanggal')->get();

        return Inertia::render('Miners/Mcu', [
            'judul'    => 'Miners — MCU',
            'subjudul' => 'Surat pengajuan pemeriksaan kesehatan dan hasilnya.',

            'baris' => $surat->map(fn (Mcu $m) => [
                'id'      => $m->id,
                'nomor'   => $m->no_registrasi,
                'tanggal' => $m->tanggal?->toDateString(),
                'kepada'  => $m->kepada,
                'perihal' => $m->perihal,
                'status'  => $m->status,
                'orang'   => $m->orang->map(fn (McuOrang $o) => [
                    'id'      => $o->id,
                    'pekerja_id' => $o->pekerja_id,
                    'nama'    => $o->nama,
                    'nik'     => $o->nik,
                    'hasil_id'=> $o->hasil_id,
                    'hasil'   => $o->hasil?->nama,
                    'layak'   => $o->layak(),
                    'napza'   => $o->hasil_napza,
                    'periksa' => $o->tanggal_periksa?->toDateString(),
                    'sampai'  => $o->berlaku_sampai?->toDateString(),
                    'keadaan' => Keadaan::mcu($o),
                ]),
                'alur' => $this->alur($m),
            ]),

            'saring'   => ['cari' => $r->query('cari', '')],
            'STATUS'   => Mcu::STATUS,
            'KEADAAN'  => Keadaan::LABEL,
            'NADA'     => Keadaan::NADA,
            'hasilMcu' => HasilMcu::query()->terpakai()->get(['id', 'nama', 'layak']),
            'pekerja'  => Pekerja::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'nik']),
            'peranSaya'=> Jalur::peran($r->user()),
            'BULAN_BERLAKU' => McuOrang::BULAN_BERLAKU,
        ]);
    }

    public function mcuStore(Request $r)
    {
        $data = $r->validate([
            'no_registrasi' => ['nullable', 'string', 'max:40'],
            'tanggal'       => ['required', 'date'],
            'kepada'        => ['required', 'string', 'max:150'],
            'perihal'       => ['nullable', 'string'],
        ]);

        $mcu = Mcu::create($data + ['tanggal' => Waktu::tanggal($data['tanggal']), 'user_id' => $r->user()?->id]);
        $mcu->terbitkanAlur();

        return back()->with('sukses', 'Surat pengajuan MCU dibuat.');
    }

    public function mcuUpdate(Request $r, Mcu $mcu)
    {
        $data = $r->validate([
            'no_registrasi' => ['nullable', 'string', 'max:40'],
            'tanggal'       => ['required', 'date'],
            'kepada'        => ['required', 'string', 'max:150'],
            'perihal'       => ['nullable', 'string'],
            'catatan'       => ['nullable', 'string'],
        ]);

        $mcu->update($data + ['tanggal' => Waktu::tanggal($data['tanggal'])]);

        return back()->with('sukses', 'Surat pengajuan diperbarui.');
    }

    public function mcuDestroy(Mcu $mcu)
    {
        $this->buangAlur($mcu);
        $mcu->delete();

        return back()->with('sukses', 'Surat pengajuan dihapus.');
    }

    public function mcuTambahOrang(Request $r, Mcu $mcu)
    {
        $data = $r->validate(['pekerja_id' => ['required', 'exists:mnr_pekerja,id']]);

        $p = Pekerja::findOrFail($data['pekerja_id']);

        /* Identitasnya DISALIN, tidak dirujuk. Nama dan jabatan yang
           tercetak pada surat ke klinik adalah yang berlaku saat surat
           dibuat; dibaca dari master, surat tahun lalu berubah isinya
           begitu orangnya naik jabatan. */
        McuOrang::create([
            'mcu_id'        => $mcu->id,
            'pekerja_id'    => $p->id,
            'nama'          => $p->nama,
            'nik'           => $p->nik,
            'jabatan'       => $p->jabatan?->nama,
            'usia'          => $p->usia(),
            'departemen_id' => $p->departemen_id,
            'aktif'         => true,
        ]);

        return back()->with('sukses', $p->nama.' ditambahkan ke surat.');
    }

    public function mcuHapusOrang(Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $orang->delete();

        return back()->with('sukses', 'Nama dihapus dari surat.');
    }

    public function mcuHasil(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $data = $r->validate([
            'hasil_id'        => ['nullable', 'exists:mnr_hasil_mcu,id'],
            'tanggal_periksa' => ['nullable', 'date'],
            'hasil_napza'     => ['nullable', 'in:negatif,positif'],
            'catatan'         => ['nullable', 'string'],
        ]);

        $periksa = $data['tanggal_periksa'] ? Waktu::tanggal($data['tanggal_periksa']) : null;

        /* Masa berlakunya DIHITUNG dari tanggal periksa, bukan diisi
           tangan. Diisi tangan, dua orang yang diperiksa pada hari yang
           sama dapat berbeda masa berlakunya — dan yang lebih panjang
           tidak pernah dipertanyakan siapa pun. */
        $orang->update($data + [
            'tanggal_periksa' => $periksa,
            'berlaku_sampai'  => $periksa?->copy()->addMonths(McuOrang::BULAN_BERLAKU),
            'tanggal_berikut' => $periksa?->copy()->addMonths(McuOrang::BULAN_BERLAKU),
            'berkas_hasil'    => Berkas::simpan($r->file('berkas_hasil'), 'miners/mcu') ?? $orang->berkas_hasil,
            'berkas_rekomendasi' => Berkas::simpan($r->file('berkas_rekomendasi'), 'miners/mcu') ?? $orang->berkas_rekomendasi,
            'berkas_napza'    => Berkas::simpan($r->file('berkas_napza'), 'miners/mcu') ?? $orang->berkas_napza,
        ]);

        return back()->with('sukses', 'Hasil MCU '.$orang->nama.' disimpan.');
    }

    public function mcuRujukan(Request $r, Mcu $mcu, McuOrang $orang)
    {
        abort_unless((int) $orang->mcu_id === (int) $mcu->id, 404);

        $data = $r->validate([
            'tanggal_surat'  => ['required', 'date'],
            'dokter'         => ['required', 'string', 'max:150'],
            'poliklinik'     => ['nullable', 'string', 'max:150'],
            'rumah_sakit'    => ['nullable', 'string', 'max:150'],
            'diagnosis_awal' => ['nullable', 'string'],
            'keterangan'     => ['nullable', 'string'],
        ]);

        McuRujukan::create($data + [
            'mcu_orang_id'  => $orang->id,
            'tanggal_surat' => Waktu::tanggal($data['tanggal_surat']),
            'berkas'        => Berkas::simpan($r->file('berkas'), 'miners/rujukan'),
        ]);

        return back()->with('sukses', 'Rujukan dicatat.');
    }

    public function mcuTindak(Request $r, Mcu $mcu)
    {
        return $this->tindak($r, $mcu, 'Surat MCU');
    }

    /* ═══════════════════ INDUKSI ═══════════════════ */

    public function induksiIndex(Request $r)
    {
        $surat = Induksi::query()->with(['orang.pekerja', 'alur'])
            ->orderByDesc('tanggal')->get();

        return Inertia::render('Miners/Induksi', [
            'judul'    => 'Miners — Induksi',
            'subjudul' => 'Induksi keselamatan dan nilai post test-nya.',

            'baris' => $surat->map(fn (Induksi $i) => [
                'id'      => $i->id,
                'nomor'   => $i->no_registrasi,
                'tanggal' => $i->tanggal?->toDateString(),
                'perihal' => $i->perihal,
                'status'  => $i->status,
                'orang'   => $i->orang->map(fn (InduksiOrang $o) => [
                    'id'        => $o->id,
                    'pekerja_id'=> $o->pekerja_id,
                    'nama'      => $o->pekerja?->nama,
                    'nilai'     => $o->nilai,
                    'percobaan' => $o->percobaan,
                    'status'    => $o->status,
                    'lulus'     => $o->lulus(),
                    'bolehUlang'=> $o->bolehMengulang(),
                    'sampai'    => $o->berlaku_sampai?->toDateString(),
                    'keadaan'   => Keadaan::induksi($o),
                ]),
                'alur' => $this->alur($i),
            ]),

            'STATUS'       => Induksi::STATUS,
            'STATUS_ORANG' => InduksiOrang::STATUS,
            'KEADAAN'      => Keadaan::LABEL,
            'NADA'         => Keadaan::NADA,
            'pekerja'      => Pekerja::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'nik']),
            'peranSaya'    => Jalur::peran($r->user()),
            'NILAI_LULUS'  => InduksiOrang::NILAI_LULUS,
            'MAKS_PERCOBAAN' => InduksiOrang::MAKS_PERCOBAAN,
        ]);
    }

    public function induksiStore(Request $r)
    {
        $data = $r->validate([
            'no_registrasi' => ['nullable', 'string', 'max:40'],
            'tanggal'       => ['required', 'date'],
            'perihal'       => ['nullable', 'string'],
        ]);

        $induksi = Induksi::create($data + [
            'tanggal' => Waktu::tanggal($data['tanggal']),
            'user_id' => $r->user()?->id,
        ]);
        $induksi->terbitkanAlur();

        return back()->with('sukses', 'Jadwal induksi dibuat.');
    }

    public function induksiUpdate(Request $r, Induksi $induksi)
    {
        $data = $r->validate([
            'no_registrasi' => ['nullable', 'string', 'max:40'],
            'tanggal'       => ['required', 'date'],
            'perihal'       => ['nullable', 'string'],
        ]);

        $induksi->update($data + ['tanggal' => Waktu::tanggal($data['tanggal'])]);

        return back()->with('sukses', 'Jadwal induksi diperbarui.');
    }

    public function induksiDestroy(Induksi $induksi)
    {
        $this->buangAlur($induksi);
        $induksi->delete();

        return back()->with('sukses', 'Jadwal induksi dihapus.');
    }

    public function induksiTambahOrang(Request $r, Induksi $induksi)
    {
        $data = $r->validate(['pekerja_id' => ['required', 'exists:mnr_pekerja,id']]);

        $p = Pekerja::findOrFail($data['pekerja_id']);

        InduksiOrang::create([
            'induksi_id'      => $induksi->id,
            'pekerja_id'      => $p->id,
            'mcu_orang_id'    => $p->mcu()->first()?->id,
            'tanggal_induksi' => $induksi->tanggal,
            'percobaan'       => 1,
            'status'          => 'belum',
        ]);

        return back()->with('sukses', $p->nama.' didaftarkan ke induksi.');
    }

    public function induksiNilai(Request $r, Induksi $induksi, InduksiOrang $orang)
    {
        abort_unless((int) $orang->induksi_id === (int) $induksi->id, 404);

        $data = $r->validate([
            'nilai'   => ['required', 'integer', 'min:0', 'max:100'],
            'lokasi'  => ['nullable', 'string', 'max:150'],
            'catatan' => ['nullable', 'string'],
        ]);

        /* Percobaan bertambah tiap kali nilai dicatat ULANG, bukan tiap
           kali baris ini disimpan. Tanpa pembedaan itu, memperbaiki
           salah ketik pada nilai menghabiskan jatah remidi orangnya. */
        $percobaan = $orang->nilai === null ? $orang->percobaan : $orang->percobaan + 1;

        $tanggal = $orang->tanggal_induksi ?? $induksi->tanggal;

        $orang->update($data + [
            'percobaan'      => $percobaan,
            'status'         => InduksiOrang::statusDari($data['nilai'], $percobaan),
            'berlaku_sampai' => $data['nilai'] >= InduksiOrang::NILAI_LULUS && $tanggal
                ? $tanggal->copy()->addMonths(InduksiOrang::BULAN_BERLAKU)
                : null,
            'berkas_sertifikat' => Berkas::simpan($r->file('berkas_sertifikat'), 'miners/induksi') ?? $orang->berkas_sertifikat,
            'berkas_hadir'      => Berkas::simpan($r->file('berkas_hadir'), 'miners/induksi') ?? $orang->berkas_hadir,
        ]);

        return back()->with('sukses', 'Nilai induksi disimpan.');
    }

    public function induksiHapusOrang(Induksi $induksi, InduksiOrang $orang)
    {
        abort_unless((int) $orang->induksi_id === (int) $induksi->id, 404);

        $orang->delete();

        return back()->with('sukses', 'Peserta dihapus.');
    }

    public function induksiTindak(Request $r, Induksi $induksi)
    {
        return $this->tindak($r, $induksi, 'Induksi');
    }

    /* ═══════════════════ MINE PERMIT ═══════════════════ */

    public function permitIndex(Request $r)
    {
        $permit = Permit::query()
            ->with(['pekerja', 'tipe', 'kategori', 'mcuOrang', 'induksiOrang', 'berkas', 'alur', 'simper'])
            ->when(trim((string) $r->query('cari')), fn ($q, $c) => $q->where(
                fn ($w) => $w->where('no_registrasi', 'like', "%{$c}%")
                    ->orWhereHas('pekerja', fn ($p) => $p->where('nama', 'like', "%{$c}%"))))
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal')->get();

        return Inertia::render('Miners/Permit', [
            'judul'    => 'Miners — Mine Permit',
            'subjudul' => 'Kartu masuk tambang, beserta MCU dan induksi yang mendasarinya.',

            'baris' => $permit->map(fn (Permit $p) => [
                'id'       => $p->id,
                'pekerja_id' => $p->pekerja_id,
                'pekerja'  => $p->pekerja?->nama,
                'nomor'    => $p->no_registrasi,
                'tanggal'  => $p->tanggal?->toDateString(),
                'tipe'     => $p->tipe?->nama,
                'tipe_id'  => $p->tipe_permit_id,
                'kategori' => $p->kategori?->nama,
                'cakupan'  => $p->cakupan_area,
                'warna'    => $p->kode_warna,
                'status'   => $p->status,
                'sampai'   => $p->berlaku_sampai?->toDateString(),
                'efektif'  => $p->habisEfektif()?->toDateString(),
                'sumber'   => $p->sumber_berlaku,
                'sisa'     => $p->sisaHari(),
                'gugurMcu' => $p->gugurKarenaMcu(),
                'mcu'      => $p->mcuOrang?->berlaku_sampai?->toDateString(),
                'simper'   => $p->simper->count(),
                'berkas'   => $p->berkas->map(fn (PermitBerkas $b) => [
                    'id' => $b->id, 'jenis' => $b->jenis, 'catatan' => $b->catatan,
                    'ada' => $b->berkas !== null,
                ]),
                'keadaan'  => Keadaan::permit($p),
                'alur'     => $this->alur($p),
            ]),

            'saring'   => ['cari' => $r->query('cari', ''), 'status' => $r->query('status', '')],
            'STATUS'   => Permit::STATUS,
            'CAKUPAN'  => Acuan::ZONA_AKSES,
            'WARNA'    => Acuan::WARNA_KARTU,
            'KEADAAN'  => Keadaan::LABEL,
            'NADA'     => Keadaan::NADA,
            'tipe'     => TipePermit::query()->terpakai()->get(['id', 'nama', 'hari_berlaku']),
            'kategori' => KategoriPermit::query()->orderBy('nama')->get(['id', 'tipe_permit_id', 'nama']),
            'pekerja'  => Pekerja::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'nik']),
            'peranSaya'=> Jalur::peran($r->user()),
            'berkasWajib' => Acuan::berkasWajib('permit_baru'),
        ]);
    }

    public function permitStore(Request $r)
    {
        $data = $this->validasiPermit($r);

        $pekerja = Pekerja::findOrFail($data['pekerja_id']);
        $tipe    = TipePermit::find($data['tipe_permit_id'] ?? null);
        $terbit  = Waktu::tanggal($data['tanggal']);

        [$habis, $sumber] = Permit::hitungBerlaku($tipe, $terbit);

        /* MCU dan induksi terakhir DILEKATKAN saat kartu dibuat, bukan
           dicari ulang saat dibaca. Dicari ulang, kartu tahun lalu akan
           menunjuk MCU tahun ini begitu yang baru terbit — dan
           pertanyaan "hasil MCU mana yang menjadi dasar kartu ini"
           kehilangan jawabannya justru pada kartu yang dipersoalkan. */
        $permit = Permit::create($data + [
            'tanggal'          => $terbit,
            'mcu_orang_id'     => $pekerja->mcu()->first()?->id,
            'induksi_orang_id' => $pekerja->induksi()->first()?->id,
            'berlaku_sampai'   => $habis,
            'sumber_berlaku'   => $sumber,
            'user_id'          => $r->user()?->id,
        ]);

        $permit->terbitkanAlur();

        return back()->with('sukses', 'Mine Permit dibuat sebagai draf.');
    }

    public function permitUpdate(Request $r, Permit $permit)
    {
        $data = $this->validasiPermit($r);

        $tipe   = TipePermit::find($data['tipe_permit_id'] ?? null);
        $terbit = Waktu::tanggal($data['tanggal']);

        [$habis, $sumber] = Permit::hitungBerlaku($tipe, $terbit);

        $permit->update($data + [
            'tanggal'        => $terbit,
            'berlaku_sampai' => $habis,
            'sumber_berlaku' => $sumber,
        ]);

        return back()->with('sukses', 'Mine Permit diperbarui.');
    }

    public function permitDestroy(Permit $permit)
    {
        $this->buangAlur($permit);
        $permit->delete();

        return back()->with('sukses', 'Mine Permit dihapus.');
    }

    public function permitBerkas(Request $r, Permit $permit)
    {
        $data = $r->validate([
            'jenis'   => ['required', 'string', 'max:100'],
            'nomor'   => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
            'berkas'  => ['nullable', 'file', 'max:'.Berkas::MAKS_DOKUMEN_KB],
        ]);

        PermitBerkas::updateOrCreate(
            ['permit_id' => $permit->id, 'jenis' => $data['jenis']],
            [
                'nomor'   => $data['nomor'] ?? null,
                'tanggal' => isset($data['tanggal']) ? Waktu::tanggal($data['tanggal']) : null,
                'catatan' => $data['catatan'] ?? null,
                'berkas'  => Berkas::simpan($r->file('berkas'), 'miners/permit'),
            ],
        );

        return back()->with('sukses', 'Lampiran disimpan.');
    }

    public function permitCabut(Request $r, Permit $permit)
    {
        $data = $r->validate(['alasan_cabut' => ['required', 'string', 'max:200']]);

        $permit->update($data + [
            'status'        => 'dicabut',
            'tanggal_cabut' => Waktu::kini()->startOfDay(),
        ]);

        return back()->with('sukses', 'Mine Permit dicabut.');
    }

    public function permitTindak(Request $r, Permit $permit)
    {
        return $this->tindak($r, $permit, 'Mine Permit');
    }

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

        return back()->with('sukses', $sebutan.' — '.match ($data['keadaan']) {
            'setuju'       => 'disetujui.',
            'tolak'        => 'ditolak.',
            default        => 'dikembalikan ke pengaju.',
        });
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
    private function alur(object $dokumen): array
    {
        return $dokumen->alur->map(fn ($a) => [
            'urutan'  => $a->urutan,
            'peran'   => $a->peran,
            'label'   => \App\Models\Miners\Alur::PERAN[$a->peran] ?? $a->peran,
            'keadaan' => $a->keadaan,
            'pada'    => $a->bertindak_pada ? Waktu::lokal($a->bertindak_pada)->toDateTimeString() : null,
            'catatan' => $a->catatan,
        ])->all();
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
    private function validasiPermit(Request $r): array
    {
        return $r->validate([
            'pekerja_id'         => ['required', 'exists:mnr_pekerja,id'],
            'no_registrasi'      => ['nullable', 'string', 'max:40'],
            'tanggal'            => ['required', 'date'],
            'tipe_permit_id'     => ['nullable', 'exists:mnr_tipe_permit,id'],
            'kategori_permit_id' => ['nullable', 'exists:mnr_kategori_permit,id'],
            'cakupan_area'       => ['nullable', 'in:'.implode(',', array_keys(Acuan::ZONA_AKSES))],
            'kode_warna'         => ['nullable', 'in:'.implode(',', array_keys(Acuan::WARNA_KARTU))],
            'kontraktor_id'      => ['nullable', 'exists:companies,id'],
        ]);
    }

    /** @return array<string,mixed> */
    private function validasiSimper(Request $r, ?Simper $simper = null): array
    {
        return $r->validate([
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
        ]);
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
