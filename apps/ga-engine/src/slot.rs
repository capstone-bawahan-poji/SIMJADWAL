//! Slot waktu, dibaca dari `sesi.json`.
//!
//! Id slot = urutan hari lalu sesi, mulai dari 1. Untuk data FSTI:
//!
//! ```text
//!           sesi 1   sesi 2   sesi 3   sesi 4
//!           (3 SKS)  (2 SKS)  (3 SKS)  (2 SKS)
//! Senin        1        2        3        4
//! Selasa       5        6        7        8
//! Rabu         9       10       11       12
//! Kamis       13       14       15       16
//! Jumat       17       18       19       20
//! ```
//!
//! Sesi 1 dan 3 berdurasi 150 menit (tipe 3 SKS), sesi 2 dan 4 berdurasi
//! 90 menit (tipe 2 SKS). Jadi kelas 3 SKS hanya bisa di 10 slot ganjil
//! kolomnya, dan kelas 2 SKS di 10 slot lainnya.

use serde::{Deserialize, Serialize};

use crate::dataset::SesiRaw;
use crate::error::KromosomError;

/// Nilai satu gen: id slot waktu (1-based).
pub type IdSlot = u8;

/// Urutan hari yang dikenali. Index 0 = Senin.
pub const NAMA_HARI: [&str; 6] = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];

/// Satu slot waktu hasil decode.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct SlotWaktu {
    pub id_slot: IdSlot,
    /// 0 = Senin, 1 = Selasa, ...
    pub hari_index: u8,
    pub nama_hari: String,
    /// Mulai dari 1, sama dengan `session` di sesi.json.
    pub sesi_ke: u8,
    pub mulai: String,
    pub selesai: String,
    /// Jumlah SKS yang cocok untuk slot ini (kolom `type` di sesi.json).
    pub tipe_sks: u8,
}

/// Seluruh slot yang tersedia, terurut menurut id.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct DaftarSlot {
    slots: Vec<SlotWaktu>,
}

impl DaftarSlot {
    /// Bangun daftar slot dari isi sesi.json. Urutan di file tidak
    /// berpengaruh: slot selalu diurutkan hari lalu sesi.
    pub fn dari_sesi(sesi: &[SesiRaw]) -> Result<Self, KromosomError> {
        if sesi.is_empty() {
            return Err(KromosomError::DataTidakValid("sesi.json kosong".into()));
        }
        let mut sementara = Vec::with_capacity(sesi.len());
        for s in sesi {
            let hari_index = NAMA_HARI
                .iter()
                .position(|h| h.eq_ignore_ascii_case(s.day.trim()))
                .ok_or_else(|| KromosomError::DataTidakValid(format!("hari tidak dikenal: {}", s.day)))?
                as u8;
            if s.session == 0 {
                return Err(KromosomError::DataTidakValid(format!("sesi 0 pada hari {}", s.day)));
            }
            sementara.push((hari_index, s));
        }
        sementara.sort_by_key(|(h, s)| (*h, s.session));
        for pasangan in sementara.windows(2) {
            if (pasangan[0].0, pasangan[0].1.session) == (pasangan[1].0, pasangan[1].1.session) {
                return Err(KromosomError::DataTidakValid(format!(
                    "{} sesi {} muncul dua kali",
                    pasangan[0].1.day, pasangan[0].1.session
                )));
            }
        }
        if sementara.len() > IdSlot::MAX as usize {
            return Err(KromosomError::DataTidakValid("jumlah slot terlalu banyak".into()));
        }
        let slots = sementara
            .into_iter()
            .enumerate()
            .map(|(i, (hari_index, s))| SlotWaktu {
                id_slot: (i + 1) as IdSlot,
                hari_index,
                nama_hari: NAMA_HARI[hari_index as usize].to_string(),
                sesi_ke: s.session,
                mulai: s.start.clone(),
                selesai: s.end.clone(),
                tipe_sks: s.tipe,
            })
            .collect();
        Ok(Self { slots })
    }

