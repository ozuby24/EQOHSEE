<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use App\Models\SmkpChecklistAnswer;
use App\Models\SmkpChecklistCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmkpChecklistController extends Controller
{
    public function show(Pjp $pjp): Response
    {
        $categories = SmkpChecklistCategory::query()->orderBy('urutan')->with('items')->get();
        $answers = $pjp->smkpChecklistAnswers()->get()->keyBy('smkp_checklist_item_id');

        return Inertia::render('Pjp/ChecklistSmkp', [
            'pjp' => $pjp->only(['id', 'nama_perusahaan']),
            'categories' => $categories,
            'answers' => $answers,
            'score' => $pjp->smkpScore(),
            'categoryBreakdown' => $pjp->smkpCategoryBreakdown(),
            'legalitasStatus' => $pjp->smkpLegalitasStatus(),
        ]);
    }

    public function update(Request $request, Pjp $pjp): RedirectResponse
    {
        $data = $request->validate([
            'jawaban' => ['required', 'array'],
            'jawaban.*.item_id' => ['required', 'integer', 'exists:smkp_checklist_items,id'],
            'jawaban.*.jawaban' => ['nullable', 'string', 'in:'.implode(',', array_keys(SmkpChecklistAnswer::JAWABAN))],
            'jawaban.*.nilai' => ['nullable', 'string', 'in:'.implode(',', array_keys(SmkpChecklistAnswer::NILAI))],
            'jawaban.*.penjelasan' => ['nullable', 'string'],
        ]);

        foreach ($data['jawaban'] as $jawaban) {
            SmkpChecklistAnswer::updateOrCreate(
                ['pjp_id' => $pjp->id, 'smkp_checklist_item_id' => $jawaban['item_id']],
                [
                    'jawaban' => $jawaban['jawaban'] ?? null,
                    'nilai' => $jawaban['nilai'] ?? null,
                    'penjelasan' => $jawaban['penjelasan'] ?? null,
                ],
            );
        }

        return back()->with('success', 'Checklist SMKP berhasil disimpan.');
    }
}
