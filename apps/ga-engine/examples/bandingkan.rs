//! Jalankan semua algoritma pada data FSTI dengan seed dan anggaran evaluasi
//! yang sama, lalu bandingkan hasilnya.
//!
//! cargo run --release --example bandingkan            (seed 1-5)
//! cargo run --release --example bandingkan -- 10      (seed 1-10)

use ga_engine::{jalankan_penjadwalan, siapkan, DatasetMentah, Pengaturan, PilihanAlgoritma};

fn main() {
    let jumlah_seed: u64 = std::env::args()
        .nth(1)
        .and_then(|s| s.parse().ok())
        .unwrap_or(5);
    let data = siapkan(&DatasetMentah::fsti_bawaan()).expect("data FSTI");
    println!(
        "Data FSTI: {} kelas, {} dosen, {} ruangan, {} slot",
        data.laporan.jumlah_kelas,
        data.laporan.jumlah_dosen,
        data.laporan.jumlah_ruang,
        data.laporan.jumlah_slot
    );
    println!("HC = hard constraint (bentrok dosen + melebihi ruangan), SC = soft constraint (dosen padat + tanpa jeda)\n");
    println!(
        "{:<20} {:>4} {:>8} {:>8} {:>4} {:>4} {:>11} {:>9} {:>8}",
        "Algoritma", "Seed", "Awal", "Akhir", "HC", "SC", "Iterasi", "Evaluasi", "Waktu"
    );

    for algoritma in PilihanAlgoritma::semua_bawaan() {
        let (mut layak, mut total_sc, mut total_ms) = (0, 0, 0);
        for seed in 1..=jumlah_seed {
            // Mengganti algoritma cukup dengan mengganti satu field ini.
            let pengaturan = Pengaturan {
                algoritma: algoritma.clone(),
                seed,
                ..Default::default()
            };
            let h = jalankan_penjadwalan(&data, &pengaturan, &mut |_| true).expect("penjadwalan");
            let r = &h.hasil;
            let n = &r.penilaian;
            println!(
                "{:<20} {:>4} {:>8} {:>8} {:>4} {:>4} {:>11} {:>9} {:>6}ms",
                r.algoritma,
                seed,
                r.riwayat_penalti[0],
                n.penalti,
                n.pelanggaran_hard(),
                n.pelanggaran_soft(),
                r.iterasi,
                r.jumlah_evaluasi,
                h.durasi_ms
            );
            if n.layak() {
                layak += 1;
            }
            total_sc += n.pelanggaran_soft();
            total_ms += h.durasi_ms;
        }
        println!(
            "  -> layak (HC = 0): {layak}/{jumlah_seed} | rata-rata SC tersisa: {:.1} | rata-rata waktu: {} ms\n",
            total_sc as f64 / jumlah_seed as f64,
            total_ms / jumlah_seed
        );
    }
}
