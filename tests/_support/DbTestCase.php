<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Veritabanı + HTTP feature testlerinin tabanı.
 *
 * Şema test çalıştırması başına bir kez kurulur (pixmenu_test); her testten önce
 * tüm tablolar boşaltılır ve test kendi fixture'ını kurar.
 *
 * @internal
 */
abstract class DbTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected Fixture $fx;

    /** Testlerin dosya yazdığı geçici upload kökü. */
    protected string $uploadRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateAll();
        cache()->clean();

        // CSRF ayrı testte doğrulanır; diğer feature testlerinde global filtre kapatılır.
        $filters = config('Filters');
        unset($filters->globals['before']['csrf']);

        $this->uploadRoot = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'pixmenu_test_' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR;
        mkdir($this->uploadRoot, 0777, true);
        Services::injectMock('images', new \App\Services\ImageService($this->uploadRoot));

        $this->fx = new Fixture($this->db);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->uploadRoot);

        parent::tearDown();
    }

    private function truncateAll(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->db->listTables() as $table) {
            if ($table === 'migrations') {
                continue;
            }

            $this->db->query('TRUNCATE TABLE ' . $this->db->escapeIdentifiers($table));
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    // ---------------------------------------------------------------
    // Oturum yardımcıları
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $user restaurant_users satırı
     */
    protected function asPanelUser(array $user): static
    {
        return $this->withSession([
            'panel_auth' => [
                'id'            => (int) $user['id'],
                'restaurant_id' => (int) $user['restaurant_id'],
                'login_at'      => time(),
            ],
        ]);
    }

    protected function asAdmin(?int $adminId = null): static
    {
        return $this->withSession([
            'admin_auth' => ['id' => $adminId ?? $this->fx->adminId, 'login_at' => time()],
        ]);
    }

    protected function assertRedirectsTo(TestResponse $response, string $path): void
    {
        $response->assertRedirect();
        $this->assertSame(
            rtrim(site_url($path), '/'),
            rtrim((string) $response->getRedirectUrl(), '/'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function row(string $table, int $id): ?array
    {
        return $this->db->table($table)->where('id', $id)->get()->getRowArray();
    }
}
