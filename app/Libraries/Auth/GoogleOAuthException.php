<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

use RuntimeException;

/**
 * Google token/userinfo çağrısı başarısız (ağ hatası, geçersiz kod, beklenmeyen yanıt).
 * Mesaj yalnızca günlüğe yazılır; kullanıcıya genel bir hata gösterilir.
 */
final class GoogleOAuthException extends RuntimeException
{
}
