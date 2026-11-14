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
        // İşlem kaydı: yalnızca POST'ta yazılır (ActivityLogFilter).
        service('activity')->succeed();

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

        service('activity')->succeed();

        return $this->redirectTo($to)->with('success', $message);
    }

    /**
     * Servis çağrısını sarar: doğrulama ve iş kuralı hatalarını uygun yanıta çevirir.
     *
     * @param callable(): ResponseInterface $action
     */
    protected function handle(callable $action): ResponseInterface
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            return $this->failValidation($e->errors());
        } catch (\App\Exceptions\BusinessRuleException $e) {
            return $this->failRule($e->getMessage());
        }
    }

    /**
     * POST'taki ID listesi (ör. language_ids[]). Sayı olmayan değerler -1 olur ve
     * servis doğrulamasında "geçersiz" olarak reddedilir (sessizce atılmaz).
     *
     * @return list<int>
     */
    protected function intList(string $field): array
    {
        $raw = $this->request->getPost($field);

        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            $ids[] = (is_string($value) && ctype_digit($value) && (int) $value > 0) ? (int) $value : -1;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Checkbox alanı → 0/1.
     */
    protected function flag(string $field): int
    {
        return in_array((string) $this->request->getPost($field), ['1', 'on', 'true'], true) ? 1 : 0;
    }

    /**
     * sort_order alanı (boşsa $default).
     *
     * @throws ValidationException
     */
    protected function sortOrder(int $default = 0): int
    {
        $raw = trim((string) $this->request->getPost('sort_order'));

        if ($raw === '') {
            return $default;
        }

        if (! ctype_digit($raw) || (int) $raw > 65535) {
            throw ValidationException::single('sort_order', lang('App.field.sortOrder'));
        }

        return (int) $raw;
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
        // CI4, kural yokken run() için false döndürür; boş kural kümesi "geçerli" sayılır.
        if ($rules === []) {
            return;
        }

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
     * Dosya gönderildiyse o alanın CI4 doğrulama kurallarını, gönderilmediyse boş dizi döndürür.
     *
     * @return array<string, string>
     */
    protected function imageRules(string $field, string $type): array
    {
        if ($this->uploaded($field) === null) {
            return [];
        }

        return [$field => service('images')->rules($field, $type)];
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
