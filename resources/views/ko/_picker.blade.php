@php
  $tabs = [
    ['ko.index','Dashboard'], ['ko.register','Register SPIP'], ['ko.kelayakan','Kelayakan'],
    ['ko.perawatan','Perawatan'], ['ko.pengaman','Pengaman'], ['ko.kajian','Kajian Teknis'],
    ['ko.tenaga','Tenaga Teknis'], ['ko.tindak','Tindak Lanjut'],
  ];
  if (auth()->user()->isAdmin()) $tabs[] = ['ko.pengaturan','Pengaturan'];
@endphp
<div class="flex flex-wrap items-center gap-1.5 mb-4">
  @if(auth()->user()->isAdmin())
    <form method="GET" class="mr-1">
      @foreach(request()->except(['perusahaan','page']) as $k => $v)
        <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? '' : $v }}">
      @endforeach
      <select name="perusahaan" onchange="this.form.submit()"
              class="ring-focus rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12.5px] font-semibold shadow-sm">
        <option value="ALL">Semua perusahaan</option>
        @foreach($perusahaan as $co)
          <option value="{{ $co->id }}" @selected(request('perusahaan') == $co->id)>{{ $co->code ?: $co->name }}</option>
        @endforeach
      </select>
    </form>
  @endif

  @foreach($tabs as $tab)
    <a href="{{ route($tab[0]) }}"
       class="text-[12px] font-semibold px-3 py-1.5 rounded-full border transition-all duration-200
              {{ request()->routeIs($tab[0]) ? 'bg-cam-ink text-white border-cam-ink shadow-sm' : 'bg-white text-stone-600 border-stone-200 hover:border-cam-ink/40 hover:-translate-y-0.5' }}">{{ $tab[1] }}</a>
  @endforeach
</div>
