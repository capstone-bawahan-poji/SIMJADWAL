use reqwest::Method;
use serde_json::{json, Value};

use super::types::{AuthToken, AuthUser, LoginRequest};
use super::{ApiClient, ApiError};

const DEVICE_NAME: &str = "simjadwal-desktop";

impl ApiClient {
    pub async fn login(&self, email: &str, password: &str) -> Result<AuthUser, ApiError> {
        self.clear_token()?;
        let body = LoginRequest {
            email,
            password,
            device_name: DEVICE_NAME,
        };
        let auth: AuthToken = self
            .data(self.request(Method::POST, "/auth/login")?.json(&body))
            .await?;
        self.set_token(&auth.token)?;
        Ok(auth.user)
    }

    pub async fn logout(&self) -> Result<(), ApiError> {
        let result = match self.has_token()? {
            true => {
                self.no_content(self.request(Method::POST, "/auth/logout")?)
                    .await
            }
            false => Ok(()),
        };
        self.clear_token()?;
        result
    }

    pub async fn me(&self) -> Result<Option<AuthUser>, ApiError> {
        if !self.has_token()? {
            return Ok(None);
        }
        match self.data(self.request(Method::GET, "/auth/me")?).await {
            Ok(user) => Ok(Some(user)),
            Err(e) if e.status == 401 => Ok(None),
            Err(e) => Err(e),
        }
    }

    pub async fn broadcast_auth(
        &self,
        socket_id: &str,
        channel_name: &str,
    ) -> Result<Value, ApiError> {
        if !self.has_token()? {
            return Err(ApiError::not_signed_in());
        }
        let body = json!({ "socket_id": socket_id, "channel_name": channel_name });
        self.raw(
            self.request(Method::POST, "/broadcasting/auth")?
                .json(&body),
        )
        .await
    }
}