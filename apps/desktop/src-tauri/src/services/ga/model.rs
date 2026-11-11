//! Wire types shared with the frontend. Field names follow the server JSON (snake_case).
//! Unknown fields are ignored, so the frontend can pass the dataset as it came from the API.

use std::collections::HashMap;

use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash, Serialize, Deserialize)]
pub enum SelectionOperator {
    TS,
    TSR,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Serialize, Deserialize)]
#[serde(rename_all = "lowercase")]
pub enum PreferenceType {
    Want,
    Avoid,
}

#[derive(Debug, Clone, Deserialize)]
pub struct Dataset {
    pub fingerprint: String,
    pub period: String,
    /// "study_program" or "tpb". TPB datasets have no faculty and group their classes.
    #[serde(default)]
    pub scope: Option<String>,
    #[serde(default)]
    pub faculty: Option<FacultyRef>,
    /// The program a study_program dataset schedules.
    #[serde(default)]
    pub study_program: Option<StudyProgramRef>,
    /// study_program datasets: the cells (slot, room) the faculty gave the program. Every meeting
    /// must sit in one of them, one meeting per cell.
    #[serde(default)]
    pub cells: Vec<Cell>,
    /// Slots the program's lecturers already teach in other programs' active schedules.
    #[serde(default)]
    pub lecturer_busy: Vec<LecturerBlock>,
    pub study_programs: Vec<StudyProgram>,
    pub time_slots: Vec<TimeSlot>,
    pub rooms: Vec<Room>,
    pub courses: Vec<Course>,
    pub classes: Vec<ClassRow>,
    pub lecturers: Vec<Lecturer>,
    pub constraint_types: Vec<ConstraintType>,
    #[serde(default)]
    pub occupied_rooms: Vec<OccupiedRoom>,
    #[serde(default)]
    pub tpb_groups: Vec<TpbGroup>,
    #[serde(default)]
    pub revision: Option<Revision>,
    #[serde(default)]
    pub tpb_blocks: Option<TpbBlocks>,
}

impl Dataset {
    pub fn is_tpb(&self) -> bool {
        self.scope.as_deref() == Some("tpb")
    }
}

/// TPB revision of a published schedule: only `revise_group_ids` move.
#[derive(Debug, Clone, Default, Deserialize)]
pub struct Revision {
    #[serde(default)]
    pub required: bool,
    #[serde(default)]
    pub revise_group_ids: Vec<i64>,
}

/// A TPB group: its classes share one slot per meeting. Regular cohorts (maba, non_maba,
/// campuran) may not meet twice in one slot for the same program and target semester.
#[derive(Debug, Clone, Deserialize)]
pub struct TpbGroup {
    pub id: i64,
    pub cohort: String,
    #[serde(default)]
    pub study_programs: Vec<TpbParticipant>,
}

impl TpbGroup {
    pub fn is_regular(&self) -> bool {
        matches!(self.cohort.as_str(), "maba" | "non_maba" | "campuran")
    }
}

#[derive(Debug, Clone, Deserialize)]
pub struct TpbParticipant {
    pub id: i64,
    #[serde(default)]
    pub target_semester: Option<u8>,
}

