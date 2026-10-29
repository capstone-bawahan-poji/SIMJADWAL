pub mod api;
pub mod commands;
pub mod error;
pub mod services;
pub mod state;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .manage(state::RunRegistry::default())
        .manage(api::ApiClient::default())
        .invoke_handler(tauri::generate_handler![
            commands::greet::greet,
            commands::ga::ga_start_batch,
            commands::ga::ga_cancel,
            commands::ga::ga_running_batches,
            commands::ga::ga_list_results,
            commands::ga::ga_get_result,
            commands::ga::ga_delete_result,
            commands::api::auth_login,
            commands::api::auth_logout,
            commands::api::auth_me,
            commands::api::auth_import_token,
            commands::api::broadcast_auth,
            commands::api::api_dataset,
            commands::api::api_faculties,
            commands::api::api_ga_presets,
            commands::api::api_create_ga_preset,
            commands::api::api_update_ga_preset,
            commands::api::api_delete_ga_preset,
            commands::api::ga_upload_result,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}