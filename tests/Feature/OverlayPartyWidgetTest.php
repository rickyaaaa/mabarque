<?php

namespace Tests\Feature;

use Tests\TestCase;

class OverlayPartyWidgetTest extends TestCase
{
    public function test_party_widget_is_bottom_anchored_for_variable_party_sizes(): void
    {
        $css = file_get_contents(public_path('css/overlay.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('body.overlay-body.is-widget { min-height: 0; height: 100vh;', $css);
        $this->assertStringContainsString('body.widget-party .overlay-card { display: flex; height: 100%;', $css);
        $this->assertStringContainsString('body.widget-party .overlay-party { display: flex; min-height: 0; margin-top: auto; flex-direction: column-reverse;', $css);
    }
}
