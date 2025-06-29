<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAccessService;
use App\Models\Person;
use App\Models\Couple;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Administrative controller for cross-team data access.
 * 
 * SECURITY: All access logged and requires explicit authorization.
 * Replaces dangerous developer bypasses with controlled, audited access.
 * 
 * This controller provides secure administrative functions for system
 * maintenance and cross-team operations in the genealogy application.
 */
class CrossTeamController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        // Require authentication and admin context
        $this->middleware(['auth', 'admin.context']);
    }
    
    /**
     * Get all people across teams (administrative access).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function allPeople(Request $request): JsonResponse
    {
        try {
            $people = AdminAccessService::getAllPeople();
            
            return response()->json([
                'success' => true,
                'data' => $people,
                'total' => $people->count(),
                'message' => 'Cross-team people data retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cross-team people data', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cross-team data: ' . $e->getMessage()
            ], 403);
        }
    }
    
    /**
     * Get all couples across teams (administrative access).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function allCouples(Request $request): JsonResponse
    {
        try {
            $couples = AdminAccessService::getAllCouples();
            
            return response()->json([
                'success' => true,
                'data' => $couples,
                'total' => $couples->count(),
                'message' => 'Cross-team couples data retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cross-team couples data', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cross-team data: ' . $e->getMessage()
            ], 403);
        }
    }
    
    /**
     * Get team statistics (administrative access).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function teamStatistics(Request $request): JsonResponse
    {
        try {
            $statistics = AdminAccessService::getCrossTeamStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Cross-team statistics retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cross-team statistics', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ], 403);
        }
    }
    
    /**
     * Get detailed information about a specific team (administrative access).
     * 
     * @param Request $request
     * @param int $teamId
     * @return JsonResponse
     */
    public function teamDetails(Request $request, int $teamId): JsonResponse
    {
        try {
            // This doesn't require AdminAccessService since we're just getting team info
            $team = Team::with(['users', 'owner'])
                ->withCount(['people', 'couples'])
                ->findOrFail($teamId);
            
            // Get team-specific data using AdminAccessService
            $teamPeople = AdminAccessService::withoutPersonTeamScope(function () use ($teamId) {
                return Person::where('team_id', $teamId)->with(['couples'])->get();
            });
            
            $teamCouples = AdminAccessService::withoutCoupleTeamScope(function () use ($teamId) {
                return Couple::where('team_id', $teamId)->with(['person1', 'person2'])->get();
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'team' => $team,
                    'people' => $teamPeople,
                    'couples' => $teamCouples,
                ],
                'message' => "Team {$teamId} details retrieved successfully"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve team details', [
                'error' => $e->getMessage(),
                'team_id' => $teamId,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team details: ' . $e->getMessage()
            ], 403);
        }
    }
    
    /**
     * Health check endpoint to verify administrative access is working.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function healthCheck(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Administrative access is working',
            'user' => [
                'id' => auth()->id(),
                'email' => auth()->user()->email,
                'is_developer' => auth()->user()->is_developer,
            ],
            'admin_context' => [
                'justification' => $request->header('X-Admin-Justification'),
                'timestamp' => now()->toISOString(),
            ]
        ]);
    }
}