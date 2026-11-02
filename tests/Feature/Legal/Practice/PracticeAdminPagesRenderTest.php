<?php

namespace Tests\Feature\Legal\Practice;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Document;
use App\Models\Hearing;
use App\Models\Task;
use App\Support\Access;

class PracticeAdminPagesRenderTest extends PracticeTestCase
{
    public function test_practice_admin_pages_render_for_an_assigned_advocate(): void
    {
        $document = Document::factory()->withVersion()->create(['matter_id' => $this->matter->id]);
        $task = Task::factory()->create(['matter_id' => $this->matter->id, 'assigned_to' => $this->lead->id]);
        Hearing::factory()->create(['matter_id' => $this->matter->id]);
        Deadline::factory()->create(['matter_id' => $this->matter->id]);
        Appointment::factory()->create(['organizer_id' => $this->lead->id, 'matter_id' => $this->matter->id]);

        $this->actingAs($this->lead);

        foreach ([
            '/admin/documents', '/admin/documents/create', DocumentResource::getUrl('view', ['record' => $document]),
            '/admin/tasks', '/admin/tasks/create', TaskResource::getUrl('edit', ['record' => $task]),
            '/admin/hearings', '/admin/deadlines', '/admin/appointments', '/admin/calendar', '/admin',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_task_report_renders_for_partner(): void
    {
        $this->actingAs($this->userWithRole(Access::PARTNER))->get('/admin/reports/tasks')->assertOk();
    }

    public function test_client_role_cannot_reach_practice_pages(): void
    {
        $this->actingAs($this->userWithRole(Access::CLIENT))->get('/admin/documents')->assertForbidden();
    }
}
