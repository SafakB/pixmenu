<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\BranchModel;
use App\Models\MenuTemplateModel;
use App\Services\Concerns\Transactional;

/**
 * Menü tasarım şablonları (global, yalnızca admin).
 *
 * `standart` şablonu silinemez ve pasifleştirilemez (yeni şubelerin varsayılanı ve
 * tasarım dosyası olmayan şablonların düştüğü yer). Şubede kullanılan şablon da
 * silinemez/pasifleştirilemez.
 */
final class MenuTemplateService
{
    use Transactional;

    public const CODE_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * @param array{code: string, name: string, description: string, is_active: int, sort_order: int} $data
     */
    public function create(array $data): string
    {
        // `new` ve `reorder` rota segmentleriyle çakışır.
        if (preg_match(self::CODE_PATTERN, $data['code']) !== 1 || strlen($data['code']) > 30
            || in_array($data['code'], ['new', 'reorder'], true)) {
            throw ValidationException::single('code', lang('App.template.codeInvalid'));
        }

        $model = new MenuTemplateModel();

        if ($model->find($data['code']) !== null) {
            throw ValidationException::single('code', lang('App.template.codeTaken'));
        }

        $model->insert([
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
            'is_active'   => $data['is_active'],
            'sort_order'  => $data['sort_order'],
        ]);

        return $data['code'];
    }

    /**
     * `code` oluşturulduktan sonra DEĞİŞTİRİLEMEZ.
     *
     * @param array<string, mixed>                                                      $template mevcut kayıt
     * @param array{name: string, description: string, is_active: int, sort_order: int} $data
     */
    public function update(array $template, array $data): void
    {
        $code = (string) $template['code'];

        if ((int) $template['is_active'] === 1 && $data['is_active'] === 0) {
            $this->assertCanDeactivate($code);
        }

        (new MenuTemplateModel())->update($code, [
            'name'        => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
            'is_active'   => $data['is_active'],
            'sort_order'  => $data['sort_order'],
        ]);
    }

    /**
     * @param array<string, mixed> $template
     */
    public function toggle(array $template): void
    {
        $code    = (string) $template['code'];
        $newFlag = (int) $template['is_active'] === 1 ? 0 : 1;

        if ($newFlag === 0) {
            $this->assertCanDeactivate($code);
        }

        (new MenuTemplateModel())->update($code, ['is_active' => $newFlag]);
    }

    /**
     * @param array<string, mixed> $template
     */
    public function delete(array $template): void
    {
        $code = (string) $template['code'];

        if ($code === MenuTemplateModel::DEFAULT_CODE) {
            throw new BusinessRuleException(lang('App.template.defaultLocked'));
        }

        // Silinmiş şubeler de sayılır: FK RESTRICT onları da korur.
        $count = (new BranchModel())->countUsingTemplateGlobally($code, true);

        if ($count > 0) {
            throw new BusinessRuleException(lang('App.template.inUse', [$count]));
        }

        (new MenuTemplateModel())->delete($code);
    }

    /**
     * @param list<string> $codes
     */
    public function reorder(array $codes): void
    {
        $model = new MenuTemplateModel();

        if ($codes === [] || $model->whereIn('code', $codes)->countAllResults() !== count($codes)) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        $this->atomic(static function () use ($codes, $model): void {
            foreach ($codes as $index => $code) {
                $model->update($code, ['sort_order' => $index]);
            }
        });
    }

    private function assertCanDeactivate(string $code): void
    {
        if ($code === MenuTemplateModel::DEFAULT_CODE) {
            throw new BusinessRuleException(lang('App.template.defaultLocked'));
        }

        $count = (new BranchModel())->countUsingTemplateGlobally($code);

        if ($count > 0) {
            throw new BusinessRuleException(lang('App.template.inUse', [$count]));
        }
    }
}
