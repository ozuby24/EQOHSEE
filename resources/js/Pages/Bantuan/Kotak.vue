<script setup lang="ts">
/**
 * Bantuan — kotak percakapan pemakai.
 *
 * Satu utas, dua saluran. Pertanyaan bisa dikirim ke asisten AI atau langsung
 * ke admin, dan keduanya tercatat di utas yang sama supaya admin membaca apa
 * yang sudah dicoba, bukan keluhan yang diketik ulang dari awal.
 *
 * Ketika asisten belum diaktifkan, tombolnya tidak sekadar mati — kotaknya
 * mengatakan alasannya dan mengarahkan ke admin. Kotak bantuan yang menjawab
 * dengan tebakan lebih berbahaya daripada kotak bantuan yang mengaku belum
 * aktif, sebab yang ditanyakan di sini menyangkut keselamatan kerja.
 */
import { nextTick, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanBantuan } from '../../types';

const props = defineProps<HalamanBantuan>();

const form = useForm({ isi: '', saluran: 'ai' as 'ai' | 'admin' });
const gulung = ref<HTMLElement | null>(null);

async function keBawah() {
  await nextTick();
  if (gulung.value) gulung.value.scrollTop = gulung.value.scrollHeight;
}

keBawah();
watch(() => props.pesan.length, keBawah);

function kirim(saluran: 'ai' | 'admin') {
  if (!form.isi.trim() || form.processing) return;

  form.saluran = saluran;
  form.post('/bantuan', {
    preserveScroll: true,
    only: ['pesan', 'status', 'errors'],
    onSuccess: () => { form.reset('isi'); },
  });
}

/* Enter mengirim ke saluran bawaan; Shift+Enter menyisipkan baris baru. */
function tekan(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    kirim(props.aiAktif ? 'ai' : 'admin');
  }
}

const gaya: Record<string, string> = {
  pengguna: 'ml-auto bg-[color:var(--eq-aksen,#F57C00)] text-white',
  asisten:  'bg-white border border-stone-200 text-cam-ink',
  admin:    'bg-cam-lime-soft border border-cam-lime/30 text-cam-ink',
  sistem:   'mx-auto bg-stone-100 text-stone-500 text-[11.5px] text-center',
};

const label: Record<string, string> = {
  asisten: 'Asisten AI',
  admin:   'Admin',
};
</script>

<template>
  <Head title="Bantuan" />

  <div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden flex flex-col"
         style="height:calc(100vh - 190px); min-height:440px">

      <div class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 class="text-[14px] font-bold text-cam-ink">Butuh Bantuan?</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            <template v-if="aiAktif">Tanya asisten AI, atau teruskan ke admin bila perlu jawaban resmi.</template>
            <template v-else>Asisten AI belum diaktifkan — pertanyaan Anda diteruskan langsung ke admin.</template>
          </p>
        </div>

        <Link v-if="admin" href="/bantuan/masuk"
              class="text-[11.5px] font-semibold shrink-0" style="color:var(--eq-aksen,#F57C00)">
          Kotak masuk admin →
        </Link>
      </div>

      <div ref="gulung" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-stone-50/60">
        <p v-if="!pesan.length" class="text-[12.5px] text-stone-400 text-center py-10">
          Belum ada percakapan. Tuliskan pertanyaan Anda di bawah.
        </p>

        <div v-for="m in pesan" :key="m.id" class="flex">
          <div class="rounded-2xl px-4 py-2.5 max-w-[80%] whitespace-pre-wrap break-words text-[12.5px] leading-relaxed"
               :class="gaya[m.peran] ?? gaya.sistem">
            <div v-if="label[m.peran]" class="text-[10px] font-bold uppercase tracking-wider opacity-60 mb-1">
              {{ m.peran === 'admin' && m.nama ? `Admin · ${m.nama}` : label[m.peran] }}
            </div>
            {{ m.isi }}
            <div v-if="m.peran !== 'sistem'" class="text-[10px] opacity-50 mt-1">{{ m.waktu }}</div>
          </div>
        </div>
      </div>

      <div class="border-t border-stone-100 p-3.5">
        <textarea v-model="form.isi" rows="2" @keydown="tekan"
                  :placeholder="aiAktif ? 'Tulis pertanyaan… (Enter kirim, Shift+Enter baris baru)' : 'Tulis pertanyaan untuk admin…'"
                  class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] resize-none
                         focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0"></textarea>

        <p v-if="form.errors.isi" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.isi }}</p>

        <div class="flex flex-wrap items-center gap-2 mt-2.5">
          <button v-if="aiAktif" type="button" :disabled="form.processing || !form.isi.trim()"
                  @click="kirim('ai')"
                  class="eq-btn-utama disabled:opacity-40 disabled:cursor-not-allowed"
                  style="flex:none;padding:9px 18px">
            {{ form.processing && form.saluran === 'ai' ? 'Menanya…' : 'Tanya Asisten AI' }}
          </button>

          <button type="button" :disabled="form.processing || !form.isi.trim()" @click="kirim('admin')"
                  class="rounded-xl border border-stone-200 px-4 py-2 text-[12.5px] font-semibold text-cam-ink
                         hover:border-stone-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
            {{ form.processing && form.saluran === 'admin' ? 'Mengirim…' : 'Kirim ke Admin' }}
          </button>

          <!-- Peringatan ini hanya berlaku bila asistennya memang menjawab. -->
          <span v-if="aiAktif" class="text-[11px] text-stone-400 ml-auto">
            Jawaban asisten dapat keliru — untuk keputusan K3, pastikan ke admin atau KTT.
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
