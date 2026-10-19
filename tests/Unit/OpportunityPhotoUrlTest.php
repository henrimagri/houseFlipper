<?php

namespace Tests\Unit;

use App\Support\OpportunityPhotoUrl;
use Tests\TestCase;

class OpportunityPhotoUrlTest extends TestCase
{
    public function test_local_public_photo_keeps_relative_link(): void
    {
        config()->set('opportunities.photo_url', null);
        $this->assertSame('/storage/opportunities/photo.jpg', OpportunityPhotoUrl::for('opportunities/photo.jpg'));
    }

    public function test_invalid_cdn_and_unmanaged_paths_fail_closed(): void
    {
        config()->set('opportunities.photo_url', 'http://127.0.0.1/files');
        $this->assertNull(OpportunityPhotoUrl::for('opportunities/photo.jpg'));
        $this->assertNull(OpportunityPhotoUrl::for('../private/photo.jpg'));
    }
}
