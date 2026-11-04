# Projektplan erlebt – Bewertungs-Assistent

> Änderung gegenüber dem Plan unten: Statt OpenRouter nutzt die App direkt die Claude API (`ANTHROPIC_API_KEY`, offizielles PHP-SDK).
>
> Zweite Änderung: Bewertungen anderer gehen jetzt auch in den Text-Prompt, aber nur als Sachinfos über den Ort, nie als eigenes Erlebnis. Gespeichert werden sie weiterhin nicht.

## Kontext

**erlebt** macht aus einem Google-Maps-Link und 4–6 gezielten Fragen einen fertigen Bewertungstext, nutzbar am Rechner und am iPhone. Das Repo ist bis auf die README leer. Die App wird nach deinem Deploy-Standard gebaut, der Betrieb läuft über GHCR und Coolify.

**Der Abend ist erfolgreich, wenn** du auf `https://erlebt.einsle.cloud` einen Text für den Goldnen Anker erzeugt und mit deiner heutigen Bewertung verglichen hast.

## Entscheidungen

| Thema | Entscheidung |
|---|---|
| Produkt | Laravel-Webapp, die Python-CLI-Bausteine aus dem Konzept entfallen |
| Stack | PHP 8.5, Laravel 13, Livewire, Tailwind, Pest |
| Zugang | Ein Nutzer mit E-Mail und Passwort, keine Registrierung |
| Datenbank | PostgreSQL als eigener Coolify-Service, nicht im Deploy-Compose |
| Gespeichert wird | Ort, deine Antworten, erzeugte Texte. Fremde Bewertungen werden nicht gespeichert |
| Modelle | Standard: Claude Haiku 5.5 für Fragen, Claude Sonnet 5.5 für Text, per Env austauschbar |
| Domain | `erlebt.einsle.cloud` |
| Repo und Image | Öffentlich, Lizenz AGPL-3.0. Deine Instanz bleibt hinter deinem Login |
| Git und Deploy | Direkt auf `main`, Auto-Deploy aktiv. Jeder Push geht also live |
| Secrets | Lokal in einer `.env` im Codespace, die du selbst befüllst; in Produktion Coolify-Variablen |
| Version 1 | Kern, Überarbeiten, Verlaufsansicht, Markdown-Export |
| Phase 2 | iOS-Kurzbefehl mit Home-Bildschirm-Symbol. Bis dahin Link von Hand einfügen |

## Architektur

**Ablauf:** Link → Ort bestätigen → Fragen → Sterne, Länge, Ton → Text → Kopieren, Überarbeiten, Export

| Baustein | Aufgabe |
|---|---|
| `app/Services/MapsLinkResolver.php` | URL aus dem geteilten Text ziehen, Kurzlink auflösen, Name und Koordinaten lesen |
| `app/Services/PlacesClient.php` | Text Search (nur `places.id`), dann Place Details mit Bewertungen, Sprache `de` |
| `app/Services/OpenRouterClient.php` | Modellaufruf mit Timeout und verständlichen Fehlern |
| `app/Services/QuestionGenerator.php` | Ortsdaten und Bewertungen rein, geprüftes JSON `[{frage, warum, beispiel}]` raus |
| `app/Services/ReviewWriter.php` | Text erzeugen und überarbeiten, die Signatur nimmt keine fremden Bewertungen an |
| `app/Livewire/ReviewWizard.php` | Der Assistent, mit sichtbarem Ladezustand während der Modellaufrufe |
| `app/Livewire/ReviewHistory.php` | Verlauf, Detailansicht, Markdown-Download |

**Daten:** `users`; `reviews` (Ort, `place_id`, Fragen und Antworten als JSON, Sterne, Länge, Ton); `review_versions` (Text, Änderungswunsch, Modell). Sitzungen und Cache liegen ebenfalls in Postgres.

**Kein Queue-Worker in Version 1:** Modellaufrufe laufen synchron im Request. Das spart einen Container und Komplexität. Ein auskommentierter Worker-Block bleibt im Compose als Vorlage für spätere Projekte.

### Fünf Punkte, die das Konzept noch nicht abdeckt

