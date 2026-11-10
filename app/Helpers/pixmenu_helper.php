<?php

declare(strict_types=1);

use CodeIgniter\HTTP\RequestInterface;

if (! function_exists('wants_json')) {
    /**
     * İstek JSON yanıt bekliyor mu? (AJAX, JSON gövde veya Accept: application/json)
     */
    function wants_json(RequestInterface $request): bool
    {
        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return true;
        }

        $accept      = strtolower($request->getHeaderLine('Accept'));
        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}

if (! function_exists('json_error_body')) {
    /**
     * Spec §11.2 hata gövdesi.
     *
     * @param array<string, string> $errors
     *
     * @return array<string, mixed>
     */
    function json_error_body(string $message, array $errors = []): array
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return $body;
    }
}

if (! function_exists('upload_url')) {
    /**
     * DB'deki göreli upload yolunu tam URL'ye çevirir.
     */
    function upload_url(?string $path): ?string
    {
        return ($path === null || $path === '') ? null : base_url($path);
    }
}
