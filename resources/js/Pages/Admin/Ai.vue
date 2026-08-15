<script setup lang="ts">
/**
 * Pengaturan integrasi AI.
 *
 * Kunci API tidak pernah sampai ke halaman ini. Yang dikirim server
 * hanya penanda "terpasang" beserta empat huruf terakhirnya, cukup
 * untuk membedakan kunci mana yang sedang dipakai tanpa
 * memperlihatkan isinya. Kolom isiannya karena itu selalu mulai
 * kosong, dan kosong berarti "biarkan yang sudah ada".
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { HalamanAi } from '../../types';

const props = defineProps<HalamanAi>();

const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});
const jawabanAi = computed<string | null>(() => (halaman.props.kilat as any)?.aiJawaban ?? null);

const form = useForm({
  penyedia: props.terpilih,
  model: props.model,
  maks_token: props.maksToken,
  kunci: '',
});

const terpilih = computed(() => props.penyedia.find((p) => p.kode === form.penyedia));

/** Ganti penyedia: modelnya ikut kembali ke bawaan penyedia itu. */
function pilih(kode: string) {
  form.penyedia = kode;
  form.model = props.penyedia.find((p) => p.kode === kode)?.modelBawaan ?? '';
  form.kunci = '';
}

function simpan() {
  form.post(props.tautan.simpan, { preserveScroll: true, onSuccess: () => form.reset('kunci') });
}

const menguji = ref(false);

function uji() {
  menguji.value = true;
  router.post(props.tautan.uji,
    { penyedia: form.penyedia, model: form.model, kunci: form.kunci },
    { preserveScroll: true, onFinish: () => (menguji.value = false) });
}