1. **Fragen und Antworten gehören zusammen.** Das Konzept gibt dem Bewertungs-Prompt nur deine Antworten, aber ein „Ja“ auf „Viele erwähnen das Wild – hattet ihr das?“ ist ohne Frage wertlos. Der Prompt bekommt deshalb die Fragen als Kontext, mit der Regel: Fakten nur aus den Antworten. Ein Test prüft, dass keine Bewertungstexte im Prompt landen.
2. **Link-Auflösung absichern (SSRF).** Nur eine feste Liste von Google-Hosts ist erlaubt, jede Weiterleitung wird einzeln geprüft. So kann kein Link die App zu internen Adressen führen.
3. **Fremde Bewertungen sind unsichere Eingaben.** Sie könnten Anweisungen an das Modell enthalten. Sie werden im Prompt klar als Daten markiert, die Antwort wird gegen das Schema geprüft, und die Ausgabe wird escaped.
4. **Kostenbremse in der App.** Zusätzlich zur Google-Quota begrenzt `MAX_RUNS_PER_DAY` (Standard 20) die Durchläufe, der Login ist gegen Durchprobieren gedrosselt.
5. **Google-Nutzungsbedingungen zum Speichern.** Sie erlauben dauerhaft eigentlich nur die `place_id`. Für einen brauchbaren Verlauf speichere ich zusätzlich Name und Adresse des Orts. Das halte ich bei reinem Eigengebrauch für vertretbar, aber es bleibt eine bewusste Abweichung. Falls du sie nicht willst, sag es, dann speichere ich nur die `place_id` und lade den Namen bei Bedarf nach.

## Wiederverwendbares Compose

`deploy/docker-compose.coolify.yml` lässt sich ohne Codeänderung in andere Projekte kopieren:

- Image kommt aus `APP_IMAGE`, Tag aus `APP_TAG`, alle Werte sind `${...}`-Variablen
- Kein `db`-Service und kein `depends_on` darauf, der DB-Host kommt aus `DB_HOST`
- Projektspezifische Variablen stehen in einem eigenen, kommentierten Block
- Zusätzlich gibt es für die Entwicklung `docker-compose.yml` mit lokalem Postgres. Dieses läuft nie in Produktion

## Secrets im öffentlichen Repo

Weil das Repo öffentlich ist, greifen mehrere Sicherungen:

- `.env` steht vor dem ersten Commit in `.gitignore` und `.dockerignore`, `.env.example` enthält keine Werte
- `gitleaks` läuft bei jedem Push in der GitHub Action
- GitHub Push Protection wird aktiviert
- `.mcp.json` enthält keinen Schlüssel, E-Mail und Passwort deines Logins kommen nur aus Env-Variablen
- Vor dem ersten Push prüfe ich die Git-Inhalte einmal von Hand

## Umsetzung

Der Plan beginnt mit einem früh deployten Grundgerüst, weil Probleme mit Dockerfile und Coolify sonst erst am Ende auffallen. Da jeder Push live geht, ist der Login von Anfang an drin, und Tests laufen vor jedem Push.

### Phase 0 – Einrichtung (20 Min.)

- Context7 als MCP-Server in `.mcp.json` eintragen. **Danach ist ein Neustart von Claude Code nötig**, ab dann nutze ich es für alle Bibliotheksfragen
- `.devcontainer/devcontainer.json` mit PHP 8.5. Der Codespace hat aktuell PHP 8.4.27, bis zum Rebuild laufen Composer und Artisan deshalb über das 8.5-Docker-Image
- Laravel 13 anlegen, `"php": "^8.5"`, Livewire und Pest installieren
- `LICENSE` (AGPL-3.0), `.gitignore`, `.env.example`
- **Du:** `.env` mit deinen Schlüsseln befüllen

### Phase 1 – Grundgerüst live (45 Min.)

- Login, eigener Nutzer per Seeder, leere Startseite
- `Dockerfile`, `.dockerignore`, `build.yml` (mit `gitleaks`), beide Compose-Dateien
- Erster Push, Action grün, Image in GHCR
- **Du:** die Coolify-Schritte aus der Liste unten
- **Fertig, wenn:** `/up` liefert 200, `php -v` zeigt mindestens 8.5.12, der Login funktioniert

### Phase 2 – Ort auflösen (35 Min.)

- `MapsLinkResolver` und `PlacesClient`, Bestätigungsschritt im Assistenten
- Fallback: Ortsname als Text eingeben; ohne Google-Schlüssel allgemeine Fragen
- **Fertig, wenn:** beim Goldnen Anker Name, Adresse, Note und bis zu 5 Bewertungen ankommen

### Phase 3 – Fragen erzeugen (35 Min.)

