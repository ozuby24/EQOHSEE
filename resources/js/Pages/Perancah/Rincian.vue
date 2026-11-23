<script setup lang="ts">
/*
  Rincian satu perancah: tag yang berlaku, spesifikasi, hasil
  kalkulator dan kajian, lalu seluruh riwayatnya — inspeksi dan tag yang
  pernah terbit. Riwayat hanya bertambah; tag lama tetap terbaca dengan
  warnanya sendiri, sehingga urutan kejadiannya dapat direkonstruksi.
*/
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Panggung from '../../Components/Energi/Panggung.vue';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import Sketsa from '../../Components/Perancah/Sketsa.vue';
import KartuTag from '../../Components/Perancah/KartuTag.vue';
import { n, tgl } from '../../energi';
import { tag, sisa, waktu, PUTUSAN } from '../../perancah';
import { useDialog } from '../../dialog';
import Dialog from '../../Components/Dialog.vue';

const { dialog, tanya, batal, lanjut } = useDialog();

const props = propHalaman();
const u = computed(() => props.unit ?? {});
const h = computed(() => props.hitung ?? null);
const kaj = computed(() => props.kajian ?? null);
const w = computed(() => tag(u.value.status));

const stat = computed(() => [
  { label: 'Tag berlaku', nilai: w.value.pendek, ikon: 'tag', catatan: u.value.kode_tag ?? '—' },
  { label: 'Faktor keamanan', nilai: n(h.value?.faktor_keamanan, 2), satuan: '×', ikon: 'scale', catatan: 'Minimum 4,0×' },
  { label: 'Rasio H/B', nilai: n(h.value?.rasio_hb, 2), satuan: ': 1', ikon: 'move-vertical', catatan: `${u.value.jumlah_ikatan} ikatan · min ${h.value?.ikatan_minimum ?? 0}` },
  { label: 'Tekanan tumpuan', nilai: n(h.value?.tekanan_tanah_kn_m2, 1), satuan: 'kN/m²', ikon: 'mountain', catatan: u.value.jenis_tanah },
]);
const lencana = computed(() => [
  kaj.value ? `Kajian: ${PUTUSAN[kaj.value.putusan]?.label} (${kaj.value.skor}/100)` : 'Belum dikaji',
  ...(kaj.value?.perlu_pe ? ['Wajib disahkan P.E.'] : []),
  u.value.status === 'dibongkar' ? 'Sudah dibongkar' : `Tag ${sisa(u.value.inspeksi_berikut)}`,
]);

const inspeksi = computed(() => (props.inspeksi ?? []).map((i: any, urut: number) => ({ ...i, urut, p: PUTUSAN[i.putusan] })));
const riwayatTag = computed(() => (props.riwayatTag ?? []).map((t: any, urut: number) => ({ ...t, urut, w: tag(t.warna) })));
const kriteria = computed(() => (kaj.value?.kriteria ?? []).map((c: any, urut: number) => ({ ...c, urut })));
const bom = computed(() => (h.value?.bom ?? []).map((b: any, urut: number) => ({ ...b, urut })));
const boleh = computed(() => u.value.status !== 'dibongkar');

async function kaji() { router.post(`/perancah/${u.value.id}/kaji`, {}, { preserveScroll: true }); }
async function bongkar() {
  if (await tanya({ judul: 'Catat perancah sudah dibongkar?', pesan: `Tag aktif ${u.value.kode_tag ?? ''} dicabut dan ${u.value.kode} keluar dari peta aktif. Riwayatnya tetap tersimpan.`, labelAksi: 'Sudah dibongkar', nada: 'bahaya' })) {
    router.post(`/perancah/${u.value.id}/bongkar`);
  }
}
const ckelas = (s: string) => (s === 'lulus' ? 'pr-hijau' : s === 'peringatan' ? 'pr-kuning' : 'pr-merah');
const clabel = (s: string) => (s === 'lulus' ? 'Lulus' : s === 'peringatan' ? 'Bersyarat' : 'Gagal');
</script>

