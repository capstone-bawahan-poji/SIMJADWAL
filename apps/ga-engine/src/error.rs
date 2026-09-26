use std::fmt;

use crate::slot::IdSlot;

/// Semua kesalahan yang bisa muncul saat memuat data atau membangun kromosom.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum KromosomError {
    /// File JSON tidak bisa dibaca atau formatnya salah.
    DataTidakTerbaca(String),
    /// Isi data tidak masuk akal (mis. hari tidak dikenal, sesi dobel).
    DataTidakValid(String),
    /// Id slot tidak ada di daftar slot.
    SlotTidakAda { slot: IdSlot, maks: IdSlot },
    /// Gen berisi slot yang tidak cocok dengan SKS / allowed_sessions kelas itu.
    SlotTidakDiizinkan { gen: usize, id_kelas: String, slot: IdSlot },
    /// Panjang kromosom tidak sama dengan jumlah kelas.
    PanjangTidakSesuai { diharapkan: usize, didapat: usize },
    /// Tidak ada kelas yang perlu dijadwalkan.
    LayoutKosong,
    /// Id kelas muncul lebih dari sekali.
    KelasDuplikat(String),
}

impl fmt::Display for KromosomError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Self::DataTidakTerbaca(p) => write!(f, "data tidak terbaca: {p}"),
            Self::DataTidakValid(p) => write!(f, "data tidak valid: {p}"),
            Self::SlotTidakAda { slot, maks } => write!(f, "slot {slot} tidak ada (1..={maks})"),
            Self::SlotTidakDiizinkan { gen, id_kelas, slot } => write!(
                f,
                "gen {gen} ({id_kelas}) berisi slot {slot} yang tidak diizinkan untuk kelas itu"
            ),
            Self::PanjangTidakSesuai { diharapkan, didapat } => write!(
                f,
                "panjang kromosom {didapat}, seharusnya {diharapkan} (jumlah kelas)"
            ),
            Self::LayoutKosong => write!(f, "tidak ada kelas yang perlu dijadwalkan"),
            Self::KelasDuplikat(id) => write!(f, "id kelas {id} muncul lebih dari sekali"),
        }
    }
}

impl std::error::Error for KromosomError {}
