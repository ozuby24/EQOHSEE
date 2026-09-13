<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Perusahaan Jasa Pertambangan yang dipantau pemegang izin.
 *
 * Dipantau lewat tiga aspek yang berjalan BERSAMAAN — persyaratan
 * (checklist prakualifikasi SMKP), pelaporan (kepatuhan unggah dokumen),
 * dan evaluasi (kinerja per semester). Ketiganya berdiri sendiri: satu
 * PJP muncul di ketiga halaman sekaligus, masing-masing dengan skornya
 * sendiri. Tidak ada urutan yang harus dilewati.
 *
 * Seluruh aturan skor tinggal di sini, bukan di pengendali. Skor yang
 * dihitung di pengendali akan berbeda antara halaman daftar, ekspor, dan
 * laporan cetak begitu salah satunya diubah — dan bedanya tidak
 * menimbulkan galat, hanya dua angka yang sama-sama terlihat sah.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Pjp extends Model
{
    use BerpemilikPerusahaan;
    use HasFactory;

    /** Kunci = nilai tersimpan; nilai = label yang dibaca orang. */
    public const STATUS = [
        'aktif'               => 'Aktif Dipantau',
        'perlu_tindak_lanjut' => 'Perlu Tindak Lanjut',
        'tidak_aktif'         => 'Tidak Aktif',
    ];

    /** Batas achievement di bawah mana sebuah PJP disebut perlu perhatian. */
    public const AMBANG_PERHATIAN = 80;

    protected $fillable = [
        'company_id',
        'nama_perusahaan',
        'nib',
        'penanggung_jawab',
        'alamat',
        'status',
        'catatan',
    ];

    /** Pemegang izin yang memantau — bukan PJP-nya sendiri. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function laporans(): HasMany
    {
        return $this->hasMany(PjpLaporan::class)->latest();
    }

    public function smkpChecklistAnswers(): HasMany
    {
        return $this->hasMany(SmkpChecklistAnswer::class);
    }

    public function evaluasis(): HasMany
    {
        return $this->hasMany(PjpEvaluasi::class)
            ->orderByDesc('tahun')->orderByDesc('semester');
    }

    /* ---------- skor persyaratan (checklist prakualifikasi SMKP) ---------- */

    /**
     * Skor kepatuhan checklist prakualifikasi SMKP.
     *
     * Σ(bobot × nilai ÷ 3) ÷ Σbobot × 100, dari kategori berbobot A–P
     * (total bobot 178). Dua perlakuan yang mudah tertukar dan keduanya
     * disengaja:
     *
     *  - Item bernilai `na` keluar dari PEMBILANG DAN PENYEBUT. Pertanyaan
     *    yang tidak berlaku bagi sebuah PJP tidak boleh menghukum skornya.
     *  - Item yang BELUM DIJAWAB tetap masuk penyebut dengan skor 0.
     *    Kalau tidak, PJP yang baru menjawab satu pertanyaan dengan nilai
     *    penuh akan terbaca 100% patuh.
     *
     * Kategori LEGALITAS tidak ikut sama sekali — ia gerbang wajib yang
     * dihitung terpisah lewat smkpLegalitasStatus().
     */
    public function smkpScore(): array
    {
        $rincian = $this->smkpCategoryBreakdown();

        $totalBobot = array_sum(array_column($rincian, 'bobot_dinilai'));
        $totalSkor  = array_sum(array_column($rincian, 'skor'));

        $persentase = $totalBobot > 0 ? round($totalSkor / $totalBobot * 100, 1) : 0.0;

        return [
            'total_bobot'     => $totalBobot,
            'total_skor'      => round($totalSkor, 1),
            'persentase'      => $persentase,
            'kategori_risiko' => self::kategoriRisiko($persentase),
        ];
    }

    /**
     * Skor yang sama, dipecah per kategori A–P.
     *
     * Satu angka total menjawab "seberapa patuh", tidak menjawab "apa
     * yang harus diperbaiki lebih dulu". Rincian inilah yang membuat
     * kategori terlemah dapat ditunjuk dan dibuka langsung formulirnya.
     */
    public function smkpCategoryBreakdown(): array
    {
        $jawaban = $this->smkpChecklistAnswers()->get()->keyBy('smkp_checklist_item_id');

        $kategori = SmkpChecklistCategory::query()
            ->where('kode', '!=', SmkpChecklistCategory::LEGALITAS)
            ->orderBy('urutan')
            ->with('items')
            ->get();

        return $kategori->map(function (SmkpChecklistCategory $k) use ($jawaban) {
            $bobotDinilai = 0;
            $skor = 0.0;

            foreach ($k->items as $item) {
                $nilai = $jawaban->get($item->id)?->nilai;

                if ($nilai === SmkpChecklistAnswer::NILAI_NA) continue;

                $bobotDinilai += $item->bobot;
                $skor += $item->bobot * ((int) ($nilai ?? 0) / 3);
            }

            return [
                'kode'          => $k->kode,
                'nama'          => $k->nama,
                'bobot'         => $k->bobot,
                'bobot_dinilai' => $bobotDinilai,
                'skor'          => round($skor, 1),
                'persentase'    => $bobotDinilai > 0 ? round($skor / $bobotDinilai * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Kelengkapan Dokumen Legalitas — hitungan lulus/belum, bukan skor.
     *
     * Akta, NIB, IUJP, dan NPWP tidak punya tingkatan "cukup memadai":
     * dokumennya ada atau tidak ada. Karena itu yang dihitung hanya
     * berapa item berjawaban "ya", dan hasilnya berdiri di luar skor 178.
     */
    public function smkpLegalitasStatus(): array
    {
        $jawaban = $this->smkpChecklistAnswers()->get()->keyBy('smkp_checklist_item_id');

        $items = SmkpChecklistCategory::where('kode', SmkpChecklistCategory::LEGALITAS)
            ->first()?->items ?? collect();

        return [
            'total'   => $items->count(),
            'lengkap' => $items->filter(
                fn (SmkpChecklistItem $item) => $jawaban->get($item->id)?->jawaban === 'ya'
            )->count(),
        ];
    }

    /**
     * Label kategori risiko pekerjaan yang layak dipercayakan kepada PJP ini.
     *
     * Perlu dibaca terbalik dari dugaan yang wajar: "Kritis" BUKAN berarti
     * PJP-nya berbahaya, melainkan bahwa skornya sudah cukup tinggi untuk
     * dipercaya mengerjakan pekerjaan berisiko kritis. Karena itu skor
     * 100% memang berlabel "Kritis", dan itu benar.
     */
    public static function kategoriRisiko(float $persentase): string
    {
        return match (true) {
            $persentase > 75  => 'Kritis',
            $persentase >= 55 => 'Tinggi',
            $persentase >= 36 => 'Sedang',
            $persentase >= 20 => 'Rendah',
            default           => 'Sangat Rendah',
        };
    }

    /* ---------- skor pelaporan ---------- */

    /**
     * Skor kepatuhan pelaporan: rata-rata ketepatan waktu dan kesesuaian isi.
     *
     * Persentase kesesuaian hanya dihitung dari dokumen yang SUDAH dinilai;
     * yang belum dinilai tidak dianggap tidak sesuai.
     *
     * `null` berarti PJP ini belum pernah mengunggah apa pun — sengaja
     * dibedakan dari 0. Keduanya digambar berbeda: 0% adalah kegagalan
     * kepatuhan, null adalah ketiadaan data. Menyamakannya membuat PJP
     * yang baru terdaftar kemarin tampil semerah PJP yang setahun tidak
     * pernah melapor.
     */
    public function pelaporanScore(): ?float
    {
        $laporans = $this->laporans()->get();

        if ($laporans->isEmpty()) return null;

        $tepatWaktu = $laporans->filter(fn (PjpLaporan $l) => $l->tepat_waktu)->count()
            / $laporans->count() * 100;

        $dinilai = $laporans->filter(fn (PjpLaporan $l) => $l->kesesuaian_isi !== null);

        $sesuai = $dinilai->isNotEmpty()
            ? $dinilai->filter(fn (PjpLaporan $l) => $l->kesesuaian_isi === 'sesuai')->count()
                / $dinilai->count() * 100
            : null;

        return round($sesuai !== null ? ($tepatWaktu + $sesuai) / 2 : $tepatWaktu, 1);
    }

    /* ---------- skor gabungan lintas aspek ---------- */

    /**
     * Nilai TERENDAH di antara ketiga aspek yang datanya tersedia.
     *
     * Terendah, bukan rata-rata. Satu aspek yang buruk adalah alasan
     * menindaklanjuti sebuah PJP, dan rata-rata justru menyembunyikannya:
     * checklist 95% dan evaluasi 90% akan menutupi kepatuhan pelaporan
     * 30% menjadi angka 72 yang terlihat sekadar "perlu perhatian".
     *
     * Skor SMKP selalu berupa angka (checklist kosong = 0%, bukan null),
     * sedangkan pelaporan dan evaluasi benar-benar bisa null saat belum
     * ada datanya — yang null diabaikan, bukan dihitung sebagai 0.
     *
     * Dipakai hanya di tempat yang memerlukan SATU angka lintas aspek:
     * kartu "Paling Perlu Perhatian" di beranda modul. Ketiga halaman
     * aspek memakai skor aspeknya masing-masing, bukan ini.
     */
    public function achievement(): ?float
    {
        $skor = array_filter([
            $this->smkpScore()['persentase'],
            $this->pelaporanScore(),
            $this->evaluasis()->first()?->skor_rata_rata,
        ], fn (?float $v) => $v !== null);

        return $skor === [] ? null : min($skor);
    }

    /* ---------- saringan & hitungan ---------- */

    /**
     * Pencarian dan penyaringan yang dipakai bersama oleh daftar, ketiga
     * halaman aspek, dan ekspor. Ditulis sekali di sini supaya ekspor
     * tidak pernah mengirim baris yang berbeda dari yang terlihat di layar.
     */
    public function scopeFilter(Builder $query, ?string $cari, ?string $status): Builder
    {
        return $query
            ->when($cari, fn ($q, $cari) => $q->where('nama_perusahaan', 'like', "%{$cari}%"))
            ->when($status, fn ($q, $status) => $q->where('status', $status));
    }

    /**
     * Hitungan per status, SELALU memuat setiap kunci status (0 bila kosong).
     *
     * Bilah status bertumpuk menggambar tiga segmen tetap. Kunci yang
     * hilang saat belum ada datanya membuat segmennya lenyap, dan yang
     * terlihat adalah bilah dua warna yang seolah-olah lengkap.
     */
    public static function statusCountsFor(?Builder $query = null): array
    {
        $hitung = ($query ?? static::query())
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return collect(array_keys(self::STATUS))
            ->mapWithKeys(fn (string $status) => [$status => (int) ($hitung[$status] ?? 0)])
            ->all();
    }

    /**
     * PJP yang belum atau terlambat mengirim Laporan Bulanan bulan ini.
     *
     * Kosong selama tanggal batas belum terlewati: sebelum tanggal 3
     * berakhir, belum ada yang terlambat. Yang berstatus tidak_aktif
     * dikecualikan — PJP yang memang sudah tidak bekerja lagi tidak perlu
     * muncul sebagai tunggakan setiap bulan.
     *
     * Seluruh batas waktunya ditetapkan menurut kalender SETEMPAT lalu
     * dipindah ke UTC untuk dibandingkan, bukan dengan whereMonth() atas
     * kolom UTC. Bulan menurut UTC bergeser delapan jam dari bulan yang
     * dimaksud orang, sehingga unggahan awal bulan dapat terbaca sebagai
     * bulan sebelumnya — tidak terhitung sama sekali, dan perusahaan yang
     * sudah melapor tetap tertagih.
     */
    public static function belumLaporanBulananBulanIni(): Collection
    {
        $kini = Waktu::kini();

        if ($kini->day <= PjpLaporan::BATAS_TANGGAL_LAPORAN) {
            return collect();
        }

        $awal  = $kini->copy()->startOfMonth();
        $batas = $awal->copy()->addDays(PjpLaporan::BATAS_TANGGAL_LAPORAN);
        $akhir = $kini->copy()->endOfMonth();

        // Dibandingkan dalam UTC, zona penyimpanannya.
        $awalUtc  = $awal->copy()->utc();
        $batasUtc = $batas->copy()->utc();
        $akhirUtc = $akhir->copy()->utc();

        $bulanIni = fn (Builder $q) => $q->where('jenis', 'laporan_bulanan')
            ->whereBetween('created_at', [$awalUtc, $akhirUtc]);

        return static::query()
            ->where('status', '!=', 'tidak_aktif')
            ->where(function (Builder $luar) use ($bulanIni, $batasUtc) {
                $luar->whereDoesntHave('laporans', $bulanIni)
                     ->orWhereHas('laporans', fn (Builder $q) => $bulanIni($q)
                         ->where('created_at', '>', $batasUtc));
            })
            ->orderBy('nama_perusahaan')
            ->get(['id', 'nama_perusahaan']);
    }

    /**
     * PJP dengan achievement di bawah ambang, terendah lebih dulu.
     *
     * Dihitung di PHP, bukan lewat kueri: achievement menggabungkan tiga
     * skor yang masing-masing membaca tabel berbeda, dan tidak ada satu
     * kolom pun yang dapat diurutkan basis data.
     *
     * Karena itu ia memuat seluruh PJP beserta relasinya sekali di muka
     * (eager load), bukan membiarkan tiap baris menanyakan jawabannya
     * sendiri. Tanpa itu, halaman beranda modul menjalankan sekitar lima
     * kueri tambahan PER PJP — pada aplikasi asal tercatat 78 kueri untuk
     * tujuh PJP saja, dan jumlah itu tumbuh diam-diam bersama datanya.
     */
    public static function perluPerhatian(int $ambil = 5): Collection
    {
        return static::query()
            ->with(['laporans', 'evaluasis', 'smkpChecklistAnswers'])
            ->get()
            ->map(fn (Pjp $pjp) => [
                'id'              => $pjp->id,
                'nama_perusahaan' => $pjp->nama_perusahaan,
                'achievement'     => $pjp->achievement(),
            ])
            ->filter(fn (array $b) => $b['achievement'] !== null
                                   && $b['achievement'] < self::AMBANG_PERHATIAN)
            ->sortBy('achievement')
            ->take($ambil)
            ->values();
    }
}
