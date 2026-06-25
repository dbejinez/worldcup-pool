<?php

namespace App\Http\Controllers;

use App\Models\Pool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPoolController extends Controller
{
    /**
     * Pending pool-creation requests (global admin only).
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $pools = Pool::whereNull('approved_at')
            ->with('creator')
            ->latest()
            ->get();

        return view('admin.pools.index', compact('pools'));
    }

    public function approve(Request $request, Pool $pool): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $pool->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', "Approved \"{$pool->name}\".");
    }

    public function reject(Request $request, Pool $pool): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        // Only reject pools that are still pending.
        abort_if($pool->isApproved(), 403, 'This pool is already approved.');

        $name = $pool->name;
        $pool->delete();

        return back()->with('status', "Rejected and removed \"{$name}\".");
    }
}
