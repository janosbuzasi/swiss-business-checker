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
- Optional live Swissreg trademark API lookup with traffic-light status
- Live ZEFIX PublicREST integration via server-side PHP
- UID/CHE register hints derived from ZEFIX results
- ZEFIX manual fallback link with query
- JSON API endpoint
- Short-lived server-side response cache
- Basic JSON API rate limiting
- MIT licensed

## Important disclaimer

This tool is an initial research helper, not legal advice.

The `.ch` domain check is only a DNS-based hint. Final domain availability must be checked through nic.ch or an accredited registrar.

ZEFIX live results are technical API results from the central business name index. Final legally relevant decisions still require official verification.

Swissreg can be checked live when IPI datadelivery API credentials are configured. Similar trade marks, deleted entries, protected terms, cantonal/legal restrictions and industry-specific requirements may still apply.

UID/CHE hints are derived from ZEFIX live results when available and should be verified in the official UID register.

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

## Private local config

The public `includes/config.php` stays generic. For deployment-specific credentials, create this file on your web server:

```text
includes/config.local.php
```

The repository includes a template:

```text
includes/config.local.example.php
```

On your server, copy it once:

```bash
cp includes/config.local.example.php includes/config.local.php
```

Then fill in your credentials in `includes/config.local.php`. It is ignored by Git and is loaded automatically when present. Example:

```php
<?php
return [
    'swissreg_api' => [
        'enabled' => true,
        'refresh_token' => 'your-refresh-token',
    ],
];
```

You can also use it for username/password credentials:

```php
<?php
return [
    'swissreg_api' => [
        'enabled' => true,
        'username' => 'your-user',
        'password' => 'your-password',
    ],
];
```

Do not commit `includes/config.local.php`. It is meant to exist only on your server or local machine.

## Optional Swissreg API credentials

Swissreg live lookups use the official IPI datadelivery API:

```text
https://www.swissreg.ch/public/api/v1
```

Configure either a refresh token:

```bash
export SWISSREG_API_REFRESH_TOKEN="your-refresh-token"
```

or username/password credentials:

```bash
export SWISSREG_API_USERNAME="your-user"
export SWISSREG_API_PASSWORD="your-password"
```

Without credentials, the app keeps a direct official Swissreg CH-Marke search link with the entered query.

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
- Swissreg traffic-light trademark status when API credentials are configured
- Live ZEFIX API match count and result list when available
- UID/CHE matches from live ZEFIX results with official UID register links
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
  "version": "2.2.0",
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
    "status": "live-api",
    "matches": 1,
    "traffic_light": "yellow",
    "results": [
      {
        "name": "PECHE-MIGNON",
        "registration_number": "1100370641",
        "status": "Gelöscht",
        "status_kind": "deleted",
        "detail_url": "https://www.swissreg.ch/database-client/register/detail/trademark/1100370641"
      }
    ]
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
  "uid": {
    "status": "zefix-derived",
    "success": true,
    "traffic_light": "green",
    "matches": 1,
    "uids": [
      {
        "uid": "CHE-123.456.789",
        "name": "Elefanten Holding AG",
        "search_url": "https://www.uid.admin.ch/Search.aspx?lang=de&search=CHE-123.456.789"
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
|   |-- config.local.example.php
|   |-- config.local.php (optional, ignored by Git)
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
- [x] Optional Swissreg API traffic-light lookup
- [x] ZEFIX PublicREST API integration
- [x] UID/CHE register hints from ZEFIX results
- [x] ZEFIX manual fallback link
- [x] JSON API
- [x] Short-lived cache and JSON API rate limiting
- [x] No database

Possible future improvements:

- [ ] Multi-TLD checks: `.ch`, `.com`, `.swiss`, `.io`
- [x] Better IDN handling
- [ ] Configurable scoring
- [ ] Dockerfile
- [ ] GitHub Actions PHP linting
- [ ] Screenshots and demo page

## Official references

- nic.ch domain lookup: https://www.nic.ch/whois/
- Swissreg trade mark database: https://www.swissreg.ch/database-client/search/query/trademarks
- ZEFIX official search: https://www.zefix.admin.ch/de/search/entity/welcome
- ZEFIX PublicREST API documentation: https://www.zefix.admin.ch/ZefixPublicREST/swagger-ui/index.html
- ZEFIX OpenAPI JSON: https://www.zefix.admin.ch/ZefixPublicREST/v3/api-docs
- Swissreg API documentation: https://www.swissreg.ch/public/apidocs/singlehtml/index.html
- UID register: https://www.uid.admin.ch/Search.aspx?lang=de

## Changelog

### v2.3.0

- Added UID/CHE register card derived from live ZEFIX UID results.
- Added official UID register links for manual verification.
- Added UID/CHE data to JSON API output.
- Added `includes/config.local.example.php` as a template for private server credentials.

### v2.2.0

- Added optional Swissreg live API lookup via IPI datadelivery credentials.
- Added Swissreg traffic-light status for no matches, deleted/unclear entries, and active/pending entries.
- Added Swissreg result cards with status and detail links.
- Changed the manual Swissreg fallback to open the entered query directly in CH-Marke search.
- Added optional ignored `includes/config.local.php` for private deployment credentials.

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