- OpenRouter-Client, Modell-IDs gegen die aktuelle OpenRouter-Liste prüfen
- Fragen-Prompt, Schemaprüfung mit einem Wiederholungsversuch, danach allgemeine Fragen als Rückfallebene
- Fragen überspringbar, dazu Sterne, Länge und Ton

### Phase 4 – Text erzeugen (35 Min.)

- Bewertungs-Prompt mit den Regeln gegen Erfundenes, Speichern, Kopieren-Button
- Kopieren mit Rückfallebene am iPhone: Der Text lässt sich auch von Hand markieren
- Ohne eine einzige Antwort weist die App darauf hin, statt einen Text zu erfinden
- Vergleich mit deiner heutigen Bewertung, Prompt nachschärfen

### Phase 5 – Abschluss (40 Min.)

- Überarbeiten: „kürzer“, „persönlicher“, freier Wunsch; jede Version wird gespeichert
- Verlaufsansicht und Markdown-Export
- Mobil-Durchgang in iPhone-Größe
- README auf Deutsch: Einrichtung, Selbst-Hosting, Kopieren des Compose
- Release-Tag `v0.1.0`

**Summe: 3,5 Stunden.** Wird es knapp, fällt zuerst das Nachschärfen des Prompts weg. Schluss ist spätestens 22:30 Uhr.

## Deine manuellen Schritte

Für diese Schritte brauche ich dich, weil ich keinen Zugang habe oder deine Werte nicht im Chat stehen sollen.

| Wann | Was |
|---|---|
| Phase 0 | `.env` im Codespace befüllen, Claude Code neu starten |
| Phase 0 | Codespace einmal neu bauen, damit PHP 8.5 aktiv ist |
| Vor dem ersten Push | GitHub Push Protection im Repo aktivieren |
| Phase 1 | DNS-Eintrag für `erlebt.einsle.cloud` prüfen |
| Phase 1 | Coolify: Docker Compose Empty anlegen, Compose einfügen, Variablen setzen, Domain und Port 8080 |
| Phase 1 | Coolify: App mit dem Netz des Postgres-Service verbinden, internen DB-Host als `DB_HOST` eintragen |
| Phase 1 | Einmalig im Container `php artisan db:seed --force` für deinen Login |
| Phase 1 | GHCR: Package auf öffentlich stellen |
| Nach erstem Deploy | Repo-Secrets `COOLIFY_WEBHOOK`, `COOLIFY_TOKEN` und Variable `AUTO_DEPLOY=true` setzen |

## Risiken

| Risiko | Gegenmaßnahme |
|---|---|
| Vorlagen aus dem Standard passen nicht mehr ganz zu `serversideup/php` (Env-Namen, Dateirechte in der Composer-Stufe, `curl` für den Healthcheck) | Gegen die aktuelle Doku über Context7 prüfen, der erste CI-Build und Deploy in Phase 1 beweisen es |
| Tailwind findet in der Asset-Stufe keine Klassen aus `vendor` | Beim ersten Build prüfen, Assets notfalls nach der Composer-Stufe bauen |
| Direkter Push auf `main` bringt einen Fehler live | Tests vor jedem Push; das alte Image bleibt erhalten, wenn der Build scheitert; Rollback über `APP_TAG` |
| Modell erfindet Details | Prompt-Regeln, Test ohne fremde Bewertungen, du liest den Text vor dem Posten |
| Kurzlink-Format ändert sich | Fallback auf Ortsnamen als Text |
| Abend wächst aus dem Rahmen | iOS-Kurzbefehl und alles Weitere erst in Phase 2, Timebox bis 22:30 Uhr |

## Prüfung

- **Automatisch:** Pest mit `Http::fake` für Google und OpenRouter. Abgedeckt sind Link-Formate und abgelehnte Hosts, Fragen-Schema, keine fremden Bewertungen im Text-Prompt, Zugriff nur mit Login, keine Registrierung, Tageslimit, Markdown-Export
- **Secrets:** `gitleaks` grün, `git ls-files` enthält keine `.env`, das Image enthält keine `.env`
- **Echt, im Codespace:** `docker compose up`, kompletter Durchlauf mit dem Goldnen Anker
- **Produktion:** `/up` liefert 200, `php -v` mindestens 8.5.12, Durchlauf am iPhone inklusive Kopieren, Text in Google einfügen und selbst veröffentlichen
- **Auto-Deploy:** Ein kleiner Push erreicht ohne Klick die Produktion
- **Wiederverwendung:** Das Deploy-Compose enthält keinen `erlebt`-spezifischen Wert außerhalb der Variablen-Standards
