# EspoCRM (Fluia Fork)

> [!IMPORTANT]
> **Internal Fork Notice**: This repository is a customized internal fork of [EspoCRM](https://github.com/espocrm/espocrm) tailored specifically for the **Fluia** ecosystem.
> 
> - **No Third-Party Support**: This repository is maintained solely for internal needs. We do not provide public support, warranty, issue triage, or maintenance for external users.
> - **No Upstream Contributions**: We do not actively submit upstream contributions or accept external pull requests. If you are looking for the official community CRM, please visit [espocrm.com](https://www.espocrm.com) or the [EspoCRM upstream repository](https://github.com/espocrm/espocrm).

---

## License & Open Source Compliance

This project is licensed under the **GNU Affero General Public License v3.0 (GNU AGPLv3)** in full compliance with EspoCRM's original licensing terms.

* Original Copyright © Yuriy Kuznetsov, Taras Machyshyn, Oleksiy Avramenko.
* In accordance with AGPLv3 Section 5 and Section 13, the source code and modifications remain open and publicly available.
* For complete license terms, refer to the [LICENSE.txt](LICENSE.txt) file.

---

## Modifications in this Fork

This fork adapts EspoCRM into a streamlined, high-performance customer data backend for Fluia by eliminating unused features, optimizing the container footprint, and simplifying the user navigation.

### 1. Docker & Runtime Architecture
- **Slim Single-Container Stack**: Lightweight Alpine Linux-based Docker image bundling PHP 8.3 (FPM) and Nginx orchestrated by Supervisord (~150MB image).
- **OPcache & Healthchecks**: Configured non-blocking curl healthchecks and adjusted OPcache settings to avoid CLI/FPM preload runner panics.
- **Dedicated Cron Worker**: Uses the same lightweight base image to execute scheduled jobs (`php cron.php`) reliably without external package pulls.

### 2. Disabled Modules & Scopes
Unused features have been completely disabled at the metadata/scope level (`"disabled": true`), stripping them from the ORM, REST API, navigation, and relations:
- **Marketing**:
  - `Campaign` (Campañas)
  - `MassEmail` (Correos masivos)
  - `TargetList` & `TargetListCategory` (Listas de objetivos)
- **Support & Helpdesk**:
  - `Case` (Casos)
  - `KnowledgeBaseArticle` & `KnowledgeBaseCategory` (Base de conocimiento)
- **Templates & Scheduling**:
  - `EmailTemplate` & `EmailTemplateCategory` (Plantillas de correo)
  - `WorkingTimeCalendar` (Calendarios de jornada laboral)
- **Communication & Activities**:
  - Streamlined defaults removing unneeded default tabs (Meetings, Calls, in-app Email client).
- **Administration Panel Synchronization**:
  - Cleaned up `app/adminPanel` metadata to remove obsolete entries (`Email Templates` under Messaging and `Working Time Calendars` under Setup), preventing broken routes and UI desynchronization.

### 3. Sidebar Navigation Refactor
- **Direct Access (No Hidden Overflow)**: Removed the three-dots overflow delimiter (`_delimiter_` in `tabList`), allowing all primary navigation items to remain directly accessible on the main sidebar.
- **New Section Divider**: Added a dedicated `Tools` / `Herramientas` section divider (`$Tools`) in the navigation bar.
- **Grouped Utilities**: Placed `Template` (Document templates) and `Import` directly under the new `Tools` section.
- **Multi-language Support**: Added i18n label definitions for `Tools` / `Herramientas` in `en_US`, `es_ES`, and `es_MX`.

---

## Getting Started

### Prerequisites
- Docker & Docker Compose v2+

### Running the Stack
1. Clone the repository and configure environment variables in `docker-compose.yml` (or `.env`):
   ```bash
   docker compose up -d --build
   ```
2. Access the CRM:
   - Web interface: `http://localhost:8080`
   - Database: MariaDB on port `3306`

### Maintenance Commands
- Rebuild metadata and application cache inside container:
  ```bash
  docker compose exec espocrm php rebuild.php
  ```
- Clear application cache:
  ```bash
  docker compose exec espocrm php clear_cache.php
  ```

---

## Upstream Links
- Official Website: [https://www.espocrm.com](https://www.espocrm.com)
- Upstream Source: [https://github.com/espocrm/espocrm](https://github.com/espocrm/espocrm)
- Documentation: [https://docs.espocrm.com](https://docs.espocrm.com)
- Forum: [https://forum.espocrm.com](https://forum.espocrm.com)
