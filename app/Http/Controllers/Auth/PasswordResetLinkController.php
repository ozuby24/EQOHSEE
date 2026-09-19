<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create()
    {
        return Inertia::render('Auth/LupaSandi', [
            /* Kunci SITUS Turnstile; rahasianya tidak pernah meninggalkan
               server. null berarti fiturnya mati. */
            'turnstile' => Turnstile::kunciSitus(),
            'tindakan'  => Turnstile::TINDAKAN['lupa-sandi'],
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],

            /* Pintu ini mengirim surel ke alamat yang diketik pengirim
               permintaannya, dan itulah yang membuatnya menarik untuk
               disalahgunakan: bukan untuk masuk, melainkan untuk
               membanjiri kotak surat orang lain dengan surel yang
               membawa nama perusahaan ini pada bagian pengirimnya —
               dengan server ini yang menanggung reputasi pengirimnya.
               Pembatas laju menahan kecepatannya; kotak ini menahan
               skripnya. */
            Turnstile::KOLOM => Turnstile::aturan(Turnstile::TINDAKAN['lupa-sandi']),
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
