@props(['dok', 'halaman' => 1, 'dari' => 1, 'akhir' => false])

{{--
  Satu lembar dokumen terkendali.

  Kop dicetak ulang pada tiap lembar karena nomor halaman harus ikut berubah,
  dan peramban tidak dapat menghitungnya sendiri saat mencetak — `counter(page)`
  tidak didukung. Karena itu pemenggalan halaman ditentukan di sini, bukan
  diserahkan ke peramban, sehingga "Halaman 2 dari 3" selalu benar.
--}}
<section class="lembar bg-white rounded-2xl shadow-card border border-stone-100 p-6 sm:p-8 {{ $akhir ? '' : 'lembar-putus' }}">

  {{-- Kop --}}
  <table class="w-full border-collapse text-[10.5px] mb-5">
    <tbody>
      <tr>
        <td class="border border-stone-300 p-2 w-[16%] text-center align-middle">
          @if($dok['logo'])
            <img src="{{ asset('storage/'.$dok['logo']) }}" alt="" class="h-10 w-auto max-w-full object-contain mx-auto">
          @else
            <div class="font-display text-[12px] font-black text-cam-ink leading-tight">{{ $dok['perusahaan'] }}</div>
          @endif
        </td>

        <td class="border border-stone-300 p-2 text-center align-middle">
          <div class="font-bold uppercase tracking-wide text-stone-500 text-[9.5px]">{{ $dok['jenis'] }}</div>
          <div class="font-bold uppercase text-cam-ink leading-snug mt-1 text-[11px]">{{ $dok['judul'] }}</div>
        </td>

        <td class="border border-stone-300 p-2 w-[34%] align-middle">
          <table class="w-full">
            <tbody>
              @foreach ([
                ['No. Dokumen',    $dok['nomor']],
                ['Tgl Penerbitan', $dok['terbit'] ? $dok['terbit']->translatedFormat('d F Y') : '—'],
                ['Tgl Persetujuan',$dok['setuju'] ? $dok['setuju']->translatedFormat('d F Y') : '—'],
                ['No. Revisi',     $dok['revisi']],
                ['Halaman',        $halaman.' dari '.$dari],
              ] as [$k, $v])
                <tr>
                  <td class="text-stone-500 whitespace-nowrap py-px">{{ $k }}</td>
                  <td class="text-stone-400 px-1 py-px">:</td>
                  <td class="font-semibold text-cam-ink num py-px">{{ $v }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </td>
      </tr>

      <tr>
        <td colspan="3" class="border border-stone-300 px-2 py-1.5">
          <div><span class="text-stone-500 inline-block w-24">Divisi</span><span class="text-stone-400">:</span> <span class="text-cam-ink">{{ $dok['divisi'] }}</span></div>
          <div><span class="text-stone-500 inline-block w-24">Departemen</span><span class="text-stone-400">:</span> <span class="text-cam-ink">{{ $dok['departemen'] }}</span></div>
        </td>
      </tr>
    </tbody>
  </table>

  {{ $slot }}
</section>
