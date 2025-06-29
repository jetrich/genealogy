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

        if (!$this->file) {
            $this->toast()->error(__('gedcom.file_required'))->send();
            return;
        }

        try {
            // Create import instance with user
            $import = new Import(
                $this->name,
                $this->description,
                $this->file->getClientOriginalName(),
                $this->user
            );

            // Get the temporary file path
            $gedcomFilePath = $this->file->getRealPath();

            // Import the GEDCOM file
            $result = $import->import($gedcomFilePath);

            if ($result['success']) {
                $stats = $result['stats'];
                $team = $result['team'];

                // Show success message with import statistics
                $message = sprintf(
                    'GEDCOM import successful! Team "%s" created with %d individuals and %d families.',
                    $team->name,
                    $stats['individuals'],
                    $stats['families']
                );

                if ($stats['errors'] > 0) {
                    $message .= sprintf(' (%d errors encountered - check logs)', $stats['errors']);
                }

                $this->toast()->success(__('gedcom.import_success'), $message)->send();

                // Redirect to the new team
                return redirect()->route('teams.show', $team);
            } else {
                $this->toast()->error(__('gedcom.import_failed'))->send();
            }

        } catch (\Exception $e) {
            \Log::error('GEDCOM import error in Livewire component', [
                'error' => $e->getMessage(),
                'file' => $this->file ? $this->file->getClientOriginalName() : 'none',
                'user_id' => $this->user->id,
            ]);

            $this->toast()->error(__('gedcom.import_failed'), $e->getMessage())->send();
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
