use std::collections::HashMap;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Arc, Mutex};

#[derive(Default)]
pub struct RunRegistry {
    batches: Mutex<HashMap<String, Arc<AtomicBool>>>,
}

impl RunRegistry {
    pub fn register(&self, batch_id: &str) -> Arc<AtomicBool> {
        let flag = Arc::new(AtomicBool::new(false));
        self.lock().insert(batch_id.to_string(), flag.clone());
        flag
    }

    pub fn cancel(&self, batch_id: &str) -> bool {
        match self.lock().get(batch_id) {
            Some(flag) => {
                flag.store(true, Ordering::Relaxed);
                true
            }
            None => false,
        }
    }

    pub fn finish(&self, batch_id: &str) {
        self.lock().remove(batch_id);
    }

    pub fn running(&self) -> Vec<String> {
        self.lock().keys().cloned().collect()
    }

    fn lock(&self) -> std::sync::MutexGuard<'_, HashMap<String, Arc<AtomicBool>>> {
        self.batches.lock().unwrap_or_else(|e| e.into_inner())
    }
}