<template>
  <Head :title="`${u.kode} · Scaffolding`" />
  <div class="space-y-4">
    <section class="flex flex-wrap items-center justify-between gap-2">
      <Link href="/perancah/peta" class="text-[12.5px] font-semibold text-stone-500 hover:underline">← Daftar &amp; peta</Link>
      <div v-if="boleh" class="flex flex-wrap gap-2">
        <Link :href="`/perancah/${u.id}/inspeksi`" class="eq-btn-utama inline-flex items-center gap-2"><Ikon nama="clipboard-check" :ukuran="16" />Inspeksi sekarang</Link>
        <Link :href="`/perancah/${u.id}/ubah`" class="eq-btn-lain inline-flex items-center gap-2"><Ikon nama="ruler" :ukuran="16" />Ubah spesifikasi</Link>
        <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="kaji"><Ikon nama="calculator" :ukuran="16" />Kaji ulang</button>
        <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="bongkar"><Ikon nama="package" :ukuran="16" />Catat dibongkar</button>
      </div>
    </section>

    <Panggung tema="perancah" :judul="`${u.kode} · ${u.judul}`"
              :sub="`${u.zona}${u.peralatan ? ' · ' + u.peralatan : ''}${u.izin ? ' · izin ' + u.izin : ''} · ${u.dimensi} · ${u.jenis_label}`"
              :stat="stat" :lencana="lencana" />

    <section class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)] items-start">
      <div class="space-y-3">
        <KartuTag v-if="props.tag" :tag="props.tag" />
        <p v-else class="text-[12.5px] text-stone-500 text-center py-10">Belum ada tag aktif.</p>
        <Link v-if="props.tag" :href="`/perancah/tag/${props.tag.kode}`" class="eq-btn-lain w-full justify-center inline-flex items-center gap-2"><Ikon nama="printer" :ukuran="16" />Buka & cetak kartu tag</Link>
      </div>

      <div class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
          <Kartu judul="Spesifikasi & lokasi" ikon="map-pin" warna="amber">
            <dl class="grid grid-cols-[130px_1fr] gap-y-1.5 text-[12.5px]">
              <dt class="text-stone-500">Kelas beban</dt><dd class="font-semibold">{{ u.kelas }} · {{ u.swl }} kg/m²</dd>
              <dt class="text-stone-500">Dek kerja</dt><dd class="font-semibold">{{ u.jumlah_dek }}</dd>
              <dt class="text-stone-500">Tumpuan</dt><dd class="font-semibold">{{ u.jenis_tanah }}</dd>
              <dt class="text-stone-500">Angin lokasi</dt><dd class="font-semibold num">{{ n(u.angin_kmh, 0) }} km/jam</dd>
              <dt class="text-stone-500">Proteksi jatuh</dt><dd class="font-semibold">{{ u.ada_guardrail ? 'Guardrail' : 'Tanpa guardrail' }} · {{ u.ada_toeboard ? 'toe board' : 'tanpa toe board' }}{{ u.ada_outrigger ? ' · outrigger' : '' }}</dd>
              <dt class="text-stone-500">Koordinat</dt><dd class="font-semibold num">{{ u.lat ? `${Number(u.lat).toFixed(6)}, ${Number(u.lng).toFixed(6)}` : '—' }} · elv {{ n(u.elevasi_m, 1) }} m</dd>
              <dt class="text-stone-500">Kontraktor</dt><dd class="font-semibold">{{ u.kontraktor || '—' }}</dd>
              <dt class="text-stone-500">Scaffolder</dt><dd class="font-semibold">{{ u.scaffolder_nama || '—' }} <span v-if="u.scaffolder_sertifikat" class="text-stone-400">· {{ u.scaffolder_sertifikat }}</span></dd>
              <dt class="text-stone-500">Dipasang</dt><dd class="font-semibold">{{ tgl(u.tanggal_pasang) }} · bongkar {{ tgl(u.rencana_bongkar) }}</dd>
            </dl>
          </Kartu>
          <Kartu v-if="kaj" judul="Kajian kelayakan" :ket="`${PUTUSAN[kaj.putusan]?.label} · skor ${kaj.skor}/100 · ${waktu(kaj.ditinjau_at)}`" ikon="clipboard-check"
                 :warna="kaj.putusan === 'layak' ? 'hijau' : kaj.putusan === 'bersyarat' ? 'amber' : 'merah'">
            <ul class="space-y-2">
              <li v-for="c in kriteria" :key="c.kode" class="flex items-start gap-2.5 text-[12px]">
                <span class="pr-pil shrink-0" :class="ckelas(c.status)">{{ clabel(c.status) }}</span>
                <div class="min-w-0"><p class="font-bold text-cam-ink">{{ c.nama }}</p><p class="text-stone-500">{{ c.aktual }} · {{ c.batas }}</p></div>
              </li>
            </ul>
            <ul v-if="kaj.rekomendasi?.length" class="mt-3 space-y-1 text-[11.5px] rc-saran">
              <li v-for="(s, i) in kaj.rekomendasi" :key="i">• {{ s }}</li>
            </ul>
          </Kartu>
        </div>

        <div v-if="h" class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
          <Kartu judul="Sketsa tampak depan" :ket="`${h.bay} bay × ${h.lift} lift · ${n(h.luas_dek_m2, 1)} m² dek`" ikon="construction" warna="amber">
            <Sketsa :panjang="u.panjang_m" :tinggi="u.tinggi_m" :bay="h.bay" :lift="h.lift" :dek="u.jumlah_dek" :ikatan="u.jumlah_ikatan"
                    :guardrail="u.ada_guardrail" :toeboard="u.ada_toeboard" :outrigger="u.ada_outrigger" :warna="w.warna" />
          </Kartu>
          <Kartu judul="Bill of materials & beban" :ket="`Beban mati ${n(h.beban_mati_kg, 0)} kg · hidup ${n(h.beban_hidup_kg, 0)} kg · kaki ${n(h.beban_kaki_kn, 2)} kN`" ikon="boxes" warna="biru" rapat>
            <table class="w-full text-[12px]"><tbody class="divide-y divide-stone-100">
              <tr v-for="b in bom" :key="b.butir"><td class="px-5 py-2">{{ b.butir }}</td><td class="px-5 py-2 text-right font-bold num">{{ n(b.jumlah, 0) }} <span class="text-stone-400 font-semibold">{{ b.satuan }}</span></td></tr>
            </tbody></table>
          </Kartu>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <Kartu judul="Riwayat inspeksi" :ket="`${inspeksi.length} inspeksi tercatat`" ikon="history" warna="ungu" rapat>
        <ol class="divide-y divide-stone-100">
          <li v-for="i in inspeksi" :key="i.id" class="px-5 py-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="pr-pil" :class="i.p?.kelas">{{ i.p?.label }}</span>
              <span class="text-[12.5px] font-bold text-cam-ink">{{ i.jenis }}</span>
              <span class="text-[11.5px] text-stone-500">{{ waktu(i.diperiksa_at) }} · {{ i.inspektur }} ({{ i.sertifikat }})</span>
            </div>
            <p class="text-[11.5px] text-stone-500 mt-1">{{ i.lulus }} lulus · {{ i.gagal_kritis }} gagal kritis · {{ i.gagal_minor }} gagal minor
              <template v-if="i.berlaku_sampai"> · berlaku s.d. {{ waktu(i.berlaku_sampai) }}</template></p>
            <p v-if="i.gagal.length" class="text-[11.5px] rc-gagal mt-1">Gagal: {{ i.gagal.join('; ') }}</p>
            <p v-if="i.perbaikan" class="text-[11.5px] text-stone-600 mt-0.5">Perbaikan: {{ i.perbaikan }}</p>
            <p v-if="i.syarat" class="text-[11.5px] rc-saran mt-0.5">Syarat: {{ i.syarat }}</p>
          </li>
          <li v-if="!inspeksi.length" class="px-5 py-8 text-center text-[12.5px] text-stone-400">Belum pernah diinspeksi — tag tetap merah.</li>
        </ol>
      </Kartu>
      <Kartu judul="Riwayat scafftag" ket="Tag lama dinonaktifkan, tidak dihapus" ikon="tags" warna="amber" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="t in riwayatTag" :key="t.kode" class="px-5 py-2.5 flex items-center gap-3">
            <span class="w-2.5 h-9 rounded-full shrink-0" :style="{ background: t.w.warna, opacity: t.aktif ? 1 : 0.45 }" />
            <div class="min-w-0 flex-1">
              <Link :href="`/perancah/tag/${t.kode}`" class="text-[12.5px] font-bold text-cam-ink num hover:underline">{{ t.kode }}</Link>
              <p class="text-[11px] text-stone-500 truncate">{{ t.label }} · {{ waktu(t.terbit_at) }} · {{ t.oleh }}</p>
            </div>
            <span v-if="t.aktif" class="pr-pil pr-biru">Aktif</span>
          </li>
        </ul>
      </Kartu>
    </section>
    <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
  </div>
</template>

<style scoped>
.rc-saran { color: #92400E; }
.rc-gagal { color: #B91C1C; }
:root[data-tema="gelap"] .rc-saran { color: #F6CF7A; }
:root[data-tema="gelap"] .rc-gagal { color: #F5A9A9; }
</style>
