<script setup lang="ts">
/*
  Rencana pengangkatan & simulasi 4 fase.

  Layar terbagi: isian di kiri, hasilnya di kanan dan ikut bergulir —
  tampak samping crane, kurva beban, rigging, empat fase, dan pratinjau
  kajian 6 pilar. Hitungannya dikerjakan SERVER (rumus yang sama dengan
  yang tersimpan), dipanggil 300 ms sesudah isian terakhir berubah,
  sehingga tidak ada salinan rumus di peramban yang dapat berselisih
  dengan angka resminya.

  Crane dan alat bantu dipilih dari register; yang tidak layak
  digunakan memang tidak muncul di daftar pilihan.
*/
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import Kartu from '../../Components/Energi/Kartu.vue';
import Ikon from '../../Components/Energi/Ikon.vue';
import DiagramCrane from '../../Components/Angkat/DiagramCrane.vue';
import DiagramSling from '../../Components/Angkat/DiagramSling.vue';
import KurvaBeban from '../../Components/Angkat/KurvaBeban.vue';
import { n, json } from '../../energi';
import { simulasi, klas, fase, kajian, kelayakan } from '../../angkat';

const props = propHalaman();
const pil = computed(() => props.pilihan ?? {});
const reg = computed(() => props.register ?? { alat: [], gear: [] });
const sunting = computed(() => !!props.rencana?.id);
const awal = props.rencana ?? {};
const form = useForm<Record<string, any>>({
  judul: awal.judul ?? '', lokasi: awal.lokasi ?? '', area: awal.area ?? '', jadwal_at: awal.jadwal_at ?? '',
  alat_id: awal.alat_id ?? null, gear: [...(awal.gear ?? [])], catatan: awal.catatan ?? '',
  operator_nama: awal.operator_nama ?? '', operator_sio: awal.operator_sio ?? '', rigger_nama: awal.rigger_nama ?? '',
  rigger_sertifikat: awal.rigger_sertifikat ?? '', signalman_nama: awal.signalman_nama ?? '', pengawas_nama: awal.pengawas_nama ?? '',
  ...(awal.masukan ?? {}),
});

const MASUKAN = ['jenis_alat', 'kategori_beban', 'tanah', 'berat_bersih_t', 'berat_rigging_t', 'daf', 'radius_m', 'boom_m', 'tinggi_rintangan_m',
  'tinggi_beban_m', 'tinggi_rigging_m', 'angin_kmh', 'luas_mat_m2', 'kemiringan_pct', 'jarak_sutt_m', 'jumlah_kaki', 'sudut_sling', 'jenis_sling',
  'hitch', 'cog_offset_pct', 'wll_sling_t', 'wll_shackle_t', 'spreader', 'blind_lift', 'di_atas_fasilitas'];
const ORANG = ['operator_nama', 'operator_sio', 'rigger_nama', 'rigger_sertifikat', 'signalman_nama', 'pengawas_nama'];

const hasil = ref<any>(props.awal ?? null);
const pratinjauKajian = ref<any>(null);
const galat = ref('');
let jeda: ReturnType<typeof setTimeout> | null = null;
async function hitung() {
  try {
    const isi: Record<string, any> = { alat_id: form.alat_id, gear: form.gear };
    [...MASUKAN, ...ORANG].forEach(k => (isi[k] = form[k]));
    const j = await json('/pengangkatan/rencana/hitung', isi);
    hasil.value = j.hasil; pratinjauKajian.value = j.kajian; galat.value = '';
  } catch (e: any) { galat.value = e.message; }
}
watch(() => [...MASUKAN, ...ORANG].map(k => form[k]).concat([form.alat_id, form.gear.join(',')]), () => {
  if (jeda) clearTimeout(jeda); jeda = setTimeout(hitung, 300);
});
onMounted(hitung);

const alatTerpilih = computed(() => reg.value.alat.find((a: any) => a.id === form.alat_id) ?? null);
watch(() => form.alat_id, () => {
  const a = alatTerpilih.value;
  if (!a) return;
  form.jenis_alat = a.jenis;
  if (form.boom_m > a.boom_maks_m) form.boom_m = a.boom_maks_m;
  if (!form.operator_nama && a.operator_nama) { form.operator_nama = a.operator_nama; form.operator_sio = a.operator_sio ?? ''; }
});
const katAlat = computed(() => (pil.value.alat ?? []).find((a: any) => a.nilai === form.jenis_alat));
const boomMaks = computed(() => alatTerpilih.value?.boom_maks_m ?? katAlat.value?.boom_m ?? 60);
const tetap = computed(() => ['overhead', 'hoist'].includes(katAlat.value?.kategori));

