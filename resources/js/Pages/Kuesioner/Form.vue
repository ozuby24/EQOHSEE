<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

type Skala = { value: number; name: string; hint?: string };
type Butir = { code: string; q: string };
type Parameter = { code: string; name: string; items: Butir[] };

const props = defineProps<{
  token: string;
  cat: string;
  company: { name: string };
  kategori: { label: string; indicator: number };
  entitas: unknown[];
  skala: Skala[];
  params: Parameter[];

  /* Identitas yang sudah diisi di halaman pembuka. Diketik sekali,
     dibawa ke sini — mengetiknya dua kali adalah cara tercepat membuat
     orang berhenti di tengah. */
  identitas?: Record<string, string | null>;
}>();

const form = useForm({
  nrp:        props.identitas?.nrp ?? '',
  perusahaan: props.identitas?.perusahaan || props.company.name,
  jabatan:    props.identitas?.jabatan ?? '',
  dept:       props.identitas?.dept ?? '',
  answers: {} as Record<string, number>,
});

function kirim() {
  form.post(`/q/${props.token}/${props.cat}`);
}
</script>

<template>
  <Head :title="`Kuesioner — ${kategori.label}`" />

  <div class="max-w-3xl mx-auto">
    <div class="brand-gradient rounded-2xl p-6 text-white mb-5">
      <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-cam-lime-light">Kuesioner Keselamatan Pertambangan</span>
      <h1 class="stat mt-2 leading-tight">{{ kategori.label }}</h1>
      <p class="text-[12px] text-white/55 mt-1">{{ company.name }} · {{ params.length }} parameter · {{ params.reduce((n, p) => n + p.items.length, 0) }} pertanyaan</p>
    </div>

    <div v-if="Object.keys(form.errors).length" class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px] mb-4">
      <ul class="space-y-0.5">
        <li v-for="(message, key) in form.errors" :key="key">• {{ message }}</li>
      </ul>
    </div>

    <form class="space-y-5" @submit.prevent="kirim">
      <!--
        Identitas sudah diisi di halaman pembuka; di sini ia ditampilkan
        agar dapat diperiksa, bukan diketik ulang. Jabatan sengaja tidak
        dapat diubah dari sini — mengubahnya berarti mengubah kuesioner
        yang sedang diisi, dan jawaban yang sudah masuk akan menggantung
        pada kuesioner yang lain.
      -->
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
          <h2 class="text-[13px] font-bold text-cam-ink">Identitas Anda</h2>
          <a :href="`/q/${props.token}`" class="text-[12px] font-semibold text-cam-lime-deep">
            Ubah identitas
          </a>
        </div>

        <dl class="grid sm:grid-cols-2 gap-x-4 gap-y-1.5 text-[12.5px]">
          <div class="flex gap-2">
            <dt class="w-24 text-stone-500">Jabatan</dt>
            <dd class="font-semibold text-cam-ink">{{ form.jabatan || '—' }}</dd>
          </div>
          <div class="flex gap-2">
            <dt class="w-24 text-stone-500">Perusahaan</dt>
            <dd class="font-semibold text-cam-ink">{{ form.perusahaan || '—' }}</dd>
          </div>
          <div class="flex gap-2">
            <dt class="w-24 text-stone-500">Departemen</dt>
            <dd class="font-semibold text-cam-ink">{{ form.dept || '—' }}</dd>
          </div>
          <div class="flex gap-2">
            <dt class="w-24 text-stone-500">NRP / NIK</dt>
            <dd class="font-semibold text-cam-ink num">{{ form.nrp || '—' }}</dd>
          </div>
        </dl>
      </div>

      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h2 class="text-[13px] font-bold text-cam-ink mb-2">Skala penilaian</h2>
        <div class="grid sm:grid-cols-5 gap-2">
          <div v-for="scale in skala" :key="scale.value" class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-2">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-md bg-cam-lime text-white text-[10px] font-bold grid place-items-center">{{ scale.value }}</span>
              <span class="text-[11.5px] font-bold text-stone-700">{{ scale.name }}</span>
            </div>
            <p class="text-[10.5px] text-stone-500 mt-1 leading-snug">{{ scale.hint ?? '' }}</p>
          </div>
        </div>
      </div>

      <div v-for="parameter in params" :key="parameter.code" class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="px-5 py-3 bg-stone-50 border-b border-stone-200">
          <h3 class="text-[12.5px] font-bold text-cam-ink"><span class="num">{{ parameter.code }}</span> · {{ parameter.name }}</h3>
        </div>
        <div class="divide-y divide-stone-100">
          <div v-for="item in parameter.items" :key="item.code" class="px-5 py-4">
            <div class="text-[12.5px] text-cam-ink leading-snug mb-3">
              <span class="num text-[10.5px] font-bold text-stone-400">{{ item.code }}</span><br>
              {{ item.q }}
            </div>
            <div class="flex flex-wrap gap-2">
              <label v-for="scale in skala" :key="`${item.code}-${scale.value}`" class="cursor-pointer">
                <input v-model="form.answers[item.code]" type="radio" :name="`answers[${item.code}]`" :value="scale.value" class="peer sr-only" required>
                <span class="block rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-semibold text-stone-600 peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent transition">
                  {{ scale.value }} · {{ scale.name }}
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" :disabled="form.processing" class="lime-gradient shadow-glow rounded-xl text-white w-full py-3.5 text-[14px] font-bold hover:brightness-105 transition disabled:opacity-50">
        {{ form.processing ? 'Mengirim…' : 'Kirim jawaban' }}
      </button>
      <p class="text-center text-[11px] text-stone-400 pb-6">
        <Link :href="`/q/${token}`" class="text-cam-lime-deep font-semibold hover:underline">Kembali memilih kuesioner</Link>
        <span class="mx-1">·</span> Jawaban dipakai sebagai skor metode Kuesioner (KS).
      </p>
    </form>
  </div>
</template>
