<script setup lang="ts">
/*
  Pengguna & Peran MERANTI — pengganti menu "User" dan "Roles & Hak
  Akses" aplikasi lama. Akunnya akun EQOHSEE; yang diatur di sini hanya
  peran MERANTI-nya. Administrator EQOHSEE selalu berakses penuh.
*/
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import PilihPerusahaan from '../../Components/Energi/PilihPerusahaan.vue';

const props = propHalaman();
const cari = ref('');
const sibuk = ref<number | null>(null);

function atur(id: number, peran: string) {
  sibuk.value = id;
  router.put(`/meranti/pengguna/${id}`, { peran: peran || null }, { preserveScroll: true, onFinish: () => { sibuk.value = null; } });
}
const tampil = (u: any) => !cari.value || `${u.nama} ${u.email} ${u.jabatan ?? ''}`.toLowerCase().includes(cari.value.toLowerCase());

const MATRIKS = [
  { peran: 'Data Master', hak: [true, true, true, true, true] },
  { peran: 'EMC', hak: [true, true, true, true, false] },
  { peran: 'Crew / bawaan', hak: [true, true, false, false, false] },
  { peran: 'Manager', hak: [true, false, false, false, false] },
];
const HAK = ['Lihat semua', 'Tambah data', 'Ubah', 'Hapus', 'Data master, peran, log, impor'];
</script>

<template>
  <Head title="Pengguna & Peran MERANTI" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-3">
      <input v-model="cari" type="search" placeholder="Cari nama, surel, jabatan" aria-label="Cari pengguna" class="pg-i">
      <PilihPerusahaan :daftar="props.daftarPerusahaan" :terpilih="props.perusahaan" />
    </section>

    <Kartu judul="Hak akses per peran" ket="Sama dengan RBAC empat peran MERANTI" ikon="shield-check" warna="hijau" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px] pg-tabel">
          <thead><tr><th>Peran</th><th v-for="h in HAK" :key="h" class="text-center">{{ h }}</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="r in MATRIKS" :key="r.peran">
              <td class="font-bold text-cam-ink whitespace-nowrap">{{ r.peran }}</td>
              <td v-for="(h, i) in r.hak" :key="i" class="text-center"><span :class="h ? 'pg-ya' : 'pg-tidak'">{{ h ? '✓' : '—' }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="px-5 py-3 text-[11.5px] text-stone-500">Administrator EQOHSEE selalu berakses penuh. Pengguna tanpa peran MERANTI mendapat hak bawaan setara Crew — sama seperti isian lapangan di aplikasi lain.</p>
    </Kartu>

    <Kartu judul="Pengguna" :ket="`${(props.akun ?? []).length} akun`" ikon="users" rapat>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px] pg-tabel">
          <thead><tr><th>Nama</th><th>Jabatan</th><th>Peran MERANTI</th><th>Hak yang berlaku</th></tr></thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="u in (props.akun ?? []).filter(tampil)" :key="u.id" :class="{ 'opacity-60': !u.aktif }">
              <td><p class="font-bold text-cam-ink">{{ u.nama }}</p><p class="text-[11px] text-stone-500">{{ u.email }}<template v-if="!u.aktif"> · nonaktif</template></p></td>
              <td>{{ u.jabatan || '—' }}</td>
              <td>
                <span v-if="u.admin" class="pg-admin">Administrator</span>
                <select v-else :value="u.peran ?? ''" class="pg-i" :disabled="sibuk === u.id" :aria-label="`Peran MERANTI ${u.nama}`" @change="atur(u.id, ($event.target as HTMLSelectElement).value)">
                  <option value="">Bawaan (setara Crew)</option>
                  <option v-for="p in props.peran ?? []" :key="p.nilai" :value="p.nilai">{{ p.label.split(' — ')[0] }}</option>
                </select>
              </td>
              <td class="text-[11.5px] text-stone-600">{{ u.efektif }}</td>
            </tr>
            <tr v-if="!(props.akun ?? []).length"><td colspan="4" class="px-5 py-6 text-center text-stone-400">Belum ada pengguna di perusahaan ini.</td></tr>
          </tbody>
        </table>
      </div>
    </Kartu>
  </div>
</template>

<style scoped>
.pg-i { border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12.5px; padding: 6px 10px; min-width: 210px; background: #fff; }
.pg-tabel thead th { text-align: left; font-size: 11px; font-weight: 700; color: #78716C; padding: 8px 12px; background: #FAFAF9; white-space: nowrap; }
.pg-tabel thead th.text-center { text-align: center; }
.pg-tabel td { padding: 9px 12px; vertical-align: middle; }
.pg-tabel th:first-child, .pg-tabel td:first-child { padding-left: 20px; }
.pg-ya { color: #15803D; font-weight: 900; }
.pg-tidak { color: #CBD5E1; }
.pg-admin { display: inline-block; padding: 3px 10px; border-radius: 999px; background: #0A3A2A; color: #fff; font-size: 11px; font-weight: 800; }
:root[data-tema="gelap"] .pg-i { background: #20272B; border-color: #2E373C; color: #D5DDE1; }
:root[data-tema="gelap"] .pg-tabel thead th { background: #20272B; color: #93A1A8; }
</style>
