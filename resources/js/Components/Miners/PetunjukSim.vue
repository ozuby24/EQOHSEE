<script setup lang="ts">
/**
 * Berkas SIM + Masa Berlaku SIM dengan petunjuk deteksi (v14 Safe Track).
 *
 * Begitu berkas SIM dipilih, server membacanya (`POST
 * /miners/simper/deteksi-sim`) dan tanggal yang terbaca DIUSULKAN:
 *   · bila medan tanggal masih kosong, tanggal itu diisikan dan sumbernya
 *     'auto';
 *   · bila pengguna sudah mengetik tanggal, ketikannya TIDAK diganti —
 *     usulannya hanya tampil sebagai tombol "Pakai".
 * Tidak terbaca? Pengguna mengisi tangan; tidak ada yang menghalangi.
 */
import { ref } from 'vue';
import Medan from './Medan.vue';

const props = withDefaults(defineProps<{
  tanggal: string;
  sumber: string;
  berkas?: File | null;
  label?: string;
  galatBerkas?: string | null;
  galatTanggal?: string | null;
  wajib?: boolean;
}>(), { berkas: null, label: 'Berkas SIM', galatBerkas: null, galatTanggal: null, wajib: true });

const emit = defineEmits<{
  (e: 'update:tanggal', v: string): void;
  (e: 'update:sumber', v: string): void;
  (e: 'berkas', v: File | null): void;
}>();

const id = `sim-${Math.random().toString(36).slice(2, 8)}`;
const membaca = ref(false);
const hasil = ref<{ tanggal: string | null; pesan: string; keyakinan: string } | null>(null);

function xsrf(): string {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

async function pilih(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0] ?? null;
  emit('berkas', f);
  hasil.value = null;
  if (!f) return;

  membaca.value = true;
  try {
    const data = new FormData();
    data.append('berkas', f);
    const r = await fetch('/miners/simper/deteksi-sim', {
      method: 'POST', body: data, credentials: 'same-origin',
      headers: { 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    hasil.value = r.ok ? await r.json() : null;
    if (hasil.value?.tanggal && !props.tanggal) pakai();
  } catch {
    hasil.value = null;
  } finally {
    membaca.value = false;
  }
}

function pakai() {
  if (!hasil.value?.tanggal) return;
  emit('update:tanggal', hasil.value.tanggal);
  emit('update:sumber', 'auto');
}

function ketik(e: Event) {
  emit('update:tanggal', (e.target as HTMLInputElement).value);
  emit('update:sumber', 'manual');
}

const tglId = (v: string) => v.split('-').reverse().join('-');
</script>

<template>
  <div class="mn-sim-deteksi">
    <Medan :label="props.label" :untuk="`${id}-b`" :wajib="props.wajib" :galat="props.galatBerkas">
      <input :id="`${id}-b`" type="file" class="mn-isian" accept=".jpg,.jpeg,.png,.pdf" @change="pilih">
      <span v-if="membaca" class="mn-petunjuk">Membaca masa berlaku dari berkas…</span>
      <span v-else-if="hasil?.tanggal" class="mn-petunjuk mn-sim-terbaca" role="status">
        ✓ {{ hasil.pesan }}
        <button v-if="props.tanggal && props.tanggal !== hasil.tanggal" type="button" class="mn-btn mn-btn-hantu mn-btn-kecil"
                @click="pakai">Pakai {{ tglId(hasil.tanggal) }}</button>
      </span>
      <span v-else-if="hasil" class="mn-petunjuk" role="status">{{ hasil.pesan }}</span>
    </Medan>
    <Medan label="Masa Berlaku SIM" :untuk="`${id}-t`" :wajib="props.wajib" :galat="props.galatTanggal"
           petunjuk="Terisi sendiri bila tanggal terbaca dari berkas. Tidak terbaca? Isi tanggalnya di sini.">
      <input :id="`${id}-t`" :value="props.tanggal" type="date" class="mn-isian" @input="ketik">
    </Medan>
  </div>
</template>
