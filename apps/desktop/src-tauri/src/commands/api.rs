use serde_json::Value;
use tauri::{AppHandle, Manager, State};

use crate::api::types::{AuthUser, FacultySummary, GaPreset, GaPresetInput};
use crate::api::{ApiClient, ApiError, ErrorCode};
use crate::error::AppError;
use crate::services::storage::{RunListItem, Storage, UploadState, UploadStatus};

#[tauri::command]
pub async fn auth_login(
    api: State<'_, ApiClient>,
    email: String,
    password: String,
) -> Result<AuthUser, ApiError> {
    api.login(&email, &password).await
}

#[tauri::command]
pub async fn auth_logout(api: State<'_, ApiClient>) -> Result<(), ApiError> {
    api.logout().await
}

#[tauri::command]
pub async fn auth_me(api: State<'_, ApiClient>) -> Result<Option<AuthUser>, ApiError> {
    api.me().await
}

#[tauri::command]
pub async fn auth_import_token(
    api: State<'_, ApiClient>,
    token: String,
) -> Result<Option<AuthUser>, ApiError> {
    if !api.has_token()? {
        api.set_token(&token)?;
    }
    api.me().await
}

#[tauri::command]
pub async fn broadcast_auth(
    api: State<'_, ApiClient>,
    socket_id: String,
    channel_name: String,
) -> Result<Value, ApiError> {
    api.broadcast_auth(&socket_id, &channel_name).await
}

#[tauri::command]
pub async fn api_dataset(
    api: State<'_, ApiClient>,
    period: Option<String>,
    faculty_id: Option<i64>,
) -> Result<Value, ApiError> {
    api.dataset(period.as_deref(), faculty_id).await
}

#[tauri::command]
pub async fn api_faculties(api: State<'_, ApiClient>) -> Result<Vec<FacultySummary>, ApiError> {
    api.faculties().await
}

#[tauri::command]
pub async fn api_ga_presets(
    api: State<'_, ApiClient>,
    faculty_id: i64,
) -> Result<Vec<GaPreset>, ApiError> {
    api.ga_presets(faculty_id).await
}

#[tauri::command]
pub async fn api_create_ga_preset(
    api: State<'_, ApiClient>,
    input: GaPresetInput,
) -> Result<GaPreset, ApiError> {
    api.create_ga_preset(&input).await
}

#[tauri::command]
pub async fn api_update_ga_preset(
    api: State<'_, ApiClient>,
    id: i64,
    input: GaPresetInput,
) -> Result<GaPreset, ApiError> {
    api.update_ga_preset(id, &input).await
}

#[tauri::command]
pub async fn api_delete_ga_preset(api: State<'_, ApiClient>, id: i64) -> Result<(), ApiError> {
    api.delete_ga_preset(id).await
}

#[tauri::command]
pub async fn ga_upload_result(
    app: AppHandle,
    api: State<'_, ApiClient>,
    run_id: String,
) -> Result<RunListItem, AppError> {
    let storage = Storage::new(&app.path().app_data_dir()?);
    let record = storage.load(&run_id)?;

    let upload = match api.upload_run(&record.result).await {
        Ok(run) => UploadState {
            status: UploadStatus::Uploaded,
            server_run_id: Some(run.id),
            error: None,
        },
        Err(e) if e.code == ErrorCode::Unauthenticated => return Err(e.into()),
        Err(e) => UploadState {
            status: UploadStatus::Failed,
            server_run_id: None,
            error: Some(upload_failure(&e)),
        },
    };

    storage.set_upload(&run_id, upload)
}

fn upload_failure(error: &ApiError) -> String {
    match error.code {
        ErrorCode::DatasetChanged => {
            "Data di server sudah berubah. Muat ulang data lalu jalankan ulang.".to_string()
        }
        _ => error.message.clone(),
    }
}