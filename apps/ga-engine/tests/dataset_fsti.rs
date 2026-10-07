//! Uji engine dengan dataset FSTI asli (data/fsti).

use std::collections::BTreeSet;

use ga_engine::{populasi_awal, siapkan, DatasetMentah};

fn dataset() -> ga_engine::Dataset {
    siapkan(&DatasetMentah::fsti_bawaan()).expect("dataset FSTI harus bisa disiapkan")
}

#[test]
fn ukuran_data_sesuai_file() {
    let d = dataset();
    assert_eq!(d.laporan.jumlah_kelas, 309, "satu gen per baris matkul.json");
    assert_eq!(d.laporan.jumlah_slot, 20);
    assert_eq!(d.ruang.len(), 28, "30 baris, B102 tercantum 3 kali");
    assert_eq!(d.laporan.kelas_per_sks.get(&3), Some(&171));
    assert_eq!(d.laporan.kelas_per_sks.get(&2), Some(&138));
    assert_eq!(d.laporan.kelas_per_prodi.len(), 9);
}

#[test]
fn variasi_nama_dosen_disatukan() {
    let d = dataset();
    assert_eq!(d.dosen.len(), 83, "86 ejaan berbeda, 83 orang");
    let gabungan = d
        .laporan
        .peringatan
        .iter()
        .filter(|p| p.starts_with("Nama dosen ditulis"))
        .count();
    assert_eq!(gabungan, 2, "Indrawan dan Alvianus Kristian Sumual");
}

#[test]
fn id_kelas_unik_walau_kode_dan_label_kelas_sama() {
    let d = dataset();
    let ids: BTreeSet<_> = d.layout.kelas().iter().map(|k| k.id.as_str()).collect();
    assert_eq!(ids.len(), 309);
}

#[test]
fn domain_tiap_gen_sesuai_sks() {
    let d = dataset();
    for k in d.layout.kelas() {
        assert_eq!(k.slot_diizinkan.len(), 10, "{}", k.id);
        for &s in &k.slot_diizinkan {
            assert_eq!(d.slot.get(s).unwrap().tipe_sks, k.sks, "{} slot {s}", k.id);
        }
    }
}

#[test]
fn semua_kelas_berlabel_fsti() {
    let d = dataset();
    assert_eq!(d.laporan.kelas_per_fakultas.get("FSTI"), Some(&309));
    assert!(d.layout.kelas().iter().all(|k| k.fakultas.as_deref() == Some("FSTI")));
}

#[test]
fn muat_dari_folder_sama_dengan_data_bawaan() {
    let folder = concat!(env!("CARGO_MANIFEST_DIR"), "/data/fsti");
    let dari_folder = DatasetMentah::dari_folder(folder).unwrap();
    assert_eq!(dari_folder, DatasetMentah::fsti_bawaan(), "label diambil dari nama folder");
}

#[test]
fn populasi_acak_valid_dan_bisa_didecode() {
    let d = dataset();
    let populasi = populasi_awal(&d.layout, 100, 12345);
    assert_eq!(populasi.len(), 100);
    for k in &populasi {
        k.validasi(&d.layout).unwrap();
        let jadwal = k.decode(&d.layout, &d.slot).unwrap();
        assert_eq!(jadwal.len(), 309);
        assert!(jadwal.iter().all(|j| j.waktu.tipe_sks == j.sks));
        assert_eq!(k.kelas_per_slot(d.slot.total()).iter().sum::<usize>(), 309);
    }
}