const h = computed(() => hasil.value);
const rig = computed(() => h.value?.rigging ?? null);
const gearPilihan = computed(() => {
  const g: Record<string, any[]> = {};
  for (const x of reg.value.gear ?? []) (g[x.jenis_label] ??= []).push(x);
  return g;
});
function togel(id: number) {
  const i = form.gear.indexOf(id);
  if (i >= 0) form.gear.splice(i, 1); else form.gear.push(id);
}
const beban = computed(() => (pil.value.beban ?? []).find((b: any) => b.nilai === form.kategori_beban));

function kirim() {
  if (sunting.value) form.put(`/pengangkatan/rencana/${props.rencana.id}`);
  else form.post('/pengangkatan/rencana');
}
const ukurKelas = (ok: boolean, waspada = false) => (!ok ? 'fm-buruk' : waspada ? 'fm-waspada' : 'fm-baik');
</script>

<template>
  <Head :title="sunting ? 'Ubah rencana pengangkatan' : 'Rencana pengangkatan baru'" />
  <form class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)] items-start" @submit.prevent="kirim">
    <div class="space-y-4">
      <Kartu :judul="sunting ? `Ubah ${props.kodeBerikut}` : 'Pekerjaan & jadwal'" :ket="sunting ? 'Menyimpan akan menyimulasikan ulang dan menghitung ulang kajian' : `Kode otomatis ${props.kodeBerikut}`" ikon="clipboard-list" warna="biru">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fm-l md:col-span-2">Pekerjaan pengangkatan<input v-model="form.judul" required maxlength="180" placeholder="mis. Penggantian engine HD785 unit DT-117" class="fm-i"></label>
          <label class="fm-l md:col-span-2">Jenis beban
            <select v-model="form.kategori_beban" class="fm-i"><option v-for="b in pil.beban" :key="b.nilai" :value="b.nilai">{{ b.label }}</option></select>
          </label>
          <label class="fm-l">Lokasi<input v-model="form.lokasi" required maxlength="150" placeholder="mis. Workshop PIT-2 bay 3" class="fm-i"></label>
          <label class="fm-l">Area
            <select v-model="form.area" class="fm-i"><option v-for="a in pil.area" :key="a" :value="a">{{ a }}</option></select>
          </label>
          <label class="fm-l">Jadwal pengangkatan<input v-model="form.jadwal_at" type="datetime-local" class="fm-i"></label>
        </div>
      </Kartu>

      <Kartu judul="Crane & geometri" :ket="reg.alatTolak ? `${reg.alatTolak} crane tidak layak digunakan disembunyikan` : 'Pilih dari register agar SUKET ikut diperiksa'" ikon="construction" warna="amber">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fm-l md:col-span-2">Crane dari register
            <select v-model="form.alat_id" class="fm-i">
              <option :value="null">— Tanpa register (pakai angka katalog) —</option>
              <option v-for="a in reg.alat" :key="a.id" :value="a.id">{{ a.kode }} · {{ a.nama }} · {{ n(a.kapasitas_t, 0) }} t · {{ kelayakan(a.status).pendek }}</option>
            </select>
          </label>
          <label v-if="!form.alat_id" class="fm-l md:col-span-2">Jenis pesawat angkat (katalog)
            <select v-model="form.jenis_alat" class="fm-i"><option v-for="a in pil.alat" :key="a.nilai" :value="a.nilai">{{ a.label }}</option></select>
          </label>
          <label class="fm-l">Radius kerja {{ n(form.radius_m, 1) }} m
            <input v-model.number="form.radius_m" type="range" min="3" :max="Math.max(4, Math.floor(boomMaks * 0.95))" step="0.5" class="w-full accent-blue-600 mt-2">
          </label>
          <label class="fm-l">{{ tetap ? 'Tinggi kait' : 'Panjang boom' }} {{ n(form.boom_m, 1) }} m
            <input v-model.number="form.boom_m" type="range" :min="Math.max(5, form.radius_m + 2)" :max="boomMaks" step="0.5" class="w-full accent-blue-600 mt-2">
          </label>
          <div class="md:col-span-2 grid grid-cols-3 gap-3">
            <label class="fm-l">Tinggi rintangan (m)<input v-model.number="form.tinggi_rintangan_m" type="number" step="0.1" min="0" max="150" class="fm-i num"></label>
            <label class="fm-l">Tinggi beban (m)<input v-model.number="form.tinggi_beban_m" type="number" step="0.1" min="0" max="60" class="fm-i num"></label>
            <label class="fm-l">Tinggi rigging (m)<input v-model.number="form.tinggi_rigging_m" type="number" step="0.1" min="0" max="60" class="fm-i num"></label>
          </div>
        </div>
      </Kartu>

      <Kartu judul="Beban & rigging" ket="Tegangan sling dihitung dari berat bersih × DAF" ikon="link-2" warna="ungu">
        <div class="grid gap-3 md:grid-cols-3">
          <label class="fm-l">Berat bersih (t)<input v-model.number="form.berat_bersih_t" type="number" step="0.05" min="0.1" max="1000" required class="fm-i num"></label>
          <label class="fm-l">Berat rigging (t)<input v-model.number="form.berat_rigging_t" type="number" step="0.05" min="0" max="100" class="fm-i num"></label>
          <label class="fm-l">DAF (kosong = bawaan)<input v-model.number="form.daf" type="number" step="0.01" min="1" max="2" :placeholder="katAlat ? String(katAlat.daf) : ''" class="fm-i num"></label>
          <label class="fm-l">Jumlah kaki
            <select v-model.number="form.jumlah_kaki" class="fm-i"><option v-for="k in [1, 2, 3, 4]" :key="k" :value="k">{{ k }} kaki</option></select>
          </label>
          <label class="fm-l">Jenis sling
            <select v-model="form.jenis_sling" class="fm-i"><option v-for="g in (pil.gear ?? []).filter((x: any) => ['wire_rope', 'rantai', 'webbing'].includes(x.nilai))" :key="g.nilai" :value="g.nilai">{{ g.label }}</option></select>
          </label>
          <label class="fm-l">Hitch
            <select v-model="form.hitch" class="fm-i"><option v-for="x in pil.hitch" :key="x.nilai" :value="x.nilai">{{ x.label }} · ×{{ x.faktor }}</option></select>
          </label>
          <label class="fm-l md:col-span-2">Sudut sling terhadap horizontal {{ n(form.sudut_sling, 0) }}°
            <input v-model.number="form.sudut_sling" type="range" min="20" max="90" step="1" class="w-full accent-violet-600 mt-2">
          </label>
          <label class="fm-l">Geser titik berat {{ n(form.cog_offset_pct, 0) }}%
            <input v-model.number="form.cog_offset_pct" type="range" min="0" max="35" step="1" class="w-full accent-violet-600 mt-2">
          </label>
          <label class="fm-l">WLL sling (t)<input v-model.number="form.wll_sling_t" type="number" step="0.25" min="0.5" class="fm-i num"></label>
          <label class="fm-l">WLL shackle (t)<input v-model.number="form.wll_shackle_t" type="number" step="0.25" min="0.5" class="fm-i num"></label>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
          <label v-for="c in [['spreader', 'Spreader beam'], ['blind_lift', 'Blind lift (operator tak melihat beban)'], ['di_atas_fasilitas', 'Melintas di atas fasilitas beroperasi']]" :key="c[0]" class="fm-cek">
            <input v-model="form[c[0]]" type="checkbox"> {{ c[1] }}
          </label>
        </div>
      </Kartu>

      <Kartu judul="Landasan & lingkungan" ket="Daya dukung tanah tambang, angin, dan saluran bertegangan" ikon="mountain" warna="toska">
        <div class="grid gap-3 md:grid-cols-3">
          <label class="fm-l md:col-span-3">Landasan crane
            <select v-model="form.tanah" class="fm-i"><option v-for="t in pil.tanah" :key="t.nilai" :value="t.nilai">{{ t.label }} · izin {{ t.izin }} kN/m²</option></select>
          </label>
          <label class="fm-l">Luas mat outrigger (m²)<input v-model.number="form.luas_mat_m2" type="number" step="0.25" min="0.5" max="100" class="fm-i num"></label>
          <label class="fm-l">Kemiringan (%)<input v-model.number="form.kemiringan_pct" type="number" step="0.1" min="0" max="30" class="fm-i num"></label>
          <label class="fm-l">Angin (km/jam)<input v-model.number="form.angin_kmh" type="number" step="1" min="0" max="200" class="fm-i num"></label>
          <label class="fm-l md:col-span-3">Jarak ke SUTT / saluran bertegangan {{ n(form.jarak_sutt_m, 0) }} m
            <input v-model.number="form.jarak_sutt_m" type="range" min="0" max="60" step="0.5" class="w-full accent-teal-600 mt-2">
          </label>
        </div>
      </Kartu>

      <Kartu judul="Alat bantu dari register" :ket="reg.gearTolak ? `${reg.gearTolak} alat bantu dikarantina/kedaluwarsa tidak dapat dipilih` : 'Hanya yang layak digunakan yang tampil'" ikon="link-2" warna="hijau">
        <div v-for="(daftar, jenis) in gearPilihan" :key="jenis" class="mb-3 last:mb-0">
          <p class="text-[11px] font-bold text-stone-500 mb-1.5">{{ jenis }}</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="g in daftar" :key="g.id" type="button" class="fm-cek" :class="{ 'fm-cek-aktif': form.gear.includes(g.id) }" @click="togel(g.id)">
              <Ikon :nama="form.gear.includes(g.id) ? 'circle-check' : 'link-2'" :ukuran="14" />{{ g.kode }} · {{ n(g.wll_t, 2) }} t
              <span v-if="g.status === 'segera'" class="pr-pil pr-kuning">segera riksa</span>
            </button>
          </div>
        </div>
        <p v-if="!Object.keys(gearPilihan).length" class="text-[12px] text-stone-400">Belum ada alat bantu layak di register.</p>
      </Kartu>

      <Kartu judul="Personel" ket="Pilar 5 kajian membaca isian ini" ikon="hard-hat" warna="ungu">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="fm-l">Operator crane<input v-model="form.operator_nama" maxlength="120" class="fm-i"></label>
          <label class="fm-l">No. SIO operator<input v-model="form.operator_sio" maxlength="80" class="fm-i"></label>
          <label class="fm-l">Rigger<input v-model="form.rigger_nama" maxlength="120" class="fm-i"></label>
          <label class="fm-l">Sertifikat rigger<input v-model="form.rigger_sertifikat" maxlength="80" class="fm-i"></label>
          <label class="fm-l">Signalman<input v-model="form.signalman_nama" maxlength="120" class="fm-i"></label>
          <label class="fm-l">Pengawas operasional (POP/POM)<input v-model="form.pengawas_nama" maxlength="120" class="fm-i"></label>
          <label class="fm-l md:col-span-2">Catatan<textarea v-model="form.catatan" rows="2" maxlength="2000" class="fm-i" /></label>
        </div>
        <p v-if="Object.keys(form.errors).length" class="mt-3 text-[12px] fm-galat">{{ Object.values(form.errors)[0] }}</p>
        <div class="flex flex-wrap gap-2 mt-4">
          <button class="eq-btn-utama" :disabled="form.processing">{{ sunting ? 'Simpan & simulasikan ulang' : 'Simpan rencana' }}</button>
          <Link :href="sunting ? `/pengangkatan/rencana/${props.rencana.id}` : '/pengangkatan'" class="eq-btn-lain">Batal</Link>
        </div>
      </Kartu>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-4">
      <section v-if="h" class="fm-hasil">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
          <p class="text-[13.5px] font-extrabold text-cam-ink">Simulasi pengangkatan</p>
          <div class="flex flex-wrap gap-1.5">
            <span class="pr-pil" :class="klas(h.klasifikasi).kelas">{{ klas(h.klasifikasi).pendek }}</span>
            <span class="fm-ramal" :style="{ background: simulasi(h.putusan).warna }">{{ simulasi(h.putusan).label }}</span>
          </div>
        </div>
        <p v-if="h.alasan_kritis?.length" class="text-[11.5px] text-stone-500 mb-1">Kritis karena {{ h.alasan_kritis.join(', ') }}.</p>
        <DiagramCrane :h="h" />
        <p v-if="galat" class="text-[12px] fm-galat">{{ galat }}</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
          <div class="fm-angka" :class="ukurKelas(h.cek.crane, h.utilisasi_pct > 75)"><span>Utilisasi crane</span><b class="num">{{ n(h.utilisasi_pct, 1) }}%</b><small>batas {{ n(h.batas_util, 0) }}% · kap {{ n(h.kapasitas_t, 1) }} t</small></div>
          <div class="fm-angka"><span>Beban kotor</span><b class="num">{{ n(h.berat_kotor_t, 2) }}</b><small>t · DAF {{ n(h.daf, 2) }} · kait {{ n(h.hook_t, 2) }} t</small></div>
          <div class="fm-angka" :class="ukurKelas(h.cek.tanah, h.tekanan_tanah > h.izin_tanah * 0.85)"><span>Tekanan outrigger</span><b class="num">{{ n(h.tekanan_tanah, 0) }}</b><small>kN/m² · izin {{ n(h.izin_tanah, 0) }} · mat min {{ n(h.mat_min_m2, 2) }} m²</small></div>
          <div class="fm-angka" :class="ukurKelas(h.cek.ruang)"><span>Ruang bebas</span><b class="num">{{ n(h.tinggi_ujung_m - h.tinggi_perlu_m, 1) }} m</b><small>ujung {{ n(h.tinggi_ujung_m, 1) }} · perlu {{ n(h.tinggi_perlu_m, 1) }}</small></div>
        </div>
      </section>

      <Kartu v-if="h" judul="Kurva beban crane" :ket="`${h.alat.label} · boom ${n(h.boom_m, 1)} m · arahkan kursor untuk membaca kapasitas`" ikon="chart-line" warna="biru">
        <KurvaBeban :kurva="h.kurva" :radius="h.radius_m" :kotor="h.berat_kotor_t" :batas="h.batas_util" :radius-aman="h.radius_aman_m" />
        <div class="grid grid-cols-2 gap-2 mt-3">
          <div class="fm-angka fm-baik"><span>Radius maksimum aman</span><b class="num">{{ h.radius_aman_m ? n(h.radius_aman_m, 1) + ' m' : '—' }}</b><small>untuk beban kotor ini</small></div>
          <div class="fm-angka fm-baik"><span>Berat bersih maksimum</span><b class="num">{{ n(h.bersih_maks_t, 2) }} t</b><small>pada radius {{ n(h.radius_m, 1) }} m</small></div>
        </div>
      </Kartu>

      <Kartu v-if="rig" judul="Rigging" :ket="`Sling ${n(rig.util_sling, 1)}% WLL · shackle ${n(rig.util_shackle, 1)}% · rekomendasi WLL ≥ ${n(rig.wll_rekomendasi_t, 2)} t`" ikon="link-2" :warna="rig.status === 'gagal' ? 'merah' : rig.status === 'waspada' ? 'amber' : 'hijau'">
        <div class="grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-3 items-center">
          <DiagramSling :r="rig" :cog="form.cog_offset_pct" :spreader="form.spreader" />
          <dl class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[12px]">
            <dt class="text-stone-500">Tegangan maks</dt><dd class="font-bold num text-right">{{ n(rig.tegangan_maks_t, 2) }} t</dd>
            <dt class="text-stone-500">Dalam kN</dt><dd class="font-bold num text-right">{{ n(rig.tegangan_maks_kn, 1) }} kN</dd>
            <dt class="text-stone-500">Faktor sudut</dt><dd class="font-bold num text-right">{{ n(rig.lam, 3) }}</dd>
            <dt class="text-stone-500">Tekan horizontal</dt><dd class="font-bold num text-right">{{ n(rig.tekan_horizontal_t, 2) }} t</dd>
            <dt class="text-stone-500">MBL perlu (SF {{ n(rig.sf, 0) }})</dt><dd class="font-bold num text-right">{{ n(rig.mbl_perlu_t, 1) }} t</dd>
          </dl>
        </div>
        <ul v-if="rig.catatan.length" class="mt-3 space-y-1.5 text-[11.5px] fm-saran">
          <li v-for="(c, i) in rig.catatan" :key="i" class="flex gap-2"><Ikon nama="info" :ukuran="14" class="shrink-0 mt-px" />{{ c }}</li>
        </ul>
      </Kartu>

      <Kartu v-if="h" judul="Simulasi 4 fase" :ket="`Skor ${h.skor}/100`" ikon="play" warna="biru" rapat>
        <ol class="divide-y divide-stone-100">
          <li v-for="f in h.fase" :key="f.fase" class="px-5 py-3 flex items-start gap-3">
            <span class="fs-no" :style="{ background: fase(f.status).warna }"><Ikon :nama="f.ikon" :ukuran="15" /></span>
            <div class="min-w-0 flex-1"><p class="text-[12.5px] font-bold text-cam-ink">Fase {{ f.fase }} · {{ f.nama }}</p><p class="text-[11.5px] text-stone-500">{{ f.rinci }}</p></div>
            <span class="pr-pil" :class="fase(f.status).kelas">{{ fase(f.status).pendek }}</span>
          </li>
        </ol>
      </Kartu>

      <Kartu v-if="pratinjauKajian" judul="Pratinjau kajian 6 pilar" :ket="`${kajian(pratinjauKajian.putusan).label} · skor ${n(pratinjauKajian.skor, 1)} — butir lapangan dinilai sesudah disimpan`" ikon="clipboard-check" :warna="pratinjauKajian.putusan === 'tidak_memenuhi' ? 'merah' : 'biru'">
        <ul class="space-y-2">
          <li v-for="p in pratinjauKajian.pilar" :key="p.kode" class="text-[12px]">
            <div class="flex justify-between"><span class="font-bold text-cam-ink">{{ p.kode }} · {{ p.nama }}</span><span class="num font-bold">{{ n(p.skor, 0) }}</span></div>
            <div class="fs-bar"><i :style="{ width: p.skor + '%', background: p.skor >= 90 ? '#15803D' : p.skor >= 60 ? '#D97706' : '#DC2626' }" /></div>
          </li>
        </ul>
        <p v-if="pratinjauKajian.penghenti?.length" class="mt-3 text-[11.5px] fm-galat">Penghenti: {{ pratinjauKajian.penghenti.join('; ') }}</p>
        <p v-if="beban" class="mt-3 text-[11.5px] text-stone-500">Acuan: {{ beban.label }}.</p>
      </Kartu>
    </aside>
  </form>
