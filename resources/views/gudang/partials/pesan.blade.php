@if(session('sukses'))
  <div class="rounded-xl px-4 py-3 text-[12.5px] font-semibold flex items-center gap-2.5"
       style="background:var(--eq-aksen-tipis,rgba(14,116,126,.12));color:var(--eq-aksen,#0E747E)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
         stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0" aria-hidden="true">
      <path d="m5 12.5 4.5 4.5L19 7.5"/>
    </svg>
    {{ session('sukses') }}
  </div>
@endif

@if($errors->any())
  <div class="rounded-xl bg-red-50 border border-red-100 px-4 py-3">
    <p class="text-[12.5px] font-bold text-red-700">Ada {{ $errors->count() }} isian yang perlu diperbaiki:</p>
    <ul class="mt-1.5 space-y-0.5">
      @foreach($errors->all() as $galat)
        <li class="text-[12px] text-red-600">· {{ $galat }}</li>
      @endforeach
    </ul>
  </div>
@endif
