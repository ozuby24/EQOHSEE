<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\{Smkp, SmkpTahap};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

#[ScopedBy(MilikPerusahaan::class)]
class SmkpAudit extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'smkp_audits';

    protected $fillable = [
        'company_id', 'tahun', 'judul', 'status', 'tahap',
        'tanggal_mulai', 'tanggal_selesai', 'ketua_auditor',
        'hasil', 'auditor', 'profil', 'user_id',
        'permulaan', 'rencana', 'kecukupan', 'kinerja', 'risiko',
    ];

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
            'tahap'           => 'integer',
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function findings(): HasMany  { return $this->hasMany(SmkpFinding::class, 'audit_id'); }

    public function attendees(): HasMany
    {
        return $this->hasMany(SmkpAttendee::class, 'audit_id');
    }

    public function hadir(string $rapat)
    {
        return $this->attendees()->where('rapat', $rapat)->orderBy('id')->get();
    }

    /* ---------- tahapan ---------- */

    /**
     * Tim auditor yang ditugaskan, lengkap dengan peran dan lingkupnya.
     *
     * SATU DAFTAR, DUA SUMBER YANG SALING MELENGKAPI — bukan dua daftar yang
     * dapat berselisih:
     *
     *   permulaan.tim   susunan nama pada Tahap I. Inilah yang menentukan
     *                   SIAPA dan berapa orang, dan angka itu pula yang
     *                   membagi durasi audit.
     *   rencana.tugas   pembagian tugas pada Rencana Audit, menambahkan
     *                   nomor registrasi dan lingkup elemen tiap orang.
     *
     * Peran diturunkan dari urutan pada susunan Tahap I, jadi selalu ada
     * tepat satu ketua tim. Baris pembagian tugas dicocokkan MENURUT NAMA;
     * nama yang hanya ada di pembagian tugas tetap ikut, sebab menghilangkan
     * orang dari laporan lebih buruk daripada menampilkannya tanpa peran.
     *
     * @return list<array{nama:string,peran:string,registrasi:?string,lingkup:?string}>
     */
    public function tim(): array
    {
        $tugas = [];
        foreach ((array) ($this->rencana['tugas'] ?? []) as $b) {
            $nama = trim((string) ($b['nama'] ?? ''));
            if ($nama !== '') $tugas[$nama] = $b;
        }

        $out = [];

        foreach (SmkpTahap::susunanTim((array) ($this->permulaan ?? [])) as $orang) {
            $b = $tugas[$orang['nama']] ?? [];
            unset($tugas[$orang['nama']]);

            $out[] = $orang + [
                'registrasi' => $b['registrasi'] ?? null,
                'lingkup'    => $b['lingkup'] ?? null,
            ];
        }

        // Sisa nama yang belum ada pada susunan Tahap I, beserta data lama
        // pada kolom `auditor` bagi audit yang dibuat sebelum susunan ada.
        $sisa = array_values($tugas);
        if ($out === [] && $sisa === []) {
            $sisa = array_values(array_filter(
                (array) ($this->auditor ?? []),
                fn ($a) => trim((string) ($a['nama'] ?? '')) !== ''
            ));
        }

        foreach ($sisa as $b) {
            $out[] = [
                'nama'       => trim((string) ($b['nama'] ?? '')),
                'peran'      => trim((string) ($b['peran'] ?? '')) ?: SmkpTahap::peranAuditor(count($out)),
                'registrasi' => $b['registrasi'] ?? null,
                'lingkup'    => $b['lingkup'] ?? null,
            ];
        }

        return $out;
    }

    public function mandays(): array
    {
        return SmkpTahap::mandays((array) ($this->permulaan ?? []));
    }

    public function rekapKecukupan(): array
    {
        return SmkpTahap::rekapKecukupan((array) ($this->kecukupan ?? []));
    }

    public function rekapRencana(): array
    {
        return SmkpTahap::rekapRencana($this->rencana);
    }

    /** Apakah Rencana Audit selaras dengan hitungan hari kerja Tahap I. */
    public function selarasRencana(): array
    {
        return SmkpTahap::selarasRencana($this->rencana, $this->mandays(), (array) ($this->permulaan ?? []));
    }

    /**
     * Audit lapangan hanya boleh berjalan setelah Tahap I tuntas: seluruh
     * elemen sudah ditinjau kecukupan dokumentasinya dan Rencana Audit lengkap
     * sembilan komponen. Ini yang membedakan dua tahap dari sekadar dua menu.
     */
    public function siapTahapDua(): bool
    {
        return $this->rekapKecukupan()['siap'] && $this->rekapRencana()['lengkap'];
    }

    /**
     * Status tiap langkah pada alur audit.
     *
     * Dihitung sekali lalu dipakai seluruh menu, agar satu halaman tidak
     * menghitung ulang rekap yang sama belasan kali.
     *
     * Nilai tiap kunci: ['selesai' => bool, 'ket' => string ringkas].
     */
    public function statusAlur(): array
    {
        $p  = (array) ($this->permulaan ?? []);
        $kj = array_filter((array) ($this->kinerja ?? []), fn ($v) => trim((string) $v) !== '');
        $kc = $this->rekapKecukupan();
        $rr = $this->rekapRencana();
        $rk = $this->rekap();
        $md = $this->mandays();

        $kelayakan = array_filter(
            (array) ($p['kelayakan'] ?? []),
            fn ($v) => trim((string) $v) !== ''
        );
        $totalKelayakan = count(SmkpTahap::indikatorKelayakan());

        $hadirBuka  = $this->attendees()->where('rapat', 'pembukaan')->count();
        $hadirTutup = $this->attendees()->where('rapat', 'penutupan')->count();
        $temuan     = $this->findings()->count();

        // Komponen Rencana Audit dikelompokkan mengikuti langkah di menu,
        // bukan satu per satu — sebuah langkah selesai bila seluruh
        // komponen di bawahnya terisi.
        $ren = fn (array $kunci) => !in_array(false, array_map(fn ($k) => $rr['terisi'][$k] ?? false, $kunci), true);

        return [
            'kontak'    => $this->langkah(!empty($p['tanggal_kontak']), count($this->tim()).' auditor ditugaskan'),
            'kinerja'   => $this->langkah(count($kj) > 0, count($kj).' dari '.count(SmkpTahap::butirKinerja()).' angka terisi'),
            'kelayakan' => $this->langkah(count($kelayakan) === $totalKelayakan, count($kelayakan)."/{$totalKelayakan} indikator dievaluasi"),
            /* Mandays dasar kini dibaca dari tabel, jadi ia selalu terisi.
               Yang menandakan langkah ini selesai bukan lagi angkanya
               melainkan MASUKANNYA: jumlah pekerja auditi. Tanpa itu tabel
               jatuh ke baris terkecil dan menagih tiga hari untuk tambang
               berapa pun besarnya. */
            'mandays'   => $this->langkah(
                $md['pekerja'] > 0,
                $md['pekerja'] > 0
                    ? $md['total'].' mandays · '.$md['durasi'].' hari di lapangan · Tahap II '.$md['tahap2'].' hari'
                    : 'Jumlah pekerja auditi belum diisi',
            ),
            'kecukupan' => $this->langkah($kc['siap'], $kc['lengkap'].' lengkap · '.$kc['tidak'].' tidak lengkap · '.$kc['belum'].' belum ditinjau'),
            'berita'    => $this->langkah($kc['siap'], 'Berkas resmi Tahap I'),

            'lingkup'   => $this->langkah($ren(['tujuan','kriteria','ruang_lingkup']), 'Tujuan · Kriteria · Ruang lingkup'),
            'jadwal'    => $this->langkah($ren(['tanggal','susunan']), 'Tanggal pelaksanaan dan susunan kegiatan'),
            'tim'       => $this->langkah($ren(['tugas']), count((array) ($this->rencana['tugas'] ?? [])).' auditor dengan lingkupnya'),
            'sampel'    => $this->langkah($ren(['metode']), count((array) ($this->risiko['present'] ?? [])).' risiko periode berjalan tercatat'),
            'sah'       => $this->langkah($ren(['pengesahan']), 'Pengesahan KTT dan Ketua Tim'),
            'rencana-cetak' => $this->langkah($rr['lengkap'], $rr['jumlah'].' dari '.$rr['total'].' komponen wajib'),

            'pembukaan' => $this->langkah($hadirBuka > 0, $hadirBuka.' peserta tercatat'),
            'nilai'     => $this->langkah($rk['dinilai'] >= $rk['berlaku'] && $rk['berlaku'] > 0, $rk['dinilai'].'/'.$rk['berlaku'].' butir dinilai · nilai '.number_format($rk['skor'], 2)),
            'temuan'    => $this->langkah($temuan > 0, $temuan ? $temuan.' temuan diangkat' : 'Belum ada temuan diangkat'),
            'penutupan' => $this->langkah($hadirTutup > 0, $hadirTutup.' peserta tercatat'),

            'laporan'     => $this->langkah($rk['dinilai'] > 0, 'Nilai akhir '.number_format($rk['skor'], 2).' · '.$rk['tingkat']['label']),
            'hadir-buka'  => $this->langkah($hadirBuka > 0, $hadirBuka.' peserta'),
            'hadir-tutup' => $this->langkah($hadirTutup > 0, $hadirTutup.' peserta'),
        ];
    }

    private function langkah(bool $selesai, string $ket): array
    {
        return ['selesai' => $selesai, 'ket' => $ket];
    }

    /** Nilai satu butir: angka, 'N/A', atau null bila belum dinilai. */
    public function nilai(string $kodeButir)
    {
        return Smkp::nilaiButir($this->hasil ?? [], $kodeButir);
    }

    public function ket(string $kode): string
    {
        return (string) ($this->hasil[$kode]['ket'] ?? '');
    }

    public function bukti(string $kode): string
    {
        return (string) ($this->hasil[$kode]['bukti'] ?? '');
    }

    /** Rekap penuh — didelegasikan ke mesin hitung agar rumus hanya ada di satu tempat. */
    public function rekap(): array
    {
        return Smkp::rekap($this->hasil ?? []);
    }

    /** Persentase butir yang sudah dinilai (0..1) — untuk bilah kemajuan. */
    public function kemajuan(): float
    {
        $r = $this->rekap();
        return $r['berlaku'] > 0 ? $r['dinilai'] / $r['berlaku'] : 0.0;
    }
}
