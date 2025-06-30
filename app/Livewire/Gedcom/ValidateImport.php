<?php

declare(strict_types=1);

namespace App\Livewire\Gedcom;

use App\Php\Gedcom\Validator;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\Log;

final class ValidateImport extends Component
{
    use WithFileUploads;
    use Interactions;

    public ?TemporaryUploadedFile $file = null;
    public ?array $validationResults = null;
    public bool $showResults = false;
    public bool $isValidating = false;

    protected array $rules = [
        'file' => 'required|file|mimes:ged,gedcom|max:51200', // 50MB max
    ];

    protected array $messages = [
        'file.required' => 'Please select a GEDCOM file to validate.',
        'file.file' => 'The uploaded item must be a file.',
        'file.mimes' => 'Only GEDCOM files (.ged, .gedcom) are allowed.',
        'file.max' => 'File size cannot exceed 50MB.',
    ];

    public function validateGedcom(): void
    {
        $this->validate();

        $this->isValidating = true;
        $this->validationResults = null;
        $this->showResults = false;

        try {
            Log::info('Starting GEDCOM validation', [
                'filename' => $this->file->getClientOriginalName(),
                'size' => $this->file->getSize(),
                'user_id' => auth()->id(),
            ]);

            $validator = new Validator();
            $gedcomFilePath = $this->file->getRealPath();
            
            $this->validationResults = $validator->validate($gedcomFilePath);
            $this->showResults = true;

            Log::info('GEDCOM validation completed', [
                'filename' => $this->file->getClientOriginalName(),
                'valid' => $this->validationResults['valid'],
                'issues_count' => count($this->validationResults['issues']),
                'warnings_count' => count($this->validationResults['warnings']),
                'user_id' => auth()->id(),
            ]);

            if ($this->validationResults['valid']) {
                $this->toast()
                    ->success(__('app.success'), 'GEDCOM file validation completed successfully!')
                    ->send();
            } else {
                $this->toast()
                    ->warning(__('app.warning'), 'GEDCOM file has issues that need attention.')
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('GEDCOM validation failed', [
                'error' => $e->getMessage(),
                'filename' => $this->file->getClientOriginalName(),
                'user_id' => auth()->id(),
            ]);

            $this->toast()
                ->error(__('app.error'), 'Validation failed: ' . $e->getMessage())
                ->send();
        } finally {
            $this->isValidating = false;
        }
    }

    public function proceedToImport(): void
    {
        if (!$this->validationResults || !$this->validationResults['can_import']) {
            $this->toast()
                ->error(__('app.error'), 'Cannot proceed to import. Please fix validation issues first.')
                ->send();
            return;
        }

        // Store validation results in session for the import page
        session([
            'gedcom_validation_results' => $this->validationResults,
            'gedcom_validated_file' => $this->file->getClientOriginalName(),
        ]);

        // Redirect to import page
        $this->redirect(route('gedcom.import'));
    }

    public function resetValidation(): void
    {
        $this->reset(['file', 'validationResults', 'showResults', 'isValidating']);
        session()->forget(['gedcom_validation_results', 'gedcom_validated_file']);
    }

    public function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue',
            default => 'gray',
        };
    }

    public function getSeverityIcon(string $severity): string
    {
        return match ($severity) {
            'error' => 'exclamation-circle',
            'warning' => 'exclamation-triangle',
            'info' => 'information-circle',
            default => 'question-mark-circle',
        };
    }

    public function getCategoryDescription(string $category): string
    {
        return match ($category) {
            'file_access' => 'File Access Problems',
            'file_structure' => 'File Structure Issues',
            'parsing' => 'GEDCOM Parsing Problems',
            'version' => 'Version Compatibility',
            'compatibility' => 'Format Compatibility',
            'data' => 'Data Quality Issues',
            'missing_data' => 'Missing Information',
            'name_format' => 'Name Format Problems',
            'family_structure' => 'Family Structure Issues',
            'broken_reference' => 'Broken Cross-References',
            'data_quality' => 'Data Quality Concerns',
            'size' => 'File Size Considerations',
            default => ucfirst(str_replace('_', ' ', $category)),
        };
    }

    public function render()
    {
        return view('livewire.gedcom.validate-import');
    }
}