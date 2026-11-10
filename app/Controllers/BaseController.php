<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Tüm controller'ların tabanı. Controller'lar incedir: doğrulama + servis
 * çağrısı + yanıt. İş mantığı app/Services altındadır (spec §2.4).
 */
abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form', 'text', 'pixmenu'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
    }

    /**
     * Bulunamadı / başka tenant / yetkisiz — hepsi AYNI istisna (spec §2.1.6).
     */
    protected function notFound(): never
    {
        throw PageNotFoundException::forPageNotFound();
    }

    protected function wantsJson(): bool
    {
        return wants_json($this->request);
    }

    /**
     * @param array<string, mixed>|object $data
     */
    protected function jsonSuccess(array|object $data = [], string $message = '', int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status'  => 'success',
            'data'    => $data === [] ? new \stdClass() : $data,
            'message' => $message,
        ]);
    }

    /**
     * @param array<string, string> $errors
     */
    protected function jsonError(string $message, array $errors = [], int $status = 422): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(json_error_body($message, $errors));
    }

    /**
     * Doğrulama hatası yanıtı: HTML → geri dön + eski girdi + errors; JSON → 422.
     *
     * @param array<string, string> $errors
     */
    protected function failValidation(array $errors): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->jsonError(lang('App.validationFailed'), $errors, 422);
        }

        return redirect()->back()->withInput()->with('errors', $errors);
    }

    /**
     * İş kuralı reddi: HTML → geri dön + error flash; JSON → 422.
     */
    protected function failRule(string $message): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->jsonError($message, [], 422);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    /**
     * Başarılı yazma sonrası Post/Redirect/Get (JSON isteğinde 200 JSON).
     *
     * @param array<string, mixed> $data
     */
    protected function done(string $to, string $message, array $data = []): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->jsonSuccess($data, $message);
        }

        return $this->redirectTo($to)->with('success', $message);
    }

    protected function redirectTo(string $path): RedirectResponse
    {
        return redirect()->to(site_url($path));
    }

    /**
     * CI4 doğrulamasını çalıştırır; hata varsa ValidationException fırlatır.
     *
     * @param array<string, mixed> $rules
     */
    protected function validateOrFail(array $rules): void
    {
        if (! $this->validate($rules)) {
            throw new ValidationException($this->validator->getErrors());
        }
    }

    /**
     * JSON gövdesinden {"ids":[...]} okur (reorder uçları).
     *
     * @return list<int>
     *
     * @throws ValidationException
     */
    protected function jsonIds(): array
    {
        try {
            $body = $this->request->getJSON(true);
        } catch (\Throwable) {
            $body = null;
        }

        $ids = is_array($body) ? ($body['ids'] ?? null) : null;

        if (! is_array($ids) || $ids === []) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        $clean = [];

        foreach ($ids as $id) {
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw ValidationException::single('ids', lang('App.reorder.invalid'));
            }

            $clean[] = (int) $id;
        }

        if (count($clean) !== count(array_unique($clean)) || count($clean) > 1000) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        return $clean;
    }

    /**
     * POST'tan translations[code][field] dizisini güvenli biçimde alır.
     *
     * @return array<string, array<string, string>>
     */
    protected function translationsInput(): array
    {
        $raw = $this->request->getPost('translations');

        if (! is_array($raw)) {
            return [];
        }

        $clean = [];

        foreach ($raw as $code => $fields) {
            if (! is_string($code) || ! is_array($fields)) {
                continue;
            }

            foreach ($fields as $field => $value) {
                if (is_string($field) && (is_string($value) || is_numeric($value))) {
                    $clean[$code][$field] = (string) $value;
                }
            }
        }

        return $clean;
    }

    /**
     * Yüklenmiş (ve gerçekten gönderilmiş) dosyayı döndürür; yoksa null.
     */
    protected function uploaded(string $field): ?\CodeIgniter\HTTP\Files\UploadedFile
    {
        $file = $this->request->getFile($field);

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }
}
