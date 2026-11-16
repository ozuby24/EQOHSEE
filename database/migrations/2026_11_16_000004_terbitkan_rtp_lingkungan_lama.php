<?php

use App\Models\{EnvAudit, EnvAuditScore};
use App\Support\RtpLingkungan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Terbitkan RTP untuk nilai verifikasi ≤ 2 yang sudah ada sebelum RTP
 * berlaku — lewat jalur yang sama dengan penyimpanan nilai biasa
 * (RtpLingkungan::perbarui), seperti migrasi RTP nilai dua DBEST.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('env_audit_rtps') || !Schema::hasTable('env_audit_scores')) return;

        EnvAuditScore::query()->whereNotNull('verifikasi')->where('verifikasi', '<=', RtpLingkungan::AMBANG_TEMUAN)
            ->orderBy('audit_id')->chunkById(500, function ($skor) {
                $audit = EnvAudit::withoutGlobalScopes()->whereIn('id', $skor->pluck('audit_id')->unique())->get()->keyBy('id');
                foreach ($skor as $s) {
                    if ($a = $audit->get($s->audit_id)) RtpLingkungan::perbarui($a, $s);
                }
            });
    }

    /** Tidak dibalik: setelah terbit, RTP bisa sudah diisi auditee. */
    public function down(): void
    {
    }
};
