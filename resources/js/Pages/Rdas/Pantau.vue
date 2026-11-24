<script setup lang="ts">
/*
  Formulir Petak Ukur (PU) — dirancang untuk diisi di lapangan dari HP.

  Intensitas sampling 5%: jumlah PU minimum dihitung dari luas petak
  (PU lingkaran 0,1 ha). Persen tumbuh, batang sehat per hektar, dan
  kebutuhan bibit sulam dihitung server setiap angka berubah — angka yang
  tampil sama dengan angka yang tersimpan.
*/
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import { n, json } from '../../energi';
import { tumbuh, warnaTumbuh, tanggal } from '../../rdas';

const props = propHalaman();
const p = computed(() => props.petak ?? {});
const b = computed(() => props.blok ?? {});
const pil = computed(() => props.pilihan ?? {});
const form = useForm({ tanggal: new Date().toISOString().slice(0, 10), periode: props.periode ?? 'p1', jumlah_pu: props.puMinimum ?? 1,
  diamati: 0, sehat: 0, merana: 0, tinggi_cm: null as number | null, diameter_mm: null as number | null, pengamat: props.pengamat ?? '', catatan: '' });

const h = ref<any>(null);
let jeda: ReturnType<typeof setTimeout> | null = null;
async function hitung() {
  try { h.value = await json('/rehab-das/pantau/hitung', { diamati: form.diamati || 0, sehat: form.sehat || 0, merana: form.merana || 0, kerapatan: b.value.kerapatan, luas_petak: p.value.luas_ha, jumlah_pu: form.jumlah_pu || 0 }); } catch { /* pratinjau */ }
}
watch(() => [form.diamati, form.sehat, form.merana, form.jumlah_pu], () => { if (jeda) clearTimeout(jeda); jeda = setTimeout(hitung, 250); });
onMounted(hitung);
const mati = computed(() => Math.max(0, (form.diamati || 0) - (form.sehat || 0) - (form.merana || 0)));
const lebih = computed(() => (form.sehat || 0) + (form.merana || 0) > (form.diamati || 0));
const tanamPerPu = computed(() => Math.round((b.value.kerapatan ?? 625) * 0.1));
function isiCepat() { form.diamati = form.jumlah_pu * tanamPerPu.value; }
function kirim() { form.post(`/rehab-das/petak/${p.value.id}/pantau`); }
</script>

