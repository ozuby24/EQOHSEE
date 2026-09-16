<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use App\Models\PjpCatatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PjpCatatanController extends Controller
{
    public function store(Request $request, Pjp $pjp): RedirectResponse
    {
        $data = $request->validate([
            'isi' => ['required', 'string'],
        ]);

        $pjp->catatans()->create($data);

        return back()->with('success', 'Catatan berhasil ditambahkan.');
    }

    public function destroy(Pjp $pjp, PjpCatatan $catatan): RedirectResponse
    {
        abort_unless($catatan->pjp_id === $pjp->id, 404);

        $catatan->delete();

        return back()->with('success', 'Catatan berhasil dihapus.');
    }
}
