@extends('layouts.app')
@section('title','Direktori')
@section('subjudul','Kontak rekan kerja di perusahaan Anda')

@section('content')
<div class="max-w-[1200px] mx-auto space-y-5">

  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex gap-2.5">
    <input name="cari" value="{{ $cari }}" placeholder="Cari nama, jabatan, atau departemen…"
           class="flex-1 rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]
                  focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0">
    <button class="eq-btn-utama" style="flex:none;padding:10px 22px">Cari</button>
  </form>

  @if($orang->isEmpty())
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 px-6 py-12 text-center">
      <p class="text-[13.5px] font-bold text-[#14385A]">
        {{ $cari ? 'Tidak ada yang cocok dengan pencarian Anda' : 'Belum ada rekan terdaftar' }}
      </p>
    </div>
  @else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @foreach($orang as $o)
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 flex items-start gap-3.5">
          @if($o->avatar)
            <img src="{{ asset('storage/'.$o->avatar) }}" alt=""
                 class="w-12 h-12 rounded-full object-cover border border-stone-200 shrink-0">
          @else
            <span class="w-12 h-12 rounded-full grid place-items-center text-white font-black shrink-0"
                  style="background:linear-gradient(135deg,var(--eq-aksen,#0E747E),#2CB0BC)">
              {{ strtoupper(substr($o->name, 0, 1)) }}
            </span>
          @endif

          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-bold text-[#14385A] truncate">{{ $o->name }}</p>
            <p class="text-[11.5px] text-stone-500 truncate">
              {{ $o->position ?: '—' }}@if($o->department) · {{ $o->department }}@endif
            </p>

            <div class="mt-2.5 space-y-1">
              @if($o->email)
                <a href="mailto:{{ $o->email }}"
                   class="block text-[11.5px] text-stone-600 truncate hover:underline">{{ $o->email }}</a>
              @endif
              @if($o->phone)
                <a href="tel:{{ $o->phone }}"
                   class="block text-[11.5px] text-stone-600 hover:underline">{{ $o->phone }}</a>
              @endif
            </div>

            @if($o->company && auth()->user()->isAdmin())
              <p class="mt-2 text-[10.5px] text-stone-400 truncate">{{ $o->company->name }}</p>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <div>{{ $orang->links() }}</div>
  @endif

</div>
@endsection