<template>
  <Head :title="`Petak Ukur ${p.kode}`" />
  <form class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] items-start" @submit.prevent="kirim">
    <div class="space-y-4">
      <Kartu :judul="`Petak Ukur · ${b.kode} petak ${p.kode}`" :ket="`${n(p.luas_ha, 2)} ha · ${b.kerapatan} btg/ha · IS 5% → minimal ${props.puMinimum} PU (0,1 ha)`" ikon="scan-line" warna="hijau">
        <div class="grid gap-3 grid-cols-2 md:grid-cols-3">
          <label class="pa-l">Tanggal<input v-model="form.tanggal" type="date" required class="pa-i"></label>
          <label class="pa-l">Periode<select v-model="form.periode" class="pa-i"><option v-for="x in pil.periode" :key="x.nilai" :value="x.nilai">{{ x.label }}</option></select></label>
          <label class="pa-l">Jumlah PU<input v-model.number="form.jumlah_pu" type="number" min="1" required class="pa-i num"></label>
        </div>
        <div class="grid gap-3 grid-cols-2 md:grid-cols-4 mt-4">
          <label class="pa-besar">Diamati<input v-model.number="form.diamati" type="number" min="1" required class="num" inputmode="numeric"></label>
          <label class="pa-besar pa-sehat">Hidup sehat<input v-model.number="form.sehat" type="number" min="0" required class="num" inputmode="numeric"></label>
          <label class="pa-besar pa-merana">Hidup merana<input v-model.number="form.merana" type="number" min="0" required class="num" inputmode="numeric"></label>
          <div class="pa-besar pa-mati">Mati<b class="num">{{ n(mati, 0) }}</b></div>
        </div>
        <button type="button" class="eq-btn-mini mt-2" @click="isiCepat">Isi diamati = {{ form.jumlah_pu }} PU × {{ tanamPerPu }} tanaman</button>
        <p v-if="lebih" class="text-[12px] pa-galat mt-2">Sehat + merana melebihi jumlah diamati.</p>
        <div class="grid gap-3 grid-cols-2 mt-4">
          <label class="pa-l">Tinggi rata-rata (cm)<input v-model.number="form.tinggi_cm" type="number" step="0.1" min="0" class="pa-i num"></label>
          <label class="pa-l">Diameter rata-rata (mm)<input v-model.number="form.diameter_mm" type="number" step="0.1" min="0" class="pa-i num"></label>
          <label class="pa-l">Pengamat<input v-model="form.pengamat" required maxlength="120" class="pa-i"></label>
          <label class="pa-l">Catatan<input v-model="form.catatan" maxlength="2000" class="pa-i"></label>
        </div>
        <p v-if="Object.keys(form.errors).length" class="mt-3 text-[12px] pa-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="flex flex-wrap gap-2 mt-4">
          <button class="eq-btn-utama" :disabled="form.processing || lebih">Simpan Petak Ukur</button>
          <Link :href="`/rehab-das/blok/${b.id}`" class="eq-btn-lain">Batal</Link>
        </div>
      </Kartu>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-4">
      <section v-if="h" class="pa-hasil" :style="{ background: `linear-gradient(135deg, ${warnaTumbuh(h.diamati ? h.persen_tumbuh : null)}, #14532D)` }">
        <p class="text-[12px] font-bold opacity-90">Persen tumbuh</p>
        <p class="pa-angka num">{{ h.diamati ? n(h.persen_tumbuh, 1) : '—' }}<small>%</small></p>
        <p class="text-[13px] font-extrabold">{{ h.diamati ? tumbuh(h.status).label : 'Isi jumlah tanaman diamati' }}</p>
        <div class="grid grid-cols-3 gap-2 mt-4 text-left">
          <div class="pa-kaca"><span>Sehat/ha</span><b class="num">{{ h.diamati ? n(h.sehat_ha, 0) : '—' }}</b><small>{{ !h.diamati ? 'ambang 500' : h.sehat_cukup ? '≥ 500 ✓' : '< 500' }}</small></div>
          <div class="pa-kaca"><span>Hidup/ha</span><b class="num">{{ h.diamati ? n(h.hidup_ha, 0) : '—' }}</b><small>dari {{ b.kerapatan }}</small></div>
          <div class="pa-kaca"><span>Bibit sulam</span><b class="num">{{ h.diamati ? n(h.kebutuhan_sulam, 0) : '—' }}</b><small>batang</small></div>
        </div>
        <p class="text-[11.5px] mt-3 opacity-90">Intensitas sampling {{ n(h.intensitas_pct, 1) }}% {{ h.intensitas_cukup ? '— memenuhi IS 5%' : `— kurang; minimal ${h.pu_minimum} PU` }}</p>
      </section>
      <Kartu judul="Riwayat petak ini" ikon="history" warna="biru" rapat>
        <ul class="divide-y divide-stone-100">
          <li v-for="r in props.riwayat" :key="r.kode" class="px-5 py-2.5 flex items-center gap-3 text-[12px]">
            <span class="flex-1">{{ tanggal(r.tanggal) }} · {{ pil.periode?.find((x: any) => x.nilai === r.periode)?.label }}</span>
            <b class="num" :style="{ color: warnaTumbuh(r.persen_tumbuh) }">{{ n(r.persen_tumbuh, 1) }}%</b>
            <span class="pr-pil" :class="tumbuh(r.status).kelas">{{ tumbuh(r.status).pendek }}</span>
          </li>
          <li v-if="!(props.riwayat ?? []).length" class="px-5 py-5 text-center text-stone-400 text-[12px]">Belum pernah dipantau.</li>
        </ul>
      </Kartu>
      <p class="text-[11.5px] text-stone-500 px-1"><Ikon nama="info" :ukuran="13" class="inline" /> Berhasil bila ≥ 80%, peringatan 75–80%, di bawah 75% wajib disulam sebelum diajukan penilaian.</p>
    </aside>
  </form>
</template>

<style scoped>
.pa-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.pa-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.pa-besar { display: grid; gap: 4px; padding: 10px 12px; border-radius: 14px; background: #F8FAF5; border: 1px solid #E7E5E4; font-size: 11px; font-weight: 700; color: #57534E; }
.pa-besar input { border: 0; background: transparent; padding: 0; font-size: 26px; font-weight: 900; color: #1C1917; width: 100%; }
.pa-besar input:focus { outline: none; box-shadow: none; }
.pa-besar b { font-size: 26px; font-weight: 900; color: #991B1B; }
.pa-sehat { border-left: 5px solid #15803D; } .pa-merana { border-left: 5px solid #D97706; } .pa-mati { border-left: 5px solid #DC2626; }
.pa-galat { color: #B91C1C; }
.pa-hasil { border-radius: 20px; padding: 20px; color: #fff; text-align: center; }
.pa-angka { font-size: 46px; font-weight: 900; line-height: 1.05; letter-spacing: -.02em; }
.pa-angka small { font-size: 18px; margin-left: 2px; }
.pa-kaca { background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .28); border-radius: 12px; padding: 8px 10px; display: grid; }
.pa-kaca span { font-size: 10.5px; font-weight: 700; opacity: .9; } .pa-kaca b { font-size: 18px; font-weight: 900; } .pa-kaca small { font-size: 10px; opacity: .85; }
:root[data-tema="gelap"] .pa-l { color: #93A1A8; }
:root[data-tema="gelap"] .pa-besar { background: #20272B; border-color: #2E373C; color: #B4BEC3; }
:root[data-tema="gelap"] .pa-besar input { color: #E8EDEF; }
:root[data-tema="gelap"] .pa-besar b, :root[data-tema="gelap"] .pa-galat { color: #F5A9A9; }
</style>
