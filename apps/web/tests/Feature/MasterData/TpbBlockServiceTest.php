<?php

namespace Tests\Feature\MasterData;

use App\Enums\MasterData\Day;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Services\MasterData\TpbBlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The slots the faculty GA must skip because TPB already took them.
 */
class TpbBlockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_programs_lecturers_and_rooms_of_placed_groups_by_parity(): void
    {
        $slot = TimeSlot::factory()->create(['day' => Day::TUESDAY, 'session' => 3]);
        $program = StudyProgram::factory()->create();
        $lecturer = Lecturer::factory()->create();
        $room = Room::factory()->shared()->create();

        $placed = TpbGroup::factory()->create(['course_id' => Course::factory()->tpb()->create(['semester' => 1])->id, 'time_slot_id' => $slot->id]);
        $placed->studyPrograms()->sync([$program->id]);
        CourseLecturer::factory()->create([
            'course_id' => $placed->course_id, 'tpb_group_id' => $placed->id,
            'lecturer_id' => $lecturer->id, 'room_id' => $room->id,
        ]);

        // Not placed yet: blocks nothing.
        $unplaced = TpbGroup::factory()->create(['course_id' => Course::factory()->tpb()->create(['semester' => 1])->id]);
        $unplaced->studyPrograms()->sync([$program->id]);

        $service = app(TpbBlockService::class);

        $this->assertSame([$program->id => [1 => [$slot->id]]], $service->blockedSlotsByStudyProgram(oddSemester: true));
        $this->assertSame([$lecturer->id => [$slot->id]], $service->blockedSlotsByLecturer(oddSemester: true));
        $this->assertSame([$room->id => [$slot->id]], $service->blockedSlotsByRoom(oddSemester: true));

        $this->assertSame([], $service->blockedSlotsByStudyProgram(oddSemester: false));
        $this->assertSame([], $service->blockedSlotsByLecturer(oddSemester: false));
    }
}
