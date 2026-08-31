<script setup lang="ts">
/**
 * Rel tahap investigasi — enam bulatan yang menyebut apa yang kurang.
 *
 * MENGAPA BUKAN SEKADAR STEPPER. Rel yang hanya menggambar bulatan
 * memberi tahu pembacanya di mana ia berada dan tidak memberi tahu apa
 * pun tentang langkah berikutnya. Investigasi kecelakaan justru gampang
 * berhenti di situ: berkasnya menganggur berbulan-bulan karena tidak ada
 * yang tahu apa yang masih kurang.
 *
 * Karena itu tiap tahap membawa tugasnya, dan tahap yang sedang berjalan
 * membawa daftar syarat yang belum terpenuhi. Tombol maju baru muncul
 * ketika daftarnya kosong.
 *
 * PANJANG RELNYA TIDAK SAMA UNTUK SETIAP BERKAS. L1 hanya empat tahap:
 * analisis dan verifikasi terpisah dilewati. Yang dilewati adalah
 * tahapnya, bukan isinya — akar masalah tetap wajib, hanya diminta di
 * tahap Rencana Aksi.
 */
import { KEADAAN } from '../../Grafik/warna';

defineProps<{
  rel: Array<{ kunci: string; label: string; tugas: string; lewat: boolean; kini: boolean; nanti: boolean }>;
  tugas?: string | null;
  kurang?: string[];
  bolehMaju?: boolean;
  sibuk?: boolean;
}>();

const emit = defineEmits<{ (e: 'maju'): void; (e: 'mundur'): void }>();
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
    <!-- ══════════ bulatan dan garisnya ══════════ -->
    <ol class="flex items-start gap-0 px-5 pt-5 pb-1 overflow-x-auto">
      <li v-for="(t, i) in rel" :key="t.kunci"
          class="flex-1 min-w-[120px] flex flex-col items-center text-center relative">

        <!-- Garis penghubung digambar DI BELAKANG bulatannya, dan tidak
             digambar sebelum bulatan pertama — garis yang menggantung di
             tepi kiri terbaca sebagai tahap yang terpotong. -->
        <span v-if="i > 0" class="absolute top-[11px] right-1/2 w-full h-[2px]"
              :style="{ background: t.lewat || t.kini ? KEADAAN.baik : '#E7E5E4' }"></span>

        <span class="relative z-10 w-[22px] h-[22px] rounded-full grid place-items-center text-[10px] font-bold"
              :style="t.lewat
                ? { background: KEADAAN.baik, color: '#fff' }
                : (t.kini
                    ? { background: '#fff', color: KEADAAN.baik, boxShadow: `inset 0 0 0 2px ${KEADAAN.baik}` }
                    : { background: '#F5F5F4', color: '#A8A29E', boxShadow: 'inset 0 0 0 2px #E7E5E4' })">
          {{ t.lewat ? '✓' : i + 1 }}
        </span>

        <span class="mt-1.5 text-[11px] leading-tight px-1"
              :class="t.kini ? 'font-bold text-cam-ink' : 'text-stone-400'">
          {{ t.label }}
        </span>
      </li>
    </ol>

    <!-- ══════════ tahap sekarang: tugasnya dan apa yang kurang ══════════ -->
    <div class="mx-5 mb-5 mt-3 rounded-xl px-4 py-3.5" style="background:#F6EEDF">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-[10px] uppercase tracking-wide font-bold" style="color:#7D8C89">
            Tahap sekarang — {{ rel.find(t => t.kini)?.label ?? '—' }}
          </p>
          <p class="text-[12.5px] text-cam-ink mt-0.5">{{ tugas || rel.find(t => t.kini)?.tugas }}</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button type="button" class="rounded-lg border border-stone-300 px-2.5 py-1.5 text-[11px] font-bold text-stone-600 hover:bg-white"
                  :disabled="sibuk" @click="emit('mundur')">←</button>

          <span v-if="bolehMaju" class="inline-flex">
            <button type="button" class="eq-btn-utama" :disabled="sibuk" @click="emit('maju')">
              Maju ke tahap berikutnya →
            </button>
          </span>
        </div>
      </div>

      <!-- Yang KURANG disebut satu per satu, bukan diringkas menjadi
           "belum lengkap". Ringkasan tidak memberi tahu apa yang harus
           dikerjakan, dan yang tidak tahu harus mengerjakan apa akan
           meninggalkan berkasnya. -->
      <ul v-if="(kurang ?? []).length" class="mt-3 grid gap-1.5">
        <li v-for="k in kurang" :key="k"
            class="flex items-start gap-2 text-[11.5px]" style="color:#92400E">
          <span class="mt-[5px] w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: KEADAAN.ingat }"></span>
          <span>{{ k }}</span>
        </li>
      </ul>

      <p v-else class="mt-3 text-[11.5px] font-semibold" :style="{ color: KEADAAN.baik }">
        ✓ Tahap ini sudah lengkap.
      </p>
    </div>
  </section>
</template>
