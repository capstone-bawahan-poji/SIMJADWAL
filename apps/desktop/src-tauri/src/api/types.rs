use std::collections::HashMap;

use serde::{Deserialize, Deserializer, Serialize};

use crate::services::ga::model::SelectionOperator;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct FacultySummary {
    pub id: i64,
    pub code: String,
    pub name: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AuthUser {
    pub id: i64,
    pub name: String,
    pub email: String,
    pub role: Option<String>,
    pub faculty_id: Option<i64>,
    pub faculty: Option<FacultySummary>,
    pub is_active: bool,
}

#[derive(Debug, Deserialize)]
pub struct AuthToken {
    pub token: String,
    pub user: AuthUser,
}

#[derive(Debug, Serialize)]
pub struct LoginRequest<'a> {
    pub email: &'a str,
    pub password: &'a str,
    pub device_name: &'a str,
}

pub type ScOverrides = HashMap<String, HashMap<String, f64>>;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct GaPreset {
    pub id: i64,
    pub faculty_id: i64,
    pub name: String,
    pub population_size: u32,
    pub min_generations: u32,
    pub max_generations: u32,
    pub stagnation_limit: u32,
    pub crossover_rate: f64,
    pub mutation_rate: f64,
    pub tournament_size: u32,
    pub selection_operator: SelectionOperator,
    pub parallel_class_limit: u32,
    pub fitness_constant: i64,
    pub base_seed: i64,
    pub run_count: u32,
    #[serde(deserialize_with = "map_or_empty_list")]
    pub sc_overrides: ScOverrides,
    pub created_by: Option<i64>,
    pub updated_at: Option<String>,
    pub can_update: bool,
    pub can_delete: bool,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct GaPresetInput {
    #[serde(skip_serializing_if = "Option::is_none")]
    pub faculty_id: Option<i64>,
    pub name: String,
    pub population_size: u32,
    pub min_generations: u32,
    pub max_generations: u32,
    pub stagnation_limit: u32,
    pub crossover_rate: f64,
    pub mutation_rate: f64,
    pub tournament_size: u32,
    pub selection_operator: SelectionOperator,
    pub parallel_class_limit: u32,
    pub fitness_constant: i64,
    pub base_seed: i64,
    pub run_count: u32,
    pub sc_overrides: ScOverrides,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct UploadedRun {
    pub id: i64,
    pub status: String,
}

fn map_or_empty_list<'de, D: Deserializer<'de>>(deserializer: D) -> Result<ScOverrides, D::Error> {
    #[derive(Deserialize)]
    #[serde(untagged)]
    enum MapOrList {
        Map(ScOverrides),
        List(Vec<serde_json::Value>),
    }

    match MapOrList::deserialize(deserializer)? {
        MapOrList::Map(map) => Ok(map),
        MapOrList::List(list) if list.is_empty() => Ok(ScOverrides::new()),
        MapOrList::List(_) => Err(serde::de::Error::custom("sc_overrides must be an object")),
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn preset_json(sc_overrides: &str) -> String {
        format!(
            r#"{{"id":1,"faculty_id":2,"name":"Eksplorasi","population_size":100,"min_generations":500,
            "max_generations":1000,"stagnation_limit":200,"crossover_rate":0.9,"mutation_rate":0.5,
            "tournament_size":2,"selection_operator":"TS","parallel_class_limit":5,"fitness_constant":500,
            "base_seed":12345,"run_count":10,"sc_overrides":{sc_overrides},"created_by":null,
            "updated_at":"2026-10-28T10:00:00+07:00","can_update":true,"can_delete":false,"extra":"ignored"}}"#
        )
    }

    #[test]
    fn preset_accepts_php_empty_array_for_overrides() {
        let preset: GaPreset = serde_json::from_str(&preset_json("[]")).unwrap();
        assert!(preset.sc_overrides.is_empty());
    }

    #[test]
    fn preset_reads_overrides_map() {
        let preset: GaPreset =
            serde_json::from_str(&preset_json(r#"{"7":{"SC_INGIN":2}}"#)).unwrap();
        assert_eq!(preset.sc_overrides["7"]["SC_INGIN"], 2.0);
        assert_eq!(preset.selection_operator, SelectionOperator::TS);
    }
}