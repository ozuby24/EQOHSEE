<script setup lang="ts">
/*
  Kalkulator spesifikasi & pendaftaran perancah.

  Layar terbagi: isian di kiri, hasilnya di kanan dan ikut bergulir —
  sketsa tampak depan, faktor keamanan, rasio H/B, tekanan tumpuan,
  lima kriteria kelayakan, dan BoM. Hitungannya dikerjakan SERVER
  (rumus yang sama dengan yang tersimpan), dipanggil 300 ms sesudah
  ketikan terakhir — tidak ada salinan rumus di peramban yang dapat
  berselisih dengan angka resminya.
*/
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import Sketsa from '../../Components/Perancah/Sketsa.vue';
import { n, json } from '../../energi';
import { PUTUSAN } from '../../perancah';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const sunting = computed(() => !!props.unit?.id);
const form = useForm<Record<string, any>>({ ...props.unit });

const hasil = ref<any>(null);
const galat = ref('');
let jeda: ReturnType<typeof setTimeout> | null = null;
const SPEK = ['jenis', 'kelas_beban', 'panjang_m', 'lebar_m', 'tinggi_m', 'jumlah_dek', 'jenis_tanah', 'jumlah_ikatan', 'angin_kmh', 'ada_guardrail', 'ada_toeboard', 'ada_outrigger'];

async function hitung() {
  try {
    const isi: Record<string, any> = {};
    SPEK.forEach(k => (isi[k] = form[k]));
    hasil.value = await json('/perancah/hitung', isi);
    galat.value = '';
  } catch (e: any) { galat.value = e.message; }
}
watch(() => SPEK.map(k => form[k]), () => { if (jeda) clearTimeout(jeda); jeda = setTimeout(hitung, 300); });
onMounted(hitung);

const h = computed(() => hasil.value?.hitung ?? null);
const k = computed(() => hasil.value?.kajian ?? null);
const swl = computed(() => (pil.value.kelas ?? []).find((x: any) => x.nilai === form.kelas_beban)?.swl ?? 240);
const izin = computed(() => (pil.value.tanah ?? []).find((x: any) => x.nilai === form.jenis_tanah)?.izin ?? 250);

const ramal = computed(() => {
  if (!k.value) return null;
  if (k.value.putusan === 'tidak_layak') return { kelas: 'fm-merah', judul: 'Akan terbit TAG MERAH', ket: 'Tidak layak teknis — perbaiki rancangan sebelum didirikan.' };
  if (k.value.putusan === 'bersyarat') return { kelas: 'fm-kuning', judul: 'Layak bersyarat → TAG KUNING', ket: 'Merah sampai inspeksi awal; kuning bila inspeksi lulus, dengan syarat tercetak.' };
  return { kelas: 'fm-hijau', judul: 'Layak → TAG HIJAU setelah inspeksi', ket: 'Merah sampai inspeksi awal; hijau bila 16 butir lulus.' };
});

const gps = ref('');
function ambilGps() {
  if (!navigator.geolocation) { gps.value = 'Perangkat ini tidak menyediakan GPS.'; return; }
  gps.value = 'Mengambil lokasi…';
  navigator.geolocation.getCurrentPosition(
    p => { form.lat = +p.coords.latitude.toFixed(7); form.lng = +p.coords.longitude.toFixed(7); gps.value = `Akurasi ±${Math.round(p.coords.accuracy)} m`; },
    e => { gps.value = e.code === 1 ? 'Izin lokasi ditolak.' : 'Lokasi tidak dapat diambil.'; },
    { enableHighAccuracy: true, timeout: 12000 });
}

function kirim() {
  if (sunting.value) form.put(`/perancah/${props.unit.id}`);
  else form.post('/perancah');
}
const statusKelas = (s: string) => (s === 'lulus' ? 'pr-hijau' : s === 'peringatan' ? 'pr-kuning' : 'pr-merah');
const statusLabel = (s: string) => (s === 'lulus' ? 'Lulus' : s === 'peringatan' ? 'Bersyarat' : 'Gagal');
</script>

