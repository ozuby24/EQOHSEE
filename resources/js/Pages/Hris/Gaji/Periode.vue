<script setup lang="ts">
/**
 * Periode gaji dan slipnya.
 *
 * ACUAN YANG BELUM DIVERIFIKASI DISEBUT DI PALING ATAS, bukan
 * disembunyikan di halaman pengaturan. Tabel tarif pajak yang belum
 * dicocokkan dengan naskah peraturannya menghasilkan angka yang tampak
 * sama meyakinkannya dengan tabel yang sudah — dan satu-satunya yang
 * membedakan keduanya adalah seseorang yang benar-benar memeriksanya.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, h, ref } from 'vue';
import { propHalaman } from '../../../halaman';
import KopHalaman from '../../../Components/KopHalaman.vue';
import UbinAngka from '../../../Components/UbinAngka.vue';
import Dialog from '../../../Components/Dialog.vue';
import { useDialog } from '../../../dialog';

const IKON: Record<string, string[]> = {
  orang:  ['M16 20v-1.5a4 4 0 0 0-8 0V20', 'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z'],
  bruto:  ['M3.5 8.5h17a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-17a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z', 'M3.5 8.5 17 5.2l.9 3.3', 'M17.5 13.5h.01'],
  site:   ['M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z', 'M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
  lembur: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5v5l3.2 1.9'],
  pajak:  ['M7 3.5h7L18 8v12.5H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z', 'M14 3.5V8h4', 'm10 16 4-5', 'M10 11h.01M14 16h.01'],
  neto:   ['M4 7.5h13.5a2.5 2.5 0 0 1 2.5 2.5v7a2 2 0 0 1-2 2H5a1 1 0 0 1-1-1Z', 'M4 7.5V6a1.5 1.5 0 0 1 1.5-1.5h10', 'M16.5 13.5h.01'],
};

const props = propHalaman();

/** Pembungkus kecil supaya tiap ubin cukup menyebut nama ikonnya. */
const Ikon = (p: { nama: string }) => h('svg', {
  viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true',
}, (IKON[p.nama] ?? []).map((d) => h('path', { d })));
const { dialog, tanya, batal, lanjut } = useDialog();

const periode = computed<any[]>(() => (props.periode ?? []) as any[]);
const slip    = computed<any[]>(() => (props.slip ?? []) as any[]);
const ringkas = computed<any>(() => props.ringkas ?? {});
const belum   = computed<string[]>(() => (props.belumVerifikasi ?? []) as string[]);

const terpilih = computed(() => periode.value.find((p) => p.id === props.terpilih) ?? null);

function pilih(id: number) {
  router.get('/hris/gaji', { periode: id }, { preserveState: true, preserveScroll: true, replace: true });
}

const baru = useForm({ tahun: String(props.tahunIni ?? ''), bulan: '' });

function buat() {
  baru.post('/hris/gaji', { preserveScroll: true, onSuccess: () => baru.reset('bulan') });
}

const jalan = useForm({});

async function hitung(p: any) {
  if (!await tanya({
    judul: `Hitung ulang ${p.label}?`,
    pesan: p.rekonsiliasi
      ? 'Desember memakai rekonsiliasi progresif Pasal 17: pajak setahun dikurangi yang sudah dipotong Januari sampai November.'
      : 'Seluruh slip pada periode ini ditulis ulang dari roster, absensi, dan lembur yang tercatat.',
    labelAksi: 'Hitung',
  })) return;

  jalan.post(`/hris/gaji/${p.id}/hitung`, { preserveScroll: true });
}

async function kunci(p: any) {
  if (!await tanya({
    judul: `Kunci ${p.label}?`,
    pesan: 'Sesudah dikunci, periode tidak dapat dihitung ulang — angkanya berhenti menjadi pratinjau '
      + 'dan menjadi dasar pembayaran.',
    labelAksi: 'Kunci',
    nada: 'bahaya',
    tegasNama: p.label,
  })) return;

  jalan.post(`/hris/gaji/${p.id}/kunci`, { preserveScroll: true });
}

