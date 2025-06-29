<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Permission;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PermissionManagement extends Component
{
    use WithPagination, AuthorizesRequests;

    public $selectedUser = null;
    public $selectedPermission = null;
    public $justification = '';
    public $search = '';
    public $showGrantModal = false;
    public $showRevokeModal = false;
    public $permissionToRevoke = null;

    protected $rules = [
        'selectedPermission' => 'required|exists:permissions,name',
        'justification' => 'required|min:10|max:500',
    ];

    protected $messages = [
        'selectedPermission.required' => 'Please select a permission to grant.',
        'selectedPermission.exists' => 'Invalid permission selected.',
        'justification.required' => 'Justification is required for permission changes.',
        'justification.min' => 'Justification must be at least 10 characters.',
        'justification.max' => 'Justification cannot exceed 500 characters.',
    ];

    public function mount()
    {
        // Ensure user has permission to manage permissions
        $this->authorize('grantPermissions', User::class);
    }

    public function selectUser($userId)
    {
        $this->selectedUser = User::find($userId);
        $this->reset(['selectedPermission', 'justification', 'showGrantModal', 'showRevokeModal']);
    }

    public function showGrantPermissionModal()
    {
        if (!$this->selectedUser) {
            $this->addError('user', 'Please select a user first.');
            return;
        }
        
        $this->showGrantModal = true;
        $this->reset(['selectedPermission', 'justification']);
    }

    public function grantPermission()
    {
        $this->validate();
        
        if (!$this->selectedUser) {
            $this->addError('user', 'No user selected.');
            return;
        }

        try {
            $permission = Permission::where('name', $this->selectedPermission)->first();
            
            // Additional check for sensitive permissions
            if ($permission->is_sensitive && !auth()->user()->hasPermission('admin.user_management.permissions')) {
                if (!auth()->user()->is_developer) {
                    $this->addError('permission', 'Insufficient privileges for sensitive permission');
                    return;
                }
            }

            $this->selectedUser->grantPermission(
                $this->selectedPermission,
                auth()->user(),
                $this->justification
            );

            $this->dispatch('permission-granted', [
                'user' => $this->selectedUser->name,
                'permission' => $this->selectedPermission
            ]);

            $this->reset(['selectedPermission', 'justification', 'showGrantModal']);
            
            session()->flash('success', "Permission '{$this->selectedPermission}' granted to {$this->selectedUser->name}");
            
        } catch (\Exception $e) {
            $this->addError('permission', $e->getMessage());
        }
    }

    public function showRevokePermissionModal($permissionName)
    {
        if (!$this->selectedUser) {
            $this->addError('user', 'Please select a user first.');
            return;
        }
        
        $this->permissionToRevoke = $permissionName;
        $this->showRevokeModal = true;
        $this->reset(['justification']);
    }

    public function revokePermission()
    {
        $this->validate(['justification' => 'required|min:10|max:500']);
        
        if (!$this->selectedUser || !$this->permissionToRevoke) {
            return;
        }

        try {
            $this->selectedUser->revokePermission(
                $this->permissionToRevoke,
                auth()->user(),
                $this->justification
            );

            $this->dispatch('permission-revoked', [
                'user' => $this->selectedUser->name,
                'permission' => $this->permissionToRevoke
            ]);

            session()->flash('success', "Permission '{$this->permissionToRevoke}' revoked from {$this->selectedUser->name}");
            
            $this->reset(['justification', 'showRevokeModal', 'permissionToRevoke']);

        } catch (\Exception $e) {
            $this->addError('permission', $e->getMessage());
        }
    }

    public function closeModals()
    {
        $this->reset(['showGrantModal', 'showRevokeModal', 'selectedPermission', 'justification', 'permissionToRevoke']);
    }

    public function getAvailablePermissionsProperty()
    {
        if (!$this->selectedUser) {
            return collect();
        }

        // Get permissions the user doesn't already have
        $userPermissionNames = $this->selectedUser->permissions()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->pluck('name');

        return Permission::whereNotIn('name', $userPermissionNames)
                        ->orderBy('category')
                        ->orderBy('name')
                        ->get();
    }

    public function getUserPermissionsProperty()
    {
        if (!$this->selectedUser) {
            return collect();
        }

        return $this->selectedUser->permissions()
            ->wherePivot(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->with(['permissions'])
            ->get();
    }

    public function render()
    {
        $users = User::where(function ($query) {
                $query->where('email', 'like', '%' . $this->search . '%')
                      ->orWhere('firstname', 'like', '%' . $this->search . '%')
                      ->orWhere('surname', 'like', '%' . $this->search . '%');
            })
            ->orderBy('surname')
            ->orderBy('firstname')
            ->paginate(10);

        $permissionsByCategory = Permission::orderBy('category')
                                          ->orderBy('name')
                                          ->get()
                                          ->groupBy('category');

        return view('livewire.admin.permission-management', [
            'users' => $users,
            'permissionsByCategory' => $permissionsByCategory,
            'availablePermissions' => $this->availablePermissions,
            'userPermissions' => $this->userPermissions,
        ]);
    }
}