<script setup lang="ts">
/**
 * Pengajuan dan persetujuan cuti.
 *
 * ANTREAN DIGAMBAR TERPISAH DARI RIWAYAT, bukan sebagai penyaring atas
 * daftar yang sama. Yang membuka layar ini hampir selalu datang untuk
 * menindak sesuatu — dan antrean yang harus dicari dulu di antara dua
 * ratus baris riwayat adalah antrean yang terlewat.
 *
 * TIAP BARIS ANTREAN MENYEBUT DAMPAK ROSTERNYA. Yang menyetujui bukan
 * sedang memeriksa hak seseorang; ia sedang memutuskan apakah regunya
 * masih cukup orang pada hari-hari yang diminta.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import KopHalaman from '../../../Components/KopHalaman.vue';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const props = propHalaman();
const { dialog, tanya, minta, batal, lanjut } = useDialog();

const antrean = computed<any[]>(() => (props.antrean ?? []) as any[]);
const riwayat = computed<any[]>(() => (props.riwayat ?? []) as any[]);
const jenis   = computed<any[]>(() => (props.jenis ?? []) as any[]);

const tahun = ref(String(props.tahun ?? ''));

function muat() {
  router.get('/hris/cuti', { tahun: tahun.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true });
}

/* ── pengajuan ── */

const form = useForm<{
  pekerja_id: string; jenis_cuti_id: string; mulai: string; selesai: string;
  alasan: string; bukti: File | null;
}>({ pekerja_id: '', jenis_cuti_id: '', mulai: '', selesai: '', alasan: '', bukti: null });

const jenisTerpilih = computed(() =>
  jenis.value.find((j) => String(j.id) === String(form.jenis_cuti_id)) ?? null);

function ajukan() {
  form.post('/hris/cuti', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => form.reset('mulai', 'selesai', 'alasan', 'bukti'),
  });
}

/* ── tindakan ── */

function kirim(id: number, aksi: string, catatan?: string) {
  useForm({ catatan: catatan ?? '' }).post(`/hris/cuti/${id}/${aksi}`, { preserveScroll: true });
}

async function setujui(c: any) {
  const tipis = c.dampak?.paling_tipis;

  if (!await tanya({
    judul: `Setujui ${c.jenis} ${c.pekerja}?`,
    pesan: [
      `${c.mulai} – ${c.selesai} · ${c.hari} hari kerja.`,
      c.sisa ? `Sisa saldo ${c.sisa.sebelum} → ${c.sisa.sesudah}.` : 'Tidak memotong saldo tahunan.',
      tipis !== null && tipis !== undefined
        ? `Regu ${c.dampak.regu} pada hari paling tipis: ${c.dampak.sebelum} → ${tipis} orang.`
        : 'Belum ada roster tersusun pada rentang itu.',
    ].join(' '),
    labelAksi: 'Setujui',
  })) return;

  kirim(c.id, 'setujui');
}

async function tolak(c: any) {
  const alasan = await minta({
    judul: `Tolak ${c.jenis} ${c.pekerja}?`,
    pesan: 'Alasannya ikut tersimpan pada pengajuan dan terbaca oleh yang mengajukan.',
    label: 'Alasan penolakan',
    jenis: 'panjang',
    min: 5,
    labelAksi: 'Tolak',
    nada: 'bahaya',
  });

  if (alasan === null) return;

  kirim(c.id, 'tolak', alasan);
}

async function teruskan(c: any) {
  if (!await tanya({
    judul: `Teruskan ${c.pekerja} ke jenjang di atasnya?`,
    pesan: 'Pengajuannya tetap menunggu; yang berwenang menindak bergeser ke KTT.',
    labelAksi: 'Teruskan',
  })) return;

  kirim(c.id, 'teruskan');
}

async function batalkan(c: any) {
  if (!await tanya({
    judul: `Batalkan cuti ${c.pekerja}?`,
    pesan: c.status === 'disetujui'
      ? 'Saldo dikembalikan dan baris rosternya dipulihkan menjadi hari kerja.'
      : 'Pengajuan yang belum ditindak akan ditutup.',
    labelAksi: 'Batalkan',
    nada: 'bahaya',
  })) return;

  kirim(c.id, 'batalkan');
}

