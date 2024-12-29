<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\User;
use App\Support\ActivityPub\HttpSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InboxController extends Controller
{
    public function inbox(User $user, Request $request): Response
    {
        // We're going to want a valid signature header.
        abort_unless(is_string($signature = $request->header('signature')), 401, __('Missing signature'));
        $signatureData = HttpSignature::parseSignatureHeader($signature);
        abort_if(! is_array($signatureData), 403, __('Invalid signature'));

        // See if the used `keyId` somehow belongs to one of the profiles known to us.
        $actor = Actor::whereHas('meta', function ($query) use ($signatureData) {
            $query->where('key', 'key_id')
                ->where('value', json_encode((array) $signatureData['keyId']));
        })
        ->first();

        if ($actor || $request->input('type') !== 'Delete') {
            // Only log deletes for or by actors we know. Other requests are okay.
            Log::debug($request->path());
            Log::debug(json_encode($request->all()));
        }

        if (! empty($actor->public_key)) {
            $publicKey = $actor->public_key;
        } else {
            // Try and fetch the remote public key.
            $data = activitypub_fetch_profile($signatureData['keyId'], $user);
            $publicKey = $data['public_key'] ?? null;
        }

        if (empty($publicKey)) {
            abort(500, __('Failed to fetch public key'));
        }

        $verified = HttpSignature::verify($publicKey, $signatureData, $request);

        if (! $verified && ! empty($actor->public_key)) {
            // Our `$actor->public_key` may be outdated.
            $meta = activitypub_fetch_profile($signatureData['keyId'], $user);

            if (! empty($meta['public_key'])) {
                // Update the actor's meta ...
                foreach (prepare_meta(array_keys($meta), array_values($meta), $actor) as $key => $value) {
                    $actor->meta()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }

                // ... and try again.
                $verified = HttpSignature::verify($meta['public_key'], $signatureData, $request);
            }
        }

        abort_unless($verified, 403, __('Invalid signature')); // Still no dice.

        $type = $request->input('type');
        if (! in_array($type, ['Create', 'Delete', 'Follow', 'Like', 'Undo', 'Update'])) {
            // Unsupported activity.
            return response()->json(new \stdClass(), 202);
        }

        // Do stuff.
        $class = '\\App\\Support\\ActivityPub\\' . $type . 'Handler';
        (new $class($request, $user))->handle();

        return response()->json(new \stdClass(), 202);
    }
}
