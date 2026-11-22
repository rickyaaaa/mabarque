<?php

namespace Tests\Feature;

use Tests\TestCase;

class OverlayPackagesWidgetTest extends TestCase
{
    public function test_package_widget_has_two_grouped_package_sections(): void
    {
        $script = file_get_contents(public_path('js/overlay.js'));
        $css = file_get_contents(public_path('css/overlay.css'));

        $this->assertIsString($script);
        $this->assertIsString($css);
        $this->assertStringContainsString('label: "MABAR VIP"', $script);
        $this->assertStringContainsString('label: "LAYANAN LAIN"', $script);
        $this->assertStringContainsString('Number(pkg.games) > 0', $script);
        $this->assertStringContainsString('Belum ada paket aktif', $script);
        $this->assertStringContainsString('overlay-package-group', $css);
        $this->assertStringContainsString('overlay-package-heading', $css);
    }
}
