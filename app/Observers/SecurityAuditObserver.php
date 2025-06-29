<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\Person;
use App\Models\Couple;
use App\Services\SecurityAuditService;

/**
 * Security observer for automatic audit logging of model changes.
 */
class SecurityAuditObserver
{
    /**
     * Handle User model events.
     */
    public function userUpdated(User $user): void
    {
        $changes = $user->getChanges();
        
        // Log security-sensitive changes
        if (isset($changes['is_developer'])) {
            SecurityAuditService::logAdminAction('developer_flag_changed', [
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'old_value' => $user->getOriginal('is_developer'),
                'new_value' => $user->is_developer,
                'change_type' => 'developer_privilege_modification',
                'requires_immediate_review' => true,
            ]);
        }
        
        if (isset($changes['email'])) {
            SecurityAuditService::logUserAction('email_changed', [
                'target_user_id' => $user->id,
                'old_email' => $user->getOriginal('email'),
                'new_email' => $user->email,
                'verification_required' => true,
            ]);
        }
        
        if (isset($changes['password'])) {
            SecurityAuditService::logUserAction('password_changed', [
                'target_user_id' => $user->id,
                'change_method' => 'user_initiated',
                'security_level' => 'medium',
            ]);
        }
    }
    
    /**
     * Handle Person model events.
     */
    public function personCreated(Person $person): void
    {
        SecurityAuditService::logGenealogyAction('person_created', $person, [
            'person_name' => $person->firstname . ' ' . $person->surname,
            'team_id' => $person->team_id,
            'creation_method' => 'manual_entry',
        ]);
    }
    
    public function personUpdated(Person $person): void
    {
        SecurityAuditService::logGenealogyAction('person_updated', $person, [
            'person_name' => $person->firstname . ' ' . $person->surname,
            'changes' => $person->getChanges(),
            'modification_type' => 'genealogy_data_update',
        ]);
    }
    
    public function personDeleted(Person $person): void
    {
        SecurityAuditService::logGenealogyAction('person_deleted', $person, [
            'person_name' => $person->firstname . ' ' . $person->surname,
            'deletion_type' => 'hard_delete',
            'requires_review' => true,
            'irreversible_action' => true,
        ]);
    }
    
    /**
     * Handle Couple model events.
     */
    public function coupleCreated(Couple $couple): void
    {
        SecurityAuditService::logGenealogyAction('couple_created', $couple, [
            'couple_id' => $couple->id,
            'team_id' => $couple->team_id,
            'relationship_type' => 'marriage_partnership',
        ]);
    }
    
    public function coupleDeleted(Couple $couple): void
    {
        SecurityAuditService::logGenealogyAction('couple_deleted', $couple, [
            'couple_id' => $couple->id,
            'deletion_type' => 'relationship_removed',
            'requires_review' => true,
        ]);
    }
}