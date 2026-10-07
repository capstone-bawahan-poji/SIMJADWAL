<script setup lang="ts">
import { computed, ref } from "vue";
import { invoke } from "@tauri-apps/api/core";

// Bentuk data harus sama dengan struct Rust di src-tauri/src/lib.rs dan apps/ga-engine.
interface SlotWaktu {
  id_slot: number;
  hari_index: number;
  nama_hari: string;
  sesi_ke: number;
  mulai: string;
  selesai: string;
  tipe_sks: number;
}

interface JadwalEntry {
  id_kelas: string;
  kode_mk: string;
  nama_mk: string;
  kelas: string;
  sks: number;
  prodi: string;
  fakultas: string | null;
  dosen: string[];
  waktu: SlotWaktu;
}

interface LaporanData {
  jumlah_kelas: number;
  jumlah_mata_kuliah: number;
  jumlah_dosen: number;
  jumlah_ruang: number;
  jumlah_slot: number;
  kelas_per_prodi: Record<string, number>;
  kelas_per_sks: Record<string, number>;
  kelas_per_fakultas: Record<string, number>;
  peringatan: string[];
}

interface HasilUji {
  laporan: LaporanData;
  genes: number[];
  kelas_per_slot: number[];
  jumlah_ruang: number;
  jadwal: JadwalEntry[];
}

const HARI = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat"];

const seed = ref(12345);
const hasil = ref<HasilUji | null>(null);
const galat = ref("");
const prodi = ref("Semua");

const daftarProdi = computed(() =>
  hasil.value ? ["Semua", ...Object.keys(hasil.value.laporan.kelas_per_prodi)] : ["Semua"],
);

const jadwalTampil = computed(() => {
  const semua = hasil.value?.jadwal ?? [];
  return prodi.value === "Semua" ? semua : semua.filter((j) => j.prodi === prodi.value);
});

function jumlahDi(hari: number, sesi: number): number {
  return hasil.value?.kelas_per_slot[hari * 4 + sesi - 1] ?? 0;
}

async function generate() {
  galat.value = "";
  try {
    hasil.value = await invoke<HasilUji>("buat_kromosom_acak", { seed: seed.value });
  } catch (e) {
    galat.value = String(e);
  }
}
</script>

<template>
  <main class="container">
    <h1>Engine GA: uji kromosom</h1>

    <form class="row" @submit.prevent="generate">
      <label>
        Seed
        <input v-model.number="seed" type="number" min="0" />
      </label>
      <button type="submit">Buat kromosom acak</button>
    </form>

    <p v-if="galat" class="galat">{{ galat }}</p>

    <template v-if="hasil">
      <p class="ringkas">
        {{ hasil.laporan.jumlah_kelas }} kelas ({{ hasil.laporan.jumlah_mata_kuliah }} mata kuliah),
        {{ hasil.laporan.jumlah_dosen }} dosen, {{ hasil.laporan.jumlah_ruang }} ruangan,
        {{ hasil.laporan.jumlah_slot }} slot. Panjang kromosom {{ hasil.genes.length }} gen.
      </p>

      <details>
        <summary>Peringatan data ({{ hasil.laporan.peringatan.length }})</summary>
        <ul>
          <li v-for="p in hasil.laporan.peringatan" :key="p">{{ p }}</li>
        </ul>
      </details>

      <h2>Jumlah kelas per slot</h2>
      <p class="catatan">Merah jika melebihi {{ hasil.jumlah_ruang }} ruangan.</p>
      <table class="grid">
        <thead>
          <tr><th></th><th v-for="s in 4" :key="s">Sesi {{ s }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="(h, i) in HARI" :key="h">
            <th>{{ h }}</th>
            <td v-for="s in 4" :key="s" :class="{ lebih: jumlahDi(i, s) > hasil.jumlah_ruang }">
              {{ jumlahDi(i, s) }}
            </td>
          </tr>
        </tbody>
      </table>

      <h2>Hasil decode</h2>
      <label class="filter">
        Prodi
        <select v-model="prodi">
          <option v-for="p in daftarProdi" :key="p">{{ p }}</option>
        </select>
      </label>
      <table>
        <thead>
          <tr>
            <th>Id kelas</th><th>Mata kuliah</th><th>Kelas</th><th>SKS</th>
            <th>Dosen</th><th>Hari</th><th>Sesi</th><th>Jam</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="j in jadwalTampil" :key="j.id_kelas">
            <td>{{ j.id_kelas }}</td>
            <td>{{ j.nama_mk }}</td>
            <td>{{ j.kelas }}</td>
            <td>{{ j.sks }}</td>
            <td>{{ j.dosen.join(", ") }}</td>
            <td>{{ j.waktu.nama_hari }}</td>
            <td>{{ j.waktu.sesi_ke }}</td>
            <td>{{ j.waktu.mulai }}-{{ j.waktu.selesai }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </main>
</template>

<style>
:root {
  font-family: system-ui, sans-serif;
  color: #1f1f1f;
  background: #f6f6f6;
}
.container { max-width: 1100px; margin: 0 auto; padding: 24px; }
h2 { font-size: 16px; margin: 24px 0 8px; }
.row { display: flex; gap: 12px; align-items: end; margin-bottom: 16px; }
label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
.filter { flex-direction: row; align-items: center; gap: 8px; margin-bottom: 8px; }
input, select, button { padding: 8px 12px; font-size: 14px; border-radius: 6px; border: 1px solid #bbb; }
button { cursor: pointer; background: #1f1f1f; color: white; border-color: #1f1f1f; }
.ringkas, .catatan { font-size: 14px; }
.catatan { color: #666; margin: 0 0 8px; }
.galat { color: #b00020; }
details { font-size: 14px; margin-bottom: 8px; }
table { width: 100%; border-collapse: collapse; background: white; }
th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #e5e5e5; font-size: 13px; }
.grid { width: auto; }
.grid td { text-align: center; min-width: 64px; }
.lebih { background: #fde2e2; color: #8a1c1c; font-weight: 600; }
@media (prefers-color-scheme: dark) {
  :root { color: #f0f0f0; background: #1c1c1c; }
  table { background: #262626; }
  th, td { border-color: #3a3a3a; }
  input, select { background: #262626; color: #f0f0f0; border-color: #555; }
  button { background: #f0f0f0; color: #1c1c1c; border-color: #f0f0f0; }
  .catatan { color: #aaa; }
  .lebih { background: #5a1f1f; color: #ffd6d6; }
}
</style>
