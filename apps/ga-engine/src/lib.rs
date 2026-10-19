//! Engine penjadwalan mata kuliah SIMJADWAL (target: FSTI).
//!
//! Crate ini terpisah dari aplikasi Tauri supaya logikanya bisa diuji
//! dengan `cargo test` tanpa membuka aplikasi.
//!
//! Modul:
//! - [`dataset`]     : memuat matkul.json, sesi.json, ruang.json dan laporan kualitas data
//! - [`slot`]        : daftar slot waktu dari sesi.json
//! - [`kromosom`]    : struktur kromosom, kunci SKS, validasi, decode, populasi awal
//! - [`evaluasi`]    : penilaian jadwal (fitness HC + SC), dipakai bersama oleh semua algoritma
//! - [`algoritma`]   : algoritma yang bisa diganti (GA, Hill Climbing)
//! - [`penjadwalan`] : pintu masuk untuk app desktop, alokasi ruangan, jadwal akhir

pub mod algoritma;
pub mod dataset;
pub mod error;
pub mod evaluasi;
pub mod kromosom;
pub mod penjadwalan;
pub mod slot;

pub use algoritma::{Algoritma, HasilOptimasi, Kemajuan, PilihanAlgoritma};
pub use dataset::{siapkan, Dataset, DatasetMentah, LaporanData};
pub use error::KromosomError;
pub use evaluasi::{BobotPenalti, JenisAturan, Pelanggaran, Penilai, Penilaian};
pub use kromosom::{populasi_awal, GeneLayout, JadwalEntry, Kelas, Kromosom};
pub use penjadwalan::{
    alokasi_ruangan, jalankan_penjadwalan, susun_jadwal, HasilPenjadwalan, Pengaturan,
};
pub use slot::{DaftarSlot, IdSlot, SlotWaktu};