<template>
  <Head :title="sunting ? 'Ubah perancah' : 'Daftarkan perancah'" />
  <form class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] items-start" @submit.prevent="kirim">
    <div class="space-y-4">
      <Kartu :judul="sunting ? `Ubah ${props.kodeBerikut}` : 'Identitas & lokasi'" :ket="sunting ? 'Perubahan spesifikasi mengkaji ulang dan menurunkan tag ke merah sampai diinspeksi' : `Kode otomatis ${props.kodeBerikut}`" ikon="map-pin" warna="amber">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fm-l md:col-span-2">Pekerjaan / judul<input v-model="form.judul" required maxlength="180" placeholder="mis. Penggantian liner chute transfer TP-3" class="fm-i"></label>
          <label class="fm-l">Zona / area
            <input v-model="form.zona" list="fm-zona" required maxlength="120" class="fm-i">
            <datalist id="fm-zona"><option v-for="z in pil.zona" :key="z" :value="z" /></datalist>
          </label>
          <label class="fm-l">Peralatan / tag aset<input v-model="form.peralatan_ref" maxlength="80" placeholder="mis. CV-03 / TP-3" class="fm-i"></label>
          <label class="fm-l">Nomor izin kerja (PTW)<input v-model="form.nomor_izin" maxlength="60" class="fm-i"></label>
          <label class="fm-l">Elevasi dasar (m)<input v-model.number="form.elevasi_m" type="number" step="0.1" class="fm-i"></label>
          <label class="fm-l">Lintang (lat)<input v-model.number="form.lat" type="number" step="0.0000001" min="-90" max="90" class="fm-i num"></label>
          <label class="fm-l">Bujur (lng)<input v-model.number="form.lng" type="number" step="0.0000001" min="-180" max="180" class="fm-i num"></label>
          <div class="md:col-span-2 flex items-center gap-3">
            <button type="button" class="eq-btn-lain inline-flex items-center gap-2" @click="ambilGps"><Ikon nama="map-pin" :ukuran="15" />Ambil GPS perangkat</button>
            <span class="text-[11.5px] text-stone-500">{{ gps || 'Presisi 7 desimal (±1 cm). Ambil di kaki perancah.' }}</span>
          </div>
        </div>
      </Kartu>

      <Kartu judul="Sistem, dimensi & beban" ket="Menentukan BoM, beban kaki, dan faktor keamanan" ikon="ruler" warna="biru">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fm-l">Sistem perancah
            <select v-model="form.jenis" class="fm-i"><option v-for="j in pil.jenis" :key="j.nilai" :value="j.nilai">{{ j.label }}</option></select>
          </label>
          <label class="fm-l">Kelas beban (SWL)
            <select v-model="form.kelas_beban" class="fm-i"><option v-for="j in pil.kelas" :key="j.nilai" :value="j.nilai">{{ j.label }} · {{ j.swl }} kg/m²</option></select>
          </label>
          <div class="md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3">
            <label class="fm-l">Panjang P (m)<input v-model.number="form.panjang_m" type="number" step="0.1" min="0.6" max="200" required class="fm-i num"></label>
            <label class="fm-l">Lebar L (m)<input v-model.number="form.lebar_m" type="number" step="0.05" min="0.6" max="30" required class="fm-i num"></label>
            <label class="fm-l">Tinggi T (m)<input v-model.number="form.tinggi_m" type="number" step="0.1" min="1" max="80" required class="fm-i num"></label>
            <label class="fm-l">Dek kerja<input v-model.number="form.jumlah_dek" type="number" min="1" max="20" required class="fm-i num"></label>
          </div>
          <label class="fm-l md:col-span-2">Tinggi {{ n(form.tinggi_m, 1) }} m
            <input v-model.number="form.tinggi_m" type="range" min="2" max="40" step="0.5" class="w-full accent-amber-600 mt-2">
          </label>
        </div>
      </Kartu>

      <Kartu judul="Tumpuan, ikatan & lingkungan" ket="Daya dukung, stabilitas, dan batas angin" ikon="mountain" warna="toska">
        <div class="grid gap-3 md:grid-cols-3">
          <label class="fm-l">Permukaan tumpuan
            <select v-model="form.jenis_tanah" class="fm-i"><option v-for="j in pil.tanah" :key="j.nilai" :value="j.nilai">{{ j.label }} · {{ j.izin }} kN/m²</option></select>
          </label>
          <label class="fm-l">Titik ikatan terpasang<input v-model.number="form.jumlah_ikatan" type="number" min="0" max="500" class="fm-i num"></label>
          <label class="fm-l">Angin di lokasi (km/jam)<input v-model.number="form.angin_kmh" type="number" min="0" max="200" step="1" class="fm-i num"></label>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
          <label v-for="c in [['ada_guardrail', 'Top & mid rail'], ['ada_toeboard', 'Toe board 150 mm'], ['ada_outrigger', 'Outrigger']]" :key="c[0]" class="fm-cek">
            <input v-model="form[c[0]]" type="checkbox"> {{ c[1] }}
          </label>
        </div>
      </Kartu>

      <Kartu judul="Pelaksana & jadwal" ikon="hard-hat" warna="ungu">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fm-l">Kontraktor<input v-model="form.kontraktor" maxlength="150" class="fm-i"></label>
          <label class="fm-l">Scaffolder (pemasang)<input v-model="form.scaffolder_nama" maxlength="120" class="fm-i"></label>
          <label class="fm-l">No. SIO / sertifikat scaffolder<input v-model="form.scaffolder_sertifikat" maxlength="80" class="fm-i"></label>
          <div class="grid grid-cols-2 gap-3">
            <label class="fm-l">Tanggal pasang<input v-model="form.tanggal_pasang" type="date" class="fm-i"></label>
            <label class="fm-l">Rencana bongkar<input v-model="form.rencana_bongkar" type="date" class="fm-i"></label>
          </div>
          <label class="fm-l md:col-span-2">Catatan<textarea v-model="form.catatan" rows="2" maxlength="2000" class="fm-i" /></label>
        </div>
        <p v-if="Object.keys(form.errors).length" class="mt-3 text-[12px] fm-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="flex flex-wrap gap-2 mt-4">
          <button class="eq-btn-utama" :disabled="form.processing">{{ sunting ? 'Simpan & kaji ulang' : 'Daftarkan perancah' }}</button>
          <Link :href="sunting ? `/perancah/${props.unit.id}` : '/perancah'" class="eq-btn-lain">Batal</Link>
        </div>
      </Kartu>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-4">
      <section class="fm-hasil">
        <div class="flex items-center justify-between gap-3 mb-2">
          <p class="text-[13.5px] font-extrabold text-cam-ink">Hasil kalkulator</p>
          <span v-if="ramal" class="fm-ramal" :class="ramal.kelas">{{ ramal.judul }}</span>
        </div>
        <Sketsa v-if="h" :panjang="form.panjang_m" :tinggi="form.tinggi_m" :bay="h.bay" :lift="h.lift" :dek="form.jumlah_dek"
                :ikatan="form.jumlah_ikatan" :guardrail="form.ada_guardrail" :toeboard="form.ada_toeboard" :outrigger="form.ada_outrigger"
                :warna="k?.putusan === 'tidak_layak' ? '#DC2626' : k?.putusan === 'bersyarat' ? '#D97706' : '#15803D'" />
        <p v-if="galat" class="text-[12px] fm-galat">{{ galat }}</p>
        <p v-if="ramal" class="text-[11.5px] text-stone-500 mt-1">{{ ramal.ket }}</p>

        <div v-if="h" class="grid grid-cols-2 gap-2 mt-3">
          <div class="fm-angka" :class="h.faktor_keamanan < 4 ? 'fm-buruk' : 'fm-baik'"><span>Faktor keamanan</span><b class="num">{{ n(h.faktor_keamanan, 2) }}×</b><small>min 4,0 · kapasitas {{ h.kapasitas_kn }} kN</small></div>
          <div class="fm-angka" :class="h.rasio_hb > h.hb_maks && form.jumlah_ikatan < h.ikatan_minimum ? 'fm-buruk' : 'fm-baik'"><span>Rasio H/B</span><b class="num">{{ n(h.rasio_hb, 2) }} : 1</b><small>batas bebas {{ h.hb_maks }} : 1 · ikatan min {{ h.ikatan_minimum }}</small></div>
          <div class="fm-angka" :class="h.tekanan_tanah_kn_m2 > izin ? 'fm-buruk' : h.tekanan_tanah_kn_m2 > izin * 0.85 ? 'fm-waspada' : 'fm-baik'"><span>Tekanan tumpuan</span><b class="num">{{ n(h.tekanan_tanah_kn_m2, 1) }}</b><small>kN/m² · izin {{ izin }}</small></div>
          <div class="fm-angka"><span>Beban per kaki</span><b class="num">{{ n(h.beban_kaki_kn, 2) }}</b><small>kN · mati {{ n(h.beban_mati_kg, 0) }} kg · hidup {{ n(h.beban_hidup_kg, 0) }} kg</small></div>
        </div>
      </section>

      <Kartu v-if="k" judul="Kajian kelayakan teknis" :ket="`Skor ${k.skor}/100 · ${PUTUSAN[k.putusan]?.label}`" ikon="clipboard-check" :warna="k.putusan === 'layak' ? 'hijau' : k.putusan === 'bersyarat' ? 'amber' : 'merah'">
        <ul class="space-y-2">
          <li v-for="c in k.kriteria" :key="c.kode" class="flex items-start gap-3 text-[12px]">
            <span class="pr-pil shrink-0" :class="statusKelas(c.status)">{{ statusLabel(c.status) }}</span>
            <div class="min-w-0"><p class="font-bold text-cam-ink">{{ c.nama }}</p><p class="text-stone-500">{{ c.aktual }} · batas {{ c.batas }}</p></div>
          </li>
        </ul>
        <ul v-if="k.rekomendasi.length" class="mt-3 space-y-1.5 text-[11.5px] fm-saran">
          <li v-for="(s, i) in k.rekomendasi" :key="i" class="flex gap-2"><Ikon nama="info" :ukuran="14" class="shrink-0 mt-px" />{{ s }}</li>
        </ul>
      </Kartu>

      <Kartu v-if="h" judul="Bill of materials" :ket="`${h.bay} bay × ${h.lift} lift · ${n(h.panjang_pipa_m, 0)} m pipa · dek ${n(h.luas_dek_m2, 1)} m² @ ${swl} kg/m²`" ikon="boxes" warna="biru" rapat>
        <table class="w-full text-[12px]">
          <tbody class="divide-y divide-stone-100">
            <tr v-for="b in h.bom" :key="b.butir"><td class="px-5 py-2">{{ b.butir }}</td><td class="px-5 py-2 text-right font-bold num whitespace-nowrap">{{ n(b.jumlah, 0) }} <span class="text-stone-400 font-semibold">{{ b.satuan }}</span></td></tr>
          </tbody>
        </table>
      </Kartu>
    </aside>
  </form>