function kelas(status: string) {
  return 'ct-' + status;
}
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1240px] mx-auto space-y-5">
    <KopHalaman :judul="props.judul as string" :subjudul="props.subjudul as string"
                tagline="Rest Well Return Strong"
                :remah="[['HRIS', '/hris'], ['Cuti & Izin', null], ['Pengajuan', null]]" ringkas />

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <div class="flex flex-wrap items-end gap-2">
        <label class="block">
          <span class="block text-[11px] text-stone-500">Tahun</span>
          <input v-model="tahun" type="number" min="2020" max="2100"
                 class="mt-1 w-24 rounded-lg border-stone-200 text-[12px]" @change="muat">
        </label>

        <Link href="/hris/cuti/saldo" class="eq-btn-lain">Saldo cuti</Link>
      </div>
    </section>

    <!-- ══════════ antrean ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Menunggu persetujuan
          <span class="font-normal text-stone-400">| {{ antrean.length }} pengajuan</span>
        </h3>
        <span class="text-[11px] text-stone-400">Yang mengajukan tidak dapat menyetujui sendiri.</span>
      </header>

      <div v-if="!antrean.length" class="px-5 py-10 text-center text-[12px] text-stone-400">
        Tidak ada pengajuan yang menunggu.
      </div>

      <div v-for="c in antrean" :key="c.id" class="px-5 py-4 border-b border-stone-100 last:border-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="text-[13px] font-bold text-cam-ink">
              {{ c.pekerja }}
              <span class="font-normal text-stone-400">· {{ c.nik }}<span v-if="c.jabatan"> · {{ c.jabatan }}</span></span>
            </div>

            <div class="text-[12px] text-stone-600 mt-0.5">
              {{ c.jenis }} — <span class="num">{{ c.mulai }}</span> s.d. <span class="num">{{ c.selesai }}</span>
              <span class="text-stone-400">
                (<span class="num">{{ c.hari }}</span> hari kerja dari <span class="num">{{ c.kalender }}</span> hari kalender)
              </span>
            </div>

            <p v-if="c.alasan" class="text-[11.5px] text-stone-500 mt-1">{{ c.alasan }}</p>

            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[11.5px]">
              <span v-if="c.sisa" :class="c.sisa.sesudah < 0 ? 'text-red-600 font-semibold' : 'text-stone-600'">
                Saldo <span class="num">{{ c.sisa.sebelum }}</span> → <span class="num">{{ c.sisa.sesudah }}</span>
              </span>
              <span v-else class="text-stone-400">Tidak memotong saldo tahunan</span>

              <span v-if="c.dampak && c.dampak.paling_tipis !== null"
                    :class="c.dampak.paling_tipis <= 0 ? 'text-red-600 font-semibold' : 'text-stone-600'">
                Regu {{ c.dampak.regu }} hari paling tipis
                <span class="num">{{ c.dampak.sebelum }}</span> → <span class="num">{{ c.dampak.paling_tipis }}</span> orang
              </span>
              <span v-else class="text-stone-400">Roster belum tersusun pada rentang itu</span>

              <span v-if="c.bukti" class="text-emerald-700">Bukti terlampir</span>
              <span v-if="c.jenjang" class="text-amber-700 font-semibold">Menunggu KTT</span>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 shrink-0">
            <button type="button" class="eq-btn-utama" @click="setujui(c)">Setujui</button>
            <button type="button" class="eq-btn-lain" @click="tolak(c)">Tolak</button>
            <button v-if="!c.jenjang" type="button" class="eq-btn-lain" @click="teruskan(c)">Teruskan</button>
            <button type="button" class="text-[11px] text-red-600 hover:underline" @click="batalkan(c)">Batalkan</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════ pengajuan baru ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[13.5px] font-bold text-cam-ink mb-1">Ajukan cuti</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Hari yang terpotong dihitung dari roster orangnya, bukan dari kalender: cuti yang jatuh
        pada periode off-site tidak memakan saldo.
      </p>

      <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="ajukan">
        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Pekerja</span>
          <select v-model="form.pekerja_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="p in (props.pekerja ?? [])" :key="p.id" :value="p.id">
              {{ p.nama }} · {{ p.nik }}<span v-if="!p.masuk"> (tanggal masuk belum diisi)</span>
            </option>
          </select>
          <span v-if="form.errors.pekerja_id" class="block text-[11px] text-red-600">{{ form.errors.pekerja_id }}</span>
        </label>

        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">Jenis</span>
          <select v-model="form.jenis_cuti_id" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="j in jenis" :key="j.id" :value="j.id">
              {{ j.nama }}<span v-if="j.hari"> ({{ j.hari }} hari)</span>
            </option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Mulai</span>
          <input v-model="form.mulai" type="date" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
          <span v-if="form.errors.mulai" class="block text-[11px] text-red-600">{{ form.errors.mulai }}</span>
        </label>

        <label class="block">
          <span class="block text-[11px] text-stone-500">Selesai</span>
          <input v-model="form.selesai" type="date" required class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <label class="block lg:col-span-2">
          <span class="block text-[11px] text-stone-500">
            Bukti
            <span v-if="jenisTerpilih?.perluBukti" class="text-red-600">— wajib untuk jenis ini</span>
          </span>
          <input type="file" class="mt-1 w-full text-[12px]"
                 @change="form.bukti = ($event.target as HTMLInputElement).files?.[0] ?? null">
          <span v-if="form.errors.bukti" class="block text-[11px] text-red-600">{{ form.errors.bukti }}</span>
        </label>

        <label class="block sm:col-span-2 lg:col-span-4">
          <span class="block text-[11px] text-stone-500">Alasan</span>
          <input v-model="form.alasan" type="text" class="mt-1 w-full rounded-lg border-stone-200 text-[12px]">
        </label>

        <p v-if="jenisTerpilih?.dasar" class="sm:col-span-2 lg:col-span-4 text-[11.5px] text-stone-500">
          Dasar: {{ jenisTerpilih.dasar }}.
          <span v-if="jenisTerpilih.potong">Memotong saldo cuti tahunan.</span>
          <span v-else>Tidak memotong saldo cuti tahunan.</span>
          <span v-if="!jenisTerpilih.berbayar">Tanpa upah.</span>
        </p>

        <div class="sm:col-span-2 lg:col-span-4">
          <button type="submit" class="eq-btn-utama" :disabled="form.processing">Ajukan</button>
        </div>
      </form>
    </section>

    <!-- ══════════ riwayat ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">
          Riwayat {{ props.tahun }}
          <span class="font-normal text-stone-400">| {{ riwayat.length }} baris</span>
        </h3>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-5 py-2 font-semibold">Pekerja</th>
              <th class="px-5 py-2 font-semibold">Jenis</th>
              <th class="px-5 py-2 font-semibold">Rentang</th>
              <th class="px-5 py-2 font-semibold text-right">Hari kerja</th>
              <th class="px-5 py-2 font-semibold">Status</th>
              <th class="px-5 py-2 font-semibold">Ditindak</th>
              <th class="px-5 py-2 font-semibold"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="c in riwayat" :key="c.id" class="border-b border-stone-100">
              <td class="px-5 py-2.5">
                <div class="font-semibold text-cam-ink">{{ c.pekerja }}</div>
                <div class="text-[10.5px] text-stone-400">{{ c.nik }}</div>
              </td>
              <td class="px-5 py-2.5 text-stone-600">{{ c.jenis }}</td>
              <td class="px-5 py-2.5 num text-stone-600">{{ c.mulai }} – {{ c.selesai }}</td>
              <td class="px-5 py-2.5 num text-right">{{ c.hari }}</td>
              <td class="px-5 py-2.5">
                <span class="ct-lencana" :class="kelas(c.status)">{{ props.STATUS?.[c.status] ?? c.status }}</span>
              </td>
              <td class="px-5 py-2.5 text-[10.5px] text-stone-500">
                {{ c.penindak || '—' }}
                <div v-if="c.catatan" class="text-stone-400">{{ c.catatan }}</div>
              </td>
              <td class="px-5 py-2.5 text-right">
                <button v-if="c.status === 'disetujui'" type="button"
                        class="text-[11px] text-red-600 hover:underline" @click="batalkan(c)">Batalkan</button>
              </td>
            </tr>

            <tr v-if="!riwayat.length">
              <td colspan="7" class="px-5 py-10 text-center text-stone-400">
                Belum ada pengajuan yang selesai ditindak pada tahun ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style>
/**
 * Lencana status, sadar tema.
 *
 * Ditulis sebagai kelas dan bukan gaya sebaris supaya aturan mode
 * gelap dapat menimpanya.
 */
.ct-lencana {
  display: inline-block;
  border-radius: 9999px;
  padding: 0.125rem 0.5rem;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.ct-menunggu   { background: #FEF3C7; color: #78350F; }
.ct-disetujui  { background: #D1FAE5; color: #065F46; }
.ct-ditolak    { background: #FEE2E2; color: #7F1D1D; }
.ct-dibatalkan { background: #F5F5F4; color: #57534E; }

:root[data-tema="gelap"] .ct-menunggu   { background: #4A3810; color: #F6D488; }
:root[data-tema="gelap"] .ct-disetujui  { background: #143A2C; color: #8FE3BE; }
:root[data-tema="gelap"] .ct-ditolak    { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .ct-dibatalkan { background: #1C262B; color: #A8B2B8; }
</style>
