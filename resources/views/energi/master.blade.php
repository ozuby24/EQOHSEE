@extends('layouts.app')
@section('title','Master Data Energi')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <x-energi.kepala judul="Master Data — Unit Alat"
      ket="Daftar alat yang catatan bahan bakarnya diikuti. Kategori menentukan acuan
           pembandingnya: sebuah unit dinilai terhadap rata-rata kelompoknya sendiri, jadi
           salah kategori berarti salah pula penilaiannya.">

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach(Energi::KATEGORI as $kode => $nama)
        <div>
          <div class="num text-[20px] font-bold text-cam-lime-deep">{{ $units->where('kategori', $kode)->count() }}</div>
          <div class="text-[10.5px] text-stone-400 mt-1">{{ $nama }}</div>
        </div>
      @endforeach
    </div>
  </x-energi.kepala>

  <div class="grid gap-4 lg:grid-cols-5">

    {{-- Formulir --}}
    <section class="kartu-lux rounded-2xl p-6 lg:col-span-2">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Daftarkan Unit</h3>

      <form method="POST" action="{{ route('energi.master.simpan') }}" class="space-y-3.5 mt-5">
        @csrf

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Kode</label>
            <input name="kode" value="{{ old('kode') }}" required placeholder="HD785-01"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
            <x-input-error :messages="$errors->get('kode')" class="mt-1.5" />
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Kategori</label>
            <select name="kategori" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
              @foreach(Energi::KATEGORI as $kode => $nama)
                <option value="{{ $kode }}" @selected(old('kategori') === $kode)>{{ $nama }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Nama</label>
          <input name="nama" value="{{ old('nama') }}" required placeholder="Dump Truck Komatsu HD785-7"
                 class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Perusahaan</label>
          <select name="company_id" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
            <option value="">Tidak ditentukan</option>
            @foreach($companies as $c)
              <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Merek</label>
            <input name="merek" value="{{ old('merek') }}" placeholder="Komatsu"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Daya (HP)</label>
            <input type="number" name="daya_hp" value="{{ old('daya_hp') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Payload (t)</label>
            <input type="number" step="0.01" name="payload_ton" value="{{ old('payload_ton') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition">
          </div>
        </div>

        <button class="w-full rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition">
          Daftarkan Unit
        </button>
      </form>
    </section>

    {{-- Daftar --}}
    <section class="kartu-lux rounded-2xl p-6 lg:col-span-3">
      <div class="flex items-baseline justify-between gap-3">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Unit Terdaftar</h3>
        <span class="text-[11.5px] text-stone-400 shrink-0">{{ $units->count() }} unit</span>
      </div>

      @if($units->count())
        <div class="overflow-x-auto mt-4 -mx-1">
          <table class="w-full text-[12.5px] min-w-[520px]">
            <thead>
              <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
                <th class="text-left py-2.5">Kode</th>
                <th class="text-left py-2.5">Nama</th>
                <th class="text-left py-2.5">Kategori</th>
                <th class="num py-2.5">HP</th>
                <th class="num py-2.5">Payload</th>
                <th class="py-2.5"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($units as $u)
                <tr class="hairline">
                  <td class="py-2.5">
                    <a href="{{ route('energi.equipment.show', $u) }}"
                       class="font-bold text-cam-ink hover:text-cam-lime-deep transition">{{ $u->kode }}</a>
                  </td>
                  <td class="py-2.5 text-stone-500">
                    {{ $u->nama }}
                    @if($u->merek)<span class="block text-[10.5px] text-stone-400">{{ $u->merek }}</span>@endif
                  </td>
                  <td class="py-2.5 text-stone-500">{{ $u->labelKategori() }}</td>
                  <td class="num py-2.5">{{ $u->daya_hp ? number_format($u->daya_hp) : '—' }}</td>
                  <td class="num py-2.5">{{ $u->payload_ton ? number_format($u->payload_ton, 1) : '—' }}</td>
                  <td class="py-2.5 text-right">
                    <form method="POST" action="{{ route('energi.master.hapus', $u) }}"
                          onsubmit="return confirm('Hapus {{ $u->kode }} beserta seluruh catatan bahan bakarnya?')">
                      @csrf @method('DELETE')
                      <button class="text-[11.5px] font-bold text-stone-400 hover:text-cam-coral transition">Hapus</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <p class="text-[10.5px] text-stone-400 mt-4 leading-relaxed">
          Menghapus unit ikut menghapus seluruh catatan bahan bakarnya — angka riwayat pada
          halaman lain akan berubah. Untuk unit yang sekadar berhenti beroperasi, biarkan
          terdaftar; unit tanpa catatan pada suatu rentang memang tidak muncul di peringkat.
        </p>
      @else
        <p class="text-[12px] text-stone-400 mt-6">Belum ada unit yang terdaftar.</p>
      @endif
    </section>
  </div>

</div>
@endsection
