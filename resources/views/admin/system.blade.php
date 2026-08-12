@extends('layouts.app')
@section('title','Pusat Kendali Sistem')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Status server --}}
  <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative">
      @verbatim<style>@keyframes eqpulse{0%,100%{box-shadow:0 0 0 0 rgba(166,227,43,.5)}70%{box-shadow:0 0 0 9px rgba(166,227,43,0)}}.eq-pulse{width:10px;height:10px;border-radius:9999px;background:#A6E32B;flex:none;animation:eqpulse 2s infinite}</style>@endverbatim
      <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Status Server</span>
      <h2 class="stat mt-1.5 flex items-center gap-3"><span class="eq-pulse"></span>Sistem Berjalan Normal</h2>
      @php
        $eqIkon = [
          'Aplikasi' => 'M4 5h16v11H4zM8 20h8M12 16v4',
          'Laravel' => 'M12 3l8 4v6c0 5-4 7-8 8-4-1-8-3-8-8V7z',
          'PHP' => 'M8 9l-4 3 4 3M16 9l4 3-4 3M14 6l-4 12',
          'Basis Data' => 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
          'Lingkungan' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c2.5 3 2.5 15 0 18M12 3c-2.5 3-2.5 15 0 18',
          'Mode Debug' => 'M12 3a9 9 0 109 9M12 8v4l3 2',
          'Zona Waktu' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
          'Waktu Server' => 'M4 7a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2zM3 10h18M8 3v4M16 3v4',
          'Ruang Disk' => 'M4 6a2 2 0 012-2h12a2 2 0 012 2v3H4zM4 15a2 2 0 012-2h12a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2zM7.5 7.5h.01M7.5 16.5h.01',
        ];
      @endphp
      <div class="grid sm:grid-cols-3 lg:grid-cols-5 gap-2 mt-5">
        @foreach($server as $label => $value)
          <div class="glass rounded-xl px-3.5 py-2.5">
            <div class="flex items-center gap-1.5 text-[9px] uppercase tracking-[0.12em] text-white/40 font-bold">
              <svg class="w-3.5 h-3.5 text-cam-lime-light shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $eqIkon[$label] ?? 'M12 3a9 9 0 100 18 9 9 0 000-18z' }}"/></svg>
              <span class="clamp-1">{{ $label }}</span>
            </div>
            <div class="text-[12px] font-bold text-white mt-1 clamp-1">{{ $value }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Aksi cepat --}}
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
      ['admin.companies.index','Kelola Perusahaan','tambah · ubah · hapus','M3 21V8l7-4 7 4v13M17 21V11l4 2v8M8 21v-4h4v4','#2E6BE6'],
      ['admin.users.index','Kelola Pengguna','peran & akses','M9 8a3.2 3.2 0 100 6.4 3.2 3.2 0 000-6.4zM3.5 20a5.5 5.5 0 0111 0M17 8.5a3 3 0 010 5.4M20.5 20a5 5 0 00-3-4.6','#0FA08F'],
      ['signatories.index','Penanda Tangan','sertifikat','M12 3l2 4 4 .6-3 3 .8 4-3.8-2-3.8 2 .8-4-3-3 4-.6zM6 21s2-4 6-4 6 4 6 4','#F0921E'],
      ['kuesioner.admin','Kuesioner PTPKKP','tautan & hasil','M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h5','#4FA82E'],
    ] as [$r,$l,$sub,$ic,$cl])
      <a href="{{ route($r) }}" class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 flex items-start gap-3.5 hover:border-cam-lime/40 card-hover transition">
        <div class="w-11 h-11 shrink-0 rounded-xl grid place-items-center" style="background:{{ $cl }}22">
          <svg class="w-[22px] h-[22px]" fill="none" stroke="{{ $cl }}" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ic }}"/></svg>
        </div>
        <div class="min-w-0">
          <div class="text-[13px] font-bold text-cam-ink">{{ $l }}</div>
          <div class="text-[11px] text-stone-400 mt-0.5">{{ $sub }}</div>
        </div>
        <svg class="w-4 h-4 ml-auto mt-1 text-stone-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    @endforeach
  </div>

  {{-- ============ Analitik interaktif (eq-analitik-js) ============ --}}
  @php
    $eqBarKey   = !empty($modul) ? array_key_first($modul) : '';
    $eqBarItems = $eqBarKey !== '' ? ($modul[$eqBarKey]['items'] ?? []) : [];
    $eqDays  = collect(range(6,0))->map(fn($i)=>now()->subDays($i));
    $eqLbl7  = $eqDays->map(fn($d)=>$d->isoFormat('dd'))->values()->all();
    $eqSeri  = $eqDays->map(fn($d)=>$logs->filter(fn($l)=>$l->created_at && $l->created_at->isSameDay($d))->count())->values()->all();
  @endphp
  <div class="grid gap-4 lg:grid-cols-3 mb-6" id="eq-analitik-js">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Sebaran Peran</h3>
        <span class="text-[11px] font-bold text-cam-lime-deep">{{ array_sum($roles) }} pengguna</span>
      </div>
      <div style="position:relative;height:210px"><canvas id="eqcRoles"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Statistik Modul</h3>
        <span class="text-[11px] font-bold text-cam-lime-deep clamp-1">{{ $eqBarKey }}</span>
      </div>
      <div style="position:relative;height:210px"><canvas id="eqcModul"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Aktivitas 7 Hari</h3>
        <span class="text-[11px] font-bold text-cam-lime-deep">{{ array_sum($eqSeri) }} log</span>
      </div>
      <div style="position:relative;height:210px"><canvas id="eqcLogs"></canvas></div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
  (function(){
    var ROLES = @json($roles), BAR = @json($eqBarItems), SERI = @json($eqSeri), LBL = @json($eqLbl7);
    function draw(){
      if(typeof Chart==='undefined'){ return setTimeout(draw,150); }
      var teal='#158D99', navy='#0F1720', amber='#E0A62C', tealL='#FF9800', tealD='#F57C00';
      Chart.defaults.font.family='Inter, system-ui, sans-serif';
      Chart.defaults.color='#64748b';
      Chart.defaults.animation.duration=800;

      var rl=Object.keys(ROLES), rv=Object.values(ROLES).map(Number);
      if(document.getElementById('eqcRoles')) new Chart(document.getElementById('eqcRoles'),{
        type:'doughnut',
        data:{labels:rl,datasets:[{data:rv,backgroundColor:[teal,navy,amber,tealL,tealD,'#8AC5CC','#173F5F'],borderColor:'#fff',borderWidth:2,hoverOffset:9}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
          plugins:{legend:{position:'bottom',labels:{boxWidth:10,boxHeight:10,padding:12,font:{size:11}}},
            tooltip:{callbacks:{label:function(c){var t=c.dataset.data.reduce(function(a,b){return a+Number(b);},0)||1;return ' '+c.label+': '+c.parsed+' ('+Math.round(c.parsed/t*100)+'%)';}}}},
          animation:{animateScale:true}}
      });

      var bl=Object.keys(BAR), bv=Object.values(BAR).map(Number);
      if(document.getElementById('eqcModul')) new Chart(document.getElementById('eqcModul'),{
        type:'bar',
        data:{labels:bl,datasets:[{data:bv,backgroundColor:tealL,hoverBackgroundColor:teal,borderRadius:6,maxBarThickness:30}]},
        options:{responsive:true,maintainAspectRatio:false,
          plugins:{legend:{display:false},tooltip:{displayColors:false}},
          scales:{x:{grid:{display:false},ticks:{font:{size:10},maxRotation:60,minRotation:0}},
                  y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#eef2f4'}}}}
      });

      var cv=document.getElementById('eqcLogs');
      if(cv){ var ctx=cv.getContext('2d'); var grad=ctx.createLinearGradient(0,0,0,210);
        grad.addColorStop(0,'rgba(21,141,153,.35)'); grad.addColorStop(1,'rgba(21,141,153,0)');
        new Chart(ctx,{type:'line',
          data:{labels:LBL,datasets:[{data:SERI,borderColor:teal,backgroundColor:grad,fill:true,tension:.4,borderWidth:2,pointRadius:3,pointHoverRadius:6,pointBackgroundColor:teal,pointBorderColor:'#fff',pointBorderWidth:1}]},
          options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{displayColors:false,intersect:false,mode:'index'}},
            scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#eef2f4'}}}}
        });
      }
    }
    if(document.readyState!=='loading') draw(); else document.addEventListener('DOMContentLoaded', draw);
  })();
  </script>

  {{-- Statistik per modul --}}
  @foreach($modul as $nama => $m)
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-3.5">
        <h3 class="text-[14px] font-bold text-cam-ink">{{ $nama }}</h3>
        @if($m['route'])<a href="{{ route($m['route']) }}" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">Buka modul →</a>@endif
      </div>
      {{-- Maksimal 5 kolom: pada 7 kolom label seperti "Percobaan kuis"
           terpotong sehingga kartu kehilangan maknanya. --}}
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
        @foreach($m['items'] as $label => $val)
          <div class="group relative bg-white rounded-xl border border-stone-100 p-3.5 overflow-hidden
                      hover:border-cam-lime/50 hover:shadow-card transition">
            <span class="absolute left-0 top-0 bottom-0 w-[3px] bg-cam-lime/70 rounded-r"></span>
            <span class="absolute -right-5 -top-5 w-16 h-16 rounded-full bg-cam-lime/5
                         group-hover:bg-cam-lime/10 transition"></span>

            <div class="relative flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="stat stat-sm text-cam-lime-deep leading-none">{{ $val }}</div>
                <div class="text-[10.5px] text-stone-400 mt-1.5 leading-snug">{{ $label }}</div>
              </div>
              <span class="shrink-0 w-8 h-8 rounded-lg grid place-items-center bg-cam-lime-soft
                           text-cam-lime-deep group-hover:scale-110 transition-transform">
                <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Ikon::untuk($label) }}"/>
                </svg>
              </span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach

  {{-- Perusahaan ringkas --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
      <h3 class="text-[14px] font-bold text-cam-ink">Perusahaan Terdaftar</h3>
      <a href="{{ route('admin.companies.create') }}" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">+ Tambah</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[12.5px]">
        <thead><tr class="bg-stone-50 border-b border-stone-100">
          @foreach (['Perusahaan','Komoditas','Lokasi','Pekerja','Pengguna',''] as $h)
            <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">{{ $h }}</th>
          @endforeach
        </tr></thead>
        <tbody>
          @forelse($companies as $c)
            <tr class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60">
              <td class="px-4 py-2.5 font-bold text-cam-ink">{{ $c->name }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ $c->commodity ?: '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ $c->location ?: '—' }}</td>
              <td class="px-4 py-2.5 text-stone-500 num">{{ $c->totalWorkers() }}</td>
              <td class="px-4 py-2.5 text-stone-500 num">{{ $c->users_count }}</td>
              <td class="px-4 py-2.5 text-right">
                <a href="{{ route('admin.companies.edit',$c) }}" class="text-[11.5px] font-semibold text-cam-lime-deep hover:underline">Kelola</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada perusahaan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Peran + Pemeliharaan --}}
  <div class="grid gap-4 lg:grid-cols-2">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-3.5">Sebaran Peran Pengguna</h3>
      @php $maks = max(array_values($roles) ?: [1]); @endphp
      <div class="space-y-2.5">
        @foreach($roles as $label => $val)
          <div>
            <div class="flex items-center justify-between text-[12px] mb-1">
              <span class="font-semibold text-stone-600">{{ $label }}</span>
              <span class="font-bold text-cam-ink num">{{ $val }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient" style="width: {{ $maks ? ($val/$maks*100) : 0 }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-3.5">Pemeliharaan Sistem</h3>
      <div class="grid grid-cols-2 gap-2">
        @foreach ([['cache','Bersihkan semua cache'],['view','Cache tampilan'],['config','Cache konfigurasi'],['route','Cache rute']] as [$a,$l])
          <form action="{{ route('admin.system.maintenance',$a) }}" method="POST">
            @csrf
            <button class="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-[11.5px] font-bold text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">{{ $l }}</button>
          </form>
        @endforeach
      </div>
      <p class="text-[11px] text-stone-400 mt-3 leading-relaxed">Jalankan setelah mengubah kode atau tampilan bila perubahan belum terlihat.</p>
    </div>
  </div>

  {{-- Log --}}
  <div>
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-[14px] font-bold text-cam-ink">Log Aktivitas Terbaru</h3>
      @if($logs->count())
        <form action="{{ route('admin.system.logs.clear') }}" method="POST" onsubmit="return confirm('Kosongkan seluruh log?')">
          @csrf @method('DELETE')
          <button class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-3 py-1.5 rounded-lg">Bersihkan log</button>
        </form>
      @endif
    </div>
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      @forelse($logs as $log)
        <div class="flex items-start gap-3 px-5 py-3 border-b border-stone-50 last:border-0">
          <div class="w-1.5 h-1.5 rounded-full bg-cam-lime mt-2 shrink-0"></div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-2">
              <span class="text-[12.5px] font-bold text-cam-ink">{{ $log->action }}</span>
              <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100 text-stone-500 px-1.5 py-0.5 rounded">{{ $log->module }}</span>
            </div>
            @if($log->detail)<div class="text-[12px] text-stone-400 mt-0.5">{{ $log->detail }}</div>@endif
          </div>
          <div class="text-right shrink-0">
            <div class="text-[11px] font-semibold text-stone-500">{{ $log->username ?: '—' }}</div>
            <div class="text-[10.5px] text-stone-300">{{ $log->created_at->format('d M · H:i') }}</div>
          </div>
        </div>
      @empty
        <div class="px-5 py-12 text-center text-[13px] text-stone-400">Belum ada aktivitas.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
