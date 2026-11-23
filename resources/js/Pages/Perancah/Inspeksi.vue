<script setup lang="ts">
/*
  Form inspeksi 16 butir — untuk dipakai di kaki perancah, di ponsel.

  Tombolnya besar (sasaran sentuh ≥ 44 px), tiga pilihan per butir, dan
  panel putusan di bawah layar berubah seketika: satu butir KRITIS gagal
  langsung memperlihatkan TAG MERAH sebelum apa pun disimpan. Inspektur
  tidak memilih warna tag; ia hanya menilai butir, dan warnanya
  mengikuti.
*/
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import { tag, PUTUSAN } from '../../perancah';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const butir = computed(() => (pil.value.periksa ?? []) as any[]);

const awal: Record<string, string> = {};
butir.value.forEach(b => (awal[b.kode] = ''));
const form = useForm<Record<string, any>>({
  jenis: props.terakhir ? 'mingguan' : 'awal', inspektur: props.pemeriksa?.nama ?? '', inspektur_sertifikat: '',
  periksa: awal, syarat_khusus: '', tindakan_perbaikan: '',
});

const grup = computed(() => {
  const g: { nama: string; butir: any[] }[] = [];
  butir.value.forEach(b => { let x = g.find(y => y.nama === b.grup); if (!x) { x = { nama: b.grup, butir: [] }; g.push(x); } x.butir.push(b); });
  return g;
});

const terisi = computed(() => butir.value.filter(b => form.periksa[b.kode]).length);
const gagalKritis = computed(() => butir.value.filter(b => b.kritis && form.periksa[b.kode] === 'gagal'));
const gagalMinor = computed(() => butir.value.filter(b => !b.kritis && form.periksa[b.kode] === 'gagal'));
const lulus = computed(() => butir.value.filter(b => form.periksa[b.kode] === 'lulus').length);

const putusan = computed(() => {
  if (props.kajian?.putusan === 'tidak_layak') return { w: 'merah', judul: 'TAG MERAH', ket: 'Kajian teknis TIDAK LAYAK — inspeksi tidak dapat mengubahnya. Ubah rancangan lebih dulu.' };
  if (gagalKritis.value.length) return { w: 'merah', judul: 'TAG MERAH', ket: `${gagalKritis.value.length} butir kritis gagal — tidak layak digunakan sampai diperbaiki dan diinspeksi ulang.` };
  if (terisi.value < butir.value.length) return { w: 'abu', judul: `${terisi.value}/${butir.value.length} butir dinilai`, ket: 'Nilai seluruh butir untuk melihat tag yang akan terbit.' };
  if (gagalMinor.value.length || form.syarat_khusus.trim() || props.kajian?.putusan === 'bersyarat')
    return { w: 'kuning', judul: 'TAG KUNING', ket: 'Layak digunakan dengan syarat yang tercetak di tag, berlaku 7 hari.' };
  return { w: 'hijau', judul: 'TAG HIJAU', ket: 'Layak digunakan, berlaku 7 hari sejak inspeksi.' };
});

function semuaLulus() { butir.value.forEach(b => (form.periksa[b.kode] = b.kode === 'INS-04' && props.unit?.jenis !== 'mobile' ? 'na' : 'lulus')); }
function samakanTerakhir() { if (props.terakhir) butir.value.forEach(b => (form.periksa[b.kode] = props.terakhir[b.kode] ?? '')); }
function kirim() { form.post(`/perancah/${props.unit.id}/inspeksi`); }
const PILIH = [['lulus', 'Lulus'], ['gagal', 'Gagal'], ['na', 'N/A']];
</script>

