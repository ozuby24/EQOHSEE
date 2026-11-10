<script setup lang="ts">
/**
 * Lencana status pengajuan dan status karyawan — pil lembut bertitik
 * (kata tetap Safe Track):
 * "open" kuning, "approve" hijau, "reject" merah, "draft" abu; "Aktif"
 * hijau dan "Tidak aktif" abu bagi karyawan.
 *
 * Kode status EQOHSEE dipetakan ke kata Safe Track; label panjangnya
 * tetap terbaca lewat `title`.
 */
import '../../../css/miners.css';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  status?: string | null;
  label?: string | null;
}>(), { status: null, label: null });

const PETA: Record<string, [string, string]> = {
  draf:        ['draft',       'mn-l-terang'],
  diajukan:    ['open',        'mn-l-kuning'],
  diperiksa:   ['open',        'mn-l-kuning'],
  ohse:        ['open',        'mn-l-kuning'],
  ktt:         ['open',        'mn-l-kuning'],
  dijadwal:    ['open',        'mn-l-kuning'],
  terbit:      ['approve',     'mn-l-sukses'],
  selesai:     ['approve',     'mn-l-sukses'],
  ditolak:     ['reject',      'mn-l-bahaya'],
  kedaluwarsa: ['expired',     'mn-l-abu'],
  dicabut:     ['non-aktif',   'mn-l-abu'],
  aktif:       ['Aktif',       'mn-l-sukses'],
  nonaktif:    ['Tidak aktif', 'mn-l-abu'],
  resign:      ['Resign',      'mn-l-abu'],
};

const isi = computed(() => PETA[props.status ?? ''] ?? [props.status ?? '—', 'mn-l-terang']);
</script>

<template>
  <span class="mn-lencana mn-titik" :class="isi[1]" :title="label ?? undefined">{{ isi[0] }}</span>
</template>
