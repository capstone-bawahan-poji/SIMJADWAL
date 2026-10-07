use ga_engine::{populasi_awal, siapkan, DatasetMentah, IdSlot, JadwalEntry, LaporanData};
use serde::Serialize;

#[derive(Serialize)]
struct HasilUji {
    laporan: LaporanData,
    genes: Vec<IdSlot>,
    /// Jumlah kelas di slot 1..=20 (index 0 = slot 1).
    kelas_per_slot: Vec<usize>,
    jumlah_ruang: usize,
    jadwal: Vec<JadwalEntry>,
}

/// Dipanggil dari Vue: invoke("buat_kromosom_acak", { seed })
/// Memakai dataset FSTI bawaan engine, membuat satu kromosom acak, lalu decode.
#[tauri::command]
fn buat_kromosom_acak(seed: u64) -> Result<HasilUji, String> {
    let data = siapkan(&DatasetMentah::fsti_bawaan()).map_err(|e| e.to_string())?;
    let kromosom = populasi_awal(&data.layout, 1, seed).remove(0);
    let jadwal = kromosom.decode(&data.layout, &data.slot).map_err(|e| e.to_string())?;
    Ok(HasilUji {
        kelas_per_slot: kromosom.kelas_per_slot(data.slot.total()),
        genes: kromosom.genes,
        jumlah_ruang: data.ruang.len(),
        laporan: data.laporan,
        jadwal,
    })
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![buat_kromosom_acak])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
