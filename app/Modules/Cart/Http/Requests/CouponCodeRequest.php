<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Requests;

use App\Modules\Cart\Data\CouponCodeData;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a coupon code typed at checkout (roadmap C5.2, S20); the checkout form (S25) uses the same
 * baseRules(). Codes are letters, digits and «-», upper-cased with Latin digits before checking.
 */
final class CouponCodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'code.required' => 'کد تخفیف را وارد کنید.',
            'code.min' => 'کد تخفیف معتبر نیست.',
            'code.max' => 'کد تخفیف معتبر نیست.',
            'code.regex' => 'کد تخفیف فقط حروف انگلیسی، عدد و «-» دارد.',
        ];
    }

    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }

    public function toDto(int $cartId): CouponCodeData
    {
        return CouponCodeData::fromArray(['cart_id' => $cartId, 'code' => (string) $this->validated('code'), 'customer_id' => (int) $this->user('customer')?->getAuthIdentifier()]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Coupon::normalizeCode((string) $this->input('code'))]);
    }
}
