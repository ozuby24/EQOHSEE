<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\{DuaFaktor, Turnstile};
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],

            /* Verifikasi Cloudflare — hanya ketika kuncinya terpasang.
               Aturannya disusun di satu tempat, dipakai sama persis oleh
               masuk, daftar, dan lupa sandi. */
            Turnstile::KOLOM => Turnstile::aturan(Turnstile::TINDAKAN['masuk']),
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Akun yang kredensialnya sedang dicoba, BILA ia memakai dua faktor.
     *
     * Dicari lewat surelnya saja, tanpa menyentuh sandinya. Yang perlu
     * diketahui di sini cuma satu: apakah masuk untuk akun ini berakhir
     * di dasbor, atau berakhir di halaman kode. Sandinya diperiksa
     * sesudahnya, sekali, oleh sahkanTanpaMasuk().
     *
     * null berarti "jalankan alur lama apa adanya" — termasuk ketika
     * surelnya tidak terdaftar sama sekali. Membedakan surel yang ada
     * dari yang tidak ada DI SINI akan membocorkan daftar akun kepada
     * siapa pun yang mau mencoba satu per satu.
     */
    public function calonDuaFaktor(): ?User
    {
        $pengguna = User::where('email', Str::lower($this->string('email')))->first();

        return DuaFaktor::menyala($pengguna) ? $pengguna : null;
    }

    /**
     * Memeriksa sandi TANPA memasukkan siapa pun.
     *
     * Auth::attempt akan memasukkannya dan membangkitkan peristiwa
     * Login, dan peristiwa itu menulis satu baris "masuk" pada jejak
     * akses serta memperbarui masuk_terakhir_at. Untuk akun berdua
     * faktor, keduanya belum benar: sandinya memang benar, tetapi
     * orangnya belum masuk dan mungkin tidak akan pernah — ia masih
     * harus menunjukkan kode. Jejak yang mencatatnya sebagai sudah
     * masuk membuat halaman "Perangkat & Keamanan" memberi tahu hal
     * yang tidak terjadi, tepat pada halaman yang dibaca orang ketika
     * ia curiga akunnya dipakai orang lain.
     *
     * @throws ValidationException
     */
    public function sahkanTanpaMasuk(User $pengguna): void
    {
        $this->ensureIsNotRateLimited();

        $kredensial = ['password' => (string) $this->string('password')];

        if (! Auth::guard('web')->getProvider()->validateCredentials($pengguna, $kredensial)) {
            RateLimiter::hit($this->throttleKey());

            /* Peristiwa Failed dibangkitkan sendiri. Ia biasanya datang
               dari Auth::attempt, yang sengaja tidak dipakai di sini —
               dan tanpa ini, sandi yang salah pada akun berdua faktor
               menjadi satu-satunya percobaan gagal yang tidak tercatat
               di mana pun. Justru akun itu yang paling perlu diawasi. */
            event(new Failed('web', $pengguna, $kredensial));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