/// Slots the active TPB schedule takes from a faculty dataset.
#[derive(Debug, Clone, Default, Deserialize)]
pub struct TpbBlocks {
    #[serde(default)]
    pub study_programs: Vec<ProgramBlock>,
    #[serde(default)]
    pub retake: Vec<RetakeBlock>,
    #[serde(default)]
    pub lecturers: Vec<LecturerBlock>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct ProgramBlock {
    pub study_program_id: i64,
    pub semester: u8,
    pub time_slot_ids: Vec<i64>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct RetakeBlock {
    pub study_program_id: i64,
    pub time_slot_ids: Vec<i64>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct LecturerBlock {
    pub lecturer_id: i64,
    pub time_slot_ids: Vec<i64>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct FacultyRef {
    pub id: i64,
}

#[derive(Debug, Clone, Deserialize)]
pub struct StudyProgramRef {
    pub id: i64,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash, Deserialize)]
pub struct Cell {
    pub time_slot_id: i64,
    pub room_id: i64,
}

#[derive(Debug, Clone, Deserialize)]
pub struct StudyProgram {
    pub id: i64,
    pub included: bool,
}

#[derive(Debug, Clone, Deserialize)]
pub struct TimeSlot {
    pub id: i64,
    pub day: u8,
    pub session: u8,
    #[serde(rename = "type")]
    pub slot_type: u8,
}

#[derive(Debug, Clone, Deserialize)]
pub struct Room {
    pub id: i64,
    pub code: String,
    pub capacity: u32,
}

#[derive(Debug, Clone, Deserialize)]
pub struct Course {
    pub id: i64,
    /// None for TPB courses: their programs come from the groups.
    #[serde(default)]
    pub study_program_id: Option<i64>,
    pub code: String,
    pub name: String,
    pub sks: u8,
    #[serde(default)]
    pub semester: Option<u8>,
    pub class_capacity: u32,
}

#[derive(Debug, Clone, Default, Deserialize)]
pub struct ClassRow {
    pub course_id: i64,
    pub class_number: u16,
    pub lecturer_id: i64,
    #[serde(default)]
    pub fixed_room_id: Option<i64>,
    #[serde(default)]
    pub fixed_time_slot_id: Option<i64>,
    #[serde(default)]
    pub tpb_group_id: Option<i64>,
    /// Revision: published slot and room per meeting of a class that does not move.
    #[serde(default)]
    pub fixed_time_slot_ids: Vec<i64>,
    #[serde(default)]
    pub fixed_room_ids: Vec<Option<i64>>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct Lecturer {
    pub id: i64,
    pub name: String,
    #[serde(default)]
    pub preferences: Vec<Preference>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct Preference {
    pub time_slot_id: i64,
    #[serde(rename = "type")]
    pub kind: PreferenceType,
}

#[derive(Debug, Clone, Deserialize)]
pub struct ConstraintType {
    pub code: String,
    pub weight: f64,
}

#[derive(Debug, Clone, Deserialize)]
pub struct OccupiedRoom {
    pub time_slot_id: i64,
    pub room_id: i64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct GaParams {
    pub population_size: usize,
    pub min_generations: u32,
    pub max_generations: u32,
    pub stagnation_limit: u32,
    pub crossover_rate: f64,
    pub mutation_rate: f64,
    pub tournament_size: usize,
    pub selection_operator: SelectionOperator,
    pub parallel_class_limit: u32,
    pub fitness_constant: f64,
    pub base_seed: u64,
    pub run_count: u32,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct RunConfig {
    pub params: GaParams,
    #[serde(default)]
    pub weights: HashMap<String, f64>,
    #[serde(default)]
    pub sc_weights_by_study_program: HashMap<String, HashMap<String, f64>>,
    #[serde(default)]
    pub preset_id: Option<i64>,
}

#[derive(Debug, Clone, Deserialize)]
pub struct BatchRequest {
    pub dataset: Dataset,
    pub config: RunConfig,
    #[serde(default)]
    pub operators: Vec<SelectionOperator>,
}

#[derive(Debug, Clone, Serialize)]
pub struct RunSpec {
    pub run_id: String,
    pub operator: SelectionOperator,
    pub run_index: u32,
    pub seed: u64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ScheduleDetail {
    pub course_id: i64,
    pub class_number: u16,
    pub meeting_number: u8,
    pub lecturer_id: i64,
    pub time_slot_id: i64,
    pub room_id: Option<i64>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct Violation {
    pub detail_index: usize,
    pub constraint_code: String,
    pub penalty: f64,
    pub message: String,
}

#[derive(Debug, Clone, Default, Serialize, Deserialize)]
pub struct RunSummary {
    pub hc_violations: u32,
    pub sc_unmet: u32,
    pub unassigned_rooms: u32,
    pub fitness_percent: f64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct RunResult {
    pub run_id: String,
    pub batch_id: String,
    /// None for a TPB run.
    pub faculty_id: Option<i64>,
    /// None for a TPB run. Results stored before the GA moved to the programs have none either.
    #[serde(default)]
    pub study_program_id: Option<i64>,
    /// TPB revision: groups placed again. Sent back with the upload.
    #[serde(default)]
    pub revise_group_ids: Vec<i64>,
    pub period: String,
    pub dataset_fingerprint: String,
    pub preset_id: Option<i64>,
    pub operator: SelectionOperator,
    pub run_index: u32,
    pub parameter_snapshot: serde_json::Value,
    pub best_fitness: f64,
    pub max_fitness: f64,
    pub best_generation: u32,
    pub best_chromosome_index: usize,
    pub final_generation: u32,
    pub fitness_history: Vec<f64>,
    pub cancelled: bool,
    pub started_at_ms: u64,
    pub finished_at_ms: u64,
    pub summary: RunSummary,
    pub schedule_details: Vec<ScheduleDetail>,
    pub violations: Vec<Violation>,
}
