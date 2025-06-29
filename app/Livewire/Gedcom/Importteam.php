<?php

declare(strict_types=1);

namespace App\Livewire\Gedcom;

use App\Livewire\Traits\TrimStringsAndConvertEmptyStringsToNull;
use App\Php\Gedcom\Import;
// use Laravel\Jetstream\Events\AddingTeam;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

final class Importteam extends Component
{
    use Interactions;
    use TrimStringsAndConvertEmptyStringsToNull;
    use WithFileUploads;

    // -----------------------------------------------------------------------
    public $user;

    public ?string $name = null;

    public ?string $description = null;

    public ?TemporaryUploadedFile $file = null;

    // -----------------------------------------------------------------------
    public function mount(): void
    {
        $this->user = auth()->user();
    }

    public function importteam(): void
    {
        $this->validate();

        try {
            // Store the uploaded file temporarily
            if (!$this->file) {
                $this->toast()->error(__('app.error'), 'No file uploaded')->send();
                return;
            }

            $gedcomFilePath = $this->file->getRealPath();
            
            $import = new Import(
                $this->name,
                $this->description,
                $this->file->getClientOriginalName(),
                $this->user
            );

            $result = $import->import($gedcomFilePath);
            
            if ($result['success']) {
                $stats = $result['stats'];
                $message = "Import completed! {$stats['individuals']} individuals";
                if ($stats['families'] > 0) {
                    $message .= ", {$stats['families']} families";
                }
                if ($stats['errors'] > 0) {
                    $message .= " ({$stats['errors']} errors)";
                }
                
                $this->toast()->success(__('app.saved'), $message)->send();
                $this->redirect(route('teams.show', $result['team']));
            }
            
        } catch (\Exception $e) {
            $this->toast()->error(__('app.error'), 'Import failed: ' . $e->getMessage())->send();
        }
    }

    // -----------------------------------------------------------------------

    public function render(): View
    {
        return view('livewire.gedcom.importteam');
    }

    // -----------------------------------------------------------------------
    protected function rules(): array
    {
        return $rules = [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'file'        => ['required', 'file'],
        ];
    }

    protected function messages(): array
    {
        return [
            'file.required' => __('validation.required'),
            'file.file'     => __('validation.required'),
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name'        => __('team.name'),
            'description' => __('team.description'),
            'file'        => __('gedcom.gedcom_file'),
        ];
    }
}
