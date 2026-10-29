use reqwest::Method;
use serde_json::{json, Value};

use super::types::{FacultySummary, GaPreset, GaPresetInput, UploadedRun};
use super::{ApiClient, ApiError};
use crate::services::ga::model::RunResult;

impl ApiClient {
    pub async fn dataset(
        &self,
        period: Option<&str>,
        faculty_id: Option<i64>,
    ) -> Result<Value, ApiError> {
        let mut query: Vec<(&str, String)> = Vec::new();
        if let Some(period) = period {
            query.push(("period", period.to_string()));
        }
        if let Some(id) = faculty_id {
            query.push(("faculty_id", id.to_string()));
        }
        self.data(
            self.request(Method::GET, "/scheduling/dataset")?
                .query(&query),
        )
        .await
    }

    pub async fn faculties(&self) -> Result<Vec<FacultySummary>, ApiError> {
        self.data(
            self.request(Method::GET, "/faculties")?
                .query(&[("per_page", "100")]),
        )
        .await
    }

    pub async fn ga_presets(&self, faculty_id: i64) -> Result<Vec<GaPreset>, ApiError> {
        self.data(
            self.request(Method::GET, "/ga-presets")?
                .query(&[("faculty_id", faculty_id)]),
        )
        .await
    }

    pub async fn create_ga_preset(&self, input: &GaPresetInput) -> Result<GaPreset, ApiError> {
        self.data(self.request(Method::POST, "/ga-presets")?.json(input))
            .await
    }

    pub async fn update_ga_preset(
        &self,
        id: i64,
        input: &GaPresetInput,
    ) -> Result<GaPreset, ApiError> {
        self.data(
            self.request(Method::PUT, &format!("/ga-presets/{id}"))?
                .json(input),
        )
        .await
    }

    pub async fn delete_ga_preset(&self, id: i64) -> Result<(), ApiError> {
        self.no_content(self.request(Method::DELETE, &format!("/ga-presets/{id}"))?)
            .await
    }

    pub async fn upload_run(&self, result: &RunResult) -> Result<UploadedRun, ApiError> {
        self.data(
            self.request(Method::POST, "/scheduling-runs")?
                .json(&run_body(result)),
        )
        .await
    }
}

fn run_body(result: &RunResult) -> Value {
    json!({
        "client_run_id": result.run_id,
        "batch_id": result.batch_id,
        "faculty_id": result.faculty_id,
        "period": result.period,
        "dataset_fingerprint": result.dataset_fingerprint,
        "preset_id": result.preset_id,
        "parameter_snapshot": result.parameter_snapshot,
        "best_fitness": result.best_fitness,
        "max_fitness": result.max_fitness,
        "best_generation": result.best_generation,
        "final_generation": result.final_generation,
        "fitness_history": result.fitness_history,
        "started_at": iso8601_utc(result.started_at_ms),
        "finished_at": iso8601_utc(result.finished_at_ms),
        "schedule_details": result.schedule_details,
        "violations": result.violations,
    })
}

fn iso8601_utc(ms: u64) -> String {
    let secs = ms / 1000;
    let (days, rem) = ((secs / 86_400) as i64, secs % 86_400);

    let z = days + 719_468;
    let era = z.div_euclid(146_097);
    let doe = z - era * 146_097;
    let yoe = (doe - doe / 1_460 + doe / 36_524 - doe / 146_096) / 365;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let day = doy - (153 * mp + 2) / 5 + 1;
    let month = if mp < 10 { mp + 3 } else { mp - 9 };
    let year = yoe + era * 400 + i64::from(month <= 2);

    format!(
        "{year:04}-{month:02}-{day:02}T{:02}:{:02}:{:02}.{:03}Z",
        rem / 3600,
        rem % 3600 / 60,
        rem % 60,
        ms % 1000
    )
}

#[cfg(test)]
mod tests {
    use super::iso8601_utc;

    #[test]
    fn formats_like_javascript_to_iso_string() {
        assert_eq!(iso8601_utc(0), "1970-01-01T00:00:00.000Z");
        assert_eq!(iso8601_utc(951_782_400_000), "2000-02-29T00:00:00.000Z");
        assert_eq!(iso8601_utc(1_793_281_514_123), "2026-10-29T13:45:14.123Z");
    }
}