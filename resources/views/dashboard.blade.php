@extends('layouts.app')
@section('title','Dashboard')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

  {{-- Hero --}}
  <section class="brand-gradient relative overflow-hidden rounded-2xl p-7 md:p-8 text-white shadow-card animate-fadeUp">
    <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative flex flex-wrap items-end justify-between gap-5">
      <div>
        <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Health · Safety · Environment</span>
        <h2 class="font-display text-[28px] md:text-[34px] font-black mt-2 leading-tight">Halo, {{ auth()->user()->name }}</h2>
        <p class="text-[13px] text-white/50 mt-1.5 max-w-md leading-relaxed">Lanjutkan pembelajaran dan pastikan kompetensi keselamatanmu tetap terjaga.</p>
      </div>
      <a href="{{ route('courses.index') }}" class="glass rounded-xl px-4 py-2.5 text-[12.5px] font-bold hover:bg-white/15 transition">Jelajahi kursus →</a>
    </div>
  </section>

  {{-- Statistik --}}
  @php $ongoing = $enrollments->where('status','ongoing')->count(); @endphp
  <div class="grid gap-4 sm:grid-cols-3">
    {{-- eq-ic-lms --}}
    @foreach ([
        [$ongoing,'Kursus berjalan','text-cam-lime-deep','rgba(21,141,153,.12)','#0E747E','<path d=\'M12 6.3 3 10l9 3.7L21 10z\'/><path d=\'M6.5 11.4V16c0 1.2 2.5 2.2 5.5 2.2s5.5-1 5.5-2.2v-4.6\'/>'],
        [$certificates,'Sertifikat','text-cam-lime-dark','rgba(224,166,44,.16)','#B4791A','<circle cx=\'12\' cy=\'9\' r=\'5\'/><path d=\'M8.5 13 7 21l5-3 5 3-1.5-8\'/>'],
        [$sopPassed,'Evaluasi SOP lulus','text-cam-ink','rgba(20,56,90,.10)','#14385A','<rect x=\'6\' y=\'5\' width=\'12\' height=\'16\' rx=\'2\'/><path d=\'M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2\'/><path d=\'M9 13.5l2 2 4-4\'/>'],
      ] as [$v,$l,$c,$ibg,$icl,$isvg])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 card-hover">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
          <div>
            <div class="stat stat-lg leading-none {{ $c }}">{{ $v }}</div>
            <div class="text-[12px] text-stone-400 mt-2">{{ $l }}</div>
          </div>
          <span style="flex:none;width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:{{ $ibg }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $icl }}" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $isvg !!}</svg>
          </span>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Pintasan modul: angka yang ditampilkan = yang butuh perhatian --}}
  <div>
    <h3 class="text-[15px] font-bold text-cam-ink mb-3">Modul Lainnya</h3>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      @foreach($modul as $m)
        <a href="{{ route($m['rute']) }}"
           class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 card-hover hover:border-cam-lime/40 transition">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="stat stat-lg leading-none" style="color:{{ $m['warna'] }}">{{ $m['nilai'] }}</div>
              <div class="text-[13px] font-bold text-cam-ink mt-2 clamp-1">{{ $m['nama'] }}</div>
              <div class="text-[11px] text-stone-400 mt-0.5">{{ $m['ket'] }}</div>
            </div>
            <span style="flex:none;width:10px;height:10px;border-radius:99px;margin-top:6px;background:{{ $m['warna'] }}"></span>
          </div>
          @if($m['total'])
            <div class="text-[10.5px] text-stone-400 mt-3 pt-3 border-t border-stone-100">
              {{ $m['total'] }} total tercatat
            </div>
          @endif
        </a>
      @endforeach
    </div>
  </div>

  {{-- Ringkasan admin --}}
  @if($admin)
  <div>
    <h3 class="text-[15px] font-bold text-cam-ink mb-3">Ringkasan Admin</h3>
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
      @foreach ([
          ['Pengguna',$admin['users'],'#0E747E','<circle cx=\'9\' cy=\'8\' r=\'3.3\'/><path d=\'M3.5 20a5.5 5.5 0 0 1 11 0\'/><path d=\'M16 5.2a3.3 3.3 0 0 1 0 6.4\'/><path d=\'M18.5 20a5.5 5.5 0 0 0-3-4.9\'/>'],
          ['Kursus',$admin['courses'],'#14385A','<path d=\'M12 6.3 3 10l9 3.7L21 10z\'/><path d=\'M6.5 11.4V16c0 1.2 2.5 2.2 5.5 2.2s5.5-1 5.5-2.2v-4.6\'/>'],
          ['Prosedur',$admin['procedures'],'#B4791A','<path d=\'M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z\'/><path d=\'M14 3v4h4\'/><path d=\'M9 13h6M9 16.5h4\'/>'],
          ['Sertifikat',$admin['certs'],'#0E747E','<circle cx=\'12\' cy=\'9\' r=\'5\'/><path d=\'M8.5 13 7 21l5-3 5 3-1.5-8\'/>'],
        ] as [$label,$val,$icl,$isvg])
        <div class="bg-cam-lime-soft border border-cam-lime/20 rounded-2xl p-4">
          <div style="display:flex;align-items:center;gap:11px">
            <span style="flex:none;width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.72)">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="{{ $icl }}" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $isvg !!}</svg>
            </span>
            <div>
              <div class="stat text-cam-lime-deep leading-none">{{ $val }}</div>
              <div class="text-[11px] text-stone-500 mt-1">{{ $label }}</div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Kursus saya --}}
  <div>
    <h3 class="text-[15px] font-bold text-cam-ink mb-3">Kursus Saya</h3>
    <div class="space-y-2.5">
      @forelse($enrollments as $en)
        <a href="{{ route('learn.show', $en->course) }}"
           class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 transition">
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
              <div class="text-[14px] font-bold text-cam-ink clamp-1">{{ $en->course->title }}</div>
              <div class="text-[11px] text-stone-400 mt-0.5">{{ $en->status === 'finished' ? 'Selesai' : 'Sedang berjalan' }}</div>
            </div>
            <span class="text-[13px] font-bold {{ $en->progress >= 100 ? 'text-cam-lime-dark' : 'text-stone-400' }}">{{ $en->progress }}%</span>
          </div>
          <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full lime-gradient transition-all" style="width: {{ $en->progress }}%"></div>
          </div>
        </a>
      @empty
        <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center">
          <p class="text-[13px] text-stone-400">Belum ada kursus.
            <a href="{{ route('courses.index') }}" class="text-cam-lime-deep font-bold hover:underline">Mulai dari sini</a>.</p>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Berita --}}
  @if($news->count())
  <div>
    <h3 class="text-[15px] font-bold text-cam-ink mb-3">Berita Terbaru</h3>
    <div class="space-y-2">
      @foreach($news as $n)
        <a href="{{ route('news.show', $n) }}"
           class="flex items-center justify-between gap-4 bg-white rounded-xl shadow-soft border border-stone-100 px-5 py-3.5 hover:border-cam-lime/40 transition">
          <div class="min-w-0">
            <div class="text-[13px] font-semibold text-cam-ink clamp-1">{{ $n->title }}</div>
            <div class="text-[10.5px] text-stone-400 mt-0.5">{{ optional($n->published_at)->format('d M Y') }}</div>
          </div>
          <span class="text-stone-300 text-[13px]">→</span>
        </a>
      @endforeach
    </div>
  </div>
  @endif
</div>
@endsection
