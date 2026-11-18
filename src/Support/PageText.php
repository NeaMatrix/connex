<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Support;

/**
 * Page copy that comes from config and may differ per language.
 */
final class PageText
{
    /**
     * The price lines Connex requires on the landing page (cptpl_price), from
     * config("connex.pages.price"): a list of lines, or lists keyed by locale.
     *
     * @return list<string>
     */
    public static function priceLines(): array
    {
        $price = config('connex.pages.price', []);

        if (! is_array($price)) {
            return [];
        }

        if (array_is_list($price)) {
            return array_values(array_filter($price, 'is_string'));
        }

        $lines = $price[app()->getLocale()] ?? reset($price);

        return is_array($lines) ? array_values(array_filter($lines, 'is_string')) : [];
    }
}