function rupiah(n: number | null) {
  if (n === null || n === undefined) return '—';
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

const rinci = ref<number | null>(null);
</script>

<template>
  <Head :title="props.judul" />
  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />

  <div class="max-w-[1280px] mx-auto space-y-5">
    <KopHalaman :judul="props.judul as string" :subjudul="props.subjudul as string"
                tagline="Paid Right On Time"
                :remah="[['HRIS', '/hris'], ['Penggajian', null], ['Periode & Slip', null]]" ringkas />

    <section class="-mt-2 flex flex-wrap items-end justify-end gap-3">

      <Link href="/hris/gaji/acuan" class="eq-btn-lain">Acuan pajak &amp; BPJS</Link>
    </section>

    <section v-if="belum.length" class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3">
      <h3 class="text-[13px] font-bold text-amber-800">
        {{ belum.length }} acuan belum diperiksa terhadap naskah peraturannya
      </h3>
      <p class="text-[11.5px] text-amber-800 mt-1">
        {{ belum.join(' · ') }}
      </p>
      <p class="text-[11.5px] text-amber-800 mt-1">
        Perhitungan tetap dapat dijalankan dan dilihat. Yang ditahan adalah PENGUNCIAN periode —
        yaitu saat angkanya berhenti menjadi pratinjau dan mulai menjadi dasar pembayaran.
        <Link href="/hris/gaji/acuan" class="underline">Buka halaman acuan</Link>
      </p>
    </section>

    <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
      <!-- ══════════ daftar periode ══════════ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden self-start">
        <header class="px-4 py-3 border-b border-stone-100">
          <h3 class="text-[13px] font-bold text-cam-ink">Periode</h3>
        </header>

        <button v-for="p in periode" :key="p.id" type="button"
                class="w-full text-left px-4 py-2.5 border-b border-stone-100 last:border-0"
                :class="p.id === props.terpilih ? 'bg-cam-lime-soft' : ''"
                @click="pilih(p.id)">
          <div class="flex items-baseline justify-between gap-2">
            <span class="text-[12.5px] font-semibold text-cam-ink">{{ p.label }}</span>
            <span class="pg-lencana" :class="'pg-' + p.status">{{ props.STATUS?.[p.status] ?? p.status }}</span>
          </div>
          <div class="text-[10.5px] text-stone-400 mt-0.5">
            {{ p.slip }} slip<span v-if="p.rekonsiliasi"> · rekonsiliasi Desember</span>
          </div>
        </button>

        <div v-if="!periode.length" class="px-4 py-8 text-center text-[12px] text-stone-400">
          Belum ada periode.
        </div>

        <form class="px-4 py-3 border-t border-stone-200 bg-stone-50/70 space-y-2" @submit.prevent="buat">
          <div class="flex gap-2">
            <input v-model="baru.tahun" type="number" min="2020" max="2100" required
                   aria-label="Tahun periode gaji"
                   class="w-24 rounded-lg border-stone-200 text-[12px]">
            <select v-model="baru.bulan" required aria-label="Bulan periode gaji"
                    class="flex-1 rounded-lg border-stone-200 text-[12px]">
              <option value="">Bulan…</option>
              <option v-for="(nama, no) in (props.BULAN ?? {})" :key="no" :value="no">{{ nama }}</option>
            </select>
          </div>
          <button type="submit" class="eq-btn-lain w-full" :disabled="baru.processing">Tambah periode</button>
          <p v-if="baru.errors.bulan" class="text-[11px] text-red-600">{{ baru.errors.bulan }}</p>
        </form>
      </section>

      <!-- ══════════ periode terpilih ══════════ -->
      <div class="space-y-4">
        <section v-if="terpilih" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="text-[14px] font-bold text-cam-ink">{{ terpilih.label }}</h3>
              <p class="text-[11.5px] text-stone-500">
                <span v-if="terpilih.dihitung">Dihitung {{ terpilih.dihitung }}.</span>
                <span v-if="terpilih.dikunci"> Dikunci {{ terpilih.dikunci }}.</span>
                <span v-if="!terpilih.dihitung">Belum dihitung.</span>
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button v-if="terpilih.status !== 'terkunci'" type="button" class="eq-btn-utama"
                      :disabled="jalan.processing" @click="hitung(terpilih)">
                {{ terpilih.status === 'draft' ? 'Hitung' : 'Hitung ulang' }}
              </button>
              <button v-if="terpilih.status === 'terhitung'" type="button" class="eq-btn-lain"
                      @click="kunci(terpilih)">Kunci periode</button>
              <span v-if="terpilih.status === 'terkunci'" class="text-[11.5px] text-stone-500 self-center">
                Terkunci — tidak dapat dihitung ulang.
              </span>
            </div>
          </div>

          <div v-if="ringkas.orang" class="mt-4 grid gap-2 grid-cols-2 lg:grid-cols-3">
            <UbinAngka :angka="ringkas.orang" label="Orang">
              <template #ikon><Ikon nama="orang" /></template>
            </UbinAngka>

            <UbinAngka :angka="rupiah(ringkas.bruto)" label="Bruto pajak">
              <template #ikon><Ikon nama="bruto" /></template>
            </UbinAngka>

            <UbinAngka :angka="rupiah(ringkas.site)" label="Tunjangan site"
                       :nilai="ringkas.site" :dari="ringkas.bruto">
              <template #ikon><Ikon nama="site" /></template>
            </UbinAngka>

            <UbinAngka :angka="rupiah(ringkas.lembur)" label="Lembur"
                       :nilai="ringkas.lembur" :dari="ringkas.bruto">
              <template #ikon><Ikon nama="lembur" /></template>
            </UbinAngka>

            <UbinAngka nada="ingat" :angka="rupiah(ringkas.pph21)" label="PPh 21"
                       :nilai="ringkas.pph21" :dari="ringkas.bruto">
              <template #ikon><Ikon nama="pajak" /></template>
            </UbinAngka>

            <UbinAngka nada="baik" :angka="rupiah(ringkas.neto)" label="Dibawa pulang"
                       :nilai="ringkas.neto" :dari="ringkas.bruto">
              <template #ikon><Ikon nama="neto" /></template>
            </UbinAngka>
          </div>

          <p v-if="ringkas.tanpaPtkp" class="mt-3 text-[11.5px] text-red-700">
            {{ ringkas.tanpaPtkp }} orang belum punya status PTKP. Pajaknya dihitung dengan kategori A
            sebagai jaga-jaga — dan itu hampir pasti bukan statusnya yang sebenarnya.
          </p>
        </section>

        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-[11.5px]">
              <thead>
                <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
                  <th class="px-4 py-2 font-semibold">Pekerja</th>
                  <th class="px-4 py-2 font-semibold text-right">Pokok</th>
                  <th class="px-4 py-2 font-semibold text-right">Site</th>
                  <th class="px-4 py-2 font-semibold text-right">Lembur</th>
                  <th class="px-4 py-2 font-semibold text-right">Bruto</th>
                  <th class="px-4 py-2 font-semibold text-right">BPJS</th>
                  <th class="px-4 py-2 font-semibold text-right">PPh 21</th>
                  <th class="px-4 py-2 font-semibold text-right">Dibawa pulang</th>
                  <th class="px-4 py-2 font-semibold"></th>
                </tr>
              </thead>

              <tbody>
                <template v-for="s in slip" :key="s.id">
                  <tr class="border-b border-stone-100">
                    <td class="px-4 py-2.5">
                      <div class="font-semibold text-cam-ink">{{ s.pekerja }}</div>
                      <div class="text-[10.5px] text-stone-400">
                        {{ s.nik }}
                        <span v-if="s.ptkp"> · {{ s.ptkp }}<span v-if="s.kategori"> (TER {{ s.kategori }})</span></span>
                        <span v-else class="text-red-600"> · PTKP belum diisi</span>
                      </div>
                    </td>
                    <td class="px-4 py-2.5 num text-right">{{ rupiah(s.pokok + s.tetap) }}</td>
                    <td class="px-4 py-2.5 num text-right">
                      {{ rupiah(s.site) }}
                      <div class="text-[10px] text-stone-400">{{ s.hariSite }} hari</div>
                    </td>
                    <td class="px-4 py-2.5 num text-right">
                      {{ rupiah(s.lembur) }}
                      <div v-if="s.lemburJam" class="text-[10px] text-stone-400">{{ s.lemburJam }} jam</div>
                    </td>
                    <td class="px-4 py-2.5 num text-right font-semibold text-cam-ink">{{ rupiah(s.bruto) }}</td>
                    <td class="px-4 py-2.5 num text-right text-stone-500">{{ rupiah(s.bpjs) }}</td>
                    <td class="px-4 py-2.5 num text-right" :class="s.pph21 < 0 ? 'text-emerald-700' : 'text-amber-700'">
                      {{ rupiah(s.pph21) }}
                      <div v-if="s.tarif" class="text-[10px] text-stone-400">{{ s.tarif }}%</div>
                    </td>
                    <td class="px-4 py-2.5 num text-right font-bold text-cam-ink">{{ rupiah(s.neto) }}</td>
                    <td class="px-4 py-2.5 text-right">
                      <button type="button" class="text-[11px] text-sky-700 hover:underline"
                              @click="rinci = rinci === s.id ? null : s.id">Rincian</button>
                    </td>
                  </tr>

                  <tr v-if="rinci === s.id" class="border-b border-stone-100 bg-stone-50/60">
                    <td colspan="9" class="px-4 py-3">
                      <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                          <h4 class="text-[11.5px] font-bold text-cam-ink mb-1">Iuran BPJS</h4>
                          <table class="text-[11px] w-full">
                            <tr v-for="(r, k) in (s.rincian?.bpjs ?? {})" :key="k" class="border-b border-stone-100">
                              <td class="py-1 pr-3 uppercase text-stone-500">{{ k }}</td>
                              <td class="py-1 num text-right text-stone-500">{{ rupiah(r.dasar) }}</td>
                              <td class="py-1 num text-right">{{ rupiah(r.perusahaan) }}</td>
                              <td class="py-1 num text-right font-semibold">{{ rupiah(r.karyawan) }}</td>
                            </tr>
                            <tr>
                              <td class="py-1 pr-3 text-stone-400 text-[10px]" colspan="2">dasar · perusahaan · karyawan</td>
                            </tr>
                          </table>
                        </div>

                        <div>
                          <h4 class="text-[11.5px] font-bold text-cam-ink mb-1">PPh 21</h4>

                          <div v-if="s.rincian?.pajak?.cara === 'ter'" class="text-[11px] text-stone-600">
                            Tarif efektif kategori {{ s.rincian.pajak.kategori }}
                            {{ s.rincian.pajak.tarif }}% × bruto {{ rupiah(s.bruto) }}
                            = {{ rupiah(s.rincian.pajak.pajak) }}
                            <div class="text-stone-400 mt-1">PP 58/2023 & PMK 168/2023 — berlaku Januari sampai November.</div>
                          </div>

                          <div v-else-if="s.rincian?.pajak" class="text-[11px] text-stone-600 space-y-0.5">
                            <div>Bruto setahun {{ rupiah(s.rincian.pajak.bruto) }}</div>
                            <div>Biaya jabatan −{{ rupiah(s.rincian.pajak.biaya_jabatan) }}</div>
                            <div>Iuran pekerja −{{ rupiah(s.rincian.pajak.iuran) }}</div>
                            <div>PTKP −{{ rupiah(s.rincian.pajak.ptkp) }}</div>
                            <div class="font-semibold text-cam-ink">PKP {{ rupiah(s.rincian.pajak.pkp) }}</div>
                            <div v-for="(l, i) in (s.rincian.pajak.lapis ?? [])" :key="i" class="text-stone-500">
                              {{ rupiah(l.dasar) }} × {{ l.tarif }}% = {{ rupiah(l.pajak) }}
                            </div>
                            <div>Pajak setahun {{ rupiah(s.rincian.pajak.pajak_setahun) }}</div>
                            <div>Sudah dipotong −{{ rupiah(s.rincian.pajak.sudah) }}</div>
                            <div class="text-stone-400 mt-1">UU 7/2021 pasal 17 — rekonsiliasi Desember.</div>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>

                <tr v-if="!slip.length">
                  <td colspan="9" class="px-4 py-10 text-center text-stone-400">
                    Periode ini belum berisi slip. Tekan “Hitung”.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style>
.pg-lencana {
  display: inline-block; border-radius: 9999px; padding: 0 0.4rem;
  font-size: 10px; font-weight: 600; white-space: nowrap;
}

.pg-draft     { background: #F5F5F4; color: #57534E; }
.pg-terhitung { background: #DBEAFE; color: #1E3A5F; }
.pg-terkunci  { background: #D1FAE5; color: #065F46; }

:root[data-tema="gelap"] .pg-draft     { background: #1C262B; color: #A8B2B8; }
:root[data-tema="gelap"] .pg-terhitung { background: #1E3F5E; color: #A8CDF0; }
:root[data-tema="gelap"] .pg-terkunci  { background: #143A2C; color: #8FE3BE; }

</style>
