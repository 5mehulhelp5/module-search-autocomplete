# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.7] - 2026-10-07

### Fixed
- Searching from the search box no longer puts the form key and the hidden spam-trap field into the search results address. Pressing Enter or the Search button led to an address such as `/catalogsearch/result/?form_key=...&website=&q=bag`, so the form key could end up in shared links, browser history and server logs. The address is now `/catalogsearch/result/?q=bag` on Hyva and Luma; the suggestion requests still send the form key and spam-trap value as before.
