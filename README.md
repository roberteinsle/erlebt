# erlebt

Bewertungs-Assistent: Google-Maps-Link einfügen, 4–6 Fragen zum eigenen Besuch beantworten, fertigen Bewertungstext kopieren und selbst bei Google veröffentlichen.

- **Keine erfundenen Erlebnisse:** Eigene Erlebnisse stammen nur aus deinen Antworten. Bewertungen anderer regen die Fragen an und können den Text um Sachinfos über den Ort ergänzen. Sie werden nicht gespeichert.
- **Kein automatisches Posten** und kein Scraping, nur die offizielle Places API.
- **Für den Eigengebrauch:** ein Login pro Instanz, keine Registrierung. Jede Instanz braucht eigene API-Schlüssel.

Stack: PHP 8.5, Laravel 13, Livewire, Tailwind, PostgreSQL.

## Selbst hosten

Du brauchst einen [Claude-API](https://console.anthropic.com)-Schlüssel, eine PostgreSQL-Datenbank und optional einen Schlüssel für die Google Places API (New). Ohne Google-Schlüssel gibst du den Ortsnamen selbst ein und bekommst allgemeine Fragen.

**Coolify oder jeder andere Compose-Host:**

1. Inhalt von [`deploy/docker-compose.coolify.yml`](deploy/docker-compose.coolify.yml) als Compose-Ressource anlegen. Das Image `ghcr.io/roberteinsle/erlebt` ist öffentlich.
2. Variablen setzen: `APP_NAME`, `APP_KEY`, `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `ANTHROPIC_API_KEY`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, optional `GOOGLE_PLACES_API_KEY`. Einen `APP_KEY` erzeugst du mit `echo "base64:$(openssl rand -base64 32)"`.
3. Domain auf Port `8080` des Dienstes `app` legen. Die App hängt im externen Docker-Netz `coolify` und erreicht darüber die Datenbank; `DB_HOST` ist deren interner Hostname. Außerhalb von Coolify musst du dieses Netz anlegen oder den Netzwerknamen in der Compose-Datei anpassen.
4. Deployen. Migrationen laufen beim Start automatisch, und der Login wird dabei aus `ADMIN_EMAIL` und `ADMIN_PASSWORD` angelegt oder aktualisiert. Ein geändertes Passwort gilt also ab dem nächsten Deploy.

## Compose für andere Projekte verwenden

`deploy/docker-compose.coolify.yml` enthält keine Datenbank, weil sie als eigener Coolify-Service mit Backup läuft. Für ein neues Laravel-Projekt kopierst du die Datei, setzt `APP_IMAGE` und passt nur den Block „Projektspezifisch“ an.

## Entwicklung

```bash
cp .env.example .env       # Werte eintragen, DB_PASSWORD frei wählen
composer install && npm ci && npm run build
php artisan key:generate
docker compose up -d       # App auf http://localhost:8080
docker compose exec app php artisan db:seed --force
vendor/bin/pest            # Tests, ohne echte API-Aufrufe
```

Die `.env` ist per `.gitignore` ausgeschlossen. Schlüssel gehören nie ins Repo; `gitleaks` prüft jeden Push.

## Lizenz

[AGPL-3.0](LICENSE)
