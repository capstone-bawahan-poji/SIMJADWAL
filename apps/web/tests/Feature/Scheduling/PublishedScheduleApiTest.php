<?php

namespace Tests\Feature\Scheduling;

use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\SchedulePublication;
use App\Models\User;
use App\Services\Scheduling\PublishedScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProgramSchedules;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * Integration API: other campus systems read the published schedule, keyed by codes.
 */
class PublishedScheduleApiTest extends TestCase
{
    use BuildsProgramSchedules, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->faculty = Faculty::factory()->create(['code' => 'FSTI']);
        $this->program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'IF']);
        $slot = TimeSlot::factory()->create(['day' => 1, 'session' => 1, 'start_time' => '07:30', 'end_time' => '10:00']);
        $room = Room::factory()->create(['code' => 'E101', 'capacity' => 40]);
        $lecturer = Lecturer::factory()->create(['nip' => '199001012020011001', 'name' => 'Rina Wati']);
        $course = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF2514201', 'name' => 'Algoritma', 'sks' => 3, 'semester' => 3]);
        $gene = ['course_id' => $course->id, 'class_number' => 2, 'lecturer_id' => $lecturer->id, 'time_slot_id' => $slot->id, 'room_id' => $room->id];

        $this->programRun($this->program, self::PERIOD, [$gene], published: true);
        SchedulePublication::query()->create([
            'faculty_id' => $this->faculty->id, 'period' => self::PERIOD, 'published_by' => User::factory()->create()->id,
            'published_at' => now(), 'checksum' => app(PublishedScheduleService::class)->checksum($this->faculty->id, self::PERIOD),
        ]);
        // Not published: never in the API.
        $draft = StudyProgram::factory()->create();
        $this->programRun($draft, self::PERIOD, [[...$gene, 'course_id' => Course::factory()->create(['study_program_id' => $draft->id])->id]]);
    }

    public function test_rows_are_flat_keyed_by_codes_and_published_only(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['schedule:read']);

        $response = $this->getJson('/api/v1/published-schedules?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', [
                'period' => self::PERIOD, 'faculty_code' => 'FSTI', 'study_program_code' => 'IF', 'course_code' => 'IF2514201',
                'course_name' => 'Algoritma', 'sks' => 3, 'semester' => 3, 'class_label' => 'B', 'meeting_number' => 1,
                'lecturer_nip' => '199001012020011001', 'lecturer_name' => 'Rina Wati', 'day' => 1, 'session' => 1,
                'start_time' => '07:30', 'end_time' => '10:00', 'room_code' => 'E101', 'room_capacity' => 40, 'is_tpb' => false,
            ])
            ->assertJsonPath('meta.next_cursor', null)
            ->assertJsonPath('meta.publications.0.faculty.code', 'FSTI');

        $this->getJson('/api/v1/published-schedules?period='.urlencode(self::PERIOD).'&faculty_code=FRTI')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/published-schedules?period='.urlencode(self::PERIOD).'&study_program_code=IF')->assertOk()->assertJsonCount(1, 'data');

        // Same ETag back = nothing changed.
        $etag = $response->headers->get('ETag');
        $this->getJson('/api/v1/published-schedules?period='.urlencode(self::PERIOD), ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_csv_export_has_the_same_columns(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['schedule:read']);

        $csv = $this->get('/api/v1/published-schedules?format=csv&period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", $csv)));

        $this->assertSame(PublishedScheduleService::COLUMNS, $lines[0]);
        $this->assertSame('IF2514201', $lines[1][3]);
        $this->assertCount(2, $lines);
    }

    public function test_an_integration_token_reads_the_published_schedule_and_nothing_else(): void
    {
        $user = User::factory()->superAdmin()->create();
        $token = $user->createToken('siakad', ['schedule:read'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->getJson('/api/v1/published-schedules/publications?period='.urlencode(self::PERIOD), $headers)
            ->assertOk()->assertJsonPath('data.0.checksum', SchedulePublication::query()->value('checksum'));
        $this->getJson('/api/v1/faculties', $headers)->assertForbidden();
        $this->getJson('/api/v1/scheduling/dataset?scope=tpb', $headers)->assertForbidden();

        // A login token keeps every ability, schedule:read included.
        $this->app['auth']->forgetGuards();
        $login = $user->createToken('desktop')->plainTextToken;
        $this->getJson('/api/v1/faculties', ['Authorization' => "Bearer {$login}"])->assertOk();
        $this->getJson('/api/v1/published-schedules?period='.urlencode(self::PERIOD), ['Authorization' => "Bearer {$login}"])->assertOk();
    }

    public function test_command_issues_a_read_only_token(): void
    {
        $user = User::factory()->create(['email' => 'siakad@itk.ac.id']);

        $this->artisan('schedule:integration-token', ['email' => 'siakad@itk.ac.id', '--name' => 'siakad'])->assertSuccessful();
        $this->assertSame(['schedule:read'], $user->tokens()->sole()->abilities);
        $this->artisan('schedule:integration-token', ['email' => 'nobody@itk.ac.id'])->assertFailed();
    }
}
