<script setup lang="ts">
/**
 * Bilah pindah antar bagian audit.
 *
 * Bagiannya dibuka satu per satu, jadi bilah ini yang menggantikan
 * gulungan dua ratus baris. Tiap pil membawa kemajuan bagiannya, sebab
 * pertanyaan "bagian mana yang belum selesai" muncul justru saat
 * berpindah.
 */
import { Link } from '@inertiajs/vue3';

defineProps<{
  bagian: Array<{ kunci: string; huruf: string; judul: string; bobot: number;
                  kriteria: number; wajib: boolean }>;
  tautanBagian: Record<string, string>;
  ikhtisar: string;
  kini: string | null;
  /** Kemajuan per bagian, berkunci huruf kecil bagiannya. */
  maju?: Record<string, { belum: number; kriteria: number; penuh: boolean }>;
}>();
</script>

<template>
  <nav class="akl-pindah" aria-label="Bagian audit">
    <Link :href="ikhtisar" :class="['akl-pil', { aktif: kini === null }]">
      <span class="akl-pil-huruf">◎</span>
      <span class="akl-pil-teks">Ikhtisar</span>
    </Link>

    <Link v-for="b in bagian" :key="b.kunci" :href="tautanBagian[b.kunci]"
          :class="['akl-pil', { aktif: kini === b.kunci, wajib: b.wajib }]"
          :title="b.judul">
      <span class="akl-pil-huruf">{{ b.huruf }}</span>
      <span class="akl-pil-teks">
        {{ b.judul }}
        <small v-if="maju?.[b.kunci]" class="num">
          {{ maju[b.kunci].kriteria - maju[b.kunci].belum }}/{{ maju[b.kunci].kriteria }}
          <template v-if="maju[b.kunci].penuh"> · penuh</template>
        </small>
      </span>
    </Link>
  </nav>
</template>

<style scoped>
.akl-pindah {
  display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem;
  scrollbar-width: none;
}
.akl-pindah::-webkit-scrollbar { display: none; }

.akl-pil {
  flex: none; display: flex; align-items: center; gap: .5rem;
  background: #fff; border: 1px solid #E7E5E4; border-radius: .9rem;
  padding: .5rem .75rem; max-width: 15rem;
  transition: border-color .16s, background-color .16s, transform .16s;
}
.akl-pil:hover { border-color: #DC6E00; transform: translateY(-1px); }
.akl-pil.aktif { background: #0F1720; border-color: #0F1720; }
.akl-pil.aktif .akl-pil-huruf { color: #FF9800; }
.akl-pil.aktif .akl-pil-teks  { color: #fff; }
.akl-pil.aktif small { color: rgba(255, 255, 255, .6); }
.akl-pil.wajib:not(.aktif) { border-color: #FDBA74; }

.akl-pil-huruf { font-size: 15px; font-weight: 800; color: #DC6E00; line-height: 1; flex: none; }
.akl-pil-teks {
  min-width: 0; font-size: 11px; font-weight: 700; color: #44403C; line-height: 1.3;
  overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
  -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.akl-pil-teks small { display: block; font-weight: 600; color: #A8A29E; font-size: 9.5px; }
</style>
