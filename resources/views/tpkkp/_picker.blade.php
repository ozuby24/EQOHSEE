@php
  /* Tahan banting: halaman yang tidak mengirim $a / $tahunn (mis. Kuesioner) tetap jalan. */
  $a      = $a      ?? \App\Models\TpkkpAssessment::forYear((int) (session('tpkkp_tahun') ?? now()->year));
  $tahunn = $tahunn ?? \App\Models\TpkkpAssessment::orderByDesc('tahun')->pluck('tahun')->all();
  $daftarTahun = $tahunn ?: [$a->tahun];

  $tabs = [
    ['tpkkp.index','Beranda','home'],        ['tpkkp.assess','Penilaian','edit'],
    ['tpkkp.matriks','Matriks','grid'],      ['tpkkp.summary','Summary','target'],
    ['tpkkp.hasil','Hasil','award'],         ['tpkkp.rekap','Rekapitulasi','list'],
    ['tpkkp.visual','Visualisasi','chart'],  ['tpkkp.program','Program','spark'],
    ['tpkkp.jadwal','Jadwal','calendar'],    ['tpkkp.sampling','Slovin','calc'],
    ['tpkkp.sampel','Rencana Sampel','users'], ['tpkkp.metode','Metode','layers'],
    ['tpkkp.rubrik','Rubrik','book'],        ['tpkkp.roster','Mitra & Akses','building'],
    ['tpkkp.kuesioner','Kuesioner','poll'],  ['tpkkp.data','Data','database'],
    ['tpkkp.profile','Profil','user'],       ['tpkkp.tentang','Instrumen','info'],
  ];
@endphp

<svg width="0" height="0" class="absolute" aria-hidden="true"><defs>
  <symbol id="ti-home" viewBox="0 0 24 24"><path d="M12 3 3 10v11h6v-6h6v6h6V10Z"/></symbol>
  <symbol id="ti-edit" viewBox="0 0 24 24"><path d="M4 17.2V20h2.8L17 9.8 14.2 7Zm14.7-9.9a1 1 0 0 0 0-1.4l-1.6-1.6a1 1 0 0 0-1.4 0L14.3 5.7 17.1 8.5Z"/></symbol>
  <symbol id="ti-grid" viewBox="0 0 24 24"><path d="M3 3h8v6H3Zm10 0h8v6h-8ZM3 11h8v10H3Zm10 0h8v10h-8Z"/></symbol>
  <symbol id="ti-target" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8Zm0-13a5 5 0 1 0 5 5 5 5 0 0 0-5-5Zm0 8a3 3 0 1 1 3-3 3 3 0 0 1-3 3Z"/></symbol>
  <symbol id="ti-award" viewBox="0 0 24 24"><path d="M12 2a6 6 0 1 0 6 6 6 6 0 0 0-6-6Zm0 10a4 4 0 1 1 4-4 4 4 0 0 1-4 4Zm-4 3-2 7 6-3 6 3-2-7a8 8 0 0 1-8 0Z"/></symbol>
  <symbol id="ti-list" viewBox="0 0 24 24"><path d="M4 5h16v2H4Zm0 6h16v2H4Zm0 6h16v2H4Z"/></symbol>
  <symbol id="ti-chart" viewBox="0 0 24 24"><path d="M4 20h16v1.5H4ZM6 11h3v8H6Zm5-6h3v14h-3Zm5 4h3v10h-3Z"/></symbol>
  <symbol id="ti-spark" viewBox="0 0 24 24"><path d="m13 2-9 12h6l-2 8 9-12h-6Z"/></symbol>
  <symbol id="ti-calendar" viewBox="0 0 24 24"><path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2Zm12 17H5V10h14Z"/></symbol>
  <symbol id="ti-calc" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm1 3v3h10V5Zm0 6v2h3v-2Zm5 0v2h3v-2Zm-5 5v2h3v-2Zm5 0v2h3v-2Z"/></symbol>
  <symbol id="ti-users" viewBox="0 0 24 24"><path d="M9 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3 0-8 1.4-8 4.3V21h16v-3.7C17 14.4 12 13 9 13Zm9-2a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 18 11Zm.5 2c-.6 0-1.3.1-1.9.2A5.3 5.3 0 0 1 19 17.3V21h5v-3.7c0-2.6-3.4-4.3-5.5-4.3Z"/></symbol>
  <symbol id="ti-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5Zm0 12.2 7.6-4.2L21 12l-9 5-9-5 1.4-1Zm0 4.3 7.6-4.2L21 16l-9 5-9-5 1.4-.7Z"/></symbol>
  <symbol id="ti-book" viewBox="0 0 24 24"><path d="M6 2h13v20H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm0 2v14h11V4Z"/></symbol>
  <symbol id="ti-building" viewBox="0 0 24 24"><path d="M4 21V3h10v6h6v12ZM6 5v14h6V5Zm8 6v8h4v-8ZM7.5 7h3v2h-3Zm0 4h3v2h-3Zm0 4h3v2h-3Z"/></symbol>
  <symbol id="ti-poll" viewBox="0 0 24 24"><path d="M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm3 12h2v3H7Zm4-6h2v9h-2Zm4 3h2v6h-2Z"/></symbol>
  <symbol id="ti-database" viewBox="0 0 24 24"><path d="M12 2c4.4 0 8 1.3 8 3v14c0 1.7-3.6 3-8 3s-8-1.3-8-3V5c0-1.7 3.6-3 8-3Zm0 2c-3.6 0-6 1-6 1s2.4 1 6 1 6-1 6-1-2.4-1-6-1Zm6 4.4C16.6 9 14.5 9.3 12 9.3S7.4 9 6 8.4v3C7.4 12 9.5 12.3 12 12.3s4.6-.3 6-.9Zm0 6C16.6 15 14.5 15.3 12 15.3s-4.6-.3-6-.9v3.9c.3.3 2.6 1.2 6 1.2s5.7-.9 6-1.2Z"/></symbol>
  <symbol id="ti-user" viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4 0-9 1.9-9 5.3V22h18v-2.7c0-3.4-5-5.3-9-5.3Z"/></symbol>
  <symbol id="ti-info" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-6h2Zm0-8h-2V7h2Z"/></symbol>
