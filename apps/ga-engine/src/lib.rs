//! Engine algoritma genetika untuk sistem penjadwalan mata kuliah.
//!
//! Crate ini terpisah dari aplikasi Tauri supaya logika GA bisa diuji
//! dengan `cargo test` tanpa membuka aplikasi.
//!
//! Modul saat ini (task 8):
//! - [`dataset`]  : memuat matkul.json, sesi.json, ruang.json per fakultas,
//!   menggabungkan beberapa fakultas, dan laporan kualitas data
//! - [`slot`]     : daftar slot waktu dari sesi.json
//! - [`kromosom`] : layout gen, struktur kromosom, validasi, decode, populasi awal

pub mod dataset;
pub mod error;
pub mod kromosom;
pub mod slot;

pub use dataset::{siapkan, Dataset, DatasetMentah, LaporanData};
pub use error::KromosomError;
pub use kromosom::{populasi_awal, GeneLayout, JadwalEntry, Kelas, Kromosom};
pub use slot::{DaftarSlot, IdSlot, SlotWaktu};
