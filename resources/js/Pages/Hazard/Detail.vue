<script setup lang="ts">
/**
 * Hazard Report — detail laporan dan tindak lanjut.
 *
 * Dua kiriman yang berbeda berdiri terpisah: tindak lanjut dan
 * penghapusan. Di Blade keduanya harus benar-benar dipisah karena form
 * bersarang tidak sah di HTML — di sini keduanya sekadar dua pemanggilan,
 * dan pemisahannya tetap dipertahankan supaya perilakunya sama.
 */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanDetailBahaya } from '../../types';

const props = defineProps<HalamanDetailBahaya>();

const form = useForm({
  status: props.r.status,
  catatan_penutupan: props.r.catatanPenutupan ?? '',
  foto_tindaklanjut: [] as File[],
});

function simpan() {
  form.post(props.tautan.tindak, { forceFormData: true, preserveScroll: true });
}

function pilihFoto(e: Event) {
  form.foto_tindaklanjut = Array.from((e.target as HTMLInputElement).files ?? []);
}

const menghapus = ref(false);

function hapus() {
  if (!confirm(`Hapus laporan ${props.r.kode}? Tindakan ini tidak dapat dibatalkan.`)) return;

  menghapus.value = true;
  router.delete(props.tautan.hapus, { onFinish: () => { menghapus.value = false; } });
}

const rinci: Array<[string, string | null]> = [
  ['Lokasi',      props.r.lokasi],
  ['Tanggal',     props.r.tanggal],
  ['Waktu',       props.r.waktu],
  ['Kategori',    props.r.kategori],
  ['Pelapor',     props.r.pelapor.nama],
  ['NRP',         props.r.pelapor.nrp],
  ['Jabatan',     props.r.pelapor.jabatan],
  ['Departemen',  props.r.pelapor.departemen],
  ['Perusahaan pelapor', props.r.pelapor.perusahaan],
  ['Ditujukan kepada',   props.r.tujuan],
];
</script>

<template>
  <Head :title="`Laporan ${r.kode}`" />

  <div class="max-w-4xl mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ r.kode }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white"
                  :style="{ background: r.warnaRisiko }">{{ r.risiko }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white"
                  :style="{ background: r.warnaStatus }">{{ r.status }}</span>
          </div>
          <p class="text-[15px] font-bold text-cam-ink mt-2.5 leading-snug">{{ r.deskripsi }}</p>
        </div>

        <a :href="tautan.kembali"
           class="text-[12px] font-semibold text-stone-500 hover:text-cam-ink shrink-0">← Kembali</a>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Rincian</h3>
      <dl class="grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
        <div v-for="([label, nilai], i) in rinci" :key="i" class="flex gap-2 text-[12.5px]">
          <dt class="text-stone-400 w-[140px] shrink-0">{{ label }}</dt>
          <dd class="text-cam-ink font-semibold min-w-0 break-words">{{ nilai ?? '—' }}</dd>
        </div>
      </dl>

      <template v-if="r.unsafeAction.length || r.unsafeCondition.length">
        <div class="grid gap-4 sm:grid-cols-2 mt-4 pt-4 border-t border-stone-100">
          <div v-if="r.unsafeAction.length">
            <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400 mb-1.5">Tindakan tidak aman</p>
            <ul class="space-y-1">
              <li v-for="(x, i) in r.unsafeAction" :key="i" class="text-[12.5px] text-cam-ink">• {{ x }}</li>
            </ul>
          </div>
          <div v-if="r.unsafeCondition.length">
            <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400 mb-1.5">Kondisi tidak aman</p>
            <ul class="space-y-1">
              <li v-for="(x, i) in r.unsafeCondition" :key="i" class="text-[12.5px] text-cam-ink">• {{ x }}</li>
            </ul>
          </div>
        </div>
      </template>

      <div v-if="r.rekomendasi || r.hirarki" class="mt-4 pt-4 border-t border-stone-100">
        <p v-if="r.hirarki" class="text-[12.5px]">
          <span class="text-stone-400">Hirarki pengendalian:</span>
          <span class="font-semibold text-cam-ink">{{ r.hirarki }}</span>
        </p>
        <p v-if="r.rekomendasi" class="text-[12.5px] text-cam-ink mt-1.5 leading-relaxed">
          <span class="text-stone-400">Rekomendasi:</span> {{ r.rekomendasi }}
        </p>
      </div>
    </div>

    <div v-if="r.foto.length" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Foto</h3>
      <div class="grid gap-3 grid-cols-2 sm:grid-cols-3">
        <a v-for="(f, i) in r.foto" :key="i" :href="f" target="_blank" rel="noopener">
          <img :src="f" alt="" class="w-full h-36 object-cover rounded-xl border border-stone-200">
        </a>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Tindak Lanjut</h3>

      <div v-if="r.ditutup" class="rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-2.5 mb-4">
        <p class="text-[12px] text-emerald-800">
          Ditutup oleh <b>{{ r.penutup ?? '—' }}</b> pada {{ r.ditutup }}.
        </p>
      </div>

      <div v-if="r.fotoTindakLanjut.length" class="grid gap-3 grid-cols-2 sm:grid-cols-3 mb-4">
        <a v-for="(f, i) in r.fotoTindakLanjut" :key="i" :href="f" target="_blank" rel="noopener">
          <img :src="f" alt="" class="w-full h-32 object-cover rounded-xl border border-stone-200">
        </a>
      </div>

      <div class="grid gap-3 sm:grid-cols-[200px_1fr]">
        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status</label>
          <select v-model="form.status"
                  class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]" aria-label="Status">
            <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
            Catatan penutupan
          </label>
          <textarea v-model="form.catatan_penutupan" rows="3"
                    class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] resize-none"></textarea>
        </div>
      </div>

      <div class="mt-3">
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">
          Foto tindak lanjut
        </label>
        <input type="file" multiple accept="image/*" @change="pilihFoto"
               class="text-[12px] text-stone-500">
      </div>

      <p v-if="form.errors.status" class="text-[11.5px] text-red-600 mt-2">{{ form.errors.status }}</p>

      <div class="flex flex-wrap items-center gap-2 mt-4">
        <button type="button" :disabled="form.processing" @click="simpan"
                class="eq-btn-utama disabled:opacity-40" style="flex:none;padding:10px 20px">
          {{ form.processing ? 'Menyimpan…' : 'Simpan Tindak Lanjut' }}
        </button>

        <button v-if="admin" type="button" :disabled="menghapus" @click="hapus"
                class="ml-auto rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-[12px]
                       font-bold text-red-700 hover:bg-red-100 transition disabled:opacity-40">
          Hapus laporan
        </button>
      </div>
    </div>
  </div>
</template>
