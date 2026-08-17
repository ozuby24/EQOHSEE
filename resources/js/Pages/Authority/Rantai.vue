<script setup lang="ts">
/**
 * Rantai paraf: terlihat bertingkat, satu tahap yang memutuskan.
 *
 * Bahaya gambar semacam ini ada pada pembacaannya, bukan pada datanya.
 * Rantai yang seluruh mata rantainya hijau mudah terbaca sebagai "sudah
 * disetujui" padahal OHSE belum memutuskan apa pun — dan pembacaan itu
 * dipakai orang untuk memutuskan apakah seseorang boleh masuk gerbang.
 *
 * Tiga hal yang menahannya:
 *
 *   Tahap penentu digambar BERBEDA — lebih tebal, berlabel tegas — dan
 *   warnanya diambil dari status, bukan dari tabel paraf.
 *
 *   Tahap paraf memakai warna netral, bukan hijau. Hijau di seluruh
 *   sistem ini berarti "aman/lolos", dan paraf bukan itu.
 *
 *   Kalimat penutupnya menyebut apa yang belum diparaf, bukan
 *   menyembunyikannya.
 */
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  rantai: Array<{
    kode: string; urut: number; label: string; terang: string; penentu: boolean;
    keadaan: string; oleh: string | null; jabatan: string | null;
    pada: string | null; catatan: string | null;
  }>;
  tertinggal?: string[];
  dapatParaf?: boolean;
  sayaPenentu?: boolean;
}>();

const emit = defineEmits<{ (e: 'paraf', tahap: string): void }>();

/**
 * Warna tiap mata rantai.
 *
 * Tahap paraf sengaja TIDAK pernah hijau: hijau dipesan untuk "lolos",
 * dan paraf tidak meloloskan apa pun. Ia memakai warna tinta biasa —
 * sudah terjadi, tidak lebih.
 */
function warna(t: { penentu: boolean; keadaan: string }): string {
  if (!t.penentu) return t.keadaan === 'paraf' ? '#44403C' : KEADAAN.netral;

  return {
    disetujui: KEADAAN.baik,
    ditolak:   KEADAAN.gawat,
    diajukan:  KEADAAN.ingat,
  }[t.keadaan] ?? KEADAAN.netral;
}

function keterangan(t: { penentu: boolean; keadaan: string; oleh: string | null; pada: string | null }): string {
  if (t.penentu) {
    return {
      draf:      'belum diajukan',
      diajukan:  'menunggu keputusan',
      disetujui: 'disetujui',
      ditolak:   'ditolak',
    }[t.keadaan] ?? t.keadaan;
  }

  return t.keadaan === 'paraf' ? `${t.oleh} · ${(t.pada ?? '').slice(0, 16)}` : 'belum diparaf';
}
</script>

<template>
  <div class="rounded-xl bg-stone-50 border border-stone-100 p-3">
    <ol class="flex flex-col sm:flex-row sm:items-stretch gap-2">
      <li v-for="t in props.rantai" :key="t.kode"
          class="flex-1 flex items-start gap-2 min-w-0">

        <!-- penanda urutan; yang penentu diberi cincin supaya terbaca
             berbeda tanpa bergantung pada warna saja -->
        <span class="shrink-0 w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold text-white mt-0.5"
              :style="{ background: warna(t), boxShadow: t.penentu ? `0 0 0 2px #fff, 0 0 0 4px ${warna(t)}` : 'none' }">
          {{ t.urut }}
        </span>

        <div class="min-w-0 flex-1">
          <p class="text-[11.5px] font-bold leading-tight" :style="{ color: warna(t) }">
            {{ t.label }}
            <span v-if="t.penentu" class="text-[9.5px] font-bold uppercase tracking-wide ml-1">
              penentu
            </span>
          </p>
          <p class="text-[10.5px] text-stone-500 leading-tight mt-0.5 truncate" :title="t.terang">
            {{ keterangan(t) }}
          </p>

          <button v-if="!t.penentu && t.keadaan !== 'paraf' && props.dapatParaf"
                  type="button" class="text-[10.5px] font-semibold text-cam-lime-deep mt-1"
                  @click="emit('paraf', t.kode)">
            Bubuhkan paraf
          </button>
        </div>
      </li>
    </ol>

    <!-- Yang belum diparaf disebut, bukan disembunyikan. OHSE boleh
         memutuskan tanpa menunggunya — tetapi harus tahu itu yang sedang
         ia lakukan. -->
    <p v-if="props.tertinggal?.length && props.sayaPenentu"
       class="text-[10.5px] mt-2 pt-2 border-t border-stone-200" :style="{ color: KEADAAN.ingat }">
      Belum diparaf: {{ props.tertinggal.join(', ') }}.
      Keputusan OHSE tetap dapat diambil tanpa menunggunya.
    </p>
  </div>
</template>
