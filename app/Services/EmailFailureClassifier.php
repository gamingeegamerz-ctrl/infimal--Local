<?php

namespace App\Services;

class EmailFailureClassifier
{
    public static function classify(string $error): string
    {
        $error = strtolower($error);

        if (str_contains($error, 'spam')) {
            return 'spam';
        }

        if (
            str_contains($error, 'hard bounce') ||
            str_contains($error, 'invalid address') ||
            str_contains($error, 'user unknown') ||
            str_contains($error, 'no such user')
        ) {
            return 'hard_bounce';
        }

        if (
            str_contains($error, 'authentication failed') ||
            str_contains($error, 'auth failed')
        ) {
            return 'auth_error';
        }

        if (
            str_contains($error, 'rate limit') ||
            str_contains($error, 'too many requests') ||
            str_contains($error, 'timeout')
        ) {
            return 'temporary';
        }

        return 'temporary';
    }
}