    pub fn total(&self) -> IdSlot {
        self.slots.len() as IdSlot
    }

    pub fn semua(&self) -> &[SlotWaktu] {
        &self.slots
    }

    /// id slot -> detail hari, sesi, jam.
    pub fn get(&self, id_slot: IdSlot) -> Result<&SlotWaktu, KromosomError> {
        if id_slot == 0 || id_slot > self.total() {
            return Err(KromosomError::SlotTidakAda { slot: id_slot, maks: self.total() });
        }
        Ok(&self.slots[id_slot as usize - 1])
    }

    /// Slot yang boleh dipakai sebuah kelas: sesinya ada di
    /// `allowed_sessions` dan tipenya sama dengan SKS kelas itu.
    pub fn domain_untuk(&self, sks: u8, allowed_sessions: &[u8]) -> Vec<IdSlot> {
        self.slots
            .iter()
            .filter(|s| s.tipe_sks == sks && allowed_sessions.contains(&s.sesi_ke))
            .map(|s| s.id_slot)
            .collect()
    }
}

#[cfg(test)]
pub(crate) mod tests {
    use super::*;

    /// Grid 5 hari x 4 sesi dengan pola tipe 3-2-3-2, sama seperti sesi.json FSTI.
    pub(crate) fn sesi_standar() -> Vec<SesiRaw> {
        let jam = [("08:00", "10:30", 3), ("10:30", "12:00", 2), ("13:00", "15:30", 3), ("15:30", "17:00", 2)];
        let mut out = Vec::new();
        for hari in &NAMA_HARI[..5] {
            for (i, (mulai, selesai, tipe)) in jam.iter().enumerate() {
                out.push(SesiRaw {
                    day: hari.to_string(),
                    session: i as u8 + 1,
                    start: mulai.to_string(),
                    end: selesai.to_string(),
                    tipe: *tipe,
                });
            }
        }
        out
    }

    #[test]
    fn id_slot_urut_hari_lalu_sesi() {
        let d = DaftarSlot::dari_sesi(&sesi_standar()).unwrap();
        assert_eq!(d.total(), 20);
        let cek = |id, hari: &str, sesi| {
            let s = d.get(id).unwrap();
            assert_eq!((s.nama_hari.as_str(), s.sesi_ke), (hari, sesi), "slot {id}");
        };
        cek(1, "Senin", 1);
        cek(6, "Selasa", 2);
        cek(9, "Rabu", 1);
        cek(12, "Rabu", 4);
        cek(20, "Jumat", 4);
    }

    #[test]
    fn urutan_file_tidak_berpengaruh() {
        let mut acak = sesi_standar();
        acak.reverse();
        assert_eq!(
            DaftarSlot::dari_sesi(&acak).unwrap(),
            DaftarSlot::dari_sesi(&sesi_standar()).unwrap()
        );
    }

    #[test]
    fn domain_mengikuti_sks() {
        let d = DaftarSlot::dari_sesi(&sesi_standar()).unwrap();
        let tiga = d.domain_untuk(3, &[1, 3]);
        let dua = d.domain_untuk(2, &[2, 4]);
        assert_eq!(tiga, vec![1, 3, 5, 7, 9, 11, 13, 15, 17, 19]);
        assert_eq!(dua, vec![2, 4, 6, 8, 10, 12, 14, 16, 18, 20]);
        // allowed_sessions yang bertentangan dengan tipe SKS -> domain kosong
        assert!(d.domain_untuk(3, &[2, 4]).is_empty());
    }

    #[test]
    fn data_rusak_ditolak() {
        let d = DaftarSlot::dari_sesi(&sesi_standar()).unwrap();
        assert!(d.get(0).is_err());
        assert!(d.get(21).is_err());

        let mut dobel = sesi_standar();
        dobel.push(dobel[0].clone());
        assert!(DaftarSlot::dari_sesi(&dobel).is_err());

        let mut hari_aneh = sesi_standar();
        hari_aneh[0].day = "Minggu".into();
        assert!(DaftarSlot::dari_sesi(&hari_aneh).is_err());
    }
}
