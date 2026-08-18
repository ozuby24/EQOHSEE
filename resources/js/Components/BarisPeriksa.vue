<script setup lang="ts">
/**
 * Satu baris hasil pemeriksaan.
 *
 * Dipakai bersama oleh Diagnosa (kesehatan sistem) dan Kesesuaian
 * (kebenaran angka). Bentuk barisnya memang sama, dan satu perender
 * untuk keduanya bukan sekadar hemat: dua salinan markah seperti ini
 * pasti berbeda cepat atau lambat, dan bedanya muncul justru pada baris
 * yang paling jarang tampil — yang merah.
 *
 * Tiap baris menyebut tiga hal, bukan satu: keadaannya, akibatnya bila
 * dibiarkan, dan langkahnya. Daftar merah tanpa akibat dan tanpa langkah
 * hanya melahirkan kebiasaan mengabaikan warna merah.
 */
defineProps<{
  baris: {
    kode: string; kelompok: string; judul: string;
    keadaan: string; nilai: string; uraian: string; tindakan: string | null;
  };
}>();

const NADA: Record<string, { label: string; teks: string; latar: string; garis: string; titik: string }> = {
  gawat:     { label: 'Gawat',           teks: 'text-red-700',     latar: 'bg-red-50',   garis: 'border-red-100',   titik: 'bg-red-500' },
  perhatian: { label: 'Perlu perhatian', teks: 'text-amber-800',   latar: 'bg-amber-50', garis: 'border-amber-100', titik: 'bg-amber-500' },
  'tak-tahu':{ label: 'Belum dinilai',   teks: 'text-stone-600',   latar: 'bg-stone-50', garis: 'border-stone-200', titik: 'bg-stone-400' },
  aman:      { label: 'Aman',            teks: 'text-emerald-700', latar: 'bg-white',    garis: 'border-stone-100', titik: 'bg-emerald-500' },
};

const nada = (k: string) => NADA[k] ?? NADA['tak-tahu'];
</script>

<template>
  <div class="rounded-2xl border shadow-card p-4"
       :class="[nada(baris.keadaan).garis, nada(baris.keadaan).latar]">
    <div class="flex items-start gap-3">
      <span class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0" :class="nada(baris.keadaan).titik"></span>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
          <span class="text-[13.5px] font-bold text-cam-ink">{{ baris.judul }}</span>

          <!-- Nada bakunya, bukan bg-white/70: pada tema gelap putih tetap
               putih sementara text-stone-500 ikut diterangkan, sehingga abu
               terang duduk di atas putih pada 2,6:1. Pasangan
               stone-100/stone-600 inilah yang dipakai lencana lain di
               aplikasi ini dan yang terbukti lolos di kedua tema. -->
          <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100
                       text-stone-600 px-1.5 py-0.5 rounded">
            {{ baris.kelompok }}
          </span>
          <span class="text-[11px] font-bold" :class="nada(baris.keadaan).teks">
            {{ nada(baris.keadaan).label }}
          </span>
        </div>

        <div class="text-[12.5px] font-semibold text-stone-700 mt-1.5 break-words">
          {{ baris.nilai }}
        </div>

        <!-- stone-600, bukan stone-500: kartu ini berlatar merah atau kuning
             muda, dan di atas latar itu stone-500 turun ke 4,4:1 — tepat di
             bawah ambang. Justru baris yang paling perlu dibaca yang latarnya
             paling berwarna. -->
        <p class="text-[12px] text-stone-600 mt-1.5 leading-relaxed">{{ baris.uraian }}</p>

        <p v-if="baris.tindakan"
           class="text-[12px] mt-2 leading-relaxed font-semibold" :class="nada(baris.keadaan).teks">
          Langkah: {{ baris.tindakan }}
        </p>
      </div>
    </div>
  </div>
</template>
