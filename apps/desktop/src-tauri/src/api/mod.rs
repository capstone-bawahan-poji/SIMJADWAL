pub mod auth;
pub mod error;
pub mod scheduling;
pub mod token;
pub mod types;

use std::sync::Mutex;
use std::time::Duration;

use reqwest::{Method, RequestBuilder, StatusCode};
use serde::de::DeserializeOwned;
use serde::Deserialize;
use serde_json::Value;

pub use error::{ApiError, ErrorCode};

const BASE_URL: &str = match option_env!("SIMJADWAL_API_URL") {
    Some(url) => url,
    None => "http://localhost:8000/api/v1",
};

#[derive(Deserialize)]
struct Envelope<T> {
    data: T,
}

enum TokenState {
    Unloaded,
    Loaded(Option<String>),
}

pub struct ApiClient {
    http: reqwest::Client,
    base_url: String,
    token: Mutex<TokenState>,
}

impl Default for ApiClient {
    fn default() -> Self {
        Self::new(BASE_URL)
    }
}

impl ApiClient {
    pub fn new(base_url: &str) -> Self {
        let http = reqwest::Client::builder()
            .timeout(Duration::from_secs(60))
            .connect_timeout(Duration::from_secs(10))
            .user_agent(concat!("simjadwal-desktop/", env!("CARGO_PKG_VERSION")))
            .build()
            .expect("HTTP client config is static");

        Self {
            http,
            base_url: base_url.trim_end_matches('/').to_string(),
            token: Mutex::new(TokenState::Unloaded),
        }
    }

    fn token(&self) -> Result<Option<String>, ApiError> {
        let mut state = self.token.lock().unwrap_or_else(|e| e.into_inner());
        if let TokenState::Unloaded = *state {
            *state = TokenState::Loaded(token::load()?);
        }
        match &*state {
            TokenState::Loaded(token) => Ok(token.clone()),
            TokenState::Unloaded => unreachable!(),
        }
    }

    pub fn has_token(&self) -> Result<bool, ApiError> {
        Ok(self.token()?.is_some())
    }

    pub fn set_token(&self, value: &str) -> Result<(), ApiError> {
        token::store(value)?;
        *self.token.lock().unwrap_or_else(|e| e.into_inner()) =
            TokenState::Loaded(Some(value.to_string()));
        Ok(())
    }

    pub fn clear_token(&self) -> Result<(), ApiError> {
        *self.token.lock().unwrap_or_else(|e| e.into_inner()) = TokenState::Loaded(None);
        token::clear()
    }

    fn request(&self, method: Method, path: &str) -> Result<RequestBuilder, ApiError> {
        let builder = self
            .http
            .request(method, format!("{}{}", self.base_url, path))
            .header("Accept", "application/json");

        Ok(match self.token()? {
            Some(token) => builder.bearer_auth(token),
            None => builder,
        })
    }

    async fn execute(&self, builder: RequestBuilder) -> Result<(StatusCode, Vec<u8>), ApiError> {
        let response = builder.send().await.map_err(|e| ApiError::network(&e))?;
        let status = response.status();
        let body = response
            .bytes()
            .await
            .map_err(|e| ApiError::network(&e))?
            .to_vec();

        if status.is_success() {
            return Ok((status, body));
        }

        let error = ApiError::from_response(status.as_u16(), &body);
        if error.code == ErrorCode::Unauthenticated {
            let _ = self.clear_token();
        }
        Err(error)
    }

    async fn data<T: DeserializeOwned>(&self, builder: RequestBuilder) -> Result<T, ApiError> {
        let (status, body) = self.execute(builder).await?;
        serde_json::from_slice::<Envelope<T>>(&body)
            .map(|envelope| envelope.data)
            .map_err(|e| ApiError::unexpected_body(status.as_u16(), &e))
    }

    async fn raw(&self, builder: RequestBuilder) -> Result<Value, ApiError> {
        let (status, body) = self.execute(builder).await?;
        serde_json::from_slice(&body).map_err(|e| ApiError::unexpected_body(status.as_u16(), &e))
    }

    async fn no_content(&self, builder: RequestBuilder) -> Result<(), ApiError> {
        self.execute(builder).await.map(|_| ())
    }
}