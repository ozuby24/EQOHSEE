<script setup lang="ts">
/*
  Log Aktivitas MERANTI — tambah, ubah, hapus, impor; termasuk riwayat
  aplikasi MERANTI lama yang dimuat dari lembar Google Sheets (bertanda ⇣).
*/
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';

const props = propHalaman();
const aksi = ref<string>(props.saring?.aksi ?? '');
const cari = ref<string>(props.saring?.cari ?? '');

let tunda: ReturnType<typeof setTimeout> | null = null;
watch([aksi, cari], () => {
  if (tunda) clearTimeout(tunda);
  tunda = setTimeout(() => router.get('/meranti/log', {
    ...(props.perusahaan ? { perusahaan: props.perusahaan } : {}), ...(aksi.value ? { aksi: aksi.value } : {}), ...(cari.value ? { cari: cari.value } : {}),
  }, { preserveState: true, preserveScroll: true, replace: true }), 350);
});

const WARNA: Record<string, string> = { Tambah: 'lg-hijau', Ubah: 'lg-emas', Hapus: 'lg-merah', Impor: 'lg-biru', Login: 'lg-abu' };
</script>

<template>
  <Head title="Log Aktivitas MERANTI" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <label class="lg-label">Aksi
          <select v-model="aksi" class="lg-i"><option value="">Semua aksi</option><option v-for="a in props.aksi ?? []" :key="a" :value="a">{{ a }}</option></select>
        </label>
        <label class="lg-label">Cari
          <input v-model="cari" type="search" class="lg-i" placeholder="Modul, pengguna, atau rincian">
        </label>
      </div>
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Kartu judul="Riwayat aktivitas" :ket="`${props.log?.total ?? 0} catatan · ⇣ = dari aplikasi MERANTI lama`" ikon="history" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px] lg-tabel">
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Modul</th><th>Rincian</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="l in props.log?.data ?? []" :key="l.id">
              <td class="whitespace-nowrap num">{{ l.waktu }}<span v-if="l.impor" class="lg-impor" title="Dari lembar Google Sheets MERANTI">⇣</span></td>
              <td class="whitespace-nowrap">{{ l.pengguna || '—' }}</td>
              <td><span class="lg-aksi" :class="WARNA[l.aksi] ?? 'lg-abu'">{{ l.aksi }}</span></td>
              <td class="whitespace-nowrap">{{ l.modul || '—' }}</td>
              <td class="lg-rinci" :title="l.detail ?? ''">{{ l.detail || '—' }}</td>
            </tr>
            <tr v-if="!(props.log?.data ?? []).length"><td colspan="5" class="px-5 py-6 text-center text-stone-400">Belum ada aktivitas.</td></tr>
          </tbody>
        </table>
      </div>
      <nav v-if="(props.log?.last_page ?? 1) > 1" class="px-5 py-3 flex flex-wrap gap-1" aria-label="Halaman log">
        <template v-for="(t, i) in props.log.links" :key="i">
          <Link v-if="t.url" :href="t.url" preserve-scroll class="lg-hal" :class="{ aktif: t.active }"><span v-html="t.label" /></Link>
          <span v-else class="lg-hal mati"><span v-html="t.label" /></span>
        </template>
      </nav>
    </Kartu>
  </div>
</template>

<style scoped>
.lg-label { display: flex; flex-direction: column; gap: 4px; font-size: 11.5px; font-weight: 700; color: #57534E; }
.lg-i { border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12.5px; padding: 7px 10px; min-width: 200px; background: #fff; }
.lg-tabel thead th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; white-space: nowrap; }
.lg-tabel td { padding: 8px 12px; vertical-align: top; }
.lg-tabel th:first-child, .lg-tabel td:first-child { padding-left: 20px; }
.lg-rinci { max-width: 520px; overflow-wrap: anywhere; color: #57534E; }
.lg-impor { margin-left: 4px; color: #2D8C8C; }
.lg-aksi { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; }
.lg-hijau { background: #E8F5EC; color: #1F6B42; } .lg-emas { background: #FBF3D9; color: #7A5D0B; } .lg-merah { background: #FDECEC; color: #991B1B; }
.lg-biru { background: #EAF1FB; color: #1E4E8C; } .lg-abu { background: #F1F5F9; color: #475569; }
.lg-hal { min-width: 32px; padding: 4px 10px; border-radius: 8px; border: 1px solid #E7E5E4; font-size: 12px; font-weight: 700; text-align: center; }
.lg-hal.aktif { background: #0A3A2A; color: #fff; border-color: #0A3A2A; }
.lg-hal.mati { color: #A8A29E; }
:root[data-tema="gelap"] .lg-label { color: #B4BEC3; }
:root[data-tema="gelap"] .lg-i, :root[data-tema="gelap"] .lg-hal { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .lg-tabel thead th { background: #20272B; color: #93A1A8; }
:root[data-tema="gelap"] .lg-rinci { color: #B4BEC3; }
</style>