function hapus(kode: string, nama: string) {
  if (!confirm(`Hapus kunci ${nama}?\n\nAsisten akan mati bila ini penyedia yang sedang dipakai. Kuncinya tidak dapat dipulihkan dari sini — ambil lagi dari penyedianya bila diperlukan.`)) return;

  router.delete(props.tautan.hapus, { data: { penyedia: kode }, preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-4xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative flex items-start justify-between gap-4 flex-wrap">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Pusat Kendali
          </span>
          <h2 class="stat mt-1.5">{{ judul }}</h2>
          <p class="text-[12px] text-white/70 mt-1.5 max-w-xl leading-relaxed">{{ subjudul }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="glass rounded-xl px-3 py-2 text-[11.5px] font-bold text-white">
            {{ aktif ? 'Aktif' : 'Belum aktif' }}
          </span>
          <Link :href="tautan.sistem"
                class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white hover:bg-white/20 transition">
            Pusat Kendali
          </Link>
        </div>
      </div>
    </section>

    <div v-if="galat.ai"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px] text-red-700 leading-relaxed">
      {{ galat.ai }}
    </div>

    <!-- Penyedia -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Penyedia</h3>
      <p class="text-[11.5px] text-stone-500 leading-relaxed mb-3.5">
        Kunci API dibayar oleh Anda sendiri, jadi penyedianya Anda yang pilih. Kunci tiap penyedia
        disimpan terpisah — berpindah penyedia tidak membuang kunci yang sudah dimasukkan.
      </p>

      <div class="grid gap-2.5 sm:grid-cols-3">
        <button v-for="p in penyedia" :key="p.kode" type="button" @click="pilih(p.kode)"
                class="text-left rounded-xl border px-4 py-3 transition"
                :class="form.penyedia === p.kode
                  ? 'border-cam-lime bg-cam-lime-soft'
                  : 'border-stone-200 hover:border-cam-lime/50'">
          <div class="text-[12.5px] font-bold text-cam-ink">{{ p.nama }}</div>
          <!-- stone-600, bukan stone-500: kartu terpilih berlatar
               cam-lime-soft, dan di atas nada itu stone-500 turun ke
               4,35:1 — tepat di bawah ambang. -->
          <div class="text-[11px] mt-1"
               :class="p.terpasang ? 'text-emerald-700 font-semibold' : 'text-stone-600'">
            {{ p.terpasang ? 'kunci terpasang ' + p.ekor : 'belum ada kunci' }}
          </div>
          <div v-if="p.dariBerkas" class="text-[10px] text-stone-600 mt-1">dari .env</div>
        </button>
      </div>
    </section>

    <!-- Kunci dan model -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-3.5">
        Kunci {{ terpilih?.nama }}
      </h3>

      <form class="space-y-4" @submit.prevent="simpan">
        <label class="block">
          <span class="text-[12px] font-semibold text-cam-ink">Kunci API</span>
          <input v-model="form.kunci" type="password" autocomplete="off" spellcheck="false"
                 :placeholder="terpilih?.terpasang
                   ? 'Terpasang ' + terpilih?.ekor + ' — biarkan kosong untuk mempertahankannya'
                   : 'Tempelkan kunci di sini'"
                 class="mt-1 w-full rounded-xl border-stone-200 text-[12.5px] font-mono">
          <span class="block text-[11px] text-stone-500 mt-1.5 leading-relaxed">
            Disimpan terenkripsi dan tidak pernah dikirim kembali ke peramban — termasuk kepada Anda.
            Ambil kuncinya di
            <a :href="terpilih?.kunciDari" target="_blank" rel="noopener"
               class="text-cam-lime-deep font-semibold hover:underline">{{ terpilih?.kunciDari }}</a>.
          </span>
          <span v-if="form.errors.kunci" class="block text-[11px] text-red-600 mt-1">{{ form.errors.kunci }}</span>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="text-[12px] font-semibold text-cam-ink">Model</span>
            <input v-model="form.model" list="model-contoh" spellcheck="false"
                   class="mt-1 w-full rounded-xl border-stone-200 text-[12.5px] font-mono">
            <datalist id="model-contoh">
              <option v-for="m in terpilih?.contohModel ?? []" :key="m" :value="m" />
            </datalist>
            <span class="block text-[11px] text-stone-500 mt-1.5">
              Bawaan {{ terpilih?.modelBawaan }}. Nama model berubah dari waktu ke waktu — bila
              ditolak, "Uji sambungan" akan menyebutkan alasannya.
            </span>
            <span v-if="form.errors.model" class="block text-[11px] text-red-600 mt-1">{{ form.errors.model }}</span>
          </label>

          <label class="block">
            <span class="text-[12px] font-semibold text-cam-ink">Batas token jawaban</span>
            <input v-model.number="form.maks_token" type="number" min="100" max="4000"
                   class="mt-1 w-full rounded-xl border-stone-200 text-[12.5px]">
            <span class="block text-[11px] text-stone-500 mt-1.5">
              Makin besar makin panjang jawabannya, dan makin mahal tiap pertanyaan.
            </span>
            <span v-if="form.errors.maks_token" class="block text-[11px] text-red-600 mt-1">{{ form.errors.maks_token }}</span>
          </label>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
          <button type="submit" :disabled="form.processing"
                  class="rounded-xl bg-cam-lime-deep px-4 py-2 text-[11.5px] font-bold text-white
                         hover:brightness-95 transition disabled:opacity-40">
            Simpan pengaturan
          </button>
          <button type="button" @click="uji" :disabled="menguji"
                  class="rounded-xl border border-stone-200 px-4 py-2 text-[11.5px] font-bold text-stone-600
                         hover:border-cam-lime hover:bg-cam-lime-soft transition disabled:opacity-40">
            {{ menguji ? 'Menguji…' : 'Uji sambungan' }}
          </button>
          <button v-if="terpilih?.terpasang && !terpilih?.dariBerkas" type="button"
                  @click="hapus(terpilih!.kode, terpilih!.nama)"
                  class="rounded-xl border border-red-100 px-4 py-2 text-[11.5px] font-bold text-red-600
                         hover:bg-red-50 transition ml-auto">
            Hapus kunci
          </button>
        </div>
      </form>
    </section>

    <!-- Pendamping diagnosa -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Pendamping diagnosa</h3>
      <p class="text-[11.5px] text-stone-500 leading-relaxed">
        Begitu kuncinya terpasang, halaman
        <Link :href="tautan.diagnosa" class="text-cam-lime-deep font-semibold hover:underline">Diagnosa Sistem</Link>
        mendapat tombol untuk meminta AI mengurutkan temuan mana yang harus ditangani lebih dulu.
        Yang dikirim hanya daftar temuannya — bukan isi basis data, bukan <code>.env</code>, bukan kode.
        Ia menasihati dan tidak menjalankan apa pun; tiap perintah yang disebutnya tetap Anda yang jalankan.
      </p>

      <div v-if="jawabanAi"
           class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-4 text-[12.5px]
                  text-stone-700 leading-relaxed whitespace-pre-wrap">{{ jawabanAi }}</div>
    </section>

  </div>
</template>
