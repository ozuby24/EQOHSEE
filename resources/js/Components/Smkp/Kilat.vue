<script setup lang="ts">
/**
 * Pesan galat dari server sebagai toast (porting `Components/Kilat.vue`
 * Aurorasys) — hanya untuk halaman audit SMKP.
 *
 * Pesan BERHASIL tidak ditampilkan di sini: kerangka EQOHSEE (AppLayout)
 * sudah menggambarnya sebagai bilah di atas halaman, dan dua pesan yang
 * sama pada satu layar terbaca sebagai galat. Yang ditambahkan komponen
 * ini hanya yang tidak digambar kerangka: `kilat.galat` dan galat
 * validasi pertama dari permintaan tanpa formulir (router.post langsung),
 * yang di Aurorasys juga muncul lewat toast ini.
 *
 * Dipasang oleh KepalaHalaman dan KepalaAudit — setiap halaman SMKP
 * memakai salah satunya.
 */
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Ikon from './Ikon.vue';

const page = usePage<any>();
const tampil = ref(false);
const teks = ref<string | null>(null);
let jeda: ReturnType<typeof setTimeout> | null = null;

const sumber = computed<string | null>(() =>
  (page.props.kilat?.galat as string | null) ?? (Object.values(page.props.errors ?? {})[0] as string | undefined) ?? null);

watch(sumber, (s) => {
  if (!s) return;
  teks.value = s;
  tampil.value = true;
  if (jeda) clearTimeout(jeda);
  jeda = setTimeout(() => { tampil.value = false; }, 7000);
}, { immediate: true });
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-3"
                leave-active-class="transition duration-200" leave-to-class="opacity-0 -translate-y-3">
      <div v-if="tampil && teks" class="smkp-ui print:hidden fixed z-[70] top-20 right-5 left-5 sm:left-auto sm:max-w-md" role="alert">
        <div class="flex items-start gap-3 rounded-kartu px-4 py-3.5 shadow-angkat border bg-white border-rose-200">
          <span class="shrink-0 grid place-items-center w-8 h-8 rounded-lg bg-rose-50 text-rose-600">
            <Ikon nama="peringatan" class="w-[18px] h-[18px]" />
          </span>
          <p class="text-[13px] leading-relaxed pt-1 flex-1 text-rose-700">{{ teks }}</p>
          <button type="button" class="shrink-0 p-1 rounded-md opacity-60 hover:opacity-100" aria-label="Tutup" @click="tampil = false">
            <Ikon nama="tutup" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
