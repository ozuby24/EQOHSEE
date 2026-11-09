<?php

namespace App\Models;

use App\Models\Scopes\JangkauanAudit;
use App\Support\{Smkp, SmkpTahap};
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Satu periode audit SMKP Minerba per perusahaan per tahun (porting
 * Aurorasys). Batas perusahaannya mengikuti User::jangkauan() — lihat
 * Scopes\JangkauanAudit.
 */
#[ScopedBy(JangkauanAudit::class)]
class SmkpAudit extends Model
{
    protected $table = 'smkp_audits';

    public const STATUS = ['draft' => 'Draft', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'];

    protected $fillable = [
        'company_id', 'tahun', 'judul', 'status', 'tahap',
        'tanggal_mulai', 'tanggal_selesai', 'ketua_auditor',
        'hasil', 'auditor', 'profil', 'user_id',
        'permulaan', 'rencana', 'kecukupan', 'kinerja', 'risiko',
        'kesesuaian', 'rekomendasi', 'laporan',
    ];

    /** Folder berkas tanda tangan digital formulir (disk local). Kolom ttd_formulir sengaja tidak fillable: jalur berkasnya hanya ditulis SmkpFormulirController. */
    public const FOLDER_TTD = 'ttd-formulir';

    public static function folderTtd(int $id): string
    {
        return self::FOLDER_TTD.'/'.$id;
    }

    protected function casts(): array
    {
        return [
            'hasil'           => 'array',
            'auditor'         => 'array',
            'profil'          => 'array',
            'permulaan'       => 'array',
            'rencana'         => 'array',
            'kecukupan'       => 'array',
            'kinerja'         => 'array',
            'risiko'          => 'array',
            'kesesuaian'      => 'array',
            'rekomendasi'     => 'array',
            'laporan'         => 'array',
            'ttd_formulir'    => 'array',
            'mandays_tetap'   => 'array',
            'tahap'           => 'integer',
            'tahun'           => 'integer',
            'tanggal_mulai'   => 'date:Y-m-d',
            'tanggal_selesai' => 'date:Y-m-d',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function findings(): HasMany  { return $this->hasMany(SmkpFinding::class, 'audit_id'); }
    public function attendees(): HasMany { return $this->hasMany(SmkpAttendee::class, 'audit_id'); }
    public function bukti(): HasMany     { return $this->hasMany(SmkpBukti::class, 'audit_id'); }

    public function namaTampil(): string
    {
        return $this->judul ?: 'Audit SMKP '.$this->tahun;
    }

    public function hadir(string $rapat)
    {
        return $this->attendees()->where('rapat', $rapat)->orderBy('id')->get();
    }

    /* ---------- tahapan ---------- */

    /**
     * Tim audit. Diambil dari pembagian tugas Rencana Audit — satu-satunya
     * tempat tim dicatat lengkap dengan nomor registrasinya — sehingga
     * Berita Acara Tahap I dan Rencana Audit selalu menyebut tim yang sama.
     */
    public function tim(): array
    {
        return array_values(array_filter(
            (array) ($this->rencana['tugas'] ?? []),
            fn ($a) => trim((string) ($a['nama'] ?? '')) !== ''
        ));
    }

    /**
     * Kode di depan nomor ketidaksesuaian: kode perusahaan yang diaudit (mis.
     * AIC, NJA, KEPS, MCN), atau inisial namanya bila kode belum diisi.
     */
    public function kodeNomorTemuan(): ?string
    {
        $c = $this->company;
        if (!$c) return null;

        $kode = preg_replace('/[^A-Za-z0-9]/', '', (string) $c->code);
        if ($kode === '') {
            $nama = preg_replace('/\b(PT|CV|Tbk|Persero)\b\.?/i', ' ', (string) $c->name);
            $kode = implode('', array_map(fn ($w) => mb_substr($w, 0, 1), preg_split('/[^\pL\pN]+/u', $nama, -1, PREG_SPLIT_NO_EMPTY)));
        }
        return mb_strtoupper($kode) ?: null;
    }

    /** Nomor ketidaksesuaian ke-$urutan untuk kategori itu (lihat Smkp::nomorTemuan). */
    public function nomorTemuanBaru(string $jenis, int $urutan): string
    {
        return Smkp::nomorTemuan($jenis, $urutan, (int) ($this->tahun ?: \App\Support\Waktu::kini()->year), $this->kodeNomorTemuan());
    }

    /**
     * Hari kerja audit. Audit di tahap Pelaporan memakai angka yang dibekukan
     * saat diselesaikan (berkas yang sudah ditandatangani tercetak sama);
     * selain itu dihitung dari isian Tahap I.
     */
    public function mandays(): array
    {
        return $this->tahap === SmkpTahap::PELAPORAN && $this->mandays_tetap
            ? $this->mandays_tetap
            : SmkpTahap::mandays((array) ($this->permulaan ?? []));
    }

    public function rekapKecukupan(): array
    {
        return SmkpTahap::rekapKecukupan((array) ($this->kecukupan ?? []));
    }

    public function rekapRencana(): array
    {
        return SmkpTahap::rekapRencana($this->rencana, $this->company?->kategori);
    }

    /**
     * Audit lapangan hanya boleh berjalan setelah Tahap I tuntas: seluruh
     * elemen sudah ditinjau kecukupan dokumentasinya dan Rencana Audit lengkap
     * sembilan komponen.
     */
    public function siapTahapDua(): bool
    {
        return $this->rekapKecukupan()['siap'] && $this->rekapRencana()['lengkap'];
    }

    /**
     * Status tiap langkah pada alur audit: ['selesai' => bool, 'ket' => string].
     */
    public function statusAlur(): array
    {
        $p  = (array) ($this->permulaan ?? []);
        $kj = array_filter((array) ($this->kinerja ?? []), fn ($v) => trim((string) $v) !== '');
        $kc = $this->rekapKecukupan();
        $rr = $this->rekapRencana();
        $rk = $this->rekap();
        $md = $this->mandays();

        $kelayakan = array_filter((array) ($p['kelayakan'] ?? []), fn ($v) => trim((string) $v) !== '');
        $totalKelayakan = count(SmkpTahap::indikatorKelayakan());

        $hadirBuka  = $this->attendees()->where('rapat', 'pembukaan')->count();
        $hadirTutup = $this->attendees()->where('rapat', 'penutupan')->count();
        $temuan     = $this->findings()->count();

        $ren = fn (array $kunci) => !in_array(false, array_map(fn ($k) => $rr['terisi'][$k] ?? false, $kunci), true);

        return [
            'kontak'    => $this->langkah(!empty($p['tanggal_kontak']), !empty($p['tanggal_kontak'])
                ? 'Kontak awal '.\Illuminate\Support\Carbon::parse($p['tanggal_kontak'])->translatedFormat('d M Y')
                : 'Belum dicatat'),
            'kinerja'   => $this->langkah(count($kj) > 0, count($kj).' dari '.count(SmkpTahap::butirKinerja()).' angka terisi'),
            'kelayakan' => $this->langkah(count($kelayakan) === $totalKelayakan, count($kelayakan)."/{$totalKelayakan} indikator dievaluasi"),
            'mandays'   => $this->langkah($md['dasar'] > 0, $md['dasar'] > 0 ? number_format($md['total'], 2, ',', '.').' hari · Tahap II '.number_format($md['tahap2'], 2, ',', '.').' hari' : 'Belum dihitung'),
            'kecukupan' => $this->langkah($kc['siap'], $kc['lengkap'].' lengkap · '.$kc['tidak'].' tidak lengkap · '.$kc['belum'].' belum ditinjau'),
            'berita'    => $this->langkah($kc['siap'], 'Berkas resmi Tahap I'),

            'lingkup'   => $this->langkah($ren(['tujuan', 'kriteria', 'ruang_lingkup']), 'Tujuan · Kriteria · Ruang lingkup'),
            'jadwal'    => $this->langkah($ren(['tanggal', 'susunan']), 'Tanggal pelaksanaan dan susunan kegiatan'),
            'tim'       => $this->langkah($ren(['tugas']), count($this->tim()).' auditor dengan lingkupnya'),
            'sampel'    => $this->langkah($ren(['metode']), count((array) ($this->risiko['present'] ?? [])).' risiko periode berjalan tercatat'),
            'sah'       => $this->langkah($ren(['pengesahan']), 'Pengesahan KTT dan Ketua Tim'),
            'rencana-cetak' => $this->langkah($rr['lengkap'], $rr['jumlah'].' dari '.$rr['total'].' komponen wajib'),

            'pembukaan' => $this->langkah($hadirBuka > 0, $hadirBuka.' peserta tercatat'),
            'nilai'     => $this->langkah($rk['dinilai'] >= $rk['berlaku'] && $rk['berlaku'] > 0, $rk['dinilai'].'/'.$rk['berlaku'].' butir dinilai · nilai '.number_format($rk['skor'], 2, ',', '.')),
            'temuan'    => $this->langkah($temuan > 0, $temuan ? $temuan.' temuan diangkat' : 'Belum ada temuan diangkat'),
            'penutupan' => $this->langkah($hadirTutup > 0, $hadirTutup.' peserta tercatat'),

            'laporan'     => $this->langkah($rk['dinilai'] > 0, 'Nilai akhir '.number_format($rk['skor'], 2, ',', '.').' · '.$rk['tingkat']['label']),
            'hadir-buka'  => $this->langkah($hadirBuka > 0, $hadirBuka.' peserta'),
            'hadir-tutup' => $this->langkah($hadirTutup > 0, $hadirTutup.' peserta'),
        ];
    }

    private function langkah(bool $selesai, string $ket): array
    {
        return ['selesai' => $selesai, 'ket' => $ket];
    }

    /** Rekap penuh — didelegasikan ke mesin hitung agar rumus hanya ada di satu tempat. */
    public function rekap(): array
    {
        return Smkp::rekap($this->hasil ?? []);
    }

    /** Persentase butir berlaku yang sudah dinilai (0..1). */
    public function kemajuan(): float
    {
        $r = $this->rekap();

        return $r['berlaku'] > 0 ? $r['dinilai'] / $r['berlaku'] : 0.0;
    }
}
