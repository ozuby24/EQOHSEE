<?php

use App\Support\Smkp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema, Storage};

/**
 * Memindahkan data audit SMKP lama EQOHSEE ke bentuk modul Aurorasys
 * (keputusan porting 7 Nov 2026: "ganti total persis Aurorasys", data lama
 * dipindahkan, TIDAK ADA YANG HILANG).
 *
 * Setiap baris yang diubah atau dibuang disalin utuh lebih dulu ke tabel
 * `smkp_arsip_eqohsee` (kolom `asli`), dan ruas yang tidak punya padanan
 * dicatat terpisah (`tanpa_padanan`). Baris yang DIBUAT migrasi ini dan
 * berkas yang disalin juga dicatat, sehingga down() dapat membalik semuanya.
 *
 * ── Yang dipindahkan ──
 *
 * Pengguna   audit_role kosong pada akun non-admin → 'auditor'. Di EQOHSEE
 *            setiap pengguna terverifikasi dapat mengubah audit SMKP
 *            perusahaannya; tanpa ini seluruhnya menjadi auditi (baca saja)
 *            dan terkunci. Nilai 'company' ("Perusahaan" di Admin) yang
 *            sudah ditetapkan administrator dibiarkan: artinya auditi.
 * Perusahaan kelas risiko 'Menengah' → 'Sedang' (daftar Aurorasys).
 * Audit      - status ⇔ tahap: Selesai melekat pada Pelaporan (tahap 3).
 *            - perusahaan kosong: diisi perusahaan pembuatnya, atau satu-
 *              satunya perusahaan; bila tidak dapat dipastikan dibiarkan
 *              kosong (hanya terlihat administrator) dan dicatat.
 *            - hasil: nilai maksimum enam butir dikoreksi ke Kepdirjen
 *              (III.2.1–III.2.3, IV.5.1 4→2; III.12.2, III.12.4 4→3) —
 *              nilai angkanya ditandai `maks_lama` = 4 (disetarakan sampai
 *              dinilai ulang). III.12.1 (v 3/4), III.12.3 (v 2/3), dan
 *              IV.3.10 (v 4) ditandai `koreksi` = nomor Daftar Koreksi.
 *              III.13 sudah bernilai maksimum 4 di EQOHSEE: tidak ditandai
 *              (riwayat migrasi Aurorasys untuk III.13 tidak diputar ulang).
 *            - permulaan: kunci yang sama disalin; kelas risiko Menengah →
 *              Sedang; faktor penyesuaian = jawaban yang BERLAKU di EQOHSEE
 *              (hitungan kinerja mengalahkan centang), 'kptk' → 'pak';
 *              susunan tim → Rencana Audit (tugas) + jumlah auditor;
 *              hari kerja hasil tabel EQOHSEE → mandays_dasar, penyesuaian
 *              0 (hari per auditor sama dengan durasi lama). Rata-rata
 *              nasional dan faktor pengurang → arsip.
 *            - audit di tahap Pelaporan: hari kerja dibekukan
 *              (mandays_tetap, aturan 'eqohsee') persis angka lama.
 *            - rencana: kunci yang sama disalin; pengesah PTL → arsip (dan
 *              mengisi KTT bila KTT kosong); matriks metode & sampel per
 *              butir → metode_sampel per sub-elemen; butir "tidak berlaku"
 *              → teks pengecualian.
 *            - laporan: domisili → legalitas; lingkup → daftar area;
 *              peralatan & distribusi diseragamkan; kesimpulan, ringkasan
 *              per elemen, dan lampiran → arsip.
 *            - OFI (smkp_ofi) dan temuan 'obs' (observasi) → Formulir
 *              Rekomendasi & OFI (kolom rekomendasi).
 * Temuan     - nomor dibangkitkan urut per audit: Kritikal/Mayor/Minor,
 *              urut sub-elemen acuan (KODE-MYR-01-2025 …).
 *            - foto penutupan → kiriman tindak lanjut berlampiran; foto
 *              saat temuan (open) → bukti audit butirnya (diterima).
 *            - respon manajemen, verifikasi oleh/pada → arsip.
 * Bukti      kolom sudah diseragamkan migrasi sebelumnya; bukti lama
 *            dianggap diterima (diverifikasi pengunggahnya, pada waktu
 *            unggah). Berkasnya DISALIN ke bukti-audit/{audit}/ — berkas
 *            asli di smkp/{audit}/ tidak dihapus.
 * Daftar hadir tanda tangan → arsip.
 *
 * Sesudah seluruhnya tersalin: kolom sampel, respon_*, foto_*,
 * verifikasi_oleh/pada, tanda_tangan dan tabel smkp_ofi dibuang, lalu
 * nomor temuan dijadikan unik per audit.
 */
