<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import { n } from '../../energi';

const props = propHalaman();
const rentang = reactive({ dari: props.dari, sampai: props.sampai });
const terapkan = () => router.get('/energi/seu', rentang, { preserveState: true });
const baris = computed(() => (props.pareto ?? []).map((p: any) => ({ label: p.label, nilai: Math.round(p.gj), keadaan: p.usul_seu ? ('serius' as const) : ('netral' as const) })));
const tandai = (u: any) => router.post(`/energi/seu/${u.id}`, { seu: !u.seu }, { preserveScroll: true });
</script>

<template>
  <Head title="Significant Energy Use" />
  <div class="space-y-4">
    <form class="flex flex-wrap items-end gap-2" @submit.prevent="terapkan">
      <label class="text-[11px] font-semibold text-stone-500">Dari<input v-model="rentang.dari" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
      <label class="text-[11px] font-semibold text-stone-500">Sampai<input v-model="rentang.sampai" type="date" class="block mt-1 rounded-lg border-stone-200 text-[12px]"></label>
      <button class="eq-btn-utama" style="padding:9px 14px">Terapkan</button>
    </form>

    <KartuGrafik judul="Pareto pemakai energi" catatan="Pemakai yang mengisi 80% energi pertama diusulkan sebagai SEU (ISO 50001 6.3). Penetapannya keputusan Manajer Energi." :angka="`${props.pareto?.length ?? 0} kelompok`">
      <Batang :baris="baris" satuan="GJ" apa-adanya />
      <template #tabel>
        <table><thead><tr><th>Pemakai</th><th>GJ</th><th>%</th><th>Kumulatif</th><th>Usulan SEU</th></tr></thead>
          <tbody><tr v-for="p in props.pareto" :key="p.kunci"><td>{{ p.label }}</td><td class="num">{{ n(p.gj, 0) }}</td><td class="num">{{ n(p.persen, 1) }}</td><td class="num">{{ n(p.kumulatif, 1) }}</td><td>{{ p.usul_seu ? 'Ya' : '—' }}</td></tr></tbody></table>
      </template>
    </KartuGrafik>

    <Kartu judul="Penetapan SEU per unit" ket="Unit bertanda SEU dipantau EnPI-nya tersendiri dan wajib tercakup audit energi.">
      <div class="flex flex-wrap gap-2">
        <button v-for="u in props.unit" :key="u.id" type="button" :disabled="!props.boleh.kelola" @click="tandai(u)"
                class="rounded-lg border px-2.5 py-1.5 text-[11.5px]" :class="u.seu ? 'bg-amber-50 border-amber-300 text-amber-800 font-bold' : 'bg-white border-stone-200 text-stone-600'">
          {{ u.seu ? '★ ' : '' }}{{ u.kode }}
        </button>
      </div>
    </Kartu>
  </div>
</template>
