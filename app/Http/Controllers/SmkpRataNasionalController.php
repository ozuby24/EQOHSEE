<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, SmkpRataNasional};
use App\Support\SmkpTahap;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Rata-rata nasional FR, SR, ASR, MFR dari Surat Edaran tahunan (port
 * DBEST #23), di Kriteria & Acuan. Administrator menambah/memperbarui satu
 * baris per tahun SE; audit memakai SE terbaru yang terbit sampai tahun
 * auditnya untuk saran faktor penyesuaian hari kerja Tahap I dan evaluasi
 * kinerja keselamatan (SmkpTahap::rataNasional).
 */
class SmkpRataNasionalController extends Controller
{
    public function simpan(Request $request)
    {
        $isian = $request->only(['tahun', 'nomor', 'tanggal', 'catatan']);
        foreach (array_keys(SmkpTahap::NILAI_NASIONAL) as $k) $isian[$k] = SmkpTahap::bilangan($request->input($k));

        $v = validator($isian, [
            'tahun'   => ['required', 'integer', 'min:2015', 'max:2100'],
            'nomor'   => ['required', 'string', 'max:120'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'fr'      => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sr'      => ['required', 'numeric', 'min:0', 'max:1000000'],
            'asr'     => ['required', 'numeric', 'min:0', 'max:1000000'],
            'mfr'     => ['required', 'numeric', 'min:0', 'max:1000000'],
        ], [
            'tahun.*'   => 'Tahun Surat Edaran 2015–2100.',
            'nomor.required' => 'Nomor Surat Edaran wajib diisi.',
            'nomor.max' => 'Nomor Surat Edaran paling banyak 120 karakter.',
            'tanggal.date_format' => 'Tanggal Surat Edaran tidak sah.',
            'required'  => 'Isi keempat nilai rata-rata nasional (FR, SR, ASR, MFR) berupa angka.',
            'numeric'   => 'Nilai rata-rata nasional harus angka (koma desimal boleh, mis. 0,09).',
            'min'       => 'Nilai rata-rata nasional tidak boleh negatif.',
        ]);
        if ($v->fails()) throw new ValidationException($v);

        $tahun = (int) $isian['tahun'];
        SmkpRataNasional::query()->updateOrCreate(['tahun' => $tahun], [
            'nomor'   => trim((string) $isian['nomor']),
            'tanggal' => trim((string) ($isian['tanggal'] ?? '')) ?: null,
            'fr' => $isian['fr'], 'sr' => $isian['sr'], 'asr' => $isian['asr'], 'mfr' => $isian['mfr'],
            'catatan' => trim((string) ($isian['catatan'] ?? '')) ?: null,
            'user_id' => $request->user()->id,
        ]);
        SmkpTahap::lupakanRataNasional();

        ActivityLog::write('Rata-rata nasional SE '.$tahun, trim((string) $isian['nomor'])
            .": FR {$isian['fr']}, SR {$isian['sr']}, ASR {$isian['asr']}, MFR {$isian['mfr']}", 'smkp');

        return redirect()->to(route('smkp.acuan').'#rata-nasional')
            ->with('ok', "Rata-rata nasional Surat Edaran {$tahun} tersimpan dan dipakai audit tahun {$tahun} dan sesudahnya (sampai ada SE yang lebih baru).");
    }

    public function hapus(int $tahun)
    {
        $r = SmkpRataNasional::query()->where('tahun', $tahun)->first();
        if ($r) {
            ActivityLog::write('Hapus rata-rata nasional SE '.$tahun, (string) $r->nomor, 'smkp');
            $r->delete();
            SmkpTahap::lupakanRataNasional();
        }

        $bawaan = SmkpTahap::RATA_NASIONAL[$tahun]['nomor'] ?? null;

        return redirect()->to(route('smkp.acuan').'#rata-nasional')->with('ok', $bawaan
            ? "Isian SE {$tahun} dihapus; kembali memakai nilai bawaan {$bawaan}."
            : "Data Surat Edaran {$tahun} dihapus.");
    }
}
