<script setup lang="ts">
/**
 * Perangkat & keamanan akun — sisi pengguna sendiri.
 *
 * Halaman ini sengaja tidak menuntut hak admin. Yang menduga sandinya
 * bocor harus dapat memutus perangkat lain saat itu juga; setiap menit
 * menunggu izin adalah menit yang diberikan cuma-cuma kepada yang
 * memegang sesi itu.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HalamanPerangkat } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanPerangkat>();

const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});

const lain = computed(() => props.sesi.filter((s) => !s.iniSaya).length);

async function putus(id: string, perangkat: string) {
  if (!await tanya(`Putus ${perangkat}?\n\nPerangkat itu langsung kehilangan aksesnya dan harus masuk lagi.`)) return;

  router.delete(props.tautan.putus, { data: { id }, preserveScroll: true });
}

async function putusLain() {
  if (!await tanya(`Putus ${lain.value} perangkat lain?\n\nPerangkat yang sedang Anda pakai sekarang tetap masuk, supaya Anda dapat langsung mengganti sandi sesudah ini.`)) return;

  router.post(props.tautan.putusLain, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative">
        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Akun</span>
        <h2 class="stat mt-1.5">{{ judul }}</h2>
        <p class="text-[12px] text-white/70 mt-1.5 max-w-xl leading-relaxed">{{ subjudul }}</p>
        <p v-if="masukTerakhir.kapan" class="text-[11.5px] text-white/60 mt-2">
          Masuk terakhir {{ masukTerakhir.kapan }}
          <span v-if="masukTerakhir.ip">dari {{ masukTerakhir.ip }}</span>
        </p>
      </div>
    </section>

    <div v-if="galat.keamanan"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px] text-red-700 leading-relaxed">
      {{ galat.keamanan }}
    </div>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Perangkat yang sedang masuk</h3>
        <button v-if="lain > 0" type="button" @click="putusLain"
                class="rounded-xl border border-red-100 px-3 py-1.5 text-[11.5px] font-bold text-red-600
                       hover:bg-red-50 transition">
          Putus {{ lain }} perangkat lain
        </button>
      </div>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Bila ada baris di sini yang bukan Anda, putuskan sekarang lalu ganti sandi Anda. Memutus
        saja tidak cukup — yang tahu sandinya dapat masuk lagi.
      </p>

      <ul class="space-y-2">
        <li v-for="s in sesi" :key="s.id"
            class="flex items-start justify-between gap-3 rounded-xl border px-3.5 py-2.5"
            :class="s.iniSaya ? 'border-cam-lime bg-cam-lime-soft' : 'border-stone-200'">
          <div class="min-w-0">
            <div class="text-[12.5px] font-semibold text-cam-ink">{{ s.perangkat }}</div>
            <div class="text-[11px] text-stone-600 mt-0.5">
              {{ s.ip }} · aktif {{ s.terakhir }}
            </div>
          </div>
          <span v-if="s.iniSaya"
                class="shrink-0 rounded-lg bg-white px-2 py-0.5 text-[10.5px] font-bold text-cam-lime-deep">
            perangkat ini
          </span>
          <button v-else type="button" @click="putus(s.id, s.perangkat)"
                  class="shrink-0 text-[11.5px] font-semibold text-red-600 hover:underline">
            Putus
          </button>
        </li>
      </ul>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Aktivitas akun terakhir</h3>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Percobaan masuk yang gagal pada akun Anda ikut tercatat di sini. Beberapa gagal dari
        alamat yang tidak Anda kenal berarti ada yang sedang menebak sandi Anda.
      </p>

      <p v-if="!riwayat.length" class="text-[12px] text-stone-600">Belum ada aktivitas tercatat.</p>

      <ul v-else class="space-y-1.5">
        <li v-for="r in riwayat" :key="r.id"
            class="flex items-start gap-2.5 rounded-xl border border-stone-100 px-3 py-2">
          <span class="mt-0.5 inline-block rounded-lg px-2 py-0.5 text-[10.5px] font-bold shrink-0"
                :class="r.gagal ? 'bg-red-100 text-red-800' : 'bg-stone-100 text-stone-700'">
            {{ r.peristiwa }}
          </span>
          <div class="min-w-0">
            <div class="text-[11px] text-stone-600">
              {{ r.ip }} · {{ r.perangkat }} · {{ r.kapan }}
            </div>
          </div>
        </li>
      </ul>
    </section>

  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
