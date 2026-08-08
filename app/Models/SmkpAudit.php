<?php

namespace App\Models;

use App\Support\{Smkp, SmkpTahap};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SmkpAudit extends Model
{
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

    /** Tim audit yang ditugaskan; dipakai Tahap I maupun Rencana Audit. */
    public function tim(): array
    {
        return array_values(array_filter(
            (array) ($this->auditor ?? []),
            fn ($a) => trim((string) ($a['nama'] ?? '')) !== ''
        ));
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

    /**
     * Audit lapangan hanya boleh berjalan setelah Tahap I tuntas: seluruh
     * elemen sudah ditinjau kecukupan dokumentasinya dan Rencana Audit lengkap
     * sembilan komponen. Ini yang membedakan dua tahap dari sekadar dua menu.
     */
    public function siapTahapDua(): bool
    {
        return $this->rekapKecukupan()['siap'] && $this->rekapRencana()['lengkap'];
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
