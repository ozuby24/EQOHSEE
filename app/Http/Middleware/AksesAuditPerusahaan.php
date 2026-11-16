<?php

namespace App\Http\Middleware;

use App\Support\AturanAksesAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan Aturan Akses Audit per perusahaan pada rute yang membawa audit
 * ({smkp} = SmkpAudit, {audit} = EnvAudit). Parameter: `audit` (menilai,
 * verifikasi, mengisi berkas) atau `unggah` (bukti & jawaban temuan).
 *
 * Batas perusahaan (global scope) tetap bekerja lebih dulu — rute yang
 * auditnya di luar jangkauan sudah berakhir 404 sebelum sampai di sini.
 */
class AksesAuditPerusahaan
{
    public function handle(Request $request, Closure $next, string $jenis = 'audit'): Response
    {
        $audit = $request->route('smkp') ?? $request->route('audit');
        $companyId = is_object($audit) ? (int) ($audit->company_id ?? 0) : null;

        $boleh = $jenis === 'unggah'
            ? AturanAksesAudit::bolehUnggah($request->user(), $companyId ?: null)
            : AturanAksesAudit::bolehAudit($request->user(), $companyId ?: null);

        abort_unless($boleh, 403, $jenis === 'unggah'
            ? 'Akun Anda bukan pengunggah data yang ditugaskan untuk perusahaan ini (Aturan Akses Audit).'
            : 'Akun Anda bukan auditor yang ditugaskan untuk perusahaan ini (Aturan Akses Audit).');

        return $next($request);
    }
}
