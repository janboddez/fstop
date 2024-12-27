<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Heavily inspired by both {@link https://github.com/pixelfed/pixelfed/blob/dev/app/Util/ActivityPub/HttpSignature.php}
 * and {@link https://github.com/aaronpk/Nautilus/blob/master/app/ActivityPub/HTTPSignature.php}.
 */
class HttpSignature
{
    public static function sign(
        User $user,
        string $url,
        string $body = null,
        array $extraHeaders = [],
        string $method = 'post'
    ): array {
        if ($body) {
            $digest = base64_encode(hash('sha256', $body, true));
        }

        $headers = static::headersToSign($url, $digest ?? null, $method);
        $headers = array_merge($headers, $extraHeaders);

        $stringToSign = static::headersToSigningString($headers);
        $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));

        $key = openssl_pkey_get_private($user->private_key);
        openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);

        // phpcs:ignore Generic.Files.LineLength.TooLong
        $headers['Signature'] = 'keyId="' . $user->actor_url . '#main-key",headers="' . $signedHeaders . '",algorithm="rsa-sha256",signature="' . base64_encode($signature) . '"';
        unset($headers['(request-target)']);

        return $headers;

        // return static::headersToCurlArray($headers);
    }

    public static function parseSignatureHeader(string $signature): array
    {
        $parts = explode(',', $signature);
        $signatureData = [];

        foreach ($parts as $part) {
            if (preg_match('/(.+)="(.+)"/', $part, $match)) {
                $signatureData[$match[1]] = $match[2];
            }
        }

        if (! isset($signatureData['keyId'])) {
            return [
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'error' => 'No keyId was found in the signature header. Found: ' . implode(', ', array_keys($signatureData)),
            ];
        }

        if (! filter_var($signatureData['keyId'], FILTER_VALIDATE_URL)) {
            return [
                'error' => 'keyId is not a URL: ' . $signatureData['keyId'],
            ];
        }

        if (! isset($signatureData['headers']) || ! isset($signatureData['signature'])) {
            return [
                'error' => 'Signature is missing headers or signature parts',
            ];
        }

        return $signatureData;
    }

    public static function verify(string $publicKey, array $signatureData, Request $request): array
    {
        $inputHeaders = $request->headers->all();
        $path = $request->getRequestUri();
        $body = $request->getContent();

        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));

        $headersToSign = [];
        foreach (explode(' ', $signatureData['headers']) as $h) {
            if ($h == '(request-target)') {
                $headersToSign[$h] = 'post ' . $path;
            } elseif ($h == 'digest') {
                $headersToSign[$h] = $digest;
            } elseif (isset($inputHeaders[$h][0])) {
                $headersToSign[$h] = $inputHeaders[$h][0];
            }
        }

        $signingString = static::headersToSigningString($headersToSign);
        $verified = openssl_verify(
            $signingString,
            base64_decode($signatureData['signature']),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        return [$verified, $signingString];
    }

    protected static function headersToSigningString(array $headers): string
    {
        return implode(
            "\n",
            array_map(
                fn ($k, $v) => strtolower($k) . ': ' . $v,
                array_keys($headers),
                $headers
            )
        );
    }

    protected static function headersToCurlArray(array $headers): array
    {
        return array_map(
            fn ($k, $v) => "$k: $v",
            array_keys($headers),
            $headers
        );
    }

    protected static function headersToSign(string $url, string $digest = null, $method = 'post'): array
    {
        $headers = [
          '(request-target)' => "$method " . parse_url($url, PHP_URL_PATH),
          'Date' => now()->format('D, d M Y H:i:s \G\M\T'),
          'Host' => parse_url($url, PHP_URL_HOST),
          'Content-Type' => 'application/activity+json',
        ];

        if ($digest) {
            $headers['Digest'] = 'SHA-256=' . $digest;
        }

        return $headers;
    }
}
