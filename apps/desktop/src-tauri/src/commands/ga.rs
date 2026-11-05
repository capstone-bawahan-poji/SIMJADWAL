use std::collections::HashMap;
use std::sync::Mutex;
use std::time::{Duration, Instant};

use serde::Serialize;
use tauri::{AppHandle, Emitter, Manager, State};
use uuid::Uuid;

use crate::error::AppError;
use crate::services::ga::batch;
use crate::services::ga::model::{BatchRequest, RunSpec, RunSummary};
use crate::services::storage::{RunListItem, RunRecord, Storage, UploadState};
use crate::state::RunRegistry;

const PROGRESS_INTERVAL: Duration = Duration::from_millis(200);

#[derive(Clone, Serialize)]
struct ProgressEvent<'a> {
    batch_id: &'a str,
    run_id: &'a str,
    generation: u32,
    best_fitness: f64,
    current_fitness: f64,
}

#[derive(Clone, Serialize)]
struct RunFinishedEvent<'a> {
    batch_id: &'a str,
    run_id: &'a str,
    best_fitness: f64,
    max_fitness: f64,
    final_generation: u32,
    cancelled: bool,
    summary: &'a RunSummary,
    error: Option<String>,
}

#[derive(Clone, Serialize)]
struct BatchFinishedEvent<'a> {
    batch_id: &'a str,
    cancelled: bool,
    error: Option<String>,
}

#[derive(Serialize)]
pub struct BatchStarted {
    batch_id: String,
    runs: Vec<RunSpec>,
}

fn storage(app: &AppHandle) -> Result<Storage, AppError> {
    Ok(Storage::new(&app.path().app_data_dir()?))
}

#[tauri::command]
pub async fn ga_start_batch(
    app: AppHandle,
    registry: State<'_, RunRegistry>,
    request: BatchRequest,
) -> Result<BatchStarted, AppError> {
    batch::validate(&request)?;
    let specs = batch::expand(&request)?;
    crate::services::ga::problem::Problem::build(&request.dataset, &request.config)?;
    let storage = storage(&app)?;

    let batch_id = Uuid::new_v4().to_string();
    let cancel = registry.register(&batch_id);
    let started = BatchStarted {
        batch_id: batch_id.clone(),
        runs: specs.clone(),
    };

    tauri::async_runtime::spawn_blocking(move || {
        let last_emit: Mutex<HashMap<String, Instant>> = Mutex::new(HashMap::new());
        let on_progress = |run_id: &str, p: crate::services::ga::engine::Progress| {
            let now = Instant::now();
            {
                let mut last = last_emit.lock().unwrap_or_else(|e| e.into_inner());
                if last
                    .get(run_id)
                    .is_some_and(|t| now.duration_since(*t) < PROGRESS_INTERVAL)
                {
                    return;
                }
                last.insert(run_id.to_string(), now);
            }
            let _ = app.emit(
                "ga://progress",
                ProgressEvent {
                    batch_id: &batch_id,
                    run_id,
                    generation: p.generation,
                    best_fitness: p.best_fitness,
                    current_fitness: p.current_fitness,
                },
            );
        };
        let on_finished = |result: &crate::services::ga::model::RunResult| {
            let error = storage
                .save(&RunRecord {
                    result: result.clone(),
                    upload: UploadState::default(),
                })
                .err()
                .map(|e| e.to_string());
            let _ = app.emit(
                "ga://run-finished",
                RunFinishedEvent {
                    batch_id: &batch_id,
                    run_id: &result.run_id,
                    best_fitness: result.best_fitness,
                    max_fitness: result.max_fitness,
                    final_generation: result.final_generation,
                    cancelled: result.cancelled,
                    summary: &result.summary,
                    error,
                },
            );
        };

        let outcome = batch::execute(
            &request,
            &batch_id,
            &specs,
            &cancel,
            &on_progress,
            &on_finished,
        );
        app.state::<RunRegistry>().finish(&batch_id);
        let _ = app.emit(
            "ga://batch-finished",
            BatchFinishedEvent {
                batch_id: &batch_id,
                cancelled: cancel.load(std::sync::atomic::Ordering::Relaxed),
                error: outcome.err().map(|e| e.to_string()),
            },
        );
    });

    Ok(started)
}

#[tauri::command]
pub fn ga_cancel(registry: State<'_, RunRegistry>, batch_id: String) -> Result<bool, AppError> {
    Ok(registry.cancel(&batch_id))
}

#[tauri::command]
pub fn ga_running_batches(registry: State<'_, RunRegistry>) -> Result<Vec<String>, AppError> {
    Ok(registry.running())
}

#[tauri::command]
pub async fn ga_list_results(app: AppHandle) -> Result<Vec<RunListItem>, AppError> {
    storage(&app)?.list()
}

#[tauri::command]
pub async fn ga_get_result(app: AppHandle, run_id: String) -> Result<RunRecord, AppError> {
    storage(&app)?.load(&run_id)
}

#[tauri::command]
pub async fn ga_delete_result(app: AppHandle, run_id: String) -> Result<(), AppError> {
    storage(&app)?.delete(&run_id)
}