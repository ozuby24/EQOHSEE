<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tanda tangan bagian perbaikan (tindak lanjut) Formulir Ketidaksesuaian
 * yang sudah dibubuhkan KTT untuk audit perusahaan jasa pertambangan (PJP)
 * dipindah ke PJO — wakil auditi yang benar untuk bagian ini, sama seperti
 * pihakAuditi() di formulir lain. Audit non-PJP tidak terpengaruh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audits = DB::table('smkp_audits')
            ->join('companies', 'companies.id', '=', 'smkp_audits.company_id')
            ->whereNotNull('smkp_audits.ttd_formulir')
            ->get(['smkp_audits.id', 'smkp_audits.rencana', 'smkp_audits.ttd_formulir', 'companies.kategori']);

        foreach ($audits as $a) {
            $rencana = json_decode((string) $a->rencana, true) ?: [];
            $pjo = trim((string) ($rencana['pengesahan']['pjo']['nama'] ?? ''));
            if ($a->kategori !== 'pjp' && $pjo === '') continue;

            $t = json_decode((string) $a->ttd_formulir, true);
            if (!is_array($t)) continue;

            $ubah = false;
            foreach (['bubuh', 'tanggal'] as $bagianUtama) {
                foreach ((array) ($t[$bagianUtama] ?? []) as $bagian => $per) {
                    if (!is_array($per) || !array_key_exists('ktt', $per) || !preg_match('/^ketidaksesuaian\.\d+\.perbaikan$/', (string) $bagian)) continue;
                    $t[$bagianUtama][$bagian]['pjo'] = $per['ktt'];
                    unset($t[$bagianUtama][$bagian]['ktt']);
                    $ubah = true;
                }
            }

            if ($ubah) DB::table('smkp_audits')->where('id', $a->id)->update(['ttd_formulir' => json_encode($t)]);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: KTT bukan wakil auditi yang benar untuk PJP.
    }
};