</template>

<style scoped>
.fm-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.fm-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.fm-cek { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12px; font-weight: 600; background: #fff; }
.fm-hasil { border-radius: 20px; padding: 16px 18px; background: linear-gradient(180deg, #FFFBEB 0%, #FFFFFF 60%); border: 1px solid #FDE68A; }
.fm-ramal { font-size: 11px; font-weight: 800; padding: 5px 10px; border-radius: 999px; color: #fff; white-space: nowrap; }
.fm-hijau { background: #15803D; } .fm-kuning { background: #D97706; } .fm-merah { background: #DC2626; }
.fm-angka { border-radius: 14px; padding: 10px 12px; background: #fff; border: 1px solid #E7E5E4; display: grid; gap: 1px; }
.fm-angka span { font-size: 10.5px; font-weight: 700; color: #78716C; }
.fm-angka b { font-size: 20px; font-weight: 900; color: #1C1917; letter-spacing: -.02em; }
.fm-angka small { font-size: 10.5px; color: #A8A29E; }
.fm-baik { border-left: 4px solid #16A34A; } .fm-waspada { border-left: 4px solid #D97706; } .fm-buruk { border-left: 4px solid #DC2626; }
.fm-buruk b { color: #B91C1C; }
.fm-galat { color: #B91C1C; }
.fm-saran { color: #92400E; }
:root[data-tema="gelap"] .fm-hasil { background: linear-gradient(180deg, #2A2414 0%, #1B2124 60%); border-color: #4A3B14; }
:root[data-tema="gelap"] .fm-angka, :root[data-tema="gelap"] .fm-cek { background: #20272B; border-color: #2E373C; }
:root[data-tema="gelap"] .fm-angka b { color: #E8EDEF; }
:root[data-tema="gelap"] .fm-l { color: #93A1A8; }
:root[data-tema="gelap"] .fm-galat, :root[data-tema="gelap"] .fm-buruk b { color: #F5A9A9; }
:root[data-tema="gelap"] .fm-saran { color: #F6CF7A; }
</style>