<template>
  <Head :title="`Inspeksi ${props.unit?.kode}`" />
  <form class="space-y-4 pb-28" @submit.prevent="kirim">
    <section class="flex flex-wrap items-center justify-between gap-2">
      <Link :href="`/perancah/${props.unit.id}`" class="text-[12.5px] font-semibold text-stone-500 hover:underline">← {{ props.unit.kode }} · {{ props.unit.judul }}</Link>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="eq-btn-lain" @click="semuaLulus">Tandai semua lulus</button>
        <button v-if="props.terakhir" type="button" class="eq-btn-lain" @click="samakanTerakhir">Salin inspeksi terakhir</button>
      </div>
    </section>

    <Kartu judul="Inspektur & jenis inspeksi" :ket="`${props.unit.zona} · ${props.unit.dimensi} · SWL ${props.unit.swl} kg/m²`" ikon="hard-hat" warna="amber">
      <div class="grid gap-3 md:grid-cols-3">
        <label class="ip-l">Jenis inspeksi
          <select v-model="form.jenis" class="ip-i"><option v-for="j in pil.inspeksi" :key="j.nilai" :value="j.nilai">{{ j.label }}</option></select>
        </label>
        <label class="ip-l">Nama inspektur kompeten<input v-model="form.inspektur" required maxlength="120" class="ip-i"></label>
        <label class="ip-l">No. sertifikat (SIO / BNSP)<input v-model="form.inspektur_sertifikat" required maxlength="80" placeholder="mis. SIO-SCF-2025-0412" class="ip-i"></label>
      </div>
      <p v-if="props.kajian" class="mt-3 text-[12px] text-stone-500">Kajian teknis terakhir:
        <span class="pr-pil" :class="PUTUSAN[props.kajian.putusan]?.kelas">{{ PUTUSAN[props.kajian.putusan]?.label }} · {{ props.kajian.skor }}/100</span>
        <span v-if="props.kajian.perlu_pe" class="pr-pil pr-ungu ml-1">Wajib disahkan P.E.</span></p>
    </Kartu>

    <Kartu v-for="g in grup" :key="g.nama" :judul="g.nama" :ket="`${g.butir.filter((b: any) => form.periksa[b.kode]).length}/${g.butir.length} dinilai`" ikon="list-checks" warna="biru" rapat>
      <ul class="divide-y divide-stone-100">
        <li v-for="b in g.butir" :key="b.kode" class="px-5 py-3 flex flex-wrap items-center gap-3" :class="form.periksa[b.kode] === 'gagal' && 'ip-gagal'">
          <div class="min-w-0 flex-1 basis-[260px]">
            <p class="text-[11px] font-bold text-stone-400 num">{{ b.kode }} <span v-if="b.kritis" class="ip-kritis">KRITIS</span></p>
            <p class="text-[13px] font-semibold text-cam-ink leading-snug">{{ b.label }}</p>
          </div>
          <div class="ip-pilih" role="radiogroup" :aria-label="b.label">
            <button v-for="p in PILIH" :key="p[0]" type="button" role="radio" :aria-checked="form.periksa[b.kode] === p[0]"
                    :class="[`ip-${p[0]}`, form.periksa[b.kode] === p[0] && 'ip-aktif']" @click="form.periksa[b.kode] = p[0]">{{ p[1] }}</button>
          </div>
        </li>
      </ul>
    </Kartu>

    <Kartu judul="Syarat & tindakan perbaikan" ikon="file-text" warna="ungu">
      <div class="grid gap-3 md:grid-cols-2">
        <label class="ip-l">Syarat khusus penggunaan (membuat tag kuning)
          <textarea v-model="form.syarat_khusus" rows="3" maxlength="1000" placeholder="mis. Wajib full body harness 100% tie-off; maks. 2 orang per bay" class="ip-i" />
        </label>
        <label class="ip-l">Tindakan perbaikan (wajib bila ada yang gagal)
          <textarea v-model="form.tindakan_perbaikan" rows="3" maxlength="2000" class="ip-i" />
        </label>
      </div>
      <p v-if="Object.keys(form.errors).length" class="mt-3 text-[12px] ip-galat">{{ Object.values(form.errors)[0] }}</p>
    </Kartu>

    <div class="ip-putusan" :class="`ip-p-${putusan.w}`">
      <div class="min-w-0">
        <p class="text-[15px] font-black">{{ putusan.judul }}</p>
        <p class="text-[11.5px] opacity-90">{{ putusan.ket }}</p>
        <p class="text-[11px] opacity-80 mt-0.5">{{ lulus }} lulus · {{ gagalKritis.length }} gagal kritis · {{ gagalMinor.length }} gagal minor</p>
      </div>
      <button class="ip-simpan" :disabled="form.processing || terisi < butir.length"><Ikon nama="tag" :ukuran="16" />Simpan & terbitkan tag</button>
    </div>
  </form>
</template>

<style scoped>
.ip-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.ip-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.ip-kritis { margin-left: 6px; font-size: 9.5px; font-weight: 900; letter-spacing: .06em; padding: 2px 6px; border-radius: 6px; background: #FEE2E2; color: #991B1B; }
.ip-pilih { display: inline-flex; gap: 6px; }
.ip-pilih button { min-width: 72px; min-height: 44px; padding: 0 12px; border-radius: 12px; font-size: 12.5px; font-weight: 800;
  border: 1.5px solid #E7E5E4; background: #fff; color: #57534E; transition: all .12s; }
.ip-lulus.ip-aktif { background: #15803D; border-color: #15803D; color: #fff; }
.ip-gagal.ip-aktif, button.ip-gagal.ip-aktif { background: #DC2626; border-color: #DC2626; color: #fff; }
.ip-na.ip-aktif { background: #57534E; border-color: #57534E; color: #fff; }
li.ip-gagal { background: #FEF2F2; }
.ip-putusan { position: sticky; bottom: 12px; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;
  padding: 14px 18px; border-radius: 18px; color: #fff; box-shadow: 0 18px 36px -18px rgba(0,0,0,.55); }
.ip-p-hijau { background: linear-gradient(110deg, #14532D, #16A34A); }
.ip-p-kuning { background: linear-gradient(110deg, #78350F, #D97706); }
.ip-p-merah { background: linear-gradient(110deg, #7F1D1D, #DC2626); }
.ip-p-abu { background: linear-gradient(110deg, #292524, #57534E); }
.ip-simpan { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 12px; background: #fff; color: #1C1917; font-weight: 800; font-size: 13px; }
.ip-simpan:disabled { opacity: .55; }
.ip-galat { color: #B91C1C; }
:root[data-tema="gelap"] .ip-pilih button { background: #20272B; border-color: #2E373C; color: #C9D2D6; }
:root[data-tema="gelap"] li.ip-gagal { background: #2B1A1A; }
:root[data-tema="gelap"] .ip-kritis { background: #4E1D1D; color: #F5A9A9; }
:root[data-tema="gelap"] .ip-galat { color: #F5A9A9; }
</style>
