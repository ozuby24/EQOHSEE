/* Mencetak seluruh tabel proyeksi dalam Markdown.
   Jalankan: node docs/monetisasi/proyeksi/tabel.cjs > /tmp/tabel.md
   Angka di Model-Pendapatan-dan-Proyeksi.md berasal dari keluaran ini. */
const M = require('./model.cjs');
const rp = (x) => 'Rp' + Math.round(x).toLocaleString('id-ID');
const jt = (x) => { const v = x/1e6; return (v<0?'−':'') + 'Rp' + Math.abs(v).toLocaleString('id-ID',{minimumFractionDigits:1,maximumFractionDigits:1}) + ' jt'; };
const pct = (x) => (x*100).toLocaleString('id-ID',{maximumFractionDigits:0}) + '%';
const n0 = (x) => Math.round(x).toLocaleString('id-ID');
const out = [];
const K = ['konservatif','moderat','optimis'];
const R = Object.fromEntries(K.map(k=>[k,M.proyeksi(k)]));

out.push('### Ringkasan tiga skenario\n');
out.push('| Indikator | Konservatif | Moderat | Optimis |\n|---|---:|---:|---:|');
const row = (lbl, f) => out.push(`| ${lbl} | ${K.map(k=>f(R[k])).join(' | ')} |`);
row('Pelanggan berbayar bulan ke-12', r=>n0(r.baris[11].S+r.baris[11].G+r.baris[11].D));
row('Pelanggan berbayar bulan ke-36', r=>n0(r.baris[35].S+r.baris[35].G+r.baris[35].D));
row('Kontrak VVIP s.d. bulan ke-36', r=>n0(r.baris[35].V));
row('MRR bulan ke-12', r=>jt(r.baris[11].mrr));
row('MRR bulan ke-36', r=>jt(r.baris[35].mrr));
row('ARR bulan ke-36 (MRR × 12)', r=>jt(r.baris[35].mrr*12));
[0,1,2].forEach(y=>{ row(`Pendapatan tahun ${y+1}`, r=>jt(r.tahunan[y].pendapatan)); });
[0,1,2].forEach(y=>{ row(`Laba bersih tahun ${y+1}`, r=>jt(r.tahunan[y].laba)); });
row('Margin bersih tahun 3', r=>pct(r.tahunan[2].laba/r.tahunan[2].pendapatan));
row('Margin kotor tahun 3', r=>pct(r.tahunan[2].marginKotor/r.tahunan[2].pendapatan));
row('Impas bulanan (pendapatan berulang menutup biaya)', r=>r.impas?`bulan ke-${r.impas}`:'> 36 bulan');
row('Balik modal kumulatif', r=>r.impasKumulatif?`bulan ke-${r.impasKumulatif}`:'> 36 bulan');
row('Kebutuhan modal kerja (titik terendah kumulatif)', r=>jt(r.titikTerendah));
row('Laba kumulatif 36 bulan', r=>jt(r.baris[35].kumulatif));

const mo = R.moderat; const y3 = mo.baris.slice(24,36);
const sum = k => y3.reduce((a,b)=>a+b[k],0);
const tot = sum('pendapatan');
out.push('\n### Komposisi pendapatan tahun ke-3 — skenario moderat\n');
out.push('| Aliran | Nilai tahun 3 | Porsi |\n|---|---:|---:|');
[['langganan','A. Langganan membership'],['addon','B. Add-on (modul, slot IUJP, paket, pengguna)'],['kreditAi','C. Top-up kredit AI'],['vvip','D. VVIP / dedicated (lisensi + layanan tahunan)'],['digital','E1. Produk digital'],['kursus','E2. Kursus berbayar Learning Center'],['iklan','F. Iklan & sponsor'],['layanan','G. Layanan onboarding']].forEach(([k,l])=>out.push(`| ${l} | ${jt(sum(k))} | ${pct(sum(k)/tot)} |`));
out.push(`| **Total** | **${jt(tot)}** | 100% |`);

out.push('\n### Struktur biaya tahun ke-3 — skenario moderat\n');
out.push('| Pos biaya | Nilai tahun 3 | % pendapatan |\n|---|---:|---:|');
[['biayaAi','Biaya AI (kredit terpakai + top-up)'],['biayaGerbang','Biaya payment gateway'],['infra','Server, basis data, penyimpanan, surel'],['konten','Bagi hasil konten & pengajar'],['pemasaran','Pemasaran + komisi mitra + komisi sponsor'],['tim','Tim inti (developer, CS/sales, admin)'],['umum','Umum & administrasi (akuntan, legal, perangkat)'],['pajak','Pajak penghasilan (asumsi)']].forEach(([k,l])=>out.push(`| ${l} | ${jt(sum(k))} | ${pct(sum(k)/tot)} |`));
out.push(`| **Total biaya** | **${jt(sum('biaya'))}** | ${pct(sum('biaya')/tot)} |`);
out.push(`| **Laba bersih** | **${jt(sum('laba'))}** | ${pct(sum('laba')/tot)} |`);

const j = M.jembatan();
out.push('\n### Jembatan kenaikan: dari simulasi dokumen awal ke model yang dioptimalkan\n');
out.push('Basis tetap: 100 Silver, 100 Gold, 20 Diamond (angka pelanggan dari simulasi §12 dokumen rangkuman), harga final.\n');
out.push('| Kode | Tuas | Tambahan omzet / bulan |\n|---|---|---:|');
out.push(`| — | Simulasi awal dihitung ulang dengan harga final (langganan ${jt(100*149000+100*399000+20*799000)} + produk digital Rp5,0 jt + sponsor Rp3,0 jt) | ${jt(j.dasar)} |`);
j.tuas.forEach(t=>out.push(`| ${t.kode} | ${t.nama} | +${jt(t.nilai)} |`));
out.push(`| | **Total setelah optimasi** | **${jt(j.total)}** (+${pct(j.total/j.dasar-1)}) |`);

out.push('\n### Ekonomi per pelanggan — skenario moderat\n');
out.push('| Paket | ARPU efektif / bln | Biaya langsung / bln | Margin kontribusi / bln | % | Umur rata-rata | LTV | Batas CAC (LTV ÷ 3) |\n|---|---:|---:|---:|---:|---:|---:|---:|');
M.unitEkonomi().forEach(u=>out.push(`| ${u.paket[0].toUpperCase()+u.paket.slice(1)} | ${rp(u.arpu)} | ${rp(u.biaya)} | ${rp(u.margin)} | ${pct(u.persenMargin)} | ${n0(u.umur)} bln | ${jt(u.ltv)} | ${jt(u.cacMaks)} |`));

out.push('\n### Lampiran — proyeksi bulanan skenario moderat\n');
out.push('| Bln | Pendaftar baru | Silver | Gold | Diamond | VVIP | MRR | Pendapatan | Biaya | Laba | Kumulatif |\n|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|');
mo.baris.forEach(b=>out.push(`| ${b.m} | ${n0(b.daftar)} | ${n0(b.S)} | ${n0(b.G)} | ${n0(b.D)} | ${b.V} | ${jt(b.mrr)} | ${jt(b.pendapatan)} | ${jt(b.biaya)} | ${jt(b.laba)} | ${jt(b.kumulatif)} |`));
process.stdout.write(out.join('\n') + '\n');
