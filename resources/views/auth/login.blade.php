@extends('layouts.guest')
@section('title','Masuk')

@section('form')
<h1 class="eq-title">Selamat datang kembali</h1>
<p class="eq-hint">Masuk untuk melanjutkan.</p>

@if (session('status'))
  <div class="eq-note ok">{{ session('status') }}</div>
@endif

@if ($errors->any())
  <div class="eq-note bad">
    <ul>@foreach ($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
  </div>
@endif

<form method="POST" action="{{ route('login') }}">
  @csrf

  <div class="eq-field">
    <label class="eq-label" for="email">Email</label>
    <input class="eq-input" id="email" type="email" name="email" value="{{ old('email') }}"
           required autofocus autocomplete="username">
  </div>

  <div class="eq-field">
    <label class="eq-label" for="password">Kata sandi</label>
    <input class="eq-input" id="password" type="password" name="password"
           required autocomplete="current-password">
  </div>

  <div class="eq-row">
    <label class="eq-check">
      <input type="checkbox" name="remember">
      Ingat saya
    </label>
    @if (Route::has('password.request'))
      <a class="eq-link" href="{{ route('password.request') }}">Lupa sandi?</a>
    @endif
  </div>

  <button class="eq-btn" type="submit">Masuk</button>
</form>

@if (Route::has('register'))
  <p class="eq-after">Belum punya akun? <a href="{{ route('register') }}">Daftar di sini</a></p>
@endif
@endsection
