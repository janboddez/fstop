<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __invoke(Request $request, User $user): Response|View
    {

        if (request()->expectsJson()) {
            return response()->json(
                $user->serialize(),
                200,
                ['Content-Type' => 'application/activity+json']
            );
        }

        $entries = $user->entries()
            ->whereIn('type', get_registered_entry_types('slug', 'page'))
            ->orderBy('published', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->with('user')
            ->simplePaginate();

        return view('theme::users.show', compact('user', 'entries'));
    }
}
