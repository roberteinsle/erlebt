<?php

namespace App\Livewire;

use App\Services\ClaudeClient;
use App\Services\ModelSettings;
use App\Services\StatusChecker;
use App\Support\UserFacingException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Einstellungen')]
class Settings extends Component
{
    public string $questionsModel = '';

    public string $reviewModel = '';

    #[Locked]
    public array $models = [];

    #[Locked]
    public ?string $modelsError = null;

    #[Locked]
    public ?string $saved = null;

    #[Locked]
    public ?string $error = null;

    #[Locked]
    public array $checks = [];

    public function mount(ClaudeClient $claude, ModelSettings $settings): void
    {
        $this->questionsModel = $settings->questions();
        $this->reviewModel = $settings->review();

        try {
            $this->models = $claude->availableModels();
        } catch (UserFacingException $exception) {
            $this->models = ClaudeClient::FALLBACK_MODELS;
            $this->modelsError = $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);
            $this->models = ClaudeClient::FALLBACK_MODELS;
            $this->modelsError = 'Die Antwort der API war unerwartet, Details stehen im Log.';
        }

        // Eine gespeicherte Auswahl bleibt sichtbar, auch wenn die Liste sie nicht mehr enthält.
        foreach ([$this->questionsModel, $this->reviewModel] as $current) {
            $this->models[$current] ??= $current;
        }
    }

    public function save(ModelSettings $settings): void
    {
        $this->saved = null;
        $this->error = null;

        if (! isset($this->models[$this->questionsModel], $this->models[$this->reviewModel])) {
            $this->error = 'Bitte wähle Modelle aus der Liste.';

            return;
        }

        $settings->save($this->questionsModel, $this->reviewModel);
        $this->saved = 'Gespeichert. Die Auswahl gilt ab der nächsten Anfrage.';
        $this->checks = [];
    }

    public function runChecks(StatusChecker $checker): void
    {
        $this->checks = $checker->run();
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
