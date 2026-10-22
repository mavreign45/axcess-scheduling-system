<?php

namespace Tests\Unit;

use App\Support\Axcess;
use PHPUnit\Framework\TestCase;

class AxcessTest extends TestCase
{
    public function test_brand_and_title_are_canonical(): void
    {
        $this->assertSame('Axcess', Axcess::NAME);
        $this->assertSame('Staff Scheduling & Workforce Management', Axcess::TAGLINE);
        $this->assertSame('Axcess — Staff Scheduling & Workforce Management', Axcess::FULL_TITLE);
    }

    public function test_labels_are_human_readable_and_unknown_values_are_formatted(): void
    {
        $this->assertSame('Approved', Axcess::label('leave_status', 'approved'));
        $this->assertSame('Break coverage', Axcess::label('conflict_type', 'missing_break'));
        $this->assertSame('Custom value', Axcess::label('unknown_group', 'custom_value'));
        $this->assertSame('—', Axcess::label('leave_status', null));
    }
}
