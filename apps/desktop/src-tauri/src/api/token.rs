use super::error::ApiError;

const SERVICE: &str = "com.capstone11.simjadwal";
const ACCOUNT: &str = "api-token";

fn entry() -> Result<keyring::Entry, ApiError> {
    keyring::Entry::new(SERVICE, ACCOUNT).map_err(|e| ApiError::keyring(&e))
}

pub fn load() -> Result<Option<String>, ApiError> {
    match entry()?.get_password() {
        Ok(token) => Ok(Some(token)),
        Err(keyring::Error::NoEntry) => Ok(None),
        Err(e) => Err(ApiError::keyring(&e)),
    }
}

pub fn store(token: &str) -> Result<(), ApiError> {
    entry()?
        .set_password(token)
        .map_err(|e| ApiError::keyring(&e))
}

pub fn clear() -> Result<(), ApiError> {
    match entry()?.delete_credential() {
        Ok(()) | Err(keyring::Error::NoEntry) => Ok(()),
        Err(e) => Err(ApiError::keyring(&e)),
    }
}