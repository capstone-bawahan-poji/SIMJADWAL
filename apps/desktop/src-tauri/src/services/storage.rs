use std::fs;
use std::path::{Path, PathBuf};

use serde::{Deserialize, Serialize};

use super::ga::model::{RunResult, RunSummary, SelectionOperator};
use crate::error::AppError;

#[derive(Debug, Clone, Default, PartialEq, Serialize, Deserialize)]
#[serde(rename_all = "snake_case")]
pub enum UploadStatus {
    #[default]
    Pending,
    Uploaded,
    Failed,
}

#[derive(Debug, Clone, Default, Serialize, Deserialize)]
pub struct UploadState {
    pub status: UploadStatus,
    pub server_run_id: Option<i64>,
    pub error: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct RunRecord {
    pub result: RunResult,
    #[serde(default)]
    pub upload: UploadState,
}

#[derive(Debug, Clone, Serialize)]
pub struct RunListItem {
    pub run_id: String,
    pub batch_id: String,
    pub period: String,
    pub operator: SelectionOperator,
    pub run_index: u32,
    pub best_fitness: f64,
    pub max_fitness: f64,
    pub best_generation: u32,
    pub final_generation: u32,
    pub cancelled: bool,
    pub started_at_ms: u64,
    pub finished_at_ms: u64,
    pub summary: RunSummary,
    pub upload: UploadState,
}

pub struct Storage {
    root: PathBuf,
}

impl Storage {
    pub fn new(app_data_dir: &Path) -> Self {
        Self {
            root: app_data_dir.join("runs"),
        }
    }

    pub fn save(&self, record: &RunRecord) -> Result<(), AppError> {
        let dir = self.root.join(safe(&record.result.batch_id)?);
        fs::create_dir_all(&dir)?;
        let path = dir.join(format!("{}.json", safe(&record.result.run_id)?));
        // Write then rename, so a crash never leaves a half-written file.
        let tmp = path.with_extension("json.tmp");
        fs::write(&tmp, serde_json::to_vec(record)?)?;
        fs::rename(tmp, path)?;
        Ok(())
    }

    pub fn load(&self, run_id: &str) -> Result<RunRecord, AppError> {
        let name = format!("{}.json", safe(run_id)?);
        for batch in self.batch_dirs()? {
            let path = batch.join(&name);
            if path.exists() {
                return Ok(serde_json::from_slice(&fs::read(path)?)?);
            }
        }
        Err(AppError::Invalid(format!(
            "Hasil run {run_id} tidak ditemukan."
        )))
    }

    pub fn list(&self) -> Result<Vec<RunListItem>, AppError> {
        let mut items = Vec::new();
        for batch in self.batch_dirs()? {
            for entry in fs::read_dir(batch)? {
                let path = entry?.path();
                if path.extension().is_some_and(|e| e == "json") {
                    if let Ok(record) = fs::read(&path)
                        .map_err(AppError::from)
                        .and_then(|b| Ok(serde_json::from_slice::<RunRecord>(&b)?))
                    {
                        items.push(list_item(record));
                    }
                }
            }
        }
        items.sort_by(|a, b| {
            b.finished_at_ms
                .cmp(&a.finished_at_ms)
                .then(a.run_index.cmp(&b.run_index))
        });
        Ok(items)
    }

    pub fn set_upload(&self, run_id: &str, upload: UploadState) -> Result<RunListItem, AppError> {
        let mut record = self.load(run_id)?;
        record.upload = upload;
        self.save(&record)?;
        Ok(list_item(record))
    }

    pub fn delete(&self, run_id: &str) -> Result<(), AppError> {
        let record = self.load(run_id)?;
        let dir = self.root.join(safe(&record.result.batch_id)?);
        fs::remove_file(dir.join(format!("{}.json", safe(run_id)?)))?;
        if fs::read_dir(&dir)?.next().is_none() {
            fs::remove_dir(dir)?;
        }
        Ok(())
    }

    fn batch_dirs(&self) -> Result<Vec<PathBuf>, AppError> {
        if !self.root.exists() {
            return Ok(Vec::new());
        }
        let mut dirs = Vec::new();
        for entry in fs::read_dir(&self.root)? {
            let path = entry?.path();
            if path.is_dir() {
                dirs.push(path);
            }
        }
        Ok(dirs)
    }
}

fn list_item(record: RunRecord) -> RunListItem {
    let r = record.result;
    RunListItem {
        run_id: r.run_id,
        batch_id: r.batch_id,
        period: r.period,
        operator: r.operator,
        run_index: r.run_index,
        best_fitness: r.best_fitness,
        max_fitness: r.max_fitness,
        best_generation: r.best_generation,
        final_generation: r.final_generation,
        cancelled: r.cancelled,
        started_at_ms: r.started_at_ms,
        finished_at_ms: r.finished_at_ms,
        summary: r.summary,
        upload: record.upload,
    }
}

fn safe(id: &str) -> Result<&str, AppError> {
    if !id.is_empty() && id.len() <= 64 && id.chars().all(|c| c.is_ascii_alphanumeric() || c == '-')
    {
        Ok(id)
    } else {
        Err(AppError::Invalid("Id run tidak valid.".into()))
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::services::ga::model::RunSummary;

    fn record(batch: &str, run: &str, finished: u64) -> RunRecord {
        RunRecord {
            result: RunResult {
                run_id: run.into(),
                batch_id: batch.into(),
                faculty_id: 1,
                period: "2026/2027-GANJIL".into(),
                dataset_fingerprint: "sha256:x".into(),
                preset_id: None,
                operator: SelectionOperator::TS,
                run_index: 0,
                parameter_snapshot: serde_json::json!({}),
                best_fitness: 600.0,
                max_fitness: 664.0,
                best_generation: 10,
                best_chromosome_index: 0,
                final_generation: 20,
                fitness_history: vec![],
                cancelled: false,
                started_at_ms: 0,
                finished_at_ms: finished,
                summary: RunSummary::default(),
                schedule_details: vec![],
                violations: vec![],
            },
            upload: UploadState::default(),
        }
    }

    fn temp_dir(name: &str) -> PathBuf {
        let dir =
            std::env::temp_dir().join(format!("simjadwal-test-{name}-{}", std::process::id()));
        let _ = fs::remove_dir_all(&dir);
        dir
    }

    #[test]
    fn saves_lists_updates_and_deletes_runs() {
        let dir = temp_dir("storage");
        let storage = Storage::new(&dir);
        storage.save(&record("b1", "r1", 1)).unwrap();
        storage.save(&record("b1", "r2", 2)).unwrap();

        let list = storage.list().unwrap();
        assert_eq!(
            list.iter().map(|i| i.run_id.as_str()).collect::<Vec<_>>(),
            vec!["r2", "r1"]
        );

        let item = storage
            .set_upload(
                "r1",
                UploadState {
                    status: UploadStatus::Uploaded,
                    server_run_id: Some(9),
                    error: None,
                },
            )
            .unwrap();
        assert_eq!(item.upload.server_run_id, Some(9));
        assert_eq!(
            storage.load("r1").unwrap().upload.status,
            UploadStatus::Uploaded
        );

        storage.delete("r1").unwrap();
        storage.delete("r2").unwrap();
        assert!(storage.list().unwrap().is_empty());
        let _ = fs::remove_dir_all(dir);
    }

    #[test]
    fn rejects_ids_that_could_escape_the_folder() {
        let storage = Storage::new(&temp_dir("escape"));
        assert!(storage.load("../secret").is_err());
        assert!(storage.save(&record("..", "r1", 1)).is_err());
    }
}
