<?php

namespace App\Models;

use App\Models\Concerns\CatatanEnergi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/** Pemakaian bahan bakar satu unit pada satu hari. */
class EnergyFuelLog extends Model
{
    use CatatanEnergi;

    protected $table = 'energy_fuel_logs';

    protected $fillable = ['equipment_id','tanggal','hm','liter','idle_jam','jarak_km','ton','bcm','cycle_menit',
                           'persen_fame','status','sumber_data','diinput_oleh','diverifikasi_oleh','diverifikasi_pada','alasan_tolak'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'hm' => 'float', 'liter' => 'float', 'idle_jam' => 'float',
                'jarak_km' => 'float', 'ton' => 'float', 'bcm' => 'float', 'cycle_menit' => 'float',
                'persen_fame' => 'float', 'diverifikasi_pada' => 'datetime'];
    }

    /**
     * Catatan bahan bakar mengikuti perusahaan unit alatnya.
     *
     * whereHas('equipment') membawa lingkup MilikPerusahaan milik unitnya
     * sendiri — termasuk perusahaan yang sedang DILIHAT administrator,
     * keturunan IUP/IUJP, dan baris tanpa pemilik. Sebelumnya lingkup
     * ini ditulis ulang di sini dan melewatkan administrator sama
     * sekali: memilih satu perusahaan tetap menjumlahkan solar seluruh
     * perusahaan, sementara listrik dan produksinya sudah tersaring —
     * intensitas energinya berlipat sebanyak jumlah perusahaan.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $u = auth()->user();
            if (!$u) return;
            if ($u->isAdmin() && \App\Support\Perusahaan::terpilih($u) === null) return;

            $builder->whereHas('equipment');
        });
    }

    public function equipment(): BelongsTo { return $this->belongsTo(EnergyEquipment::class, 'equipment_id'); }

    /** Liter per jam operasi — ukuran kehausan yang paling langsung. */
    public function literPerHm(): float { return $this->hm > 0 ? $this->liter / $this->hm : 0.0; }
}
