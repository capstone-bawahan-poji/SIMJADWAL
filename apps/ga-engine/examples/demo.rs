//! Jalankan: cargo run --example demo
//! Seed lain:  cargo run --example demo -- 123
//! Data lain:  cargo run --example demo -- 123 data/nama_fakultas
//! Gabungan:   cargo run --example demo -- 123 data/fsti data/nama_fakultas

use ga_engine::{populasi_awal, siapkan, DatasetMentah};

fn main() {
    let mut args = std::env::args().skip(1);
    let seed: u64 = args.next().and_then(|s| s.parse().ok()).unwrap_or(12345);
    let folder: Vec<String> = args.collect();
    let mentah = if folder.is_empty() {
        DatasetMentah::fsti_bawaan()
    } else {
        let bagian = folder.iter().map(|f| DatasetMentah::dari_folder(f).unwrap_or_else(|e| panic!("{e}")));
        DatasetMentah::gabung(bagian.collect::<Vec<_>>()).unwrap_or_else(|e| panic!("{e}"))
    };
    let data = siapkan(&mentah).unwrap_or_else(|e| panic!("{e}"));
    let l = &data.laporan;

    println!("== Data {} ==", mentah.nama_fakultas());
    println!(
        "{} kelas ({} mata kuliah) | {} dosen | {} ruangan | {} slot",
        l.jumlah_kelas, l.jumlah_mata_kuliah, l.jumlah_dosen, l.jumlah_ruang, l.jumlah_slot
    );
    println!("Kelas per fakultas: {:?}", l.kelas_per_fakultas);
    println!("Kelas per prodi:    {:?}", l.kelas_per_prodi);
    println!("Kelas per SKS:      {:?}", l.kelas_per_sks);
    println!("\n== Peringatan data ==");
    for p in &l.peringatan {
        println!("- {p}");
    }

    let populasi = populasi_awal(&data.layout, 1, seed);
    let k = &populasi[0];
    println!("\n== Satu kromosom acak (seed {seed}) ==");
    println!("Panjang {} gen. 12 gen pertama: {:?} ...", k.genes.len(), &k.genes[..12.min(k.genes.len())]);

    println!("\nJumlah kelas per slot (ruangan tersedia: {}):", data.ruang.len());
    let per_slot = k.kelas_per_slot(data.slot.total());
    print!("{:<8}", "");
    for sesi in 1..=4 {
        print!("{:>8}", format!("Sesi {sesi}"));
    }
    println!();
    for (h, baris) in per_slot.chunks(4).enumerate() {
        print!("{:<8}", data.slot.semua()[h * 4].nama_hari);
        for n in baris {
            print!("{n:>8}");
        }
        println!();
    }

    println!("\nDecode 10 gen pertama:");
    println!(
        "{:<4} {:<14} {:<38} {:<5} {:<7} {:<5} Jam",
        "Gen", "Id kelas", "Mata kuliah", "SKS", "Hari", "Sesi"
    );
    for (i, j) in k.decode(&data.layout, &data.slot).unwrap().iter().take(10).enumerate() {
        println!(
            "{:<4} {:<14} {:<38} {:<5} {:<7} {:<5} {}-{}",
            i + 1,
            j.id_kelas,
            j.nama_mk.chars().take(37).collect::<String>(),
            j.sks,
            j.waktu.nama_hari,
            j.waktu.sesi_ke,
            j.waktu.mulai,
            j.waktu.selesai
        );
    }
}