return new class extends Migration
{
    private const PENANDA = 'pindah_data_smkp_aurorasys';

    /** Nilai maksimum lama (skala EQOHSEE) butir yang dikoreksi. */
    private const MAKS_LAMA = [
        'III.2.1' => 4, 'III.2.2' => 4, 'III.2.3' => 4,
        'III.12.2' => 4, 'III.12.4' => 4, 'IV.5.1' => 4,
    ];

    /** Kode butir → [nomor Daftar Koreksi, nilai yang deskripsinya berubah]. */
    private const KOREKSI = [
        'III.12.1' => [1, [3, 4]],
        'III.12.3' => [2, [2, 3]],
        'IV.3.10'  => [4, [4]],
    ];

    /** Tabel hari kerja EQOHSEE: [pekerja dari, sampai, Tinggi, Menengah, Rendah]. */
    private const MANDAYS_TABLE = [
        [1, 5, 3, 2, 2],          [6, 10, 4, 3, 2],         [11, 15, 5, 4, 3],
        [16, 25, 6, 5, 4],        [26, 45, 8, 6, 4],        [46, 65, 9, 7, 5],
        [66, 85, 10, 8, 6],       [86, 125, 11, 9, 7],      [126, 175, 12, 10, 8],
        [176, 275, 13, 11, 9],    [276, 425, 15, 12, 10],   [426, 625, 16, 13, 11],
        [626, 875, 17, 14, 12],   [876, 1175, 18, 15, 13],  [1176, 1550, 19, 16, 14],
        [1551, 2025, 20, 17, 15], [2026, 3450, 21, 18, 16], [3451, 5450, 22, 19, 17],
        [5451, 10700, 23, 20, 18], [10701, 999999, 25, 22, 20],
    ];
    private const KOLOM_RISIKO = ['Tinggi' => 2, 'Menengah' => 3, 'Rendah' => 4];

    private const METODE = [
        'dokumen'   => 'Tinjauan Dokumen dan Rekaman',
        'wawancara' => 'Wawancara',
        'observasi' => 'Observasi',
    ];

    private const KUNCI_PERMULAAN = ['tanggal_kontak', 'media_kontak', 'wakil_auditi', 'jabatan_wakil', 'surat_nomor', 'surat_tanggal',
        'izin', 'alamat', 'periode', 'sifat_kontak', 'tanggal_kelayakan', 'layak', 'bukti_integritas', 'tanggal_ba', 'alamat_site',
        'sk_auditor', 'form_rapat', 'jumlah_pekerja', 'kelas_risiko', 'mandays_dasar', 'jumlah_auditor', 'penyesuaian', 'kesimpulan',
        'kelayakan', 'faktor', 'persiapan'];

    private const KUNCI_RENCANA = ['nomor', 'jenis_audit', 'alamat', 'tujuan', 'kriteria', 'pengecualian', 'dokumentasi', 'distribusi',
        'metode_sampel', 'ruang_lingkup', 'tanggal_mulai', 'tanggal_selesai', 'sumberdaya', 'metode', 'sampel', 'susunan', 'tugas', 'pengesahan'];

    /** @var array<string,int> ringkasan untuk keluaran konsol */
    private array $hitung = [];

    public function up(): void
    {
        if (!$this->sudahDipindah()) {
            DB::transaction(fn () => $this->pindah());
        }

        $this->buangLama();
    }

    /* ═════════════════════ pemindahan ═════════════════════ */

    private function sudahDipindah(): bool
    {
        return DB::table('smkp_arsip_eqohsee')->where('jenis', 'catatan')->where('tabel', 'migrasi')
            ->where('catatan', self::PENANDA)->exists();
    }

    private function pindah(): void
    {
        $this->peranPengguna();
        $this->kelasRisikoPerusahaan();

        $ofi = Schema::hasTable('smkp_ofi')
            ? DB::table('smkp_ofi')->orderBy('id')->get()->groupBy('audit_id')
            : collect();

        foreach (DB::table('smkp_audits')->orderBy('id')->get() as $a) {
            $this->audit($a, $ofi[$a->id] ?? collect());
        }

        $this->bukti();

        if (Schema::hasColumn('smkp_attendees', 'tanda_tangan')) {
            foreach (DB::table('smkp_attendees')->whereNotNull('tanda_tangan')->where('tanda_tangan', '<>', '')->get() as $h) {
                $this->arsip('smkp_attendees', $h->id, $h->audit_id, (array) $h, ['tanda_tangan' => $h->tanda_tangan]);
                $this->tambah('hadir.tanda_tangan');
            }
        }

        DB::table('smkp_arsip_eqohsee')->insert([
            'tabel' => 'migrasi', 'jenis' => 'catatan', 'catatan' => self::PENANDA,
            'tanpa_padanan' => $this->json($this->hitung), 'created_at' => now(), 'updated_at' => now(),
        ]);

        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            foreach ($this->hitung as $k => $n) echo "  smkp: {$k} = {$n}\n";
        }
    }

    /** Akun non-admin tanpa peran audit → auditor (lihat docblock kelas). */
    private function peranPengguna(): void
    {
        $ubah = DB::table('users')->where('is_admin', false)->whereNull('audit_role')->get(['id', 'audit_role']);
        foreach ($ubah as $u) {
            $this->arsip('users', $u->id, null, ['audit_role' => $u->audit_role], [], 'peran audit diisi auditor');
        }
        DB::table('users')->whereIn('id', $ubah->pluck('id'))->update(['audit_role' => 'auditor']);
        $this->hitung['pengguna.menjadi_auditor'] = $ubah->count();
        $this->hitung['pengguna.peran_company_tetap_auditi'] = DB::table('users')->where('is_admin', false)->where('audit_role', 'company')->count();
        $this->hitung['pengguna.admin'] = DB::table('users')->where('is_admin', true)->count();
    }

    private function kelasRisikoPerusahaan(): void
    {
        foreach (DB::table('companies')->where('risk_class', 'Menengah')->get(['id', 'risk_class']) as $c) {
            $this->arsip('companies', $c->id, null, ['risk_class' => $c->risk_class], [], 'kelas risiko Menengah → Sedang');
            DB::table('companies')->where('id', $c->id)->update(['risk_class' => 'Sedang']);
            $this->tambah('perusahaan.kelas_risiko');
        }
    }

    /** Satu audit beserta temuan, OFI, dan isian JSON-nya. */
    private function audit(object $a, $ofi): void
    {
        $asli = (array) $a;
        $sisa = [];
        $ubah = [];
        $this->tambah('audit');

        /* ── perusahaan ── */
        if ($a->company_id === null) {
            $calon = $a->user_id ? DB::table('users')->where('id', $a->user_id)->value('company_id') : null;
            if (!$calon && DB::table('companies')->count() === 1) $calon = DB::table('companies')->value('id');
            $bentrok = $calon && DB::table('smkp_audits')->where('company_id', $calon)->where('tahun', $a->tahun)->exists();
            if ($calon && !$bentrok) {
                $ubah['company_id'] = $calon;
                $this->tambah('audit.perusahaan_dipetakan');
            } else {
                $sisa['catatan_perusahaan'] = 'Audit tanpa perusahaan — perlu dipetakan administrator (hanya terlihat administrator).';
                $this->tambah('audit.tanpa_perusahaan');
            }
        }
        $company = DB::table('companies')->where('id', $ubah['company_id'] ?? $a->company_id)->first();

        /* ── status ⇔ tahap ── */
        $tahap = (int) $a->tahap;
        $status = (string) $a->status;
        if ($status === 'selesai' && $tahap !== 3) { $tahap = 3; $ubah['tahap'] = 3; }
        if ($tahap === 3 && $status !== 'selesai') { $status = 'selesai'; $ubah['status'] = 'selesai'; }

        /* ── hasil ── */
        $hasil = $this->dekode($a->hasil);
        $hasilBaru = $this->tandaiHasil($hasil);
        if ($hasilBaru !== $hasil) $ubah['hasil'] = $this->json($hasilBaru);

        /* ── permulaan & rencana ── */
        $p = $this->dekode($a->permulaan);
        $r = $this->dekode($a->rencana);
        $kinerja = $this->dekode($a->kinerja);
        $sampel = Schema::hasColumn('smkp_audits', 'sampel') ? $this->dekode($a->sampel ?? null) : [];

        [$pBaru, $sisaP, $md] = $this->permulaan($p, $kinerja, $company, $tahap, $status);
        [$rBaru, $sisaR] = $this->rencana($r, $p, $sampel, $this->dekode($a->auditor));
        if ($sisaP) $sisa['permulaan'] = $sisaP;
        if ($sisaR) $sisa['rencana'] = $sisaR;
        if ($sampel) $sisa['sampel'] = $sampel;
        if ($pBaru !== $p) $ubah['permulaan'] = $pBaru ? $this->json($pBaru) : null;
        if ($rBaru !== $r) $ubah['rencana'] = $rBaru ? $this->json($rBaru) : null;

        if ($tahap === 3 && $md !== null && empty($this->dekode($a->mandays_tetap ?? null))) {
            $ubah['mandays_tetap'] = $this->json($md['tetap']);
            $this->tambah('audit.hari_kerja_dibekukan');
        }

        /* ── laporan ── */
        $l = $this->dekode($a->laporan);
        if ($l) {
            [$lBaru, $sisaL] = $this->laporan($l);
            if ($sisaL) $sisa['laporan'] = $sisaL;
            $ubah['laporan'] = $lBaru ? $this->json($lBaru) : null;
        }

        /* ── temuan, observasi, OFI ── */
        $rekomendasi = $this->dekode($a->rekomendasi ?? null);
        foreach ($ofi as $o) {
            $baris = $this->barisRekomendasi((string) $o->kode, (string) $o->lingkup === 'subsub', array_filter([
                trim((string) $o->uraian),
                trim((string) $o->saran) !== '' ? 'Saran: '.trim((string) $o->saran) : null,
                trim((string) $o->penanggung_jawab) !== '' ? 'Penanggung jawab: '.trim((string) $o->penanggung_jawab) : null,
                $o->target ? 'Target: '.Carbon::parse($o->target)->translatedFormat('d F Y') : null,
                'Status: '.(['terbuka' => 'Terbuka', 'ditindaklanjuti' => 'Ditindaklanjuti', 'ditutup' => 'Ditutup'][$o->status] ?? (string) $o->status),
            ]));
            $this->arsip('smkp_ofi', $o->id, $a->id, (array) $o, $baris ? [] : ['ofi' => (array) $o], $baris ? 'OFI → rekomendasi' : 'OFI berkode tidak dikenal — hanya diarsipkan');
            if ($baris) $rekomendasi[] = $baris;
            $this->tambah('ofi');
        }

        $temuan = DB::table('smkp_findings')->where('audit_id', $a->id)->orderBy('id')->get();
        foreach ($temuan->where('jenis', 'obs') as $t) {
            $baris = $this->barisRekomendasi((string) $t->kode_kriteria, true, array_filter([
                'Observasi: '.trim((string) $t->uraian),
                trim((string) $t->akar_masalah) !== '' ? 'Catatan: '.trim((string) $t->akar_masalah) : null,
                trim((string) $t->tindakan) !== '' ? 'Tindakan: '.trim((string) $t->tindakan) : null,
                trim((string) $t->penanggung_jawab) !== '' ? 'Penanggung jawab: '.trim((string) $t->penanggung_jawab) : null,
                $t->target_selesai ? 'Target: '.Carbon::parse($t->target_selesai)->translatedFormat('d F Y') : null,
                'Status: '.$t->status,
            ]));
            $this->arsip('smkp_findings', $t->id, $a->id, (array) $t, $this->tanpaPadananTemuan($t) + ($baris ? [] : ['observasi' => (array) $t]),
                $baris ? 'temuan observasi → rekomendasi' : 'temuan observasi berkode tidak dikenal — hanya diarsipkan');
            if ($baris) $rekomendasi[] = $baris;
            DB::table('smkp_findings')->where('id', $t->id)->delete();
            $this->tambah('temuan.observasi_ke_rekomendasi');
        }
        if ($ofi->isNotEmpty() || $temuan->where('jenis', 'obs')->isNotEmpty()) {
            $ubah['rekomendasi'] = $rekomendasi ? $this->json(array_values($rekomendasi)) : null;
        }

        $this->temuan($a, $company, $temuan->where('jenis', '<>', 'obs')->values());

        /* ── simpan ── */
        $this->arsip('smkp_audits', $a->id, $a->id, $asli, $sisa);
        if ($ubah) {
            DB::table('smkp_audits')->where('id', $a->id)->update($ubah + ['updated_at' => $a->updated_at]);
        }
    }

    /** @return array<string,mixed> */
    private function tandaiHasil(array $hasil): array
    {
        foreach (self::MAKS_LAMA as $kode => $lama) {
            $b = $hasil[$kode] ?? null;
            if (is_array($b) && is_numeric($b['v'] ?? null) && !isset($b['maks_lama'])) {
                $hasil[$kode]['maks_lama'] = $lama;
                $this->tambah('hasil.maks_lama');
            }
        }
        foreach (self::KOREKSI as $kode => [$no, $nilai]) {
            $b = $hasil[$kode] ?? null;
            if (is_array($b) && is_numeric($b['v'] ?? null) && in_array((int) $b['v'], $nilai, true) && !isset($b['koreksi'])) {
                $hasil[$kode]['koreksi'] = $no;
                $this->tambah('hasil.koreksi');
            }
        }

        return $hasil;
    }

    /**
     * @return array{0:array,1:array,2:?array} [permulaan baru, tanpa padanan, hari kerja lama]
     */
    private function permulaan(array $p, array $kinerja, ?object $c, int $tahap, string $status): array
    {
        $baru = [];
        foreach (self::KUNCI_PERMULAAN as $k) {
            if (array_key_exists($k, $p)) $baru[$k] = $p[$k];
        }
        $sisa = array_diff_key($p, array_flip(self::KUNCI_PERMULAAN));
        unset($sisa['tim']); // dipindah ke Rencana Audit

        if (($baru['kelas_risiko'] ?? '') === 'Menengah') $baru['kelas_risiko'] = 'Sedang';

        // Faktor penyesuaian: jawaban yang berlaku di EQOHSEE, 'kptk' menjadi 'pak'.
        $berlaku = $this->faktorBerlaku($p, $kinerja);
        if (isset($p['faktor']) || array_filter($berlaku)) {
            $faktor = [];
            foreach (['jarak', 'metode', 'pengolahan', 'kecelakaan', 'penyakit', 'berbahaya'] as $k) $faktor[$k] = $berlaku[$k] ?? false;
            $faktor['pak'] = $berlaku['kptk'] ?? false;
            $baru['faktor'] = $faktor;
        }

        $tim = $this->timAuditor($p);
        if ($tim) $baru['jumlah_auditor'] = (string) count($tim);

        // Hari kerja: dibawa bila auditnya memang sudah dikerjakan.
        $diisi = (int) ($p['jumlah_pekerja'] ?? 0) > 0 || trim((string) ($p['kelas_risiko'] ?? '')) !== '' || $tim
            || array_filter((array) ($p['faktor'] ?? [])) || array_filter((array) ($p['pengurang'] ?? []));
        $md = null;
        if ($diisi || $tahap >= 2 || $status === 'selesai') {
            $md = $this->mandaysLama($p, $kinerja, $c);
            $baru['mandays_dasar']  = (string) $md['total'];
            $baru['jumlah_auditor'] = (string) $md['auditor'];
            $baru['penyesuaian']    = '0';
            if ((int) ($baru['jumlah_pekerja'] ?? 0) <= 0 && $md['pekerja'] > 0) $baru['jumlah_pekerja'] = (string) $md['pekerja'];
            if (trim((string) ($baru['kelas_risiko'] ?? '')) === '') $baru['kelas_risiko'] = $md['kelas'] === 'Menengah' ? 'Sedang' : $md['kelas'];
            $sisa['hari_kerja_lama'] = $md;

            $dur = (float) $md['durasi'];
            $md['tetap'] = [
                'dasar' => (float) $md['total'], 'auditor' => $md['auditor'], 'per_auditor' => $dur,
                'kondisi' => $md['penambah'], 'maks_penyesuaian' => 0.0, 'usul_penyesuaian' => 0.0, 'penyesuaian' => 0.0,
                'persen_penyesuaian' => 0.0, 'total' => $dur, 'tahap1' => (float) $md['tahap1'], 'tahap2' => (float) $md['tahap2'],
                'aturan' => 'eqohsee',
            ];
        }

        return [$baru, array_filter($sisa, fn ($v) => $v !== null && $v !== '' && $v !== []), $md];
    }

    /** @return array{0:array,1:array} */
    private function rencana(array $r, array $p, array $sampel, array $auditorLama): array
    {
        $baru = [];
        foreach (self::KUNCI_RENCANA as $k) {
            if (array_key_exists($k, $r)) $baru[$k] = $r[$k];
        }
        $sisa = array_diff_key($r, array_flip(self::KUNCI_RENCANA));

        // Pengesah PTL (pengolahan/pemurnian) tidak ada di Aurorasys.
        $pengesahan = (array) ($r['pengesahan'] ?? []);
        if (isset($pengesahan['ptl'])) {
            $sisa['pengesahan_ptl'] = $pengesahan['ptl'];
            $ptl = (array) $pengesahan['ptl'];
            if (trim((string) ($ptl['nama'] ?? '')) !== '' && trim((string) ($pengesahan['ktt']['nama'] ?? '')) === '') {
                $pengesahan['ktt'] = [
                    'nama'    => trim((string) $ptl['nama']),
                    'jabatan' => trim((string) ($ptl['jabatan'] ?? '')) ?: 'Penanggung Jawab Teknik dan Lingkungan',
                    'tanggal' => (string) ($ptl['tanggal'] ?? ''),
                ];
            }
            unset($pengesahan['ptl']);
            $baru['pengesahan'] = $pengesahan;
        }

        // Tim: susunan Tahap I (urutan menentukan ketua) + pembagian tugas.
        $tim = $this->timAuditor($p);
        if ($tim || (!isset($r['tugas']) && $auditorLama)) {
            $baru['tugas'] = $this->tugas($tim, (array) ($r['tugas'] ?? []), $auditorLama);
        }

        // Matriks metode & sampel per butir → per sub-elemen.
        if ($sampel) {
            [$ms, $kecuali, $panjang] = $this->metodeSampel($sampel);
            $baru['metode_sampel'] = $ms + (array) ($baru['metode_sampel'] ?? []);
            if ($kecuali) {
                $ada = trim((string) ($baru['pengecualian'] ?? ''));
                $baru['pengecualian'] = trim($ada."\n".implode("\n", $kecuali));
            }
            if ($panjang) $sisa['metode_sampel_terpotong'] = $panjang;
        }

        return [$baru, array_filter($sisa, fn ($v) => $v !== null && $v !== '' && $v !== [])];
    }

    /** Sama dengan SmkpAudit::tim() lama EQOHSEE. */
    private function tugas(array $tim, array $tugas, array $auditorLama): array
    {
        $per = [];
        foreach ($tugas as $b) {
            $n = trim((string) ($b['nama'] ?? ''));
            if ($n !== '') $per[$n] = $b;
        }

        $out = [];
        foreach ($tim as $i => $nama) {
            $b = $per[$nama] ?? [];
            unset($per[$nama]);
            $out[] = [
                'nama' => $nama, 'peran' => trim((string) ($b['peran'] ?? '')) ?: ($i === 0 ? 'Lead Auditor' : 'Auditor'),
                'registrasi' => (string) ($b['registrasi'] ?? ''), 'lingkup' => (string) ($b['lingkup'] ?? ''),
            ];
        }

        $sisa = array_values($per);
        if (!$out && !$sisa) {
            $sisa = array_values(array_filter($auditorLama, fn ($x) => is_array($x) && trim((string) ($x['nama'] ?? '')) !== ''));
        }
        foreach ($sisa as $b) {
            $out[] = [
                'nama' => trim((string) ($b['nama'] ?? '')), 'peran' => trim((string) ($b['peran'] ?? '')) ?: (count($out) === 0 ? 'Lead Auditor' : 'Auditor'),
                'registrasi' => (string) ($b['registrasi'] ?? ''), 'lingkup' => (string) ($b['lingkup'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{0:array<string,array{metode:string,sampel:string}>,1:list<string>,2:array<string,array>}
     *         [metode_sampel per sub-elemen, baris pengecualian, teks utuh yang terpotong]
     */
    private function metodeSampel(array $sampel): array
    {
        $nama = Smkp::namaKriteria();
        $per = [];
        $kecuali = [];
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $kode = [$s['kode'], ...array_column($s['subsub'] ?? [], 'kode')];
                foreach ($kode as $k) {
                    $b = $sampel[$k] ?? null;
                    if (!is_array($b)) continue;
                    if (!empty($b['na'])) {
                        $ket = trim((string) ($b['ket'] ?? ''));
                        $kecuali[] = $k.' — '.($nama[$k] ?? '').($ket !== '' ? ': '.$ket : '');
                        continue;
                    }
                    foreach (self::METODE as $m => $label) {
                        $isi = trim((string) ($b[$m] ?? ''));
                        if ($isi === '') continue;
                        $baris = array_values(array_filter(array_map('trim', preg_split('/\R/u', $isi))));
                        if ($k !== $s['kode']) $baris = array_map(fn ($x) => "{$k}: {$x}", $baris);
                        $per[$s['kode']][$m] = array_merge($per[$s['kode']][$m] ?? [], $baris);
                    }
                }
            }
        }

        $out = [];
        $panjang = [];
        foreach ($per as $kode => $metode) {
            $m = implode(', ', array_map(fn ($k) => self::METODE[$k], array_keys(array_intersect_key(self::METODE, $metode))));
            $sp = implode('; ', array_map(fn ($k) => implode("\n", $metode[$k]), array_keys(array_intersect_key(self::METODE, $metode))));
            if (mb_strlen($sp) > 300) {
                $panjang[$kode] = ['metode' => $m, 'sampel' => $sp];
                $sp = rtrim(mb_substr($sp, 0, 299)).'…';
            }
            $out[$kode] = ['metode' => $m, 'sampel' => $sp];
        }

        return [$out, $kecuali, $panjang];
    }

    /** @return array{0:array,1:array} */
    private function laporan(array $l): array
    {
        $baru = [];
        if (trim((string) ($l['domisili'] ?? '')) !== '') $baru['legalitas'] = trim((string) $l['domisili']);
        foreach (['kegiatan', 'penerapan', 'status_perusahaan', 'sk_izin', 'wiup', 'legalitas', 'satuan_produksi', 'tempat', 'tanggal'] as $k) {
            if (trim((string) ($l[$k] ?? '')) !== '') $baru[$k] = trim((string) $l[$k]);
        }
        $area = array_merge(
            (array) ($l['area'] ?? []),
            array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($l['lingkup'] ?? '')))))
        );
        if ($area) $baru['area'] = array_values(array_filter(array_map(fn ($x) => trim((string) $x), $area)));
        if (!empty($l['peralatan'])) {
            $baru['peralatan'] = array_values(array_map(fn ($x) => [
                'jenis' => trim((string) (((array) $x)['jenis'] ?? '')), 'tipe' => trim((string) (((array) $x)['tipe'] ?? '')),
                'produktivitas' => trim((string) (((array) $x)['produktivitas'] ?? '')), 'jumlah' => trim((string) (((array) $x)['jumlah'] ?? '')),
            ], (array) $l['peralatan']));
        }
        foreach (['produksi', 'pekerja'] as $k) if (!empty($l[$k])) $baru[$k] = $l[$k];
        if (isset($l['distribusi'])) {
            $baru['distribusi'] = is_array($l['distribusi'])
                ? implode("\n", array_filter(array_map(fn ($x) => trim((string) $x), $l['distribusi'])))
                : trim((string) $l['distribusi']);
        }

        $sisa = array_intersect_key($l, array_flip(['kesimpulan', 'elemen', 'lampiran']));
        $sisa += array_diff_key($l, array_flip(['domisili', 'kegiatan', 'penerapan', 'lingkup', 'peralatan', 'distribusi', 'kesimpulan', 'elemen',
            'lampiran', 'status_perusahaan', 'sk_izin', 'wiup', 'legalitas', 'satuan_produksi', 'produksi', 'pekerja', 'area', 'tempat', 'tanggal']));

        return [$baru, array_filter($sisa, fn ($v) => $v !== null && $v !== '' && $v !== [])];
    }

    /**
     * Baris Formulir Rekomendasi & OFI untuk sebuah kode kriteria —
     * dibersihkan seperti SmkpFormulirController::bersihkan(). Null bila
     * elemennya tidak dikenal.
     */
    private function barisRekomendasi(string $kode, bool $bolehSubsub, array $uraian): ?array
    {
        $bagian = array_values(array_filter(explode('.', trim($kode)), fn ($x) => $x !== ''));
        $peta = [];
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) $peta[$e['kode']][$s['kode']] = array_column($s['subsub'] ?? [], 'kode');
        }
        $el = $bagian[0] ?? '';
        if (!isset($peta[$el])) return null;
        $sub = count($bagian) >= 2 ? $bagian[0].'.'.$bagian[1] : '';
        if ($sub !== '' && !isset($peta[$el][$sub])) $sub = '';
        $ss = $bolehSubsub && count($bagian) >= 3 ? implode('.', $bagian) : '';
        if ($sub === '' || !in_array($ss, $peta[$el][$sub], true)) $ss = '';

        $isi = trim(implode("\n", $uraian));
        if ($isi === '') return null;
        if (mb_strlen($isi) > 3000) $isi = rtrim(mb_substr($isi, 0, 2999)).'…';

        return ['elemen' => $el, 'sub' => $sub, 'subsub' => $ss, 'uraian' => $isi];
    }

    /** Temuan bukan observasi: nomor, foto, respon. */
    private function temuan(object $a, ?object $c, $temuan): void
    {
        $kode = $this->kodeNomor($c);
        $tahun = (int) ($a->tahun ?: now()->year);

        // Urutan tampil Aurorasys: Kritikal, Mayor, Minor — masing-masing urut acuan.
        $urut = $temuan->sort(fn ($x, $y) => [...Smkp::kunciUrutTemuan((string) $x->jenis, (string) $x->kode_kriteria), $x->id]
            <=> [...Smkp::kunciUrutTemuan((string) $y->jenis, (string) $y->kode_kriteria), $y->id])->values();

        $n = [];
        foreach ($urut as $t) {
            $this->tambah('temuan');
            $ubah = [];
            $sisa = $this->tanpaPadananTemuan($t);

            if (trim((string) ($t->nomor ?? '')) === '') {
                $j = (string) $t->jenis;
                $n[$j] = ($n[$j] ?? 0) + 1;
                $ubah['nomor'] = Smkp::nomorTemuan($j, $n[$j], $tahun, $kode);
            }

            if (filled($t->foto_closed ?? null)) {
                $lampiran = $this->salinBerkas((string) $t->foto_closed, "tindak-lanjut/{$a->id}/{$t->id}", $a->id);
                $id = DB::table('smkp_tindak_lanjut')->insertGetId([
                    'finding_id'       => $t->id,
                    'user_id'          => null,
                    'akar_masalah'     => $t->akar_masalah,
                    'tindakan'         => trim((string) $t->tindakan) ?: 'Bukti penutupan temuan (foto kondisi sesudah perbaikan, dari data SMKP lama).',
                    'bukti_perbaikan'  => null,
                    'penanggung_jawab' => $t->penanggung_jawab !== null ? mb_substr((string) $t->penanggung_jawab, 0, 150) : null,
                    'lampiran'         => $this->json([$lampiran]),
                    'created_at'       => $t->tanggal_selesai ?? $t->updated_at ?? now(),
                    'updated_at'       => now(),
                ]);
                $this->arsip('smkp_tindak_lanjut', $id, $a->id, null, [], 'foto penutupan temuan '.$t->id.' → kiriman tindak lanjut', 'dibuat');
                $this->tambah('temuan.foto_penutupan_ke_tindak_lanjut');
            }

            if (filled($t->foto_open ?? null)) {
                $l = $this->salinBerkas((string) $t->foto_open, "bukti-audit/{$a->id}", $a->id);
                $id = DB::table('smkp_bukti')->insertGetId([
                    'audit_id' => $a->id, 'kode' => mb_substr((string) $t->kode_kriteria, 0, 16), 'user_id' => null,
                    'path' => $l['path'], 'nama' => mb_substr($l['nama'], 0, 200), 'mime' => $l['mime'], 'ukuran' => $l['ukuran'],
                    'keterangan' => 'Foto kondisi saat temuan '.($ubah['nomor'] ?? $t->nomor ?? $t->kode_kriteria).' diangkat (dari data SMKP lama).',
                    'status' => 'diterima', 'diverifikasi_pada' => $t->created_at ?? now(),
                    'created_at' => $t->created_at ?? now(), 'updated_at' => now(),
                ]);
                $this->arsip('smkp_bukti', $id, $a->id, null, [], 'foto temuan '.$t->id.' (open) → bukti audit', 'dibuat');
                $this->tambah('temuan.foto_open_ke_bukti');
            }

            $this->arsip('smkp_findings', $t->id, $a->id, (array) $t, $sisa);
            if ($ubah) DB::table('smkp_findings')->where('id', $t->id)->update($ubah);
        }
    }

    /** Ruas temuan lama yang tidak punya padanan. */
    private function tanpaPadananTemuan(object $t): array
    {
        return array_filter([
            'respon_diterima'  => $t->respon_diterima ?? null,
            'respon_manajemen' => $t->respon_manajemen ?? null,
            'respon_oleh'      => $t->respon_oleh ?? null,
            'respon_pada'      => $t->respon_pada ?? null,
            'foto_open'        => $t->foto_open ?? null,
            'foto_closed'      => $t->foto_closed ?? null,
            'verifikasi_oleh'  => $t->verifikasi_oleh ?? null,
            'verifikasi_pada'  => $t->verifikasi_pada ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Bukti lama: diterima, berkasnya disalin ke folder modul baru. */
    private function bukti(): void
    {
        foreach (DB::table('smkp_bukti')->orderBy('id')->get() as $b) {
            if (DB::table('smkp_arsip_eqohsee')->where('tabel', 'smkp_bukti')->where('baris_id', $b->id)->where('jenis', 'dibuat')->exists()) continue;

            $ubah = [
                'status'            => 'diterima',
                'diverifikasi_oleh' => $b->user_id,
                'diverifikasi_pada' => $b->created_at,
            ];
            $awal = "smkp/{$b->audit_id}/";
            if (str_starts_with((string) $b->path, $awal) && Storage::disk('local')->exists($b->path)) {
                $ubah['path'] = $this->salinBerkas((string) $b->path, "bukti-audit/{$b->audit_id}", (int) $b->audit_id)['path'];
            }
            $this->arsip('smkp_bukti', $b->id, $b->audit_id, (array) $b, []);
            DB::table('smkp_bukti')->where('id', $b->id)->update($ubah);
            $this->tambah('bukti');
        }
    }

    /**
     * Salin berkas lama ke folder baru (disk local). Berkas asli tidak
     * dihapus. Bila berkasnya tidak ada, jalurnya dipakai apa adanya.
     *
     * @return array{path:string,nama:string,mime:?string,ukuran:int}
     */
    private function salinBerkas(string $jalur, string $folder, ?int $auditId): array
    {
        $disk = Storage::disk('local');
        $nama = basename($jalur);
        $ada = $jalur !== '' && $disk->exists($jalur);
        $tujuan = $jalur;

        if ($ada) {
            $tujuan = $folder.'/'.$nama;
            if ($disk->exists($tujuan) && $tujuan !== $jalur) $tujuan = $folder.'/'.uniqid().'-'.$nama;
            if ($tujuan !== $jalur) {
                $disk->copy($jalur, $tujuan);
                $this->arsip('berkas', null, $auditId, ['dari' => $jalur, 'ke' => $tujuan], [], 'berkas disalin', 'berkas');
                $this->tambah('berkas.disalin');
            }
        } else {
            $this->tambah('berkas.tidak_ditemukan');
        }

        $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
        $mime = $ada ? ($disk->mimeType($tujuan) ?: null) : null;
        $mime ??= ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][$ext] ?? null;

        return ['path' => $tujuan, 'nama' => $nama, 'mime' => $mime, 'ukuran' => $ada ? (int) $disk->size($tujuan) : 0];
    }

    /* ─────────── hitungan hari kerja EQOHSEE (dibekukan di sini) ─────────── */

    /** @return array{pekerja:int,kelas:string,dasar:int,penambah:int,pengurang:int,total:int,auditor:int,durasi:float,tahap1:float,tahap2:float} */
    private function mandaysLama(array $p, array $kinerja, ?object $c): array
    {
        if ((int) ($p['jumlah_pekerja'] ?? 0) <= 0 && $c) $p['jumlah_pekerja'] = (int) $c->workers_employee + (int) $c->workers_sub;
        if (($p['kelas_risiko'] ?? '') === '' && $c && $c->risk_class) $p['kelas_risiko'] = $c->risk_class;

        $pekerja = max(0, (int) ($p['jumlah_pekerja'] ?? 0));
        $kelas = (string) ($p['kelas_risiko'] ?? '');
        if ($kelas === 'Sedang') $kelas = 'Menengah';
        $kelas = isset(self::KOLOM_RISIKO[$kelas]) ? $kelas : 'Tinggi';

        $tim = $this->timAuditor($p);
        $auditor = $tim !== [] ? count($tim) : max(1, (int) ($p['jumlah_auditor'] ?? 1));

        $baris = self::MANDAYS_TABLE[0];
        if ($pekerja >= $baris[0]) {
            $baris = self::MANDAYS_TABLE[count(self::MANDAYS_TABLE) - 1];
            foreach (self::MANDAYS_TABLE as $r) {
                if ($pekerja >= $r[0] && $pekerja <= $r[1]) { $baris = $r; break; }
            }
        }
        $dasar = (int) $baris[self::KOLOM_RISIKO[$kelas]];

        $penambah = count(array_filter($this->faktorBerlaku($p, $kinerja)));
        $pengurang = 0;
        foreach (['terintegrasi', 'kepatuhan', 'tanpa_mayor', 'shift'] as $k) if (!empty(((array) ($p['pengurang'] ?? []))[$k])) $pengurang++;

        $total = max(1, $dasar + $penambah - $pengurang);
        $durasi = round($total / $auditor, 1);
        $tahap1 = round($durasi * 0.10, 2);

        return [
            'pekerja' => $pekerja, 'kelas' => $kelas,
            'rentang' => $baris[0].'–'.($baris[1] >= 999999 ? '∞' : $baris[1]),
            'dasar' => $dasar, 'penambah' => $penambah, 'pengurang' => $pengurang, 'total' => $total,
            'auditor' => $auditor, 'durasi' => $durasi, 'tahap1' => $tahap1, 'tahap2' => max(0.0, round($durasi - $tahap1, 2)),
        ];
    }

    /** Faktor penyesuaian yang berlaku di EQOHSEE: hitungan kinerja mengalahkan centang. @return array<string,bool> */
    private function faktorBerlaku(array $p, array $kinerja): array
    {
        $angka = function (array $x, string $k): ?float {
            $v = $x[$k] ?? null;
            if ($v === null || $v === '' || !is_numeric(str_replace(',', '.', (string) $v))) return null;
            return (float) str_replace(',', '.', (string) $v);
        };
        $n = (array) ($p['nasional'] ?? []);
        $kunci = [];

        [$fr, $sr, $frN, $srN] = [$angka($kinerja, 'fr'), $angka($kinerja, 'sr'), $angka($n, 'fr'), $angka($n, 'sr')];
        if ($fr !== null && $sr !== null && $frN !== null && $srN !== null) $kunci['kecelakaan'] = $fr > $frN || $sr > $srN;
        [$asr, $mfr, $asrN, $mfrN] = [$angka($kinerja, 'asr'), $angka($kinerja, 'mfr'), $angka($n, 'asr'), $angka($n, 'mfr')];
        if ($asr !== null && $mfr !== null && $asrN !== null && $mfrN !== null) $kunci['penyakit'] = $asr > $asrN || $mfr > $mfrN;
        [$kptk, $pak] = [$angka($kinerja, 'kptk'), $angka($kinerja, 'pak')];
        if ($kptk !== null || $pak !== null) $kunci['kptk'] = ($kptk ?? 0) > 0 || ($pak ?? 0) > 0;
        $bahaya = $angka($kinerja, 'berbahaya');
        if ($bahaya !== null && $bahaya < 2) $kunci['berbahaya'] = false;

        $centang = (array) ($p['faktor'] ?? []);
        $out = [];
        foreach (['jarak', 'metode', 'pengolahan', 'kecelakaan', 'penyakit', 'berbahaya', 'kptk'] as $k) {
            $out[$k] = array_key_exists($k, $kunci) ? $kunci[$k] : !empty($centang[$k]);
        }

        return $out;
    }

    /** @return list<string> */
    private function timAuditor(array $p): array
    {
        $nama = [];
        foreach ((array) ($p['tim'] ?? []) as $b) {
            $n = trim((string) (is_array($b) ? ($b['nama'] ?? '') : $b));
            if ($n !== '') $nama[] = $n;
        }

        return $nama;
    }

    /** Sama dengan SmkpAudit::kodeNomorTemuan(). */
    private function kodeNomor(?object $c): ?string
    {
        if (!$c) return null;
        $kode = preg_replace('/[^A-Za-z0-9]/', '', (string) $c->code);
        if ($kode === '') {
            $nama = preg_replace('/\b(PT|CV|Tbk|Persero)\b\.?/i', ' ', (string) $c->name);
            $kode = implode('', array_map(fn ($w) => mb_substr($w, 0, 1), preg_split('/[^\pL\pN]+/u', $nama, -1, PREG_SPLIT_NO_EMPTY)));
        }

        return mb_strtoupper($kode) ?: null;
    }

    /* ═════════════════════ pembuangan kolom lama ═════════════════════ */

    private function buangLama(): void
    {
        if (Schema::hasColumn('smkp_audits', 'sampel')) {
            Schema::table('smkp_audits', fn (Blueprint $t) => $t->dropColumn('sampel'));
        }

        $lama = array_values(array_filter(
            ['respon_diterima', 'respon_manajemen', 'respon_oleh', 'respon_pada', 'foto_open', 'foto_closed', 'verifikasi_oleh', 'verifikasi_pada'],
            fn ($k) => Schema::hasColumn('smkp_findings', $k)
        ));
        if ($lama) Schema::table('smkp_findings', fn (Blueprint $t) => $t->dropColumn($lama));

        if (Schema::hasColumn('smkp_attendees', 'tanda_tangan')) {
            Schema::table('smkp_attendees', fn (Blueprint $t) => $t->dropColumn('tanda_tangan'));
        }

        Schema::dropIfExists('smkp_ofi');

        if (!Schema::hasIndex('smkp_findings', ['audit_id', 'nomor'], 'unique')) {
            Schema::table('smkp_findings', fn (Blueprint $t) => $t->unique(['audit_id', 'nomor']));
        }
    }

    /* ═════════════════════ pembalikan ═════════════════════ */

    public function down(): void
    {
        if (Schema::hasIndex('smkp_findings', ['audit_id', 'nomor'], 'unique')) {
            Schema::table('smkp_findings', fn (Blueprint $t) => $t->dropUnique(['audit_id', 'nomor']));
        }

        if (!Schema::hasColumn('smkp_audits', 'sampel')) {
            Schema::table('smkp_audits', fn (Blueprint $t) => $t->json('sampel')->nullable());
        }
        Schema::table('smkp_findings', function (Blueprint $t) {
            if (!Schema::hasColumn('smkp_findings', 'respon_diterima'))  $t->boolean('respon_diterima')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'respon_manajemen')) $t->text('respon_manajemen')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'respon_oleh'))      $t->string('respon_oleh')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'respon_pada'))      $t->date('respon_pada')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'foto_open'))        $t->string('foto_open')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'foto_closed'))      $t->string('foto_closed')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'verifikasi_oleh'))  $t->string('verifikasi_oleh')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'verifikasi_pada'))  $t->date('verifikasi_pada')->nullable();
        });
        if (!Schema::hasColumn('smkp_attendees', 'tanda_tangan')) {
            Schema::table('smkp_attendees', fn (Blueprint $t) => $t->string('tanda_tangan')->nullable());
        }
        if (!Schema::hasTable('smkp_ofi')) {
            Schema::create('smkp_ofi', function (Blueprint $t) {
                $t->id();
                $t->foreignId('audit_id')->constrained('smkp_audits')->cascadeOnDelete();
                $t->string('kode');
                $t->string('lingkup')->default('sub');
                $t->text('uraian');
                $t->text('saran')->nullable();
                $t->string('penanggung_jawab')->nullable();
                $t->date('target')->nullable();
                $t->string('status')->default('terbuka');
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->timestamps();
                $t->unique(['audit_id', 'kode']);
            });
        }

        if (!Schema::hasTable('smkp_arsip_eqohsee')) return;

        DB::transaction(function () {
            // Baris yang dibuat migrasi ini.
            foreach (DB::table('smkp_arsip_eqohsee')->where('jenis', 'dibuat')->get() as $x) {
                DB::table($x->tabel)->where('id', $x->baris_id)->delete();
            }
            // Berkas salinan (aslinya tidak pernah dihapus).
            foreach (DB::table('smkp_arsip_eqohsee')->where('jenis', 'berkas')->get() as $x) {
                $b = $this->dekode($x->asli);
                if (!empty($b['ke']) && ($b['ke'] !== ($b['dari'] ?? null))) Storage::disk('local')->delete($b['ke']);
            }
            // Baris asli dipulihkan apa adanya.
            foreach (DB::table('smkp_arsip_eqohsee')->where('jenis', 'asli')->orderBy('id')->get() as $x) {
                $asli = $this->dekode($x->asli);
                if (!$asli || !Schema::hasTable($x->tabel)) continue;
                $kolom = array_flip(Schema::getColumnListing($x->tabel));
                $isi = array_intersect_key($asli, $kolom);
                unset($isi['id']);
                if (DB::table($x->tabel)->where('id', $x->baris_id)->exists()) {
                    DB::table($x->tabel)->where('id', $x->baris_id)->update($isi);
                } elseif (isset($asli['id'])) {
                    DB::table($x->tabel)->insert(['id' => $asli['id']] + $isi);
                }
            }
            DB::table('smkp_arsip_eqohsee')->delete();
        });
    }

    /* ═════════════════════ perkakas ═════════════════════ */

    private function arsip(string $tabel, ?int $id, ?int $auditId, ?array $asli, array $tanpaPadanan, ?string $catatan = null, string $jenis = 'asli'): void
    {
        DB::table('smkp_arsip_eqohsee')->insert([
            'tabel' => $tabel, 'baris_id' => $id, 'audit_id' => $auditId, 'jenis' => $jenis,
            'asli' => $asli === null ? null : $this->json($asli),
            'tanpa_padanan' => $tanpaPadanan ? $this->json($tanpaPadanan) : null,
            'catatan' => $catatan, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($tanpaPadanan) $this->tambah('arsip.'.$tabel.'.tanpa_padanan');
    }

    private function tambah(string $k): void
    {
        $this->hitung[$k] = ($this->hitung[$k] ?? 0) + 1;
    }

    private function dekode(mixed $v): array
    {
        if (is_array($v)) return $v;
        $d = json_decode((string) $v, true);

        return is_array($d) ? $d : [];
    }

    private function json(mixed $v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
