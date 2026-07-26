<?php

namespace Tests\Feature;

use App\Helpers\CmsHtmlCompiler;
use Tests\TestCase;

class ContactFormPublishingTest extends TestCase
{
    public function test_a_published_contact_form_posts_to_the_connector_with_the_required_fields(): void
    {
        $html = CmsHtmlCompiler::compile([[
            'type' => 'contact_form_modern',
            'theme' => 'primary',
        ]], 'midnight');

        $this->assertStringContainsString("action='./cosmic-sync/contact.php'", $html);
        $this->assertStringContainsString("name='name'", $html);
        $this->assertStringContainsString("name='email'", $html);
        $this->assertStringContainsString("name='message'", $html);
        $this->assertStringContainsString('data-cosmic-contact-form', $html);
        $this->assertStringContainsString('border-white/20', $html);
        $this->assertStringContainsString('response.text()', $html);
        $this->assertStringContainsString('did not return a valid response', $html);
    }

    public function test_a_published_contact_form_renders_supported_custom_fields(): void
    {
        $html = CmsHtmlCompiler::compile([[
            'type' => 'contact_form_modern',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Full name', 'placeholder' => 'Your name', 'required' => true],
                ['name' => 'service', 'type' => 'select', 'label' => 'Service needed', 'placeholder' => 'Choose a service', 'required' => true, 'options' => ['Consultation', 'Project']],
                ['name' => 'consent', 'type' => 'checkbox', 'label' => 'I agree to be contacted', 'required' => true],
            ],
        ]], 'midnight');

        $this->assertStringContainsString("name='service'", $html);
        $this->assertStringContainsString('Choose a service', $html);
        $this->assertStringContainsString('Consultation', $html);
        $this->assertStringContainsString("name='consent'", $html);
        $this->assertStringContainsString('background-color:#334b67', $html);
    }
}
