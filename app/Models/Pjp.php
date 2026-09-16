<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pjp extends Model
{
    use HasFactory;

    public const STATUS = [
        'aktif' => 'Aktif Dipantau',
        'perlu_tindak_lanjut' => 'Perlu Tindak Lanjut',
        'tidak_aktif' => 'Tidak Aktif',
    ];

    protected $fillable = [
        'nama_perusahaan',
        'nib',
        'penanggung_jawab',
        'alamat',
        'status',
        'catatan',
    ];

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
        return $this->hasMany(PjpEvaluasi::class)->orderByDesc('tahun')->orderByDesc('semester');
    }

    /**
     * Riwayat catatan bertimestamp — beda dari kolom `catatan` di atas
     * (satu teks ringkas yang bisa ditimpa lewat form Ubah Data), ini log
     * tambah-saja: setiap entri baru menambah baris, bukan menimpa yang
     * lama, supaya ada jejak kenapa/kapan sesuatu berubah dari waktu ke
     * waktu.
     */
    public function catatans(): HasMany
    {
        // `latest('id')`, bukan `latest()` biasa (yang urut dari `created_at`
        // saja) — dua catatan yang ditambahkan dalam detik yang sama akan
        // punya `created_at` identik, jadi butuh `id` sebagai tiebreaker
        // supaya urutan "terbaru dulu" tetap benar dan deterministik.
        return $this->hasMany(PjpCatatan::class)->latest('id');
    }

    /**
     * Skor kepatuhan checklist prakualifikasi SMKP, dihitung dari kategori
     * berbobot A-P (kategori Dokumen Legalitas tidak ikut dihitung karena
     * berupa syarat wajib terpisah, bukan bagian dari sistem bobot 180).
     * Item dengan nilai N/A dikeluarkan dari total bobot maupun skor; item
     * yang belum diisi tetap menyumbang bobot ke penyebut (skor 0).
     */
    public function smkpScore(): array
    {
        $breakdown = $this->smkpCategoryBreakdown();

        $totalBobot = array_sum(array_column($breakdown, 'bobot_dinilai'));
        $totalSkor = array_sum(array_column($breakdown, 'skor'));

        $persentase = $totalBobot > 0 ? round($totalSkor / $totalBobot * 100, 1) : 0.0;

        return [
            'total_bobot' => $totalBobot,
            'total_skor' => round($totalSkor, 1),
            'persentase' => $persentase,
            'kategori_risiko' => self::kategoriRisikoFor($persentase),
        ];
    }

    /**
     * Rincian skor checklist SMKP per kategori (A-P, tidak termasuk
     * LEGALITAS), supaya kelihatan kategori mana yang paling lemah —
     * bukan cuma satu angka persentase total.
     */
    public function smkpCategoryBreakdown(): array
    {
        $answers = $this->smkpChecklistAnswers()->get()->keyBy('smkp_checklist_item_id');

        $categories = SmkpChecklistCategory::query()
            ->where('kode', '!=', 'LEGALITAS')
            ->orderBy('urutan')
            ->with('items')
            ->get();

        return $categories->map(function (SmkpChecklistCategory $category) use ($answers) {
            $bobotDinilai = 0;
            $skor = 0.0;

            foreach ($category->items as $item) {
                $nilai = $answers->get($item->id)?->nilai;

                if ($nilai === 'na') {
                    continue;
                }

                $bobotDinilai += $item->bobot;
                $skor += $item->bobot * ((int) ($nilai ?? 0) / 3);
            }

            return [
                'kode' => $category->kode,
                'nama' => $category->nama,
                'bobot' => $category->bobot,
                'bobot_dinilai' => $bobotDinilai,
                'skor' => round($skor, 1),
                'persentase' => $bobotDinilai > 0 ? round($skor / $bobotDinilai * 100, 1) : 0.0,
            ];
        })->values()->toArray();
    }

    /**
     * Status kelengkapan syarat wajib Dokumen Legalitas — terpisah dari
     * skor 180 checklist SMKP, jadi cuma dihitung "berapa dari total item
     * yang sudah dijawab Y", bukan skor 0-3.
     */
    public function smkpLegalitasStatus(): array
    {
        $answers = $this->smkpChecklistAnswers()->get()->keyBy('smkp_checklist_item_id');

        $items = SmkpChecklistCategory::where('kode', 'LEGALITAS')->first()?->items ?? collect();

        return [
            'total' => $items->count(),
            'lengkap' => $items->filter(fn (SmkpChecklistItem $item) => $answers->get($item->id)?->jawaban === 'ya')->count(),
        ];
    }

    /**
     * Skor kepatuhan pelaporan Tahap 2, dari rata-rata dua hal yang sudah
     * ada: persentase laporan yang tepat waktu, dan (kalau ada yang sudah
     * dievaluasi) persentase yang dinilai sesuai isinya. `null` artinya PJP
     * ini belum pernah mengunggah laporan sama sekali — beda dari skor 0,
     * supaya tidak salah ditandai "kritis" padahal cuma belum ada datanya.
     */
    public function pelaporanScore(): ?float
    {
        $laporans = $this->laporans()->get();

        if ($laporans->isEmpty()) {
            return null;
        }

        $rateTepatWaktu = $laporans->filter(fn (PjpLaporan $l) => $l->tepat_waktu)->count() / $laporans->count() * 100;

        $dievaluasi = $laporans->whereNotNull('kesesuaian_isi');
        $rateSesuai = $dievaluasi->isNotEmpty()
            ? $dievaluasi->filter(fn (PjpLaporan $l) => $l->kesesuaian_isi === 'sesuai')->count() / $dievaluasi->count() * 100
            : null;

        return round($rateSesuai !== null ? ($rateTepatWaktu + $rateSesuai) / 2 : $rateTepatWaktu, 1);
    }

    /**
     * Skor achievement keseluruhan PJP — setiap PJP berjalan di ketiga tahap
     * (Persyaratan, Pelaporan, Evaluasi) secara bersamaan, bukan bergantian,
     * jadi achievement dipakai untuk ringkasan lintas-tahap (widget "Paling
     * Perlu Perhatian" di Beranda, badge sidebar) dan diambil dari skor
     * TERENDAH di antara ketiganya yang tersedia — bukan rata-rata — supaya
     * satu area yang buruk tidak "tertutup" oleh dua area lain yang baik.
     * Skor SMKP selalu ada (checklist kosong = 0%, bukan null), sedangkan
     * pelaporan/evaluasi bisa null kalau memang belum ada datanya sama
     * sekali — null itu diabaikan, bukan dihitung sebagai 0.
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

    /**
     * Jumlah PJP dengan achievement() di bawah 80 — dipakai oleh badge
     * ringkas di sidebar (lihat HandleInertiaRequests) dan sejalan dengan
     * daftar "PJP Paling Perlu Perhatian" di Beranda.
     */
    public static function perluPerhatianCount(): int
    {
        return static::all(['id'])
            ->filter(fn (Pjp $pjp) => ($achievement = $pjp->achievement()) !== null && $achievement < 80)
            ->count();
    }

    private static function kategoriRisikoFor(float $persentase): string
    {
        return match (true) {
            $persentase > 75 => 'Kritis',
            $persentase >= 55 => 'Tinggi',
            $persentase >= 36 => 'Sedang',
            $persentase >= 20 => 'Rendah',
            default => 'Sangat Rendah',
        };
    }

    public function scopeFilter(
        Builder $query,
        ?string $search,
        ?string $status,
    ): Builder {
        return $query
            ->when($search, fn ($q, $search) => $q->where(function (Builder $q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('nib', 'like', "%{$search}%")
                    ->orWhere('penanggung_jawab', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%");
            }))
            ->when($status, fn ($q, $status) => $q->where('status', $status));
    }

    /**
     * PJP (yang masih dipantau, bukan tidak_aktif) yang belum mengirim Laporan
     * Bulanan bulan ini, atau mengirimnya lewat dari tanggal batas. Kosong
     * sebelum tanggal batas terlewati (belum dianggap terlambat).
     */
    public static function belumLaporanBulananBulanIni(): \Illuminate\Support\Collection
    {
        if (now()->day <= PjpLaporan::BATAS_TANGGAL_LAPORAN) {
            return collect();
        }

        $batasBulanIni = now()->startOfMonth()->addDays(PjpLaporan::BATAS_TANGGAL_LAPORAN);

        return static::query()
            ->where('status', '!=', 'tidak_aktif')
            ->where(function (Builder $outer) use ($batasBulanIni) {
                $outer->whereDoesntHave('laporans', function (Builder $query) {
                    $query->where('jenis', 'laporan_bulanan')
                        ->whereYear('created_at', now()->year)
                        ->whereMonth('created_at', now()->month);
                })->orWhereHas('laporans', function (Builder $query) use ($batasBulanIni) {
                    $query->where('jenis', 'laporan_bulanan')
                        ->whereYear('created_at', now()->year)
                        ->whereMonth('created_at', now()->month)
                        ->where('created_at', '>', $batasBulanIni);
                });
            })
            ->orderBy('nama_perusahaan')
            ->get(['id', 'nama_perusahaan']);
    }

    /**
     * Count of records per status, always including every status key (0 if none).
     */
    public static function statusCountsFor(?Builder $query = null): array
    {
        $query ??= static::query();

        $counts = $query->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(array_keys(self::STATUS))
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
            ->toArray();
    }
}
