<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Throwable;

/**
 * Çok tablolu yazmalar için transaction sarmalayıcı (spec §2.4, §12).
 */
trait Transactional
{
    /**
     * $work'ü transaction içinde çalıştırır. Hata olursa geri alır, $onFailure'ı
     * (ör. diske yazılmış yeni dosyaları silmek için) çağırır ve hatayı yeniden fırlatır.
     *
     * @template T
     *
     * @param callable(): T        $work
     * @param (callable(): void)|null $onFailure
     *
     * @return T
     */
    private function atomic(callable $work, ?callable $onFailure = null): mixed
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $result = $work();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction başarısız.');
            }

            $db->transCommit();

            return $result;
        } catch (Throwable $e) {
            $db->transRollback();

            if ($onFailure !== null) {
                $onFailure();
            }

            throw $e;
        }
    }
}
