<?php

namespace App\MoonShine\Palettes;

use MoonShine\ColorManager\ColorMutator;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\PaletteContract;

final class DogLivePalette implements PaletteContract
{
    public function getDescription(): string
    {
        return 'DogLive sky and meadow';
    }

    public function getColors(): array
    {
        return $this->colors(false);
    }

    public function getDarkColors(): array
    {
        return $this->colors(true);
    }

    /** @return array<string, string|array<string|int, string>> */
    private function colors(bool $dark): array
    {
        $palette = new PurplePalette;
        $colors = $dark ? $palette->getDarkColors() : $palette->getColors();
        $swatches = $dark
            ? ['body' => '#132c3b', 'primary' => '#7fb8d6', 'primary-text' => '#102c3d', 'secondary' => '#284c62', 'secondary-text' => '#d6ebf5']
            : ['body' => '#eaf5fa', 'primary' => '#367da5', 'primary-text' => '#ffffff', 'secondary' => '#dceef7', 'secondary-text' => '#326584'];

        foreach ($swatches as $key => $color) {
            $colors[$key] = ColorMutator::toOKLCH($color);
        }

        $base = $dark
            ? ['text' => '#e3eff5', 'stroke' => '#355567', 'default' => '#1b3647', 50 => '#193342', 100 => '#223e50', 200 => '#284c62', 300 => '#355567', 400 => '#47697c', 500 => '#647e8e', 600 => '#7fb8d6', 700 => '#a5becb', 800 => '#d6ebf5', 900 => '#e3eff5']
            : ['text' => '#294b60', 'stroke' => '#d5e5ed', 'default' => '#f9fcff', 50 => '#f5faff', 100 => '#eaf5fa', 200 => '#dceef7', 300 => '#c8dce7', 400 => '#a5c6d9', 500 => '#7fabc0', 600 => '#647e8e', 700 => '#367da5', 800 => '#326584', 900 => '#294b60'];

        $colors['base'] = array_map(ColorMutator::toOKLCH(...), $base);

        return $colors;
    }
}
