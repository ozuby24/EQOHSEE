<script setup lang="ts">
/**
 * Cuti saya: saldo, pengajuan, riwayat.
 *
 * FORMULIRNYA TIDAK MEMILIH ORANG. Layar admin mengirim `pekerja_id`
 * karena ia memang mengajukan atas nama orang lain; di sini pekerjanya
 * ditentukan akun yang sedang masuk, dan tidak ada satu pun kolom yang
 * dapat mengubahnya. Menerima `pekerja_id` di sini berarti seorang
 * pekerja dapat mengajukan cuti atas nama rekannya dengan menyunting
 * satu kolom tersembunyi — dan cuti itu memotong saldo rekannya.
 *
 * HARI KERJA YANG TERPOTONG DIHITUNG DI SISI PELAYAN, bukan ditebak di
 * sini. Pada pola 14:7, cuti tiga hari yang jatuh pada periode off-site
 * tidak memakan satu pun hari kerja — dan hitungan di peramban yang
 * tidak tahu rosternya akan selalu berselisih dengan hitungan yang
 * sebenarnya memotong saldo.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../../halaman';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const props = propHalaman();
const { dialog, tanya, batal, lanjut } = useDialog();

const saldo   = computed<any>(() => props.saldo ?? {});
const riwayat = computed<any[]>(() => (props.riwayat ?? []) as any[]);
const jenis   = computed<any[]>(() => (props.jenis ?? []) as any[]);
const STATUS  = computed<Record<string, string>>(() => (props.STATUS ?? {}) as any);

const form = useForm<Record<string, any>>({
  jenis_cuti_id: '', mulai: '', selesai: '', alasan: '', bukti: null as File | null,
});

const terpilih = computed(() => jenis.value.find((j) => String(j.id) === String(form.jenis_cuti_id)));

function ajukan() {
  form.post('/hris/saya/cuti', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => form.reset('mulai', 'selesai', 'alasan', 'bukti'),
  });
}

async function batalkan(c: any) {
  if (!await tanya({
    judul: `Batalkan pengajuan ${c.mulai} – ${c.selesai}?`,
    pesan: c.status === 'disetujui'
      ? 'Pengajuan ini sudah disetujui. Membatalkannya mengembalikan hari kerjanya ke kalender regu.'
      : 'Pengajuan yang dibatalkan tidak dapat dikembalikan; ajukan baru bila masih dibutuhkan.',
    labelAksi: 'Batalkan',
    nada: 'bahaya',
  })) return;

  useForm({}).post(`/hris/saya/cuti/${c.id}/batal`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul as string" />

  <div class="space-y-4">
    <!-- ── Ajukan ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <h2 class="text-[13px] font-bold">Ajukan cuti</h2>

      <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
        Hari yang terpotong dihitung dari roster Anda sendiri, bukan dari kalender: cuti yang
        jatuh pada periode off-site tidak memakan saldo. Angkanya muncul pada pesan konfirmasi
        sesudah pengajuan terkirim.
      </p>

      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Jenis</span>
          <select v-model="form.jenis_cuti_id" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="j in jenis" :key="j.id" :value="j.id">{{ j.nama }}</option>
          </select>
          <span v-if="form.errors.jenis_cuti_id" class="block text-[11px] text-red-600">
            {{ form.errors.jenis_cuti_id }}
          </span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Mulai</span>
          <input v-model="form.mulai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.mulai" class="block text-[11px] text-red-600">{{ form.errors.mulai }}</span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Selesai</span>
          <input v-model="form.selesai" type="date" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.selesai" class="block text-[11px] text-red-600">{{ form.errors.selesai }}</span>
        </label>

        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">
            Bukti
            <span v-if="terpilih?.perluBukti" class="text-red-600">— wajib untuk jenis ini</span>
          </span>
          <input type="file" class="mt-1 w-full text-[12px]"
                 @change="form.bukti = ($event.target as HTMLInputElement).files?.[0] ?? null">
          <span v-if="form.errors.bukti" class="block text-[11px] text-red-600">{{ form.errors.bukti }}</span>
        </label>

        <label class="block sm:col-span-2">
          <span class="block text-[11px] text-stone-500">Alasan</span>
          <input v-model="form.alasan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>
      </div>

      <button type="button" class="eq-btn-utama mt-4" :disabled="form.processing" @click="ajukan">
        Ajukan
      </button>
    </section>

    <!-- ── Riwayat ── -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-4 pt-4 pb-3">
        <h2 class="text-[13px] font-bold">
          Riwayat <span class="text-stone-400 font-normal">| {{ riwayat.length }} pengajuan</span>
        </h2>
      </header>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
          <thead class="bg-stone-50/70 text-[11px] text-stone-500">
            <tr>
              <th class="text-left font-medium px-4 py-2.5">Jenis</th>
              <th class="text-left font-medium px-3 py-2.5">Rentang</th>
              <th class="text-right font-medium px-3 py-2.5">Hari kerja</th>
              <th class="text-left font-medium px-3 py-2.5">Status</th>
              <th class="text-left font-medium px-3 py-2.5">Catatan</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="c in riwayat" :key="c.id" class="border-t border-stone-100">
              <td class="px-4 py-2.5">{{ c.jenis }}</td>

              <td class="px-3 py-2.5 num text-[11.5px]">
                {{ c.mulai }} <span class="text-stone-400">–</span> {{ c.selesai }}
              </td>

              <td class="px-3 py-2.5 text-right num">{{ c.hari }}</td>

              <td class="px-3 py-2.5">
                <span class="sc-lencana" :class="'sc-' + c.status">{{ STATUS[c.status] ?? c.status }}</span>
              </td>

              <td class="px-3 py-2.5 text-[11.5px] text-stone-500">{{ c.catatan ?? c.alasan ?? '—' }}</td>

              <td class="px-4 py-2.5 text-right">
                <button v-if="c.status === 'menunggu' || c.status === 'disetujui'" type="button"
                        class="text-[11px] text-red-600 hover:underline" @click="batalkan(c)">
                  Batalkan
                </button>
              </td>
            </tr>

            <tr v-if="!riwayat.length">
              <td colspan="6" class="px-4 py-10 text-center text-[12px] text-stone-400">
                Belum ada pengajuan cuti atas nama Anda.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style>
.sc-lencana{display:inline-block;border-radius:9999px;padding:0 .45rem;
  font-size:10px;font-weight:600;white-space:nowrap}
.sc-menunggu{background:#FEF3C7;color:#78350F}
.sc-disetujui{background:#D1FAE5;color:#065F46}
.sc-ditolak{background:#FEE2E2;color:#991B1B}
.sc-dibatalkan{background:#F5F5F4;color:#57534E}

:root[data-tema="gelap"] .sc-menunggu{background:#4A3810;color:#F6D488}
:root[data-tema="gelap"] .sc-disetujui{background:#143A2C;color:#8FE3BE}
:root[data-tema="gelap"] .sc-ditolak{background:#4E1D1D;color:#F5A9A9}
:root[data-tema="gelap"] .sc-dibatalkan{background:#1C262B;color:#A8B2B8}
</style>
