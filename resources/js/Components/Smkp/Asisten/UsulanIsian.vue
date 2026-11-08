<script setup lang="ts">
import { computed, ref } from 'vue';
import Ikon from '../Ikon.vue';
import { tambahkan } from './api';

/*
 * Pratinjau satu kolom formulir yang diusulkan asisten. Tidak ada yang
 * menimpa diam-diam: kolom kosong cukup "Terapkan"; kolom berisi memberi
 * pilihan "Ganti" atau "Tambahkan" (teks), atau menunjukkan nilai lama →
 * baru (nilai tunggal). Penerapan hanya mengubah formulir di layar —
 * tersimpan setelah auditor menekan Simpan.
 */
const props = withDefaults(defineProps<{
  label: string;
  usulan: string;
  sekarang: string;
  /** teks: boleh digabung · nilai: hanya diganti · tambah: hanya ditambahkan di akhir */
  mode?: 'teks' | 'nilai' | 'tambah';
  maks?: number;
  /** Tampilan usulan bila berbeda dari nilainya (mis. tanggal). */
  tampil?: string;
  tampilSekarang?: string;
}>(), { mode: 'teks', maks: 2000 });

const emit = defineEmits<{ pakai: [nilai: string] }>();

const diterapkan = ref(false);
const kosong = computed(() => !(props.sekarang ?? '').trim());
const sama = computed(() => (props.sekarang ?? '').trim() === props.usulan.trim()
  || (props.mode === 'tambah' && (props.sekarang ?? '').includes(props.usulan.trim())));

function pakai(cara: 'ganti' | 'tambah') {
  const nilai = cara === 'tambah' ? tambahkan(props.sekarang, props.usulan, props.maks) : props.usulan.slice(0, props.maks);
  emit('pakai', nilai);
  diterapkan.value = true;
}
</script>

<template>
  <div v-if="usulan.trim()" class="rounded-xl border border-slate-200 bg-white p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <span class="label mb-0">{{ label }}</span>
      <span v-if="diterapkan" class="lencana bg-slate-100 text-slate-600"><Ikon nama="centang" class="h-3 w-3" :tebal="2.5" /> Diterapkan — belum disimpan</span>
      <span v-else-if="sama" class="lencana bg-slate-100 text-slate-500">Sudah sama dengan isian</span>
    </div>

    <p v-if="mode === 'nilai'" class="mt-1.5 text-[12.5px] text-slate-700">
      <span class="text-slate-400">{{ kosong ? 'kosong' : (tampilSekarang ?? sekarang) }}</span>
      <Ikon nama="panah-kanan" class="mx-1 inline h-3.5 w-3.5 text-slate-400" />
      <b class="font-semibold text-slate-900">{{ tampil ?? usulan }}</b>
    </p>
    <pre v-else class="mt-1.5 max-h-40 overflow-y-auto whitespace-pre-wrap break-words font-sans text-[12px] leading-relaxed text-slate-700 scrollbar-tipis">{{ tampil ?? usulan }}</pre>

    <div v-if="!sama || diterapkan" class="mt-2.5 flex flex-wrap items-center gap-2">
      <template v-if="mode === 'tambah'">
        <button type="button" class="btn-garis btn-kecil" @click="pakai('tambah')"><Ikon nama="tempel" class="h-3.5 w-3.5" /> Terapkan</button>
        <span class="bantu mt-0">Ditambahkan di akhir isian{{ kosong ? '' : ' yang ada' }}.</span>
      </template>
      <template v-else-if="mode === 'nilai' || kosong">
        <button type="button" class="btn-garis btn-kecil" @click="pakai('ganti')"><Ikon nama="tempel" class="h-3.5 w-3.5" /> Terapkan</button>
        <span v-if="!kosong" class="bantu mt-0">Mengganti isian saat ini.</span>
      </template>
      <template v-else>
        <button type="button" class="btn-garis btn-kecil" @click="pakai('ganti')"><Ikon nama="tempel" class="h-3.5 w-3.5" /> Ganti</button>
        <button type="button" class="btn-hantu btn-kecil" @click="pakai('tambah')"><Ikon nama="tambah" class="h-3.5 w-3.5" /> Tambahkan</button>
        <span class="bantu mt-0">Kolom ini sudah berisi.</span>
      </template>
    </div>
  </div>
</template>
