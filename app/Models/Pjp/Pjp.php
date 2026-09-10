<?php

namespace App\Models\Pjp;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as KoleksiEloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Satu perusahaan jasa pertambangan yang dipantau.
 *
 * `company_id` menyebut PEMEGANG IUP yang memantau, bukan perusahaan
 * jasanya sendiri. Satu perusahaan jasa yang bekerja pada dua IUP muncul
 * sebagai dua baris — dan itu benar: penilaian, laporan, dan evaluasinya
 * memang dua berkas berbeda milik dua pihak berbeda, dan masing-masing
 * hanya boleh melihat berkasnya sendiri.
 *
 * TIGA TAHAP BERJALAN SEKALIGUS, BUKAN BERGANTIAN.
 *
 * Persyaratan (daftar periksa SMKP), Pelaporan (dokumen berkala), dan
 * Evaluasi (nilai semesteran) dijalani satu PJP pada waktu yang sama.
 * Tidak ada kolom "tahap sekarang", dan ketiadaannya disengaja: kolom
 * semacam itu membuat halaman Pelaporan menyembunyikan PJP yang
 * kebetulan tercatat "sedang dievaluasi", padahal laporan bulanannya
 * tetap wajib dan keterlambatannya tetap dihitung.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Pjp extends Model
{
    use BerpemilikPerusahaan;

    public const STATUS = [
        'aktif'               => 'Aktif Dipantau',
        'perlu_tindak_lanjut' => 'Perlu Tindak Lanjut',
        'tidak_aktif'         => 'Tidak Aktif',
    ];

    /** Batas achievement yang membuat sebuah PJP masuk daftar perhatian. */
    public const AMBANG_PERHATIAN = 80;

    protected $table = 'pjp_perusahaan';

    protected $fillable = [
        'company_id', 'nama_perusahaan', 'nib',
        'penanggung_jawab', 'alamat', 'status', 'catatan',
    ];

    /**
     * Status awal ikut ada DI MEMORI, bukan hanya di basis data.
     *
     * Default kolom baru terbaca sesudah barisnya dimuat ulang, sehingga
     * baris yang baru dibuat lalu langsung dikirim ke layar punya
     * `status` bernilai null — dan lencana statusnya menggambar kotak
     * kosong tanpa satu galat pun.
     */
    protected $attributes = ['status' => 'aktif'];

    /* ═══════════════════ relasi ═══════════════════ */

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function laporan()
    {
        return $this->hasMany(Laporan::class, 'pjp_id')->latest();
    }

    public function evaluasi()
    {
        return $this->hasMany(Evaluasi::class, 'pjp_id')
            ->orderByDesc('tahun')->orderByDesc('semester');
    }

    public function smkpJawaban()
    {
        return $this->hasMany(SmkpJawaban::class, 'pjp_id');
    }

    /**
     * Ketiga relasi yang dipakai menghitung skor, sekali jalan.
     *
     * Dipakai setiap halaman yang menampilkan skor untuk lebih dari satu
     * PJP. Tanpa ini tiap baris membaca basis data tiga kali lagi, dan
     * ketiganya tidak terlihat di mana pun kecuali pada waktu muat
     * halaman yang perlahan-lahan memburuk seiring bertambahnya mitra.
     */
    public function scopeDenganSkor(Builder $q): Builder
    {
        return $q->with(['laporan', 'evaluasi', 'smkpJawaban']);
    }

    /* ═══════════════════ skor ═══════════════════ */

    /**
     * Skor kepatuhan daftar periksa prakualifikasi SMKP.
     *
     * Kategori LEGALITAS TIDAK ikut dihitung: ia syarat wajib yang
     * dijawab ada/tidak ada, bukan mutu yang dinilai 0–3. Menjumlahkannya
     * ke dalam skor 178 membuat PJP yang dokumen izinnya lengkap tetapi
     * sistem keselamatannya kosong memperoleh nilai yang tidak nol.
     *
     * Butir bernilai 'na' dikeluarkan dari pembilang MAUPUN penyebut.
     * Butir yang belum diisi tetap menyumbang bobot penuh ke penyebut
     * dengan skor nol — daftar periksa yang belum dikerjakan berarti
     * belum dibuktikan, bukan berarti tidak berlaku.
     */
    public function smkpScore(): array
    {
        $rincian = $this->smkpCategoryBreakdown();

        $totalBobot = array_sum(array_column($rincian, 'bobot_dinilai'));
        $totalSkor  = array_sum(array_column($rincian, 'skor'));

        $persentase = $totalBobot > 0 ? round($totalSkor / $totalBobot * 100, 1) : 0.0;

        return [
            'total_bobot' => $totalBobot,
            'total_skor'  => round($totalSkor, 1),
            'persentase'  => $persentase,
            'kelayakan'   => self::kelayakanUntuk($persentase),
        ];
    }

    /**
     * Rincian skor per kategori A–P.
     *
     * Satu angka persentase total memberi tahu bahwa ada yang kurang,
     * tidak memberi tahu di mana. Yang dipakai orang menyusun rencana
     * perbaikan selalu barisnya.
     */
    public function smkpCategoryBreakdown(): array
    {
        $jawaban = $this->jawabanTerkunci();

        return SmkpKategori::berbobot()->map(function (SmkpKategori $kategori) use ($jawaban) {
            $bobotDinilai = 0;
            $skor         = 0.0;

            foreach ($kategori->items as $butir) {
                $nilai = $jawaban->get($butir->id)?->nilai;

                if ($nilai === 'na') continue;

                $bobotDinilai += $butir->bobot;
                $skor         += $butir->bobot * ((int) ($nilai ?? 0) / SmkpJawaban::NILAI_PENUH);
            }

            return [
                'kode'          => $kategori->kode,
                'nama'          => $kategori->nama,
                'bobot'         => $kategori->bobot,
                'bobot_dinilai' => $bobotDinilai,
                'skor'          => round($skor, 1),
                'persentase'    => $bobotDinilai > 0 ? round($skor / $bobotDinilai * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Kelengkapan syarat wajib Dokumen Legalitas.
     *
     * Dihitung sebagai "berapa dari sekian yang sudah dijawab Y", bukan
     * sebagai persentase — sebab syarat wajib tidak punya nilai tengah.
     * Sembilan dari sepuluh izin bukan berarti 90% boleh bekerja.
     */
    public function smkpLegalitasStatus(): array
    {
        $jawaban = $this->jawabanTerkunci();
        $butir   = SmkpKategori::butirLegalitas();

        return [
            'total'   => $butir->count(),
            'lengkap' => $butir->filter(
                fn (SmkpItem $b) => $jawaban->get($b->id)?->jawaban === 'ya'
            )->count(),
        ];
    }

    /**
     * Skor kepatuhan pelaporan.
     *
     * Rata-rata dua hal: berapa persen dokumen yang tepat waktu, dan —
     * bila sudah ada yang diperiksa — berapa persen yang isinya dinilai
     * sesuai. Yang belum diperiksa tidak dihitung sebagai tidak sesuai;
     * belum diperiksa berarti belum diketahui.
     *
     * `null` berarti PJP ini belum pernah mengunggah apa pun — sengaja
     * dibedakan dari 0, supaya yang baru terdaftar kemarin tidak
     * langsung muncul di daftar "paling perlu perhatian" mendahului
     * mitra yang sungguh-sungguh menunggak.
     */
    public function pelaporanScore(): ?float
    {
        $laporan = $this->laporanTerkunci();

        if ($laporan->isEmpty()) return null;

        $tepatWaktu = $laporan->filter(fn (Laporan $l) => $l->tepat_waktu)->count()
            / $laporan->count() * 100;

        $diperiksa = $laporan->whereNotNull('kesesuaian_isi');

        $sesuai = $diperiksa->isNotEmpty()
            ? $diperiksa->filter(fn (Laporan $l) => $l->kesesuaian_isi === 'sesuai')->count()
                / $diperiksa->count() * 100
            : null;

        return round($sesuai !== null ? ($tepatWaktu + $sesuai) / 2 : $tepatWaktu, 1);
    }

    /**
     * Skor keseluruhan — diambil dari yang TERENDAH, bukan rata-rata.
     *
     * Ketiga tahap berjalan bersamaan, jadi angka ringkasnya harus
     * menjawab "apakah mitra ini aman dipakai", bukan "berapa nilai
     * rapornya". Rata-rata membuat satu area yang buruk tertutup dua
     * area yang baik: daftar periksa 100, evaluasi 100, dan pelaporan 10
     * menjadi 70 — angka yang terbaca cukup, atas keadaan yang sama
     * sekali tidak.
     *
     * Skor SMKP selalu ada (daftar periksa kosong = 0%, bukan null);
     * pelaporan dan evaluasi boleh null bila memang belum ada datanya,
     * dan null diabaikan alih-alih dihitung nol.
     */
    public function achievement(): ?float
    {
        $skor = array_filter([
            $this->smkpScore()['persentase'],
            $this->pelaporanScore(),
            $this->evaluasiTerkunci()->first()?->skor_rata_rata,
        ], fn (?float $v) => $v !== null);

        return $skor === [] ? null : min($skor);
    }

    /** Achievement di bawah ambang — perlu dilihat, bukan sekadar dicatat. */
    public function perluPerhatian(): bool
    {
        $skor = $this->achievement();

        return $skor !== null && $skor < self::AMBANG_PERHATIAN;
    }

    /**
     * Sampai risiko pekerjaan setinggi apa mitra ini layak dipakai.
     *
     * BUKAN tingkat bahaya PJP-nya — arahnya justru sebaliknya, dan
     * itulah yang paling mudah salah dibaca. Skor 100% berlabel "Kritis"
     * berarti mitra ini layak menangani pekerjaan berisiko kritis, bukan
     * bahwa keadaannya kritis. Karena itu namanya `kelayakan`, dan
     * layarnya wajib menuliskannya sebagai "Layak s.d. …" — label
     * telanjang "Kritis" pada angka 100 akan dibaca terbalik oleh setiap
     * orang yang belum membaca komentar ini.
     */
    private static function kelayakanUntuk(float $persentase): string
    {
        return match (true) {
            $persentase > 75  => 'Kritis',
            $persentase >= 55 => 'Tinggi',
            $persentase >= 36 => 'Sedang',
            $persentase >= 20 => 'Rendah',
            default           => 'Sangat Rendah',
        };
    }

    /* ═══════════════════ pembacaan relasi yang tidak mengulang kueri ═══════════════════
     *
     * Ketiganya memakai relasi yang SUDAH dimuat bila memang sudah —
     * lihat scopeDenganSkor. Menulis `$this->laporan()->get()` di sini
     * akan tetap benar hasilnya tetapi membaca ulang basis data pada
     * tiap baris, membatalkan eager load yang sudah dilakukan
     * pemanggilnya tanpa satu tanda pun.
     */

    private function laporanTerkunci(): KoleksiEloquent
    {
        return $this->relationLoaded('laporan') ? $this->laporan : $this->laporan()->get();
    }

    private function evaluasiTerkunci(): KoleksiEloquent
    {
        return $this->relationLoaded('evaluasi') ? $this->evaluasi : $this->evaluasi()->get();
    }

    private function jawabanTerkunci(): Collection
    {
        $jawaban = $this->relationLoaded('smkpJawaban')
            ? $this->smkpJawaban
            : $this->smkpJawaban()->get();

        return $jawaban->keyBy('item_id');
    }

    /* ═══════════════════ kueri ═══════════════════ */

    public function scopeFilter(Builder $q, ?string $cari, ?string $status): Builder
    {
        return $q
            ->when($cari, fn (Builder $q, string $cari) => $q->where(function (Builder $w) use ($cari) {
                $w->where('nama_perusahaan', 'like', "%{$cari}%")
                  ->orWhere('nib', 'like', "%{$cari}%")
                  ->orWhere('penanggung_jawab', 'like', "%{$cari}%");
            }))
            ->when($status, fn (Builder $q, string $status) => $q->where('status', $status));
    }

    /**
     * PJP yang masih dipantau tetapi belum mengirim Laporan Bulanan
     * bulan ini, atau mengirimnya lewat tanggal batas.
     *
     * Kosong selama tanggal batas belum terlewati — sebelum tanggal 4
     * tidak ada yang terlambat, dan daftar yang menyebut seluruh mitra
     * pada tanggal 1 membuat orang berhenti membacanya.
     *
     * BATAS BULANNYA DIHITUNG DI ZONA TAMBANG lalu diubah ke UTC,
     * bukan dengan whereYear/whereMonth atas kolom UTC. Pada WITA (+8)
     * seluruh dokumen yang diunggah tanggal 1 sebelum pukul 08.00 pagi
     * tersimpan sebagai bulan SEBELUMNYA menurut UTC — dan penyaring
     * yang membandingkan bulan UTC tidak akan menemukannya, lalu
     * melaporkan mitra yang sudah mengirim sebagai belum mengirim.
     */
    public static function belumLaporanBulananBulanIni(): Collection
    {
        $kini = Waktu::kini();

        if ($kini->day <= Laporan::BATAS_TANGGAL) return collect();

        $awalBulan  = $kini->copy()->startOfMonth();
        $batasKirim = $awalBulan->copy()->addDays(Laporan::BATAS_TANGGAL);
        $akhirBulan = $kini->copy()->endOfMonth();

        [$awalUtc, $batasUtc, $akhirUtc] = [
            $awalBulan->copy()->utc(),
            $batasKirim->copy()->utc(),
            $akhirBulan->copy()->utc(),
        ];

        $bulanan = fn (Builder $q) => $q
            ->where('jenis', 'laporan_bulanan')
            ->whereBetween('created_at', [$awalUtc, $akhirUtc]);

        return static::query()
            ->where('status', '!=', 'tidak_aktif')
            ->where(function (Builder $luar) use ($bulanan, $batasUtc) {
                $luar
                    ->whereDoesntHave('laporan', $bulanan)
                    ->orWhereHas('laporan', fn (Builder $q) => $bulanan($q)
                        ->where('created_at', '>', $batasUtc));
            })
            ->orderBy('nama_perusahaan')
            ->get(['id', 'nama_perusahaan']);
    }

    /**
     * Jumlah baris per status, SELALU menyebut setiap status.
     *
     * Status yang kosong tetap disebut dengan nilai nol. Kartu yang
     * hilang dari layar ketika angkanya nol membuat susunan kartunya
     * berubah-ubah, dan pembacanya menyimpulkan statusnya sudah tidak
     * ada lagi.
     */
    public static function statusCountsFor(?Builder $q = null): array
    {
        $jumlah = ($q ?? static::query())
            ->selectRaw('status, count(*) as jml')
            ->groupBy('status')
            ->pluck('jml', 'status');

        return collect(array_keys(self::STATUS))
            ->mapWithKeys(fn (string $s) => [$s => (int) ($jumlah[$s] ?? 0)])
            ->all();
    }
}
