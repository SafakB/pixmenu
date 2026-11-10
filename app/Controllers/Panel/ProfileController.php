<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Services\Scope;
use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Kullanıcının KENDİ profili. Hedef kullanıcı her zaman oturumdaki kullanıcıdır;
 * request'ten kullanıcı ID'si okunmaz.
 */
final class ProfileController extends PanelBaseController
{
    /**
     * View: panel/profile — user{id, name, email, role}
     */
    public function edit(): string
    {
        return $this->render('panel/profile', [
            'user' => [
                'id'    => (int) $this->currentUser['id'],
                'name'  => $this->currentUser['name'],
                'email' => $this->currentUser['email'],
                'role'  => $this->currentUser['role'],
            ],
        ]);
    }

    public function update(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            (new UserService(Scope::tenant()))->updateProfile(
                $this->currentUser,
                (string) $this->request->getPost('name'),
                (string) $this->request->getPost('email'),
            );

            return $this->done('panel/profile', lang('App.saved'));
        });
    }

    public function password(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            (new UserService(Scope::tenant()))->changeOwnPassword(
                $this->currentUser,
                (string) $this->request->getPost('current_password'),
                (string) $this->request->getPost('password'),
                (string) $this->request->getPost('password_confirm'),
            );

            // Bu oturum açık kalır; diğer cihazlardaki oturumlar password_changed_at ile düşer.
            service('panelAuth')->refreshLoginTime();

            return $this->done('panel/profile', lang('App.user.passwordChanged'));
        });
    }
}
