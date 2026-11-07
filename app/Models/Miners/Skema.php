<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penyetuju bernama satu IUP — padanan `skemas` Safe Track.
 *
 * Satu baris per IUP. `company_id` NULL adalah ALUR UMUM: cadangan bagi
 * IUP yang belum punya barisnya sendiri, sama seperti baris tanpa
 * iup_id di Safe Track.
 *
 * TIDAK memakai scope perusahaan. Siapa yang menyetujui dokumen sebuah
 * IUP dibaca juga saat mitra (IUJP) mengirim berkasnya — scope milik
 * mitra itu akan menyembunyikan baris IUP induknya, dan kirimannya
 * berhenti dengan "alur belum diatur" padahal sudah diatur.
 */
class Skema extends Model
{
    protected $table = 'mnr_skema';

    protected $guarded = ['id'];

    /** Kolom pengguna per peran skema. */
    public const KOLOM = [
        'dokter' => 'dokter_user_id',
        'ohse'   => 'ohse_user_id',
        'ktt'    => 'ktt_user_id',
    ];

    /**
     * Kolom penerima tambahan per peran — PENERIMA Safe Track.
     *   'to'  ikut menerima seperti penerima utama
     *   'cc'  hanya ditembusi
     */
    public const PENERIMA = [
        'dokter' => ['cc' => 'dokter_cc'],
        'ohse'   => ['to' => 'ohse_to', 'cc' => 'ohse_cc'],
        'ktt'    => ['cc' => 'ktt_cc'],
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function dokter(): BelongsTo  { return $this->belongsTo(User::class, 'dokter_user_id'); }
    public function ohse(): BelongsTo    { return $this->belongsTo(User::class, 'ohse_user_id'); }
    public function ktt(): BelongsTo     { return $this->belongsTo(User::class, 'ktt_user_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    /** Id pengguna untuk satu peran, atau null. */
    public function penyetuju(string $peran): ?int
    {
        $kolom = self::KOLOM[$peran] ?? null;

        return $kolom && $this->{$kolom} ? (int) $this->{$kolom} : null;
    }
}