</defs></svg>

<div class="flex flex-wrap items-center gap-1.5 mb-4">
  <form method="GET" class="mr-1">
    <select name="tahun" onchange="this.form.submit()"
            class="ring-focus rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12.5px] font-semibold shadow-sm">
      @foreach($daftarTahun as $t)
        <option value="{{ $t }}" @selected($t == $a->tahun)>Periode {{ $t }}</option>
      @endforeach
      @if(!in_array(now()->year, $daftarTahun))
        <option value="{{ now()->year }}">Periode {{ now()->year }} (baru)</option>
      @endif
    </select>
  </form>

  @foreach($tabs as $tab)
    @php $aktif = request()->routeIs($tab[0]); @endphp
    <a href="{{ route($tab[0]) }}" title="{{ $tab[1] }}"
       class="group inline-flex items-center gap-1.5 text-[12px] font-semibold pl-2 pr-3 py-1.5 rounded-full border transition-all duration-200
              {{ $aktif
                 ? 'bg-cam-ink text-white border-cam-ink shadow-sm scale-[1.03]'
                 : 'bg-white text-stone-600 border-stone-200 hover:border-cam-ink/40 hover:text-cam-ink hover:-translate-y-0.5 hover:shadow-sm' }}">
      <svg class="w-3.5 h-3.5 flex-none transition-transform duration-200 group-hover:scale-110
                  {{ $aktif ? 'text-cam-lime-light' : 'text-stone-400 group-hover:text-cam-lime-deep' }}"
           fill="currentColor"><use href="#ti-{{ $tab[2] }}"/></svg>
      <span>{{ $tab[1] }}</span>
    </a>
  @endforeach
</div>
