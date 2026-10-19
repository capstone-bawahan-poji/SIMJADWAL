//! Contoh `apps/desktop/src-tauri/src/lib.rs` yang memakai ga-engine.
//! Semua command di bawah dipanggil dari Vue dengan `invoke(...)`.

use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::Arc;

use ga_engine::{
    jalankan_penjadwalan, populasi_awal, siapkan, BobotPenalti, Dataset, DatasetMentah, HasilPenjadwalan,
    IdSlot, JadwalEntry, Kemajuan, Kromosom, LaporanData, Pelanggaran, Penilai, Penilaian, Pengaturan,
    PilihanAlgoritma,
};
use serde::Serialize;
use tauri::{AppHandle, Emitter, State};

/// Data FSTI dimuat sekali saat aplikasi dibuka.
struct Mesin {
    data: Arc<Dataset>,
    berhenti: Arc<AtomicBool>,
}

/// invoke("laporan_data"): ringkasan dan peringatan data.
#[tauri::command]
fn laporan_data(mesin: State<Mesin>) -> LaporanData {
    mesin.data.laporan.clone()
}

/// invoke("daftar_algoritma"): isi dropdown algoritma beserta parameter bawaannya.
#[tauri::command]
fn daftar_algoritma() -> Vec<PilihanAlgoritma> {
    PilihanAlgoritma::semua_bawaan()
}

/// invoke("jalankan", { pengaturan }): jalankan algoritma pilihan.
/// Kemajuan dikirim lewat event "kemajuan-penjadwalan" untuk progress bar.
#[tauri::command]
async fn jalankan(
    app: AppHandle,
    mesin: State<'_, Mesin>,
    pengaturan: Pengaturan,
) -> Result<HasilPenjadwalan, String> {
    let data = mesin.data.clone();
    let berhenti = mesin.berhenti.clone();
    berhenti.store(false, Ordering::SeqCst);
    tauri::async_runtime::spawn_blocking(move || {
        jalankan_penjadwalan(&data, &pengaturan, &mut |k: &Kemajuan| {
            let _ = app.emit("kemajuan-penjadwalan", k);
            !berhenti.load(Ordering::SeqCst)
        })
        .map_err(|e| e.to_string())
    })
    .await
    .map_err(|e| e.to_string())?
}

/// invoke("hentikan"): tombol batal. Algoritma berhenti dan mengembalikan hasil terbaik sejauh ini.
#[tauri::command]
fn hentikan(mesin: State<Mesin>) {
    mesin.berhenti.store(true, Ordering::SeqCst);
}

/// invoke("slot_terbuka", { idKelas }): kunci SKS. Slot yang boleh dipilih
/// untuk kelas ini saat jadwal diatur manual.
#[tauri::command]
fn slot_terbuka(mesin: State<Mesin>, id_kelas: String) -> Result<Vec<IdSlot>, String> {
    mesin.data.layout.slot_terbuka(&id_kelas).map(|s| s.to_vec()).map_err(|e| e.to_string())
}

#[derive(Serialize)]
struct HasilCek {
    penilaian: Penilaian,
    pelanggaran: Vec<Pelanggaran>,
}

/// invoke("nilai_jadwal", { genes }): nilai jadwal setelah diubah manual.
/// Ditolak jika ada kelas yang melanggar kunci SKS.
#[tauri::command]
fn nilai_jadwal(mesin: State<Mesin>, genes: Vec<IdSlot>, bobot: Option<BobotPenalti>) -> Result<HasilCek, String> {
    let k = Kromosom::dari_genes(genes, &mesin.data.layout).map_err(|e| e.to_string())?;
    let penilai = Penilai::baru(&mesin.data, bobot.unwrap_or_default());
    Ok(HasilCek { penilaian: penilai.nilai(&k.genes), pelanggaran: penilai.rincian(&k.genes) })
}

#[derive(Serialize)]
struct HasilUji {
    laporan: LaporanData,
    genes: Vec<IdSlot>,
    kelas_per_slot: Vec<usize>,
    jumlah_ruang: usize,
    jadwal: Vec<JadwalEntry>,
}

/// invoke("buat_kromosom_acak", { seed }): dipakai App.vue contoh untuk melihat satu kromosom acak.
#[tauri::command]
fn buat_kromosom_acak(mesin: State<Mesin>, seed: u64) -> Result<HasilUji, String> {
    let data = &mesin.data;
    let kromosom = populasi_awal(&data.layout, 1, seed).remove(0);
    let jadwal = kromosom.decode(&data.layout, &data.slot).map_err(|e| e.to_string())?;
    Ok(HasilUji {
        kelas_per_slot: kromosom.kelas_per_slot(data.slot.total()),
        genes: kromosom.genes,
        jumlah_ruang: data.ruang.len(),
        laporan: data.laporan.clone(),
        jadwal,
    })
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let data = siapkan(&DatasetMentah::fsti_bawaan()).expect("data FSTI harus valid");
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .manage(Mesin { data: Arc::new(data), berhenti: Arc::new(AtomicBool::new(false)) })
        .invoke_handler(tauri::generate_handler![
            laporan_data,
            daftar_algoritma,
            jalankan,
            hentikan,
            slot_terbuka,
            nilai_jadwal,
            buat_kromosom_acak
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
