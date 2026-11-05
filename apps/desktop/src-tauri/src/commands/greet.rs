use crate::{error::AppError, services};

#[tauri::command]
pub fn greet(name: &str) -> Result<String, AppError> {
    Ok(services::greet::greeting(name))
}
