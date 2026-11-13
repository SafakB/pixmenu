<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Services\AdminAccountService;
use CodeIgniter\Test\StreamFilterTrait;
use InvalidArgumentException;
use Tests\Support\DbTestCase;

/**
 * Admin hesapları: AdminAccountService + `admin:*` komutları.
 *
 * @internal
 */
final class AdminAccountTest extends DbTestCase
{
    use StreamFilterTrait;

    public function testCreateWithGeneratedPassword(): void
    {
        $result = (new AdminAccountService())->create('Ayşe', ' Ayse@Test.Local ', 'accountant');

        $this->assertTrue($result['generated']);
        $this->assertSame(16, strlen($result['password']));

        $row = $this->db->table('admins')->where('id', $result['id'])->get()->getRowArray();
        $this->assertSame('ayse@test.local', $row['email']);
        $this->assertSame('accountant', $row['role']);
        $this->assertTrue(password_verify($result['password'], $row['password_hash']));
    }

    public function testCreateValidation(): void
    {
        $service = new AdminAccountService();

        foreach ([
            ['', 'a@test.local', 'manager', null],
            ['A', 'gecersiz', 'manager', null],
            ['A', 'a@test.local', 'ghost', null],
            ['A', 'a@test.local', 'manager', 'kisa'],
            ['A', 'admin@test.local', 'manager', null], // fixture'da var
        ] as [$name, $email, $role, $password]) {
            try {
                $service->create($name, $email, $role, $password);
                $this->fail("Beklenen hata gelmedi: {$name} / {$email} / {$role}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, $this->db->table('admins')->countAllResults());
    }

    public function testUpdateRoleActiveAndPassword(): void
    {
        $id      = $this->fx->createAdmin('manager');
        $service = new AdminAccountService();

        $service->update('manager@test.local', ['role' => 'accountant', 'is_active' => false]);
        $this->seeInDatabase('admins', ['id' => $id, 'role' => 'accountant', 'is_active' => 0]);

        $result = $service->update('manager@test.local', ['password' => null]);
        $row    = $this->db->table('admins')->where('id', $id)->get()->getRowArray();
        $this->assertTrue(password_verify((string) $result['password'], $row['password_hash']));
        $this->assertNotNull($row['password_changed_at']);
    }

    public function testLastActiveSuperAdminIsProtected(): void
    {
        $service = new AdminAccountService();

        foreach ([['is_active' => false], ['role' => 'manager']] as $changes) {
            try {
                $service->update('admin@test.local', $changes);
                $this->fail('Son süper admin korunmadı');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->seeInDatabase('admins', ['id' => $this->fx->adminId, 'role' => 'super_admin', 'is_active' => 1]);

        // Pasif bir süper admin sayılmaz.
        $this->fx->createAdmin('super_admin', 0, 'pasif@test.local');
        $this->expectException(InvalidArgumentException::class);
        $service->update('admin@test.local', ['is_active' => false]);
    }

    public function testSuperAdminCanBeDemotedWhenAnotherExists(): void
    {
        $this->fx->createAdmin('super_admin', 1, 'ikinci@test.local');

        (new AdminAccountService())->update('admin@test.local', ['role' => 'manager']);

        $this->seeInDatabase('admins', ['id' => $this->fx->adminId, 'role' => 'manager']);
    }

    public function testCommands(): void
    {
        command('admin:create --name Muhasebe --email muhasebe@test.local --role accountant');
        $this->assertStringContainsString('Admin oluşturuldu', $this->getStreamFilterBuffer());
        $this->assertStringContainsString('Parola', $this->getStreamFilterBuffer());
        $this->seeInDatabase('admins', ['email' => 'muhasebe@test.local', 'role' => 'accountant', 'is_active' => 1]);

        command('admin:update muhasebe@test.local --role=manager --active 0');
        $this->seeInDatabase('admins', ['email' => 'muhasebe@test.local', 'role' => 'manager', 'is_active' => 0]);

        $this->resetStreamFilterBuffer();
        command('admin:list');
        $this->assertStringContainsString('muhasebe@test.local', $this->getStreamFilterBuffer());
        $this->assertStringContainsString('Yönetici', $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('admin:update admin@test.local --active 0');
        $this->assertStringContainsString('Son aktif süper admin', $this->getStreamFilterBuffer());
        $this->seeInDatabase('admins', ['id' => $this->fx->adminId, 'is_active' => 1]);
    }
}
