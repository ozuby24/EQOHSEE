<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Alur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Memasang alur tinjauan pada sebuah model.
 *
 * Modelnya perlu punya kolom: status, diajukan_oleh, diajukan_pada,
 * ditinjau_oleh, ditinjau_pada, alasan_tolak.
 *
 * Status sengaja dikeluarkan dari $fillable oleh pemakainya. Selama ia
 * masih dapat diisi massal, satu baris `create($request->validated())`
 * di modul mana pun akan membuka kembali lubang yang ditutup di sini —
 * dan lubang itu tidak menimbulkan galat, hanya angka yang tidak pernah
 * ditinjau tapi terlihat sudah ditinjau.
 */
trait Ditinjau
{
    public static function bootDitinjau(): void
    {
        // Baris baru selalu lahir sebagai draf, apa pun isi payload-nya.
        static::creating(function ($model) {
            $model->status = Alur::DRAF;
            $model->diajukan_oleh = null;
            $model->diajukan_pada = null;
            $model->ditinjau_oleh = null;
            $model->ditinjau_pada = null;
            $model->alasan_tolak  = null;
        });

        // Yang sudah disetujui tidak boleh berubah isinya. Tanpa penjagaan
        // ini, angka yang sudah masuk laporan resmi masih dapat disunting
        // diam-diam lewat halaman ubah biasa.
        static::updating(function ($model) {
            $asli = $model->getOriginal('status');

            if ($asli !== Alur::DISETUJUI) return;

            $lain = array_diff(array_keys($model->getDirty()), [
                'status', 'ditinjau_oleh', 'ditinjau_pada', 'alasan_tolak', 'updated_at',
            ]);

            if ($lain !== []) {
                throw new RuntimeException(
                    'Data yang sudah disetujui tidak dapat diubah. Tolak dulu, '.
                    'atau buat data pembetulan.'
                );
            }
        });
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }

    /* ---------- pertanyaan ---------- */

    public function sudahDisetujui(): bool
    {
        return $this->status === Alur::DISETUJUI;
    }

    public function menungguTinjauan(): bool
    {
        return $this->status === Alur::DIAJUKAN;
    }

    public function dapatDiubah(): bool
    {
        return in_array($this->status, [Alur::DRAF, Alur::DITOLAK], true);
    }

    /**
     * Seseorang boleh meninjau baris ini bila ia memegang hak peninjau
     * DAN bukan pengajunya sendiri.
     */
    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!Alur::peninjau($u)) return false;
        if (!$this->menungguTinjauan()) return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    /* ---------- perpindahan ---------- */

    public function ajukan(?User $u = null): void
    {
        $this->pindah(Alur::DIAJUKAN);

        $this->diajukan_oleh = ($u ?? auth()->user())?->getKey();
        $this->diajukan_pada = now();
        $this->alasan_tolak  = null;
        $this->save();
    }

    public function setujui(?User $u = null): void
    {
        $u ??= auth()->user();

        if (!$this->dapatDitinjauOleh($u)) {
            throw new RuntimeException('Anda tidak berhak menyetujui data ini.');
        }

        $this->pindah(Alur::DISETUJUI);
        $this->tandaiPeninjau($u);
    }

    public function tolak(string $alasan, ?User $u = null): void
    {
        $u ??= auth()->user();

        if (!$this->dapatDitinjauOleh($u)) {
            throw new RuntimeException('Anda tidak berhak menolak data ini.');
        }

        $this->pindah(Alur::DITOLAK);
        $this->alasan_tolak = $alasan;
        $this->tandaiPeninjau($u);
    }

    /** Menarik kembali pengajuan, oleh pengajunya sendiri. */
    public function tarik(): void
    {
        $this->pindah(Alur::DRAF);
        $this->diajukan_oleh = null;
        $this->diajukan_pada = null;
        $this->save();
    }

    private function tandaiPeninjau(?User $u): void
    {
        $this->ditinjau_oleh = $u?->getKey();
        $this->ditinjau_pada = now();
        $this->save();
    }

    private function pindah(string $ke): void
    {
        $dari = $this->status ?? Alur::DRAF;

        if (!Alur::bolehPindah($dari, $ke)) {
            throw new RuntimeException(
                "Status tidak dapat berpindah dari '{$dari}' ke '{$ke}'."
            );
        }

        $this->status = $ke;
    }

    /* ---------- saringan ---------- */

    /** Hanya yang boleh masuk hitungan KPI dan laporan. */
    public function scopeTerhitung(Builder $q): Builder
    {
        return $q->whereIn('status', Alur::terhitung());
    }

    public function scopeMenunggu(Builder $q): Builder
    {
        return $q->where('status', Alur::DIAJUKAN);
    }
}
