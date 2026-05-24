# Swiss Business Checker 🇨🇭

A lightweight PHP tool to check Swiss business name candidates against:

- `.ch` domain availability hints
- Swissreg trade mark search
- ZEFIX company register search
- JSON API output

The project is intentionally simple and runs on basic PHP web hosting. No database required.

## Features

- Clean responsive UI
- PHP-only implementation
- DNS-based `.ch` domain hint
- Official Swissreg search link
- Official ZEFIX search link
- JSON API endpoint
- MIT licensed

## Important disclaimer

This tool is an initial research helper, not legal advice.

The `.ch` domain check is only a DNS-based hint. Final domain availability must be checked through nic.ch or an accredited registrar.

Swissreg and ZEFIX are linked as official manual checks in V1. Similar trade marks, name conflicts, protected terms, cantonal/legal restrictions and industry-specific requirements may still apply.

## Requirements

- PHP 8.0 or newer recommended
- Web server such as Apache, Nginx or PHP built-in server
- DNS functions enabled in PHP for domain hints

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
- Swissreg search link
- ZEFIX search link
- Conservative V1 score
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

Example response:

```json
{
    "ok": true,
    "query": "elefanten",
    "normalized_domain_label": "elefanten",
    "domain": {
        "domain": "elefanten.ch",
        "mode": "dns-hint",
        "has_dns_records": false,
        "available_hint": true,
        "status": "possibly-available",
        "note": "DNS lookup is only a hint. Final availability must be checked with nic.ch or a registrar."
    },
    "swissreg": {
        "status": "manual-check",
        "search_url": "https://www.swissreg.ch/database-client/search/query/trademarks?queryString=elefanten",
        "note": "Swissreg should be checked manually. Similar trade marks may still create conflicts."
    },
    "zefix": {
        "status": "manual-check",
        "search_url": "https://www.zefix.ch/en/search/entity/list?searchType=exact&search=elefanten",
        "api_docs_url": "https://www.zefix.admin.ch/ZefixPublicREST/swagger-ui/index.html",
        "note": "ZEFIX official search/API should be checked manually or integrated with credentials in a later version."
    },
    "score": 100,
    "disclaimer": "This tool is an initial technical/name research helper, not legal advice."
}
```

## Project structure

```text
swiss-business-checker/
├── index.php
├── api.php
├── includes/
│   ├── config.php
│   └── functions.php
├── assets/
│   ├── css/style.css
│   └── js/script.js
├── LICENSE
├── .gitignore
├── .gitattributes
└── README.md
```

## Roadmap

V1:

- [x] `.ch` DNS hint
- [x] Swissreg official search link
- [x] ZEFIX official search link
- [x] JSON API
- [x] No database

Possible V2:

- [ ] Optional ZEFIX PublicREST integration
- [ ] Multi-TLD checks: `.ch`, `.com`, `.swiss`, `.io`
- [ ] Better IDN handling
- [ ] Configurable scoring
- [ ] Dockerfile
- [ ] GitHub Actions PHP linting
- [ ] Screenshots and demo page

## Official references

- nic.ch domain lookup: https://www.nic.ch/whois/
- Swissreg trade mark database: https://www.swissreg.ch/database-client/search/query/trademarks
- ZEFIX PublicREST API documentation: https://www.zefix.admin.ch/ZefixPublicREST/swagger-ui/index.html

## License

MIT License. See [LICENSE](LICENSE).
