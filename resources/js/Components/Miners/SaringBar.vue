<script setup lang="ts">
/**
 * Baris saring berlabel dengan tombol "Terapkan" dan tombol bersihkan —
 * baris form.mb-filter Safe Track. Medannya disebut lewat `medan`,
 * nilainya lewat v-model, sehingga setiap daftar Miners memakai satu
 * baris saring yang sama.
 */
import type { MedanSaring } from './zona';
import '../../../css/miners.css';

const props = defineProps<{
  medan: MedanSaring[];
  modelValue: Record<string, string>;
}>();

const emit = defineEmits<{
  'update:modelValue': [nilai: Record<string, string>];
  terapkan: [];
  bersihkan: [];
}>();

function ubah(kunci: string, nilai: string) {
  emit('update:modelValue', { ...props.modelValue, [kunci]: nilai });
}
</script>

<template>
  <form class="mn-saring" @submit.prevent="emit('terapkan')">
    <div v-for="m in medan" :key="m.kunci" :class="{ 'mn-saring-lebar': m.lebar }">
      <label class="mn-label" :for="`saring-${m.kunci}`">{{ m.label }}</label>

      <select v-if="m.jenis === 'pilih'" :id="`saring-${m.kunci}`" class="mn-isian"
              :value="modelValue[m.kunci] ?? ''" @change="ubah(m.kunci, ($event.target as HTMLSelectElement).value)">
        <option value="">{{ m.semua ?? 'Semua' }}</option>
        <option v-for="o in m.opsi ?? []" :key="o.nilai" :value="String(o.nilai)">{{ o.label }}</option>
      </select>

      <input v-else :id="`saring-${m.kunci}`" class="mn-isian" :type="m.jenis === 'tanggal' ? 'date' : 'text'"
             :placeholder="m.placeholder" :value="modelValue[m.kunci] ?? ''"
             @input="ubah(m.kunci, ($event.target as HTMLInputElement).value)">
    </div>

    <div class="mn-saring-aksi">
      <button type="submit" class="mn-btn mn-btn-utama">
        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5zm1 .5v1.308l4.372 4.858A.5.5 0 0 1 7 8.5v5.306l2-.666V8.5a.5.5 0 0 1 .128-.334L13.5 3.308V2z"/></svg>
        Terapkan
      </button>
      <button type="button" class="mn-btn mn-btn-garis mn-btn-ikon" title="Bersihkan saringan" aria-label="Bersihkan saringan"
              @click="emit('bersihkan')">
        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2z"/><path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466"/></svg>
      </button>
    </div>
  </form>
</template>
