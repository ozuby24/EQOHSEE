<script setup lang="ts">
/**
 * PTPKKP — Data & Koneksi.
 *
 * Dua tindakan di halaman ini menimpa data dan tidak bisa dibatalkan dari
 * layar. Versi Blade menjaganya dengan confirm() saja — satu ketukan
 * refleks, di kotak yang tampilannya sama persis dengan kotak konfirmasi
 * lain yang tidak berbahaya.
 *
 * Di sini keduanya menuntut angka periodenya diketik ulang. Bukan untuk
 * mempersulit, melainkan supaya orang yang salah membuka periode berhenti
 * pada langkah itu: yang diketik tidak akan cocok dengan yang di layar.
 *
 * JSON impor diperiksa lebih dulu di peramban, dan kunci yang akan dipakai
 * ditampilkan sebelum tombolnya ditekan. Daftar kunci itu datang dari
 * server, bukan diketik ulang di sini.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanData } from '../../types';

const props = defineProps<HalamanData>();

/* ── impor ── */

const json = ref('');
const mengimpor = ref(false);
const konfirmasiImpor = ref('');

/** Hasil pembacaan JSON: null bila kosong, false bila tidak terbaca. */
const terbaca = computed<Record<string, unknown> | null | false>(() => {
  if (!json.value.trim()) return null;

  try {
    const isi = JSON.parse(json.value);

    return isi && typeof isi === 'object' && !Array.isArray(isi) ? isi : false;
  } catch {
    return false;
  }
});

const kunciDipakai = computed(() =>
  terbaca.value ? props.kunciDikenal.filter((k) => k in (terbaca.value as object)) : []);

const kunciDiabaikan = computed(() =>
  terbaca.value
    ? Object.keys(terbaca.value as object).filter((k) => !props.kunciDikenal.includes(k))
    : []);

const bolehImpor = computed(() =>
  !mengimpor.value
  && kunciDipakai.value.length > 0
  && konfirmasiImpor.value.trim() === String(props.tahun));

function impor() {
  mengimpor.value = true;

  router.post(`/tpkkp/data/impor?tahun=${props.tahun}`, { json: json.value }, {
    preserveScroll: true,
    onSuccess: () => { json.value = ''; konfirmasiImpor.value = ''; },
    onFinish:  () => { mengimpor.value = false; },
  });
}

/* ── kosongkan ── */

const konfirmasiReset = ref('');
const mengosongkan = ref(false);

const bolehReset = computed(() =>
  !mengosongkan.value && konfirmasiReset.value.trim() === String(props.tahun));

function kosongkan() {
  mengosongkan.value = true;

  router.post(`/tpkkp/data/reset?tahun=${props.tahun}`, {}, {
    preserveScroll: true,
    onSuccess: () => { konfirmasiReset.value = ''; },
    onFinish:  () => { mengosongkan.value = false; },
  });
}
</script>

<template>
  <Head title="PTPKKP — Data & Koneksi" />

  <div class="max-w-4xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="grid sm:grid-cols-4 gap-3">
      <div v-for="s in ringkas" :key="s.label"
           class="bg-white rounded-2xl border border-stone-200 px-4 py-3.5">
        <div class="stat text-[19px] leading-none">{{ s.nilai }}</div>
        <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold mt-1.5">
          {{ s.label }}
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Ekspor</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Unduh seluruh isi penilaian periode {{ tahun }} sebagai JSON —
        {{ kunciDikenal.join(', ') }}.
      </p>
      <!-- Unduhan berkas, bukan kunjungan Inertia: harus <a> biasa. -->
      <a :href="urlEkspor"
         class="inline-block rounded-xl bg-cam-ink text-white px-5 py-2.5 text-[12.5px] font-bold
                hover:brightness-110 transition">
        ↓ Unduh JSON periode {{ tahun }}
      </a>
    </div>

    <template v-if="bisaSunting">
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-1">Impor</h3>
        <p class="text-[11.5px] text-stone-500 mb-3">
          Tempel JSON hasil ekspor. Isi periode {{ tahun }} akan <b>ditimpa</b>.
          Hanya kunci yang dikenali yang dipakai.
        </p>

        <textarea v-model="json" rows="7" placeholder="JSON hasil ekspor — tempel utuh di sini"
                  class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5
                         text-[11.5px] font-mono"></textarea>

        <p v-if="terbaca === false" class="text-[11.5px] text-red-600 font-semibold mt-2">
          JSON tidak bisa dibaca. Pastikan disalin utuh, dari kurung pembuka sampai penutup.
        </p>

        <div v-else-if="terbaca" class="text-[11.5px] mt-2 space-y-1">
          <p v-if="kunciDipakai.length" class="text-stone-600">
            Akan dipakai: <b>{{ kunciDipakai.join(', ') }}</b>.
          </p>
          <p v-else class="text-red-600 font-semibold">
            Tidak ada kunci yang dikenali di JSON ini — tidak ada yang akan berubah.
          </p>
          <p v-if="kunciDiabaikan.length" class="text-stone-400">
            Diabaikan: {{ kunciDiabaikan.join(', ') }}.
          </p>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <input v-model="konfirmasiImpor" type="text" :placeholder="`Ketik ${tahun} untuk mengizinkan`"
                 class="ring-focus w-56 rounded-xl border border-stone-200 px-3 py-2 text-[12px] num">
          <button type="button" :disabled="!bolehImpor" @click="impor"
                  class="rounded-xl bg-cam-ink text-white px-5 py-2.5 text-[12.5px] font-bold
                         hover:brightness-110 transition disabled:opacity-30 disabled:cursor-not-allowed">
            {{ mengimpor ? 'Mengimpor…' : 'Impor & timpa' }}
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-red-200 p-5">
        <h3 class="text-[13px] font-bold text-red-700 mb-1">Kosongkan nilai</h3>
        <p class="text-[11.5px] text-stone-500 mb-3">
          Menghapus seluruh nilai penilaian periode {{ tahun }}. Roster, profil, program, dan
          jadwal tetap. Salinan otomatis disimpan ke
          <span class="font-mono">storage/app/</span> sebelum dihapus.
        </p>

        <div class="flex flex-wrap items-center gap-3">
          <input v-model="konfirmasiReset" type="text" :placeholder="`Ketik ${tahun} untuk mengizinkan`"
                 class="ring-focus w-56 rounded-xl border border-red-200 px-3 py-2 text-[12px] num">
          <button type="button" :disabled="!bolehReset" @click="kosongkan"
                  class="rounded-xl border border-red-300 text-red-700 px-5 py-2.5 text-[12.5px] font-bold
                         hover:bg-red-50 transition disabled:opacity-30 disabled:cursor-not-allowed">
            {{ mengosongkan ? 'Mengosongkan…' : `Kosongkan nilai periode ${tahun}` }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
