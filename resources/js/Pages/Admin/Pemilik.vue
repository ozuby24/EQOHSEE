<script setup lang="ts">
/**
 * Penetapan pemilik prosedur dan berita yang belum bertuan.
 *
 * Halaman ini sengaja menyebut bahwa MEMBIARKAN KOSONG adalah pilihan
 * yang sah, bukan pekerjaan yang tertunda. Prosedur baku dan pengumuman
 * se-pemasangan memang berlaku untuk semua perusahaan, dan daftar yang
 * menuntut dikosongkan sampai nol akan mendorong orang menetapkan
 * pemilik yang salah hanya supaya angkanya hilang.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import type { HalamanPemilik } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanPemilik>();

const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});

/** Pilihan per jenis: baris yang dicentang, dan perusahaan tujuannya. */
const pilihan = reactive<Record<string, { id: number[]; company_id: number | null }>>(
  Object.fromEntries(props.jenis.map((j) => [j.kunci, { id: [], company_id: null }])),
);

function semua(kunci: string, ada: boolean) {
  const j = props.jenis.find((x) => x.kunci === kunci);
  pilihan[kunci].id = ada && j ? j.baris.map((b) => b.id) : [];
}

async function tetapkan(kunci: string) {
  const p = pilihan[kunci];
  const j = props.jenis.find((x) => x.kunci === kunci);
  const nama = props.perusahaan.find((c) => c.nilai === p.company_id)?.label ?? '';

  if (!await tanya(`Tetapkan ${p.id.length} ${j?.label.toLowerCase()} sebagai milik ${nama}?\n\nSetelah ditetapkan, hanya perusahaan itu yang dapat melihatnya.`)) return;

  router.post(props.tautan.tetapkan,
    { jenis: kunci, id: p.id, company_id: p.company_id },
    { preserveScroll: true, onSuccess: () => { p.id = []; } });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative flex items-start justify-between gap-4 flex-wrap">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Pusat Kendali
          </span>
        </div>
        <Link :href="tautan.sistem"
              class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white hover:bg-white/20 transition shrink-0">
          Pusat Kendali
        </Link>
      </div>
    </section>

    <div v-if="galat.pemilik"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px] text-red-700 leading-relaxed">
      {{ galat.pemilik }}
    </div>

    <div class="rounded-2xl border border-stone-200 bg-stone-100 px-5 py-4 text-[12px] text-stone-800 leading-relaxed">
      Baris di bawah ini belum melekat pada satu perusahaan, jadi terbaca oleh
      <strong>semua</strong> perusahaan. Itu bukan kesalahan — ketika prosedur dan berita mulai
      melekat perusahaan, pemilik baris lama sengaja tidak ditebak, sebab tebakan yang salah
      menyembunyikannya dari yang berhak tanpa menimbulkan galat apa pun.
      <strong>Yang memang berlaku untuk semua perusahaan boleh dibiarkan kosong.</strong>
    </div>

    <section v-for="j in jenis" :key="j.kunci"
             class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">{{ j.label }} tanpa pemilik</h3>
        <span class="rounded-lg px-2 py-0.5 text-[10.5px] font-bold"
              :class="j.jumlah > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
          {{ j.jumlah }} baris
        </span>
      </div>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">{{ j.ket }}</p>

      <p v-if="!j.baris.length" class="text-[12px] text-stone-600">
        Semua {{ j.label.toLowerCase() }} sudah melekat pada satu perusahaan.
      </p>

      <template v-else>
        <label class="flex items-center gap-2 text-[11.5px] font-semibold text-cam-ink mb-2">
          <input type="checkbox" class="rounded border-stone-300"
                 :checked="pilihan[j.kunci].id.length === j.baris.length"
                 @change="semua(j.kunci, ($event.target as HTMLInputElement).checked)">
          Pilih semua yang tampil ({{ j.baris.length }})
        </label>

        <ul class="space-y-1 max-h-80 overflow-y-auto pr-1">
          <li v-for="b in j.baris" :key="b.id">
            <label class="flex items-start gap-2.5 rounded-xl border border-stone-100 px-3 py-2
                          hover:border-cam-lime hover:bg-cam-lime-soft transition cursor-pointer">
              <input type="checkbox" :value="b.id" v-model="pilihan[j.kunci].id"
                     class="mt-0.5 rounded border-stone-300">
              <span class="min-w-0">
                <span class="block text-[12.5px] font-semibold text-cam-ink truncate">{{ b.judul }}</span>
                <span v-if="b.ket" class="block text-[11px] text-stone-600">{{ b.ket }}</span>
              </span>
            </label>
          </li>
        </ul>

        <p v-if="j.jumlah > j.baris.length" class="text-[11px] text-stone-600 mt-2">
          Menampilkan {{ j.baris.length }} dari {{ j.jumlah }}. Tetapkan sebagian dulu,
          sisanya muncul setelah halaman disegarkan.
        </p>

        <div class="flex flex-wrap items-end gap-2.5 pt-3.5 mt-3.5 border-t border-stone-100">
          <label class="block">
            <span class="block text-[11.5px] font-semibold text-cam-ink">Tetapkan sebagai milik</span>
            <select v-model="pilihan[j.kunci].company_id"
                    class="mt-1 w-64 rounded-xl border-stone-200 text-[12.5px]">
              <option :value="null">— pilih perusahaan —</option>
              <option v-for="c in perusahaan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
            </select>
          </label>

          <button type="button" @click="tetapkan(j.kunci)"
                  :disabled="!pilihan[j.kunci].id.length || !pilihan[j.kunci].company_id"
                  class="rounded-xl bg-cam-lime-deep px-4 py-2 text-[11.5px] font-bold text-white
                         hover:brightness-95 transition disabled:opacity-40">
            Tetapkan {{ pilihan[j.kunci].id.length || '' }}
          </button>
        </div>
      </template>
    </section>

  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
