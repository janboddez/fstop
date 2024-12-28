<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\User;
use App\Support\ActivityPub\HttpSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InboxController extends Controller
{
    public function inbox(User $user = null, Request $request): Response
    {
        // We're going to want a valid signature header.
        abort_unless(is_string($signature = $request->header('signature')), 401, __('Missing signature'));
        $signatureData = HttpSignature::parseSignatureHeader($signature);
        abort_if(! is_array($signatureData), 403, __('Invalid signature'));

        // Dirty catch-all for our single-user blog.
        if (empty($user->id)) {
            $user = User::find(1);
        }

        // See if the used `keyId` somehow belongs to one of our followers.
        $follower = Follower::whereHas('meta', function ($query) use ($signatureData) {
            $query->where('key', 'key_id')
                ->where('value', json_encode((array) $signatureData['keyId']));
        })
        ->first();

        if (! empty($follower->public_key)) {
            $publicKey = $follower->public_key;
        } else {
            // Try and fetch the remote public key.
            $data = activitypub_fetch_profile($signatureData['keyId'], $user);
            $publicKey = $data['public_key'] ?? null;
        }

        if (empty($publicKey)) {
            abort(500, __('Failed to fetch public key'));
        }

        $verified = HttpSignature::verify($publicKey, $signatureData, $request);

        if (! $verified && ! empty($follower->public_key)) {
            // The key we stored previously may be outdated.
            $meta = activitypub_fetch_profile($signatureData['keyId'], $user);

            if (! empty($meta['public_key'])) {
                // Update meta.
                foreach (prepare_meta(array_keys($meta), array_values($meta), $follower) as $key => $value) {
                    $follower->meta()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }

                // Try again.
                $verified = HttpSignature::verify($meta['public_key'], $signatureData, $request);
            }
        }

        abort_unless($verified, 403, __('Invalid signature'));

        Log::debug(json_encode($request->all()));

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
