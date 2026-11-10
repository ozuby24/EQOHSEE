<script setup lang="ts">
/**
 * Baris saring berlabel — baris form.mb-filter Safe Track. Medannya
 * disebut lewat `medan`, nilainya lewat v-model, sehingga setiap daftar
 * Miners memakai satu baris saring yang sama.
 *
 * Saringan yang sedang berlaku tampil sebagai chip di bawah barisnya;
 * tombol × pada chip mengosongkan satu saringan dan langsung menerapkan.
 */
import { computed, nextTick } from 'vue';
import type { MedanSaring } from './zona';
import Ikon from './Ikon.vue';
import { IKON } from './ikon';
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

const chip = computed(() => props.medan
  .filter((m) => (props.modelValue[m.kunci] ?? '') !== '')
  .map((m) => {
    const v = props.modelValue[m.kunci];
    const o = m.opsi?.find((x) => String(x.nilai) === v);
    return { kunci: m.kunci, label: m.label, nilai: o?.label ?? v };
  }));

async function lepas(kunci: string) {
  ubah(kunci, '');
  await nextTick();
  emit('terapkan');
}
</script>

<template>
  <form @submit.prevent="emit('terapkan')">
    <div class="mn-saring">
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
        <button type="button" class="mn-btn mn-btn-hantu" title="Bersihkan saringan" @click="emit('bersihkan')">
          <Ikon :d="IKON.balik" />
          Atur ulang
        </button>
      </div>
    </div>

    <div v-if="chip.length" class="mn-chip-baris" aria-label="Saringan aktif">
      <span>Saringan aktif:</span>
      <span v-for="c in chip" :key="c.kunci" class="mn-chip">
        {{ c.label }}: <b>{{ c.nilai }}</b>
        <button type="button" :aria-label="`Hapus saringan ${c.label}`" @click="lepas(c.kunci)"><Ikon :d="IKON.x" /></button>
      </span>
    </div>
  </form>
</template>
