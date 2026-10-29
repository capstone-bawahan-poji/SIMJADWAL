use serde::{Serialize, Serializer};
use serde_json::Value;

#[derive(Debug, Clone, PartialEq, Eq)]
pub enum ErrorCode {
    Unauthenticated,
    InvalidCredentials,
    Forbidden,
    NotFound,
    MethodNotAllowed,
    ValidationFailed,
    ResourceInUse,
    InvalidState,
    ConstraintsLocked,
    DatasetChanged,
    ImportPlanChanged,
    SessionExpired,
    TooManyRequests,
    ServerError,
    HttpError,
    NetworkError,
    Other(String),
}

impl ErrorCode {
    pub fn parse(code: &str) -> Self {
        match code {
            "UNAUTHENTICATED" => Self::Unauthenticated,
            "INVALID_CREDENTIALS" => Self::InvalidCredentials,
            "FORBIDDEN" => Self::Forbidden,
            "NOT_FOUND" => Self::NotFound,
            "METHOD_NOT_ALLOWED" => Self::MethodNotAllowed,
            "VALIDATION_FAILED" => Self::ValidationFailed,
            "RESOURCE_IN_USE" => Self::ResourceInUse,
            "INVALID_STATE" => Self::InvalidState,
            "CONSTRAINTS_LOCKED" => Self::ConstraintsLocked,
            "DATASET_CHANGED" => Self::DatasetChanged,
            "IMPORT_PLAN_CHANGED" => Self::ImportPlanChanged,
            "SESSION_EXPIRED" => Self::SessionExpired,
            "TOO_MANY_REQUESTS" => Self::TooManyRequests,
            "SERVER_ERROR" => Self::ServerError,
            "HTTP_ERROR" => Self::HttpError,
            "NETWORK_ERROR" => Self::NetworkError,
            other => Self::Other(other.to_string()),
        }
    }

    pub fn as_str(&self) -> &str {
        match self {
            Self::Unauthenticated => "UNAUTHENTICATED",
            Self::InvalidCredentials => "INVALID_CREDENTIALS",
            Self::Forbidden => "FORBIDDEN",
            Self::NotFound => "NOT_FOUND",
            Self::MethodNotAllowed => "METHOD_NOT_ALLOWED",
            Self::ValidationFailed => "VALIDATION_FAILED",
            Self::ResourceInUse => "RESOURCE_IN_USE",
            Self::InvalidState => "INVALID_STATE",
            Self::ConstraintsLocked => "CONSTRAINTS_LOCKED",
            Self::DatasetChanged => "DATASET_CHANGED",
            Self::ImportPlanChanged => "IMPORT_PLAN_CHANGED",
            Self::SessionExpired => "SESSION_EXPIRED",
            Self::TooManyRequests => "TOO_MANY_REQUESTS",
            Self::ServerError => "SERVER_ERROR",
            Self::HttpError => "HTTP_ERROR",
            Self::NetworkError => "NETWORK_ERROR",
            Self::Other(code) => code,
        }
    }

    fn from_status(status: u16) -> Self {
        match status {
            401 => Self::Unauthenticated,
            403 => Self::Forbidden,
            404 => Self::NotFound,
            405 => Self::MethodNotAllowed,
            419 => Self::SessionExpired,
            422 => Self::ValidationFailed,
            429 => Self::TooManyRequests,
            500..=599 => Self::ServerError,
            _ => Self::HttpError,
        }
    }
}

impl Serialize for ErrorCode {
    fn serialize<S: Serializer>(&self, serializer: S) -> Result<S::Ok, S::Error> {
        serializer.serialize_str(self.as_str())
    }
}

#[derive(Debug, Clone, Serialize, thiserror::Error)]
#[error("{message}")]
pub struct ApiError {
    pub status: u16,
    pub code: ErrorCode,
    pub message: String,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub errors: Option<Value>,
}

impl ApiError {
    pub fn from_response(status: u16, body: &[u8]) -> Self {
        let json: Value = serde_json::from_slice(body).unwrap_or(Value::Null);
        let code = json
            .get("code")
            .and_then(Value::as_str)
            .map(ErrorCode::parse)
            .unwrap_or_else(|| ErrorCode::from_status(status));
        let message = json
            .get("message")
            .and_then(Value::as_str)
            .filter(|m| !m.is_empty())
            .map(str::to_string)
            .unwrap_or_else(|| format!("Server membalas HTTP {status}."));
        let errors = json.get("errors").filter(|e| !e.is_null()).cloned();

        Self {
            status,
            code,
            message,
            errors,
        }
    }

    pub fn network(error: &reqwest::Error) -> Self {
        let message = if error.is_timeout() {
            "Server tidak merespons. Coba lagi.".to_string()
        } else {
            "Tidak dapat terhubung ke server.".to_string()
        };

        Self {
            status: 0,
            code: ErrorCode::NetworkError,
            message,
            errors: None,
        }
    }

    pub fn unexpected_body(status: u16, error: &serde_json::Error) -> Self {
        Self {
            status,
            code: ErrorCode::Other("UNEXPECTED_RESPONSE".to_string()),
            message: format!("Respons server tidak dikenali: {error}"),
            errors: None,
        }
    }

    pub fn not_signed_in() -> Self {
        Self {
            status: 401,
            code: ErrorCode::Unauthenticated,
            message: "Silakan masuk terlebih dahulu.".to_string(),
            errors: None,
        }
    }

    pub fn keyring(error: &keyring::Error) -> Self {
        Self {
            status: 0,
            code: ErrorCode::Other("TOKEN_STORAGE".to_string()),
            message: format!("Penyimpanan kredensial sistem tidak dapat dipakai: {error}"),
            errors: None,
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn reads_code_message_and_errors_from_body() {
        let body = br#"{"message":"Data tidak valid.","code":"VALIDATION_FAILED","errors":{"name":["Wajib."]}}"#;
        let error = ApiError::from_response(422, body);

        assert_eq!(error.code, ErrorCode::ValidationFailed);
        assert_eq!(error.message, "Data tidak valid.");
        assert_eq!(error.errors.unwrap()["name"][0], "Wajib.");
    }

    #[test]
    fn domain_conflicts_keep_their_own_code() {
        let error = ApiError::from_response(409, br#"{"message":"x","code":"DATASET_CHANGED"}"#);
        assert_eq!(error.code, ErrorCode::DatasetChanged);
    }

    #[test]
    fn unknown_code_is_kept_verbatim() {
        let error = ApiError::from_response(409, br#"{"message":"x","code":"VERSION_CONFLICT"}"#);
        assert_eq!(error.code, ErrorCode::Other("VERSION_CONFLICT".into()));
        assert_eq!(error.code.as_str(), "VERSION_CONFLICT");
    }

    #[test]
    fn body_without_code_falls_back_to_status() {
        let error = ApiError::from_response(502, b"<html>Bad Gateway</html>");
        assert_eq!(error.code, ErrorCode::ServerError);
        assert_eq!(error.message, "Server membalas HTTP 502.");
    }

    #[test]
    fn serializes_in_the_server_error_shape() {
        let error = ApiError::from_response(409, br#"{"message":"Dipakai.","code":"RESOURCE_IN_USE","errors":{"references":{"scheduling_runs":2}}}"#);
        let json = serde_json::to_value(&error).unwrap();

        assert_eq!(json["status"], 409);
        assert_eq!(json["code"], "RESOURCE_IN_USE");
        assert_eq!(json["message"], "Dipakai.");
        assert_eq!(json["errors"]["references"]["scheduling_runs"], 2);
    }
}