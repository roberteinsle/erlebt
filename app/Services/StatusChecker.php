<?php

namespace App\Services;

use App\Support\UserFacingException;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StatusChecker
{
    public function __construct(
        private ClaudeClient $claude,
        private PlacesClient $places,
        private ModelSettings $models,
    ) {}

    /** @return list<array{name: string, state: string, detail: string}> state: ok, error oder off */
    public function run(): array
    {
        $checks = [$this->check('Datenbank', fn () => DB::select('select 1') && 'Verbindung steht')];
        $models = array_unique([$this->models->questions(), $this->models->review()]);

        foreach ($models as $model) {
            $checks[] = $this->check("Claude API ({$model})", function () use ($model) {
                $this->claude->ping($model);

                return 'Schlüssel, Guthaben und Modell funktionieren';
            });
        }

        $checks[] = $this->places->isConfigured()
            ? $this->check('Google Places', function () {
                $this->places->ping();

                return 'Schlüssel und API funktionieren';
            })
            : ['name' => 'Google Places', 'state' => 'off', 'detail' => 'Kein Schlüssel hinterlegt, die App stellt allgemeine Fragen'];

        return $checks;
    }

    private function check(string $name, Closure $probe): array
    {
        try {
            $detail = $probe();

            return ['name' => $name, 'state' => 'ok', 'detail' => is_string($detail) ? $detail : 'Verbindung steht'];
        } catch (UserFacingException $exception) {
            return ['name' => $name, 'state' => 'error', 'detail' => $exception->getMessage()];
        } catch (Throwable $exception) {
            Log::error('Statusprüfung fehlgeschlagen', ['pruefung' => $name, 'fehler' => $exception->getMessage()]);

            return ['name' => $name, 'state' => 'error', 'detail' => 'Prüfung fehlgeschlagen, Details stehen im Log.'];
        }
    }
}
