pub fn greeting(name: &str) -> String {
    format!("Hello, {}! You've been greeted from Rust!", name)
}

#[cfg(test)]
mod tests {
    #[test]
    fn greets_by_name() {
        assert_eq!(
            super::greeting("Ana"),
            "Hello, Ana! You've been greeted from Rust!"
        );
    }
}
