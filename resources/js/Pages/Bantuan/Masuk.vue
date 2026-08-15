<script setup lang="ts">
/**
 * Bantuan — kotak masuk admin.
 *
 * Daftar utas di kiri, percakapan di kanan. Utas yang masih terbuka selalu
 * naik ke atas: kotak masuk yang diurut waktu saja mengubur pertanyaan yang
 * belum dijawab di bawah percakapan lama yang sudah selesai.
 */
import { nextTick, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanBantuanMasuk } from '../../types';

const props = defineProps<HalamanBantuanMasuk>();

const form = useForm({ isi: '' });
const gulung = ref<HTMLElement | null>(null);

async function keBawah() {
  await nextTick();
  if (gulung.value) gulung.value.scrollTop = gulung.value.scrollHeight;
}

keBawah();
watch(() => props.pesan.length, keBawah);

function buka(id: number) {
  router.get('/bantuan/masuk', { utas: id }, { preserveScroll: true, preserveState: true });
}

function balas() {
  if (!form.isi.trim() || !props.terpilih) return;

  form.post(`/bantuan/${props.terpilih}/balas`, {
    preserveScroll: true,
    only: ['pesan', 'utas', 'status', 'errors'],
    onSuccess: () => { form.reset('isi'); },
  });
}

function selesai() {
  if (!props.terpilih) return;
  if (!confirm('Tandai percakapan ini selesai?')) return;

  router.post(`/bantuan/${props.terpilih}/selesai`, {}, { preserveScroll: true });
}

const gaya: Record<string, string> = {
  pengguna: 'bg-white border border-stone-200 text-cam-ink',
  asisten:  'ml-auto bg-stone-100 border border-stone-200 text-stone-600',
  admin:    'ml-auto bg-[color:var(--eq-aksen,#F57C00)] text-white',
  sistem:   'mx-auto bg-stone-100 text-stone-500 text-[11.5px] text-center',
};

const label: Record<string, string> = { asisten: 'Asisten AI', admin: 'Admin' };
</script>

<template>
  <Head title="Kotak Masuk Bantuan" />

  <div class="max-w-6xl mx-auto grid gap-4 lg:grid-cols-[300px_1fr]"
       style="height:calc(100vh - 190px); min-height:460px">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden flex flex-col">
      <div class="px-4 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Percakapan ({{ utas.length }})</h3>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-stone-100">
        <p v-if="!utas.length" class="px-4 py-10 text-[12.5px] text-stone-400 text-center">
          Belum ada pertanyaan masuk.
        </p>

        <button v-for="t in utas" :key="t.id" type="button" @click="buka(t.id)"
                class="w-full text-left px-4 py-3 hover:bg-stone-50 transition"
                :class="t.aktif ? 'bg-stone-50' : ''">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[12.5px] font-bold text-cam-ink truncate">{{ t.nama }}</span>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full shrink-0"
                  :class="t.status === 'terbuka'
                    ? 'bg-cam-lime-soft text-cam-lime-deep'
                    : 'bg-stone-100 text-stone-400'">
              {{ t.status }}
            </span>
          </div>
          <div class="text-[11px] text-stone-500 truncate mt-0.5">{{ t.perusahaan ?? '—' }}</div>
          <div class="text-[10.5px] text-stone-400 mt-0.5">{{ t.terakhir ?? 'belum ada pesan' }}</div>
        </button>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden flex flex-col">
      <template v-if="terpilih">
        <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between gap-2">
          <h3 class="text-[13px] font-bold text-cam-ink">
            {{ utas.find(t => t.id === terpilih)?.nama }}
          </h3>
          <button v-if="status === 'terbuka'" type="button" @click="selesai"
                  class="text-[11.5px] font-semibold text-stone-500 hover:text-cam-ink">
            Tandai selesai
          </button>
        </div>

        <div ref="gulung" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-stone-50/60">
          <div v-for="m in pesan" :key="m.id" class="flex">
            <div class="rounded-2xl px-4 py-2.5 max-w-[80%] whitespace-pre-wrap break-words text-[12.5px] leading-relaxed"
                 :class="gaya[m.peran] ?? gaya.sistem">
              <div v-if="label[m.peran]" class="text-[10px] font-bold uppercase tracking-wider opacity-60 mb-1">
                {{ label[m.peran] }}
              </div>
              {{ m.isi }}
              <div v-if="m.peran !== 'sistem'" class="text-[10px] opacity-50 mt-1">{{ m.waktu }}</div>
            </div>
          </div>
        </div>

        <div class="border-t border-stone-100 p-3.5">
          <textarea v-model="form.isi" rows="2" placeholder="Tulis balasan untuk pengguna…"
                    class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] resize-none
                           focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0"></textarea>
          <p v-if="form.errors.isi" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.isi }}</p>

          <button type="button" :disabled="form.processing || !form.isi.trim()" @click="balas"
                  class="eq-btn-utama mt-2.5 disabled:opacity-40 disabled:cursor-not-allowed"
                  style="flex:none;padding:9px 18px">
            {{ form.processing ? 'Mengirim…' : 'Kirim Balasan' }}
          </button>
        </div>
      </template>

      <p v-else class="m-auto text-[12.5px] text-stone-400">Pilih percakapan di sebelah kiri.</p>
    </div>
  </div>
</template>
