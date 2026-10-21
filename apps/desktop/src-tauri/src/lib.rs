pub mod commands;
pub mod error;
pub mod services;
pub mod state;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .manage(state::RunRegistry::default())
        .invoke_handler(tauri::generate_handler![
            commands::greet::greet,
            commands::ga::ga_start_batch,
            commands::ga::ga_cancel,
            commands::ga::ga_running_batches,
            commands::ga::ga_list_results,
            commands::ga::ga_get_result,
            commands::ga::ga_mark_uploaded,
            commands::ga::ga_mark_upload_failed,
            commands::ga::ga_delete_result,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
