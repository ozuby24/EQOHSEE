<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Alur;
use App\Support\Izin;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu izin kerja aman.
 *
 * Persetujuannya adalah penerbitannya: izin yang disetujui berarti
 * pekerjaan boleh dimulai. Dua hal yang membedakannya dari alur
 * persetujuan lain di aplikasi ini:
 *
 * - Ia kedaluwarsa. Status "disetujui" tidak berarti "berlaku
 *   sekarang", dan keduanya sengaja dijawab terpisah — izin yang sudah
 *   lewat waktunya tetap berstatus disetujui, dan pada tampilan itulah
 *   yang membuatnya terlihat sah.
 *
 * - Ia harus ditutup. Penutupan disimpan di kolomnya sendiri, bukan
 *   sebagai status, sehingga "sudah lewat tetapi belum ditutup" dapat
 *   ditanyakan langsung alih-alih disimpulkan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class IzinKerja extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    /** `status` sengaja tidak ada di sini — ia hanya berpindah lewat Ditinjau. */
    protected $fillable = [
        'company_id', 'user_id', 'nomor', 'jenis', 'lokasi', 'uraian', 'pelaksana',
        'jumlah_pekerja', 'pengawas_lapangan', 'mulai', 'selesai', 'batas_uji_menit', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'mulai'           => 'datetime',
            'selesai'         => 'datetime',
            'ditutup_pada'    => 'datetime',
            'diajukan_pada'   => 'datetime',
            'ditinjau_pada'   => 'datetime',
            'jumlah_pekerja'  => 'integer',
            'batas_uji_menit' => 'integer',
        ];
    }

    /**
     * Penutupan boleh dicatat setelah izin diterbitkan.
     *
     * Satu-satunya pengecualian atas kunci "yang sudah disetujui tidak
     * dapat diubah", dan alasannya bukan kemudahan: menutup izin adalah
     * penandaan bahwa pekerjaannya selesai, bukan penyuntingan isi izin.
     * Tanpa pengecualian ini, tidak ada satu pun izin yang pernah dapat
     * ditutup — dan seluruh peringatan "lewat waktu belum ditutup" akan
     * benar selamanya.
     *
     * Penulisannya tetap sekali saja: controller menolak penutupan pada
     * izin yang sudah tertutup.
     */
    protected function kolomSetelahDisetujui(): array
    {
        return array_merge(Alur::KOLOM_SETELAH_DISETUJUI, [
            'ditutup_pada', 'ditutup_oleh', 'catatan_penutupan',
        ]);
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function penutup(): BelongsTo  { return $this->belongsTo(User::class, 'ditutup_oleh'); }
    public function periksa(): HasMany    { return $this->hasMany(IzinPeriksa::class, 'izin_kerja_id'); }
    public function gas(): HasMany        { return $this->hasMany(IzinGas::class, 'izin_kerja_id')->orderByDesc('waktu_uji'); }

    /* ---------- keadaan ---------- */

    public function keadaanWaktu(?Carbon $sekarang = null): array
    {
        return Izin::keadaanWaktu($this->mulai, $this->selesai, $sekarang);
    }

    public function sudahDitutup(): bool
    {
        return $this->ditutup_pada !== null;
    }

    /**
     * Diterbitkan dan masih dalam masa berlakunya.
     *
     * Inilah pertanyaan yang benar-benar dipakai — "boleh bekerja
     * sekarang?" — dan ia menuntut tiga jawaban sekaligus, bukan satu.
     */
    public function sedangBerlaku(?Carbon $sekarang = null): bool
    {
        return $this->sudahDisetujui()
            && !$this->sudahDitutup()
            && $this->keadaanWaktu($sekarang)['kelas'] === 'berlaku';
    }

    /** Sudah lewat waktunya tetapi tidak pernah ditutup. */
    public function lewatBelumDitutup(?Carbon $sekarang = null): bool
    {
        return $this->sudahDisetujui()
            && !$this->sudahDitutup()
            && $this->keadaanWaktu($sekarang)['kelas'] === 'lewat';
    }

    public function perluUjiGas(): bool
    {
        return Izin::perluUjiGas($this->jenis);
    }

    public function batasUji(): int
    {
        return $this->batas_uji_menit ?: Izin::USIA_UJI_GAS_MENIT;
    }

    public function ujiTerakhir(): ?IzinGas
    {
        return $this->gas->first();
    }

    /** Uji gas terakhir masih segar terhadap batas yang berlaku. */
    public function ujiMasihSegar(?Carbon $sekarang = null): bool
    {
        return (bool) $this->ujiTerakhir()?->segar($sekarang, $this->batasUji());
    }

    /* ---------- kelengkapan syarat ---------- */

    /** @return \Illuminate\Support\Collection<int,IzinPeriksa> */
    public function wajibBelumTerpenuhi()
    {
        return $this->periksa->filter(fn (IzinPeriksa $p) => $p->wajib && !$p->terpenuhi);
    }

    /**
     * Apakah izin ini boleh diterbitkan.
     *
     * Dijawab di model, bukan hanya di controller, supaya tampilan dapat
     * menyebutkan alasannya sebelum tombolnya ditekan — penolakan yang
     * baru muncul setelah diklik membuat orang mencoba lagi dengan cara
     * lain, bukan melengkapi syaratnya.
     *
     * @return array{boleh:bool,alasan:list<string>}
     */
    public function siapDiterbitkan(array $ambang, ?Carbon $sekarang = null): array
    {
        $alasan = [];

        $kurang = $this->wajibBelumTerpenuhi();
        if ($kurang->isNotEmpty()) {
            $alasan[] = $kurang->count().' syarat wajib belum terpenuhi.';
        }

        if ($this->perluUjiGas()) {
            $uji = $this->ujiTerakhir();

            if (!$uji) {
                $alasan[] = 'Belum ada uji gas, sedangkan jenis izin ini menuntutnya.';
            } elseif (!$uji->segar($sekarang, $this->batasUji())) {
                $alasan[] = 'Uji gas terakhir sudah melewati batas '.$this->batasUji().' menit.';
            } else {
                $h = $uji->periksa($ambang);
                if (!$h['lulus']) $alasan[] = 'Bacaan gas terakhir di luar ambang.';
                if (!$h['lengkap']) $alasan[] = 'Ada parameter gas yang belum diukur.';
            }
        }

        if ($this->selesai && $this->mulai && $this->selesai->lessThanOrEqualTo($this->mulai)) {
            $alasan[] = 'Waktu selesai tidak setelah waktu mulai.';
        }

        return ['boleh' => $alasan === [], 'alasan' => $alasan];
    }

    public function toView(array $ambang = [], ?Carbon $sekarang = null): array
    {
        $waktu = $this->keadaanWaktu($sekarang);
        $siap  = $ambang === [] ? ['boleh' => false, 'alasan' => []] : $this->siapDiterbitkan($ambang, $sekarang);

        return [
            'id'      => $this->id,
            'nomor'   => $this->nomor,
            'jenis'   => $this->jenis,
            'lokasi'  => $this->lokasi,
            'uraian'  => $this->uraian,
            'pelaksana' => $this->pelaksana,
            'pekerja' => $this->jumlah_pekerja,
            'pengawas'=> $this->pengawas_lapangan,

            'mulai'   => $this->mulai?->format('d M Y H:i'),
            'selesai' => $this->selesai?->format('d M Y H:i'),
            'mulaiIso'=> $this->mulai?->toIso8601String(),
            'waktu'   => $waktu,

            'perluUjiGas' => $this->perluUjiGas(),
            'batasUji'    => $this->batasUji(),
            'ujiSegar'    => $this->ujiMasihSegar($sekarang),
            'gas'         => $this->gas->map(fn (IzinGas $g) => $g->toView($ambang, $this->batasUji()))->values(),

            'periksa'      => $this->periksa->map(fn (IzinPeriksa $p) => $p->toView())->values(),
            'wajibKurang'  => $this->wajibBelumTerpenuhi()->count(),
            'siap'         => $siap,

            'sedangBerlaku'     => $this->sedangBerlaku($sekarang),
            'lewatBelumDitutup' => $this->lewatBelumDitutup($sekarang),
            'ditutup'           => $this->sudahDitutup(),
            'ditutupPada'       => $this->ditutup_pada?->format('d M Y H:i'),
            'ditutupOleh'       => $this->penutup?->name,
            'catatanPenutupan'  => $this->catatan_penutupan,

            'status'      => $this->status,
            'statusLabel' => Alur::LABEL[$this->status] ?? $this->status,
            'catatan'     => $this->catatan,

            'alur' => [
                'dapatDiubah'   => $this->dapatDiubah(),
                'dapatDiajukan' => $this->dapatDiubah(),
                'dapatDitinjau' => $this->dapatDitinjauOleh(auth()->user()),
                'dapatDitutup'  => $this->sudahDisetujui() && !$this->sudahDitutup(),
                'pengaju'       => $this->pengaju?->name,
                'peninjau'      => $this->peninjau?->name,
                'alasanTolak'   => $this->alasan_tolak,
            ],
        ];
    }
}