</template>

<style scoped>
.fm-l { display: block; font-size: 11px; font-weight: 600; color: #78716C; }
.fm-i { display: block; width: 100%; margin-top: 4px; border-radius: 10px; border-color: #E7E5E4; font-size: 12.5px; }
.fm-cek { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; border-radius: 10px; border: 1px solid #E7E5E4; font-size: 12px; font-weight: 600; background: #fff; }
.fm-cek-aktif { border-color: #1D4ED8; background: #EFF6FF; color: #1E3A8A; }
.fm-hasil { border-radius: 20px; padding: 16px 18px; background: linear-gradient(180deg, #EFF6FF 0%, #FFFFFF 60%); border: 1px solid #BFDBFE; }
.fm-ramal { font-size: 11px; font-weight: 800; padding: 5px 10px; border-radius: 999px; color: #fff; white-space: nowrap; }
.fm-angka { border-radius: 14px; padding: 10px 12px; background: #fff; border: 1px solid #E7E5E4; display: grid; gap: 1px; }
.fm-angka span { font-size: 10.5px; font-weight: 700; color: #78716C; }
.fm-angka b { font-size: 19px; font-weight: 900; color: #1C1917; letter-spacing: -.02em; }
.fm-angka small { font-size: 10.5px; color: #A8A29E; }
.fm-baik { border-left: 4px solid #16A34A; } .fm-waspada { border-left: 4px solid #D97706; } .fm-buruk { border-left: 4px solid #DC2626; }
.fm-buruk b { color: #B91C1C; }
.fm-galat { color: #B91C1C; }
.fm-saran { color: #92400E; }
.fs-no { width: 30px; height: 30px; flex: none; border-radius: 10px; display: grid; place-items: center; color: #fff; }
.fs-bar { height: 7px; border-radius: 999px; background: #F0EFED; margin-top: 4px; overflow: hidden; }
.fs-bar i { display: block; height: 100%; border-radius: 999px; }
:root[data-tema="gelap"] .fm-hasil { background: linear-gradient(180deg, #14233A 0%, #1B2124 60%); border-color: #1E3A5F; }
:root[data-tema="gelap"] .fm-angka, :root[data-tema="gelap"] .fm-cek { background: #20272B; border-color: #2E373C; }
:root[data-tema="gelap"] .fm-cek-aktif { background: #14233A; border-color: #3B82F6; color: #BFDBFE; }
:root[data-tema="gelap"] .fm-angka b { color: #E8EDEF; }
:root[data-tema="gelap"] .fm-l { color: #93A1A8; }
:root[data-tema="gelap"] .fm-galat, :root[data-tema="gelap"] .fm-buruk b { color: #F5A9A9; }
:root[data-tema="gelap"] .fm-saran { color: #F6CF7A; }
:root[data-tema="gelap"] .fs-bar { background: #2E373C; }
</style>
