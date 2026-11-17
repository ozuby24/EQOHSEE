<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Pjp;
use App\Models\SmkpChecklistAnswer;
use App\Models\SmkpChecklistCategory;
use App\Models\SmkpChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Checklist prakualifikasi SMKP sebuah PJP.
 *
 * Formulirnya menggambar seluruh 126 pertanyaan sekaligus, bukan per
 * kategori. Prakualifikasi diisi dalam satu duduk sambil membuka berkas
 * perusahaan; memecahnya menjadi 17 langkah tersimpan berarti tujuh
 * belas kali menunggu halaman dan tujuh belas kesempatan kehilangan
 * isian yang belum sempat disimpan.
 */
class PjpChecklistController extends Controller
{
    public function tampil(Pjp $pjp)
    {
        $kategori = SmkpChecklistCategory::query()
            ->orderBy('urutan')->with('items')->get();

        $jawaban = $pjp->smkpChecklistAnswers()->get()->keyBy('smkp_checklist_item_id');

        return Inertia::render('Pjp/Checklist', [
            'judul'    => 'Persyaratan PJP',
            'subjudul' => 'Checklist prakualifikasi SMKP — '.$pjp->nama_perusahaan,

            'pjp' => ['id' => $pjp->id, 'nama_perusahaan' => $pjp->nama_perusahaan],

            'kategori' => $kategori->map(fn (SmkpChecklistCategory $k) => [
                'id'        => $k->id,
                'kode'      => $k->kode,
                'nama'      => $k->nama,
                'bobot'     => $k->bobot,
                'berbobot'  => $k->berbobot(),
                'items'     => $k->items->map(fn (SmkpChecklistItem $i) => [
                    'id'         => $i->id,
                    'nomor'      => $i->nomor,
                    'pertanyaan' => $i->pertanyaan,
                    'petunjuk'   => $i->petunjuk,
                    'bobot'      => $i->bobot,
                    'grup_kode'  => $i->grup_kode,
                    'grup_nama'  => $i->grup_nama,
                ])->values()->all(),
            ])->values()->all(),

            /*
             * Jawaban dikirim sebagai peta id item → isian, bukan larik
             * berurutan: formulir mencarinya per pertanyaan, dan larik
             * berurutan memaksa sisi peramban menelusuri 126 baris untuk
             * tiap pertanyaan yang digambar.
             */
            'jawaban' => $jawaban->map(fn (SmkpChecklistAnswer $a) => [
                'jawaban'    => $a->jawaban,
                'nilai'      => $a->nilai,
                'penjelasan' => $a->penjelasan,
            ])->all(),

            'skor'            => $pjp->smkpScore(),
            'rincianKategori' => $pjp->smkpCategoryBreakdown(),
            'legalitasStatus' => $pjp->smkpLegalitasStatus(),

            'jawabanOpsi' => SmkpChecklistAnswer::JAWABAN,
            'nilaiOpsi'   => SmkpChecklistAnswer::NILAI,
            'kodeLegalitas' => SmkpChecklistCategory::LEGALITAS,
            'totalBobot'    => SmkpChecklistCategory::TOTAL_BOBOT,

            'tautan' => [
                'simpan'  => route('pjp.checklist.simpan', $pjp),
                'detail'  => route('pjp.detail', $pjp),
                'beranda' => route('pjp.index'),
                'persyaratan' => route('pjp.persyaratan'),
            ],
        ]);
    }

    public function simpan(Request $request, Pjp $pjp)
    {
        $data = $request->validate([
            'jawaban'              => ['required', 'array'],
            'jawaban.*.item_id'    => ['required', 'integer', 'exists:smkp_checklist_items,id'],
            'jawaban.*.jawaban'    => ['nullable', Rule::in(array_keys(SmkpChecklistAnswer::JAWABAN))],
            'jawaban.*.nilai'      => ['nullable', Rule::in(array_keys(SmkpChecklistAnswer::NILAI))],
            'jawaban.*.penjelasan' => ['nullable', 'string', 'max:2000'],
        ]);

        /*
         * Satu transaksi untuk seluruh 126 baris. Tanpa itu, sambungan
         * yang putus di tengah meninggalkan checklist setengah tersimpan:
         * skornya tetap terhitung dan tetap terlihat sah, padahal
         * sebagian jawabannya berasal dari pengisian sebelumnya.
         */
        DB::transaction(function () use ($data, $pjp) {
            foreach ($data['jawaban'] as $isian) {
                SmkpChecklistAnswer::updateOrCreate(
                    ['pjp_id' => $pjp->id, 'smkp_checklist_item_id' => $isian['item_id']],
                    [
                        'jawaban'    => $isian['jawaban'] ?? null,
                        'nilai'      => $isian['nilai'] ?? null,
                        'penjelasan' => $isian['penjelasan'] ?? null,
                    ],
                );
            }
        });

        ActivityLog::write(
            'Simpan checklist persyaratan PJP',
            $pjp->nama_perusahaan.' — '.$pjp->smkpScore()['persentase'].'%',
            'pjp',
        );

        return back()->with('ok', 'Checklist persyaratan tersimpan.');
    }
}
