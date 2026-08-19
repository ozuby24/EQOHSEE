<script setup lang="ts">
/**
 * Kelola pengguna.
 *
 * Tombol hapus tidak digambar untuk akun yang sedang dipakai. Server
 * tetap menolaknya, tetapi memberi tombol yang pasti gagal hanya membuat
 * orang mencoba lalu membaca pesan galat.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { HalamanDaftarPengguna } from '../../../types';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();


const props = defineProps<HalamanDaftarPengguna>();

const saring = useForm({ q: props.q });

function cari() {
  saring
    .transform((d) => (d.q ? d : {}))
    .get(props.tautan.daftar, { preserveState: true, preserveScroll: true, replace: true });
}

async function hapus(url: string, nama: string) {
  if (!await tanya(`Hapus pengguna "${nama}"?`)) return;
  router.delete(url, { preserveScroll: true });
}

const chip = 'text-[9.5px] font-bold px-2 py-0.5 rounded tracking-wide uppercase';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-6xl mx-auto">

    <div class="flex flex-wrap items-center gap-2.5 mb-5">
      <form class="flex-1 min-w-0 basis-[200px]" @submit.prevent="cari">
        <input v-model="saring.q" placeholder="Cari nama atau email…"
               class="ring-focus w-full rounded-xl border border-stone-200 bg-white px-4 py-2.5
                      text-[13px] transition">
      </form>
      <a :href="tautan.buat" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5
                                    text-[12.5px] font-bold hover:brightness-105 transition">
        + Tambah Pengguna
      </a>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-5 py-3">Pengguna</th>
              <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3">Peran</th>
              <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3 hidden md:table-cell">Jabatan</th>
              <th class="text-left font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-3">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in daftar" :key="u.id"
                class="border-b border-stone-50 last:border-0 hover:bg-stone-50/60 transition">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl grid place-items-center font-bold text-[12px] shrink-0"
                       :class="u.admin ? 'lime-gradient text-white' : 'bg-stone-100 text-stone-500'">
                    {{ u.inisial }}
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-cam-ink clamp-1">{{ u.nama }}</div>
                    <div class="text-[11px] text-stone-400 clamp-1">{{ u.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3.5">
                <div class="flex flex-wrap gap-1">
                  <span v-if="u.admin" :class="chip" class="bg-cam-ink text-white">ADMIN</span>
                  <span v-if="u.lmsRole" :class="chip" class="bg-cam-lime-soft text-cam-lime-deep">{{ u.lmsRole }}</span>
                  <span v-if="u.auditRole" :class="chip" class="bg-stone-100 text-stone-500">{{ u.auditRole }}</span>
                  <span v-if="!u.admin && !u.lmsRole && !u.auditRole" class="text-[11px] text-stone-300">—</span>
                </div>
              </td>
              <td class="px-4 py-3.5 hidden md:table-cell">
                <div class="text-stone-600">{{ u.jabatan ?? '—' }}</div>
                <div v-if="u.departemen" class="text-[11px] text-stone-400">{{ u.departemen }}</div>
              </td>
              <td class="px-4 py-3.5">
                <span class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold"
                      :class="u.aktif ? 'text-cam-lime-deep' : 'text-stone-400'">
                  <span class="w-1.5 h-1.5 rounded-full" :class="u.aktif ? 'bg-cam-lime' : 'bg-stone-300'"></span>
                  {{ u.aktif ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-4 py-3.5">
                <div class="flex items-center justify-end gap-1">
                  <a :href="u.urlUbah" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                                              text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
                  <button v-if="!u.diri" type="button" @click="hapus(u.urlHapus, u.nama)"
                          class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                                 text-red-500 hover:bg-red-50">Hapus</button>
                </div>
              </td>
            </tr>

            <tr v-if="!daftar.length">
              <td colspan="5" class="px-5 py-12 text-center text-[13px] text-stone-400">Tidak ada pengguna.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <nav v-if="halaman.akhir > 1" class="mt-5 flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                 :class="t.aktif ? 'border-transparent bg-cam-ink text-white'
                       : t.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50'
                               : 'border-stone-100 text-stone-300'"
                 v-html="t.label" />
    </nav>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
