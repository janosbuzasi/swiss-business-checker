# Swiss Business Checker 🇨🇭

A lightweight PHP tool to check Swiss business name candidates against:

- `.ch` domain availability hints
- Swissreg trade mark search
- live ZEFIX PublicREST company register results
- JSON API output

The project is intentionally simple and runs on basic PHP web hosting. No database required.

## Features

- Clean responsive UI
- PHP-only implementation
- DNS-based `.ch` domain hint
- Official Swissreg manual search link
- Live ZEFIX PublicREST integration via server-side PHP
- ZEFIX manual fallback link with query
- JSON API endpoint
- Short-lived server-side response cache
- Basic JSON API rate limiting
- MIT licensed

## Important disclaimer

This tool is an initial research helper, not legal advice.

The `.ch` domain check is only a DNS-based hint. Final domain availability must be checked through nic.ch or an accredited registrar.

ZEFIX live results are technical API results from the central business name index. Final legally relevant decisions still require official verification.

Swissreg remains a manual trade mark check in V2. Similar trade marks, name conflicts, protected terms, cantonal/legal restrictions and industry-specific requirements may still apply.

## Requirements

- PHP 8.0 or newer recommended
- Web server such as Apache, Nginx or PHP built-in server
- PHP cURL recommended for ZEFIX API calls
- DNS functions enabled in PHP for domain hints

## Optional ZEFIX API credentials

The app calls:

```text
https://www.zefix.admin.ch/ZefixPublicREST/api/v1/company/search
```

with a JSON POST body such as:

```json
{
  "name": "elefanten",
  "activeOnly": true
}
```

The official PublicREST API uses Basic Auth. Configure credentials as environment variables to enable live checks:

```bash
export ZEFIX_API_USERNAME="your-user"
export ZEFIX_API_PASSWORD="your-password"
```

You can explicitly enable or disable the live API:

```bash
export ZEFIX_API_ENABLED=true
export ZEFIX_API_ENABLED=false
```

Without credentials, the app keeps working with manual ZEFIX links and does not attempt a live API request.

## Installation

Clone the repository:

```bash
git clone https://github.com/YOUR-USERNAME/swiss-business-checker.git
cd swiss-business-checker
```

Run locally with PHP:

```bash
php -S localhost:8080
```

Open:

```text
http://localhost:8080
```

## Usage

Open the web UI and enter a business name candidate, for example:

```text
elefanten
```

The tool returns:

- Normalized `.ch` domain candidate
- DNS availability hint
- Swissreg search button
- Live ZEFIX API match count and result list when available
- ZEFIX manual fallback button
- Candidate score
- Confidence level for the automated checks
- JSON API URL

## JSON API

Dedicated API endpoint:

```text
/api.php?name=elefanten
```

Alternative via `index.php`:

```text
/index.php?api=1&name=elefanten
```

Example response shape:

```json
{
  "ok": true,
  "version": "2.1.0",
  "query": "elefanten",
  "normalized_domain_label": "elefanten",
  "domain": {
    "domain": "elefanten.ch",
    "mode": "dns-hint",
    "has_dns_records": false,
    "available_hint": true,
    "status": "possibly-available"
  },
  "swissreg": {
    "status": "manual-check",
    "search_url": "https://www.swissreg.ch/database-client/search/query/trademarks"
  },
  "zefix": {
    "status": "live-api",
    "success": true,
    "matches": 3,
    "results": [
      {
        "name": "Elefanten Holding AG",
        "uid": "CHE...",
        "legal_seat": "Sarnen",
        "canton": "OW",
        "status": "ACTIVE",
        "legal_form": "Limited"
      }
    ]
  },
  "score": 75,
  "confidence": "medium",
  "cached": false,
  "disclaimer": "This tool is an initial technical/name research helper, not legal advice."
}
```

## Project structure

```text
swiss-business-checker/
|-- index.php
|-- api.php
|-- includes/
|   |-- config.php
|   |-- functions.php
|   `-- providers.php
|-- assets/
|   |-- css/style.css
|   `-- js/script.js
|-- LICENSE
`-- README.md
```

## Roadmap

V2:

- [x] `.ch` DNS hint
- [x] Swissreg official manual search link
- [x] ZEFIX PublicREST API integration
- [x] ZEFIX manual fallback link
- [x] JSON API
- [x] Short-lived cache and JSON API rate limiting
- [x] No database

Possible future improvements:

- [ ] Multi-TLD checks: `.ch`, `.com`, `.swiss`, `.io`
- [x] Better IDN handling
- [ ] Configurable scoring
- [ ] Optional Swissreg API integration after API access/terms setup
- [ ] Dockerfile
- [ ] GitHub Actions PHP linting
- [ ] Screenshots and demo page

## Official references

- nic.ch domain lookup: https://www.nic.ch/whois/
- Swissreg trade mark database: https://www.swissreg.ch/database-client/search/query/trademarks
- ZEFIX official search: https://www.zefix.admin.ch/de/search/entity/welcome
- ZEFIX PublicREST API documentation: https://www.zefix.admin.ch/ZefixPublicREST/swagger-ui/index.html
- ZEFIX OpenAPI JSON: https://www.zefix.admin.ch/ZefixPublicREST/v3/api-docs

## Changelog

### v2.1.0

- Disabled live ZEFIX API requests unless credentials are configured.
- Added a short-lived cache and JSON API rate limiting.
- Added confidence reporting and improved IDN domain candidates.
- Adjusted scoring so manual links do not count as completed checks.
- Corrected the documented project structure.

### v2.0.0

- Added server-side ZEFIX PublicREST company search integration.
- Added live ZEFIX result cards in the UI.
- Added optional Basic Auth support via environment variables.
- Kept Swissreg as a stable official manual check.
- Updated scoring for live ZEFIX matches.

### v1.0.1

- Changed ZEFIX link to the stable official entry page.
- Changed default examples to `elefanten`.

## License

MIT License. See [LICENSE](LICENSE).
