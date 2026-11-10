<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Services\ImageService;
use CodeIgniter\HTTP\Files\UploadedFile;
use Tests\Support\DbTestCase;

/**
 * ImageService (spec §12).
 *
 * @internal
 */
final class ImageServiceTest extends DbTestCase
{
    private ImageService $images;

    /** @var list<string> */
    private array $temp = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->images = new ImageService($this->uploadRoot);
    }

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function upload(string $contents, string $clientName, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pix');
        file_put_contents($path, $contents);
        $this->temp[] = $path;

        return new UploadedFile($path, $clientName, $mime, strlen($contents), UPLOAD_ERR_OK);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function testProductImageIsReencodedToWebpWithThumbnailInTenantDirectory(): void
    {
        $stored = $this->images->store($this->upload($this->png(2000, 1000), 'kotu ad.PNG', 'image/png'), 'product', 7);

        $this->assertMatchesRegularExpression('#^uploads/r/7/products/[0-9a-f]{32}\.webp$#', $stored['path']);
        $this->assertMatchesRegularExpression('#^uploads/r/7/products/[0-9a-f]{32}_t\.webp$#', $stored['thumb']);

        [$w, $h, $type] = getimagesize($this->uploadRoot . $stored['path']);
        $this->assertSame([1600, 800, IMAGETYPE_WEBP], [$w, $h, $type]);

        [$tw, $th] = getimagesize($this->uploadRoot . $stored['thumb']);
        $this->assertSame([480, 240], [$tw, $th]);
    }

    public function testSmallImageIsNotUpscaled(): void
    {
        $stored = $this->images->store($this->upload($this->png(300, 200), 'a.png', 'image/png'), 'category', 3);

        [$w, $h] = getimagesize($this->uploadRoot . $stored['path']);
        $this->assertSame([300, 200], [$w, $h]);
        $this->assertNull($stored['thumb']);
        $this->assertStringStartsWith('uploads/r/3/categories/', $stored['path']);
    }

    public function testNonImageContentIsRejectedEvenWithImageExtension(): void
    {
        $this->expectException(ValidationException::class);

        $this->images->store($this->upload('<?php echo "x"; ?>', 'shell.png', 'image/png'), 'product', 1);
    }

    public function testSvgIsRejectedForNonAllergenTypes(): void
    {
        $this->expectException(ValidationException::class);

        $this->images->store($this->upload('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'a.svg', 'image/svg+xml'), 'logo', 1);
    }

    public function testAllergenSvgIsSanitized(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
            . '<script>alert(2)</script><circle cx="5" cy="5" r="4"/></svg>';

        $stored = $this->images->store($this->upload($svg, 'gluten.svg', 'image/svg+xml'), 'allergen');

        $this->assertMatchesRegularExpression('#^uploads/allergens/[0-9a-f]{32}\.svg$#', $stored['path']);
        $clean = (string) file_get_contents($this->uploadRoot . $stored['path']);
        $this->assertStringNotContainsStringIgnoringCase('script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringContainsString('circle', $clean);
    }

    public function testAllergenPngIsResizedTo128(): void
    {
        $stored = $this->images->store($this->upload($this->png(512, 512), 'a.png', 'image/png'), 'allergen');

        [$w, $h] = getimagesize($this->uploadRoot . $stored['path']);
        $this->assertSame([128, 128], [$w, $h]);
    }

    public function testOversizedFileIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        // Alerjen sınırı 512 KB.
        $this->images->store($this->upload(str_repeat('a', 600 * 1024), 'a.png', 'image/png'), 'allergen');
    }

    public function testTenantImageRequiresRestaurantId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->images->store($this->upload($this->png(10, 10), 'a.png', 'image/png'), 'product');
    }

    public function testDeleteRemovesFileAndIgnoresSuspiciousPaths(): void
    {
        $stored = $this->images->store($this->upload($this->png(50, 50), 'a.png', 'image/png'), 'logo', 2);
        $this->assertTrue($this->images->exists($stored['path']));

        $outside = $this->uploadRoot . 'secret.webp';
        file_put_contents($outside, 'x');

        $this->images->delete('../secret.webp', 'uploads/../secret.webp', 'secret.webp', null, '', '/etc/passwd');
        $this->assertFileExists($outside);

        $this->images->delete($stored['path']);
        $this->assertFalse($this->images->exists($stored['path']));
    }
}
