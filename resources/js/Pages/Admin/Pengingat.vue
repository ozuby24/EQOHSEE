<script setup lang="ts">
/**
 * Pengingat batas waktu tindak lanjut temuan audit SMKP.
 *
 * Porting bagian "Pengingat" dari Aurorasys (Admin → Server → Email &
 * Pengingat): sakelar, jadwal, pratinjau pengingat 90 hari ke depan,
 * kirim yang jatuh tempo sekarang, dan riwayatnya. Server emailnya tidak
 * diatur dari sini — EQOHSEE memakai MAIL_* di .env, dan halaman ini
 * hanya mengatakan apakah pengiriman siap.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import type { HalamanPengingat } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, batal, lanjut } = useDialog();

const props = defineProps<HalamanPengingat>();

const halaman = usePage<any>();
const galat = computed<Record<string, string>>(() => halaman.props.errors ?? {});

const atur = reactive({ aktif: props.pengingat.aktif, jadwal: [...props.pengingat.jadwal] });
const berubah = computed(() => atur.aktif !== props.pengingat.aktif
  || [...atur.jadwal].sort().join() !== [...props.pengingat.jadwal].sort().join());
const urutJadwal = (j: string[]) => Object.keys(props.pengingat.pilihan).filter((k) => j.includes(k));
const labelJadwal = (j: string[]) => urutJadwal(j).map((k) => props.pengingat.pilihan[k]).join(' & ');

function ubahJadwal(k: string) {
  atur.jadwal = atur.jadwal.includes(k) ? atur.jadwal.filter((x) => x !== k) : [...atur.jadwal, k];
}

async function simpan() {
  const ya = await tanya({
    judul: atur.aktif ? 'Aktifkan pengingat tindak lanjut?' : 'Matikan pengingat tindak lanjut?',
    pesan: atur.aktif
      ? `Setiap hari pukul ${props.pengingat.jam} WIB, akun auditi menerima email ${labelJadwal(atur.jadwal)} sebelum batas waktu tindak lanjut temuannya.`
      : 'Email pengingat berhenti dikirim sampai diaktifkan lagi.',
    labelAksi: atur.aktif ? 'Simpan & aktifkan' : 'Matikan',
    nada: atur.aktif ? 'utama' : 'bahaya',
  });
  if (!ya) return;

  router.post(props.tautan.simpan,
    { aktif: atur.aktif, jadwal: urutJadwal(atur.jadwal).join(',') },
    { preserveScroll: true });
}

async function kirimSekarang() {
  const ya = await tanya({
    judul: 'Kirim pengingat yang jatuh tempo sekarang?',
    pesan: `Sama dengan pengiriman harian pukul ${props.pengingat.jam} WIB — pengingat yang sudah terkirim tidak dikirim ulang.`,
    labelAksi: 'Kirim sekarang',
  });
  if (!ya) return;

  router.post(props.tautan.kirim, {}, { preserveScroll: true });
}

const keadaan = computed(() => {
  if (!props.pengingat.aktif) return { label: 'Dimatikan', kelas: 'bg-stone-100 text-stone-600' };
  if (!props.surel.siap) return { label: 'Menunggu server email', kelas: 'bg-amber-100 text-amber-800' };

  return { label: 'Aktif', kelas: 'bg-emerald-100 text-emerald-800' };
});

const tanggal = (t: string) => {
  const [y, m, d] = t.split('-').map(Number);

  return new Date(y, m - 1, d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
const hariIni = new Date().toLocaleDateString('sv-SE');
const tanpaAuditi = computed(() => new Set(props.mendatang.filter((m) => !m.penerima.length).map((m) => m.perusahaan)).size);
</script>

<template>
  <Head :title="judul" />

  <div class="space-y-5">

    <section class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
      <div class="relative flex items-start justify-between gap-4 flex-wrap">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">
            Pusat Kendali · Audit SMKP
          </span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="glass rounded-xl px-3 py-2 text-[11.5px] font-bold text-white">{{ keadaan.label }}</span>
          <Link :href="tautan.sistem"
                class="glass rounded-xl px-3.5 py-2 text-[11.5px] font-bold text-white hover:bg-white/20 transition">
            Pusat Kendali
          </Link>
        </div>
      </div>
    </section>

    <div v-if="galat.pengingat || galat.jadwal"
         class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[12.5px] text-red-700 leading-relaxed">
      {{ galat.pengingat || galat.jadwal }}
    </div>

    <!-- Server email: dari .env, dilaporkan saja -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Server email</h3>
        <span class="rounded-lg px-2 py-0.5 text-[10.5px] font-bold"
              :class="surel.siap ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
          {{ surel.siap ? 'Siap' : 'Belum diatur' }}
        </span>
      </div>
      <p class="text-[11.5px] text-stone-600 leading-relaxed">
        <template v-if="surel.siap">
          Memakai <code>MAIL_MAILER={{ surel.mailer }}</code> di berkas <code>.env</code>, pengirim
          <b>{{ surel.dari || '—' }}</b>.
        </template>
        <template v-else>
          Email belum dapat dikirim — <code>MAIL_MAILER</code> di berkas <code>.env</code> masih
          <code>{{ surel.mailer || 'kosong' }}</code>. Pengingat tetap terjadwal, tetapi baru terkirim
          setelah server email diatur.
        </template>
      </p>
    </section>

    <!-- Sakelar & jadwal -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <h3 class="text-[14px] font-bold text-cam-ink">Pengingat batas waktu tindak lanjut</h3>
        <span class="rounded-lg px-2 py-0.5 text-[10.5px] font-bold" :class="keadaan.kelas">{{ keadaan.label }}</span>
      </div>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-4">
        Setiap hari pukul {{ pengingat.jam }} WIB, akun auditi menerima satu email berisi temuan
        perusahaannya yang belum ditutup dan mendekati target penyelesaian.
      </p>

      <label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4 cursor-pointer">
        <input v-model="atur.aktif" type="checkbox" class="mt-0.5 rounded border-stone-300 accent-[color:var(--eq-aksen,#F57C00)]">
        <span>
          <span class="block text-[13px] font-bold text-cam-ink">Kirim pengingat ke akun auditi</span>
          <span class="block text-[11.5px] text-stone-600 mt-0.5 leading-relaxed">
            Penerima: akun auditi aktif perusahaan yang diaudit; PJP yang belum punya akun auditi —
            auditi IUP induknya. Temuan berstatus Closed tidak diingatkan. Peran auditi ditetapkan di
            <Link :href="tautan.pengguna" class="text-cam-lime-deep font-semibold hover:underline">Kelola Pengguna</Link>.
          </span>
        </span>
      </label>

      <div class="mt-4">
        <p class="block text-[12px] font-semibold text-cam-ink mb-2">Kirim pada</p>
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Jadwal pengingat">
          <button v-for="(l, k) in pengingat.pilihan" :key="k" type="button"
                  :aria-pressed="atur.jadwal.includes(String(k))" :disabled="!atur.aktif"
                  class="rounded-full border px-3.5 py-1.5 text-[12px] font-bold transition disabled:opacity-40"
                  :class="atur.jadwal.includes(String(k))
                    ? 'border-cam-lime bg-cam-lime-soft text-cam-ink'
                    : 'border-stone-200 text-stone-600 hover:border-cam-lime/50'"
                  @click="ubahJadwal(String(k))">
            {{ l }}
          </button>
          <span class="text-[12px] text-stone-600">sebelum batas waktu</span>
        </div>
        <p class="text-[11px] text-stone-500 mt-2 leading-relaxed">
          Bila beberapa jadwal terlewat sekaligus (mis. temuan baru dengan target 3 minggu lagi), hanya satu email yang dikirim.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 pt-4 mt-4 border-t border-stone-100">
        <button type="button" :disabled="!berubah || (atur.aktif && !atur.jadwal.length)" @click="simpan"
                class="rounded-xl bg-cam-lime-deep px-4 py-2 text-[11.5px] font-bold text-white
                       hover:brightness-95 transition disabled:opacity-40">
          Simpan pengaturan
        </button>
        <button type="button" :disabled="!pengingat.aktif || !surel.siap" @click="kirimSekarang"
                class="rounded-xl border border-stone-200 px-4 py-2 text-[11.5px] font-bold text-stone-600
                       hover:border-cam-lime hover:bg-cam-lime-soft transition disabled:opacity-40">
          Kirim yang jatuh tempo sekarang
        </button>
        <span v-if="berubah" class="text-[11.5px] font-semibold text-amber-700">Ada perubahan yang belum disimpan.</span>
      </div>
    </section>

    <!-- Pengingat mendatang -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Pengingat 90 hari ke depan</h3>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">
        Menurut jadwal yang tersimpan{{ pengingat.aktif ? '' : ' — saat ini dimatikan' }}. Target diundur: jadwalnya dihitung ulang.
      </p>

      <div v-if="tanpaAuditi" class="mb-3.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-[12px] text-amber-800">
        {{ tanpaAuditi }} perusahaan belum punya akun auditi aktif — pengingatnya tidak terkirim sampai akunnya dibuat.
      </div>

      <div v-if="mendatang.length" class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-[12px]">
          <thead>
            <tr class="text-left text-[10.5px] font-bold uppercase tracking-wide text-stone-500 border-b border-stone-100">
              <th class="py-2 pr-3">Dikirim</th><th class="py-2 pr-3">Jadwal</th><th class="py-2 pr-3">Temuan</th>
              <th class="py-2 pr-3">Perusahaan</th><th class="py-2 pr-3">Target</th><th class="py-2">Penerima</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(m, i) in mendatang" :key="i" class="border-b border-stone-100 last:border-0 align-top">
              <td class="py-2 pr-3 whitespace-nowrap tabular-nums">
                <span v-if="m.tanggal <= hariIni" class="font-bold text-amber-700"
                      :title="`Terkirim pada jalan berikutnya pukul ${pengingat.jam} WIB, atau lewat tombol Kirim sekarang.`">Jatuh tempo</span>
                <template v-else>{{ tanggal(m.tanggal) }}</template>
              </td>
              <td class="py-2 pr-3 whitespace-nowrap text-stone-600">{{ m.jadwal }} sebelum</td>
              <td class="py-2 pr-3"><Link :href="m.url" class="font-semibold text-cam-lime-deep hover:underline">{{ m.temuan }}</Link></td>
              <td class="py-2 pr-3 text-cam-ink">{{ m.perusahaan }}</td>
              <td class="py-2 pr-3 whitespace-nowrap tabular-nums">{{ tanggal(m.target) }}</td>
              <td class="py-2 text-[11.5px]">
                <span v-if="m.penerima.length" class="text-stone-600 break-all">{{ m.penerima.join(', ') }}</span>
                <span v-else class="font-bold text-amber-700">Belum ada akun auditi</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-[12px] text-stone-600">
        Tidak ada temuan terbuka dengan target penyelesaian dalam jangkauan jadwal.
      </p>
    </section>

    <!-- Riwayat -->
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Riwayat</h3>
      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3.5">Pengiriman pengingat dan perubahan pengaturan.</p>

      <ul v-if="riwayat.length" class="divide-y divide-stone-100">
        <li v-for="r in riwayat" :key="r.id" class="py-2.5 flex items-start gap-3">
          <div class="min-w-0 flex-1">
            <p class="text-[12.5px] font-bold text-cam-ink">{{ r.aksi }}</p>
            <p class="text-[11.5px] break-words" :class="r.detail?.includes('gagal') ? 'text-red-600' : 'text-stone-600'">{{ r.detail }}</p>
          </div>
          <span class="text-[11px] text-stone-500 whitespace-nowrap">{{ r.waktu }}<template v-if="r.oleh"> · {{ r.oleh }}</template><template v-else> · penjadwal</template></span>
        </li>
      </ul>
      <p v-else class="text-[12px] text-stone-600">Belum ada pengiriman.</p>
    </section>

  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
