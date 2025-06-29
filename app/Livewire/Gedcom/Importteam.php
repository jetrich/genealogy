<?php

declare(strict_types=1);

namespace App\Livewire\Gedcom;

use App\Livewire\Traits\TrimStringsAndConvertEmptyStringsToNull;
use App\Php\Gedcom\Import;
// use Laravel\Jetstream\Events\AddingTeam;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
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
            // Enhanced security validation for file upload
            if (!$this->file) {
                Log::warning('GEDCOM import attempt without file', [
                    'user_id' => $this->user->id,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
                $this->toast()->error(__('app.error'), 'No file uploaded')->send();
                return;
            }

            // Additional security checks on uploaded file
            $this->validateUploadedFileSecurely();

            $gedcomFilePath = $this->file->getRealPath();
            
            // Log import attempt for security monitoring
            Log::info('GEDCOM import initiated', [
                'user_id' => $this->user->id,
                'team_name' => $this->name,
                'filename' => $this->file->getClientOriginalName(),
                'file_size' => $this->file->getSize(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
            ]);
            
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
                
                // Log successful import for security audit
                Log::info('GEDCOM import completed successfully', [
                    'user_id' => $this->user->id,
                    'team_id' => $result['team']->id,
                    'stats' => $stats,
                    'ip_address' => request()->ip(),
                ]);
                
                $this->toast()->success(__('app.saved'), $message)->send();
                $this->redirect(route('teams.show', $result['team']));
            }
            
        } catch (\Exception $e) {
            // Enhanced error logging for security incidents
            Log::error('GEDCOM import failed with security context', [
                'user_id' => $this->user->id,
                'error_message' => $e->getMessage(),
                'file_info' => [
                    'original_name' => $this->file?->getClientOriginalName(),
                    'size' => $this->file?->getSize(),
                    'mime_type' => $this->file?->getMimeType(),
                ],
                'security_context' => [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'session_id' => session()->getId(),
                    'referer' => request()->header('referer'),
                ],
                'trace' => $e->getTraceAsString(),
            ]);
            
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
    
    /**
     * Perform enhanced security validation on uploaded file
     */
    private function validateUploadedFileSecurely(): void
    {
        // Check file extension
        $allowedExtensions = ['ged', 'gedcom'];
        $extension = strtolower($this->file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedExtensions)) {
            Log::warning('GEDCOM import security violation: invalid file extension', [
                'user_id' => $this->user->id,
                'filename' => $this->file->getClientOriginalName(),
                'extension' => $extension,
                'ip_address' => request()->ip(),
            ]);
            throw new \Exception('Invalid file type. Only .ged and .gedcom files are allowed.');
        }
        
        // Check MIME type
        $allowedMimeTypes = ['text/plain', 'application/octet-stream', 'text/x-gedcom'];
        $mimeType = $this->file->getMimeType();
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            Log::warning('GEDCOM import security violation: invalid MIME type', [
                'user_id' => $this->user->id,
                'filename' => $this->file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'ip_address' => request()->ip(),
            ]);
            throw new \Exception('Invalid file type detected.');
        }
        
        // Check file size (max 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($this->file->getSize() > $maxSize) {
            Log::warning('GEDCOM import security violation: file too large in upload', [
                'user_id' => $this->user->id,
                'filename' => $this->file->getClientOriginalName(),
                'file_size' => $this->file->getSize(),
                'max_size' => $maxSize,
                'ip_address' => request()->ip(),
            ]);
            throw new \Exception('File size exceeds the maximum allowed limit of 50MB.');
        }
        
        // Check for dangerous filename patterns
        $filename = $this->file->getClientOriginalName();
        $dangerousPatterns = ['../', '.\\', '<', '>', '|', ':', '*', '?', '"'];
        
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($filename, $pattern) !== false) {
                Log::warning('GEDCOM import security violation: dangerous filename pattern', [
                    'user_id' => $this->user->id,
                    'filename' => $filename,
                    'dangerous_pattern' => $pattern,
                    'ip_address' => request()->ip(),
                ]);
                throw new \Exception('Filename contains invalid characters.');
            }
        }
        
        Log::info('GEDCOM file upload security validation passed', [
            'user_id' => $this->user->id,
            'filename' => $filename,
            'file_size' => $this->file->getSize(),
            'mime_type' => $mimeType,
        ]);
    }
}
