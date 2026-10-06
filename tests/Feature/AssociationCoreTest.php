<?php

namespace Tests\Feature;

use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use App\Filament\Resources\Condolences\Pages\ListCondolences;
use App\Http\Controllers\ReportController;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AssociationCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_condolence_generates_levies_excluding_deceased(): void
    {
        $active = Member::factory()->count(3)->create(['status' => MemberStatus::Active->value]);
        $deceased = Member::factory()->create(['status' => MemberStatus::Deceased->value]);

        $condolence = Condolence::factory()->create([
            'deceased_member_id' => $deceased->id,
            'amount_per_member' => 5000,
        ]);

        $condolence->generateLevies();

        $this->assertEquals(3, CondolenceLevy::where('condolence_id', $condolence->id)->count());
        $this->assertFalse(
            CondolenceLevy::where('condolence_id', $condolence->id)
                ->where('member_id', $deceased->id)
                ->exists()
        );

        foreach ($active as $member) {
            $this->assertTrue(
                CondolenceLevy::where('condolence_id', $condolence->id)
                    ->where('member_id', $member->id)
                    ->exists()
            );
        }
    }

    public function test_payment_updates_levy_status_to_paid_and_partial(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $condolence = Condolence::factory()->create(['amount_per_member' => 5000]);
        $condolence->generateLevies();

        /** @var CondolenceLevy $levy */
        $levy = CondolenceLevy::where('condolence_id', $condolence->id)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $this->assertEquals(LevyStatus::Unpaid->value, $levy->status);

        Payment::create([
            'condolence_levy_id' => $levy->id,
            'condolence_id' => $condolence->id,
            'member_id' => $member->id,
            'amount' => 2000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $levy->refresh();
        $this->assertEquals(LevyStatus::Partial->value, $levy->status);
        $this->assertEquals(2000, (float) $levy->amount_paid);

        Payment::create([
            'condolence_levy_id' => $levy->id,
            'condolence_id' => $condolence->id,
            'member_id' => $member->id,
            'amount' => 3000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $levy->refresh();
        $this->assertEquals(LevyStatus::Paid->value, $levy->status);
        $this->assertEquals(5000, (float) $levy->amount_paid);
    }

    public function test_outstanding_totals_are_correct(): void
    {
        $members = Member::factory()->count(4)->create(['status' => MemberStatus::Active->value]);
        $condolence = Condolence::factory()->create(['amount_per_member' => 1000]);
        $condolence->generateLevies();

        $firstLevy = CondolenceLevy::where('member_id', $members[0]->id)->firstOrFail();
        Payment::create([
            'condolence_levy_id' => $firstLevy->id,
            'condolence_id' => $condolence->id,
            'member_id' => $members[0]->id,
            'amount' => 1000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $condolence->refresh();

        $this->assertEquals(4000, $condolence->totalExpected());
        $this->assertEquals(1000, $condolence->totalCollected());
        $this->assertEquals(3000, $condolence->totalOutstanding());
    }

    public function test_report_routes_require_authentication(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $condolence = Condolence::factory()->create();

        $this->get(route('reports.condolence', $condolence))->assertRedirect('/login');
        $this->get(route('reports.member-statement', $member))->assertRedirect('/login');
        $this->get(route('reports.condolences-matrix'))->assertRedirect('/login');
    }

    public function test_authenticated_secretary_can_download_pdfs(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id]);
        $condolence->generateLevies();

        $this->actingAs($secretary, 'web')
            ->get(route('reports.condolence', $condolence))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($secretary, 'web')
            ->get(route('reports.member-statement', $member))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_authenticated_secretary_can_download_matrix_pdf(): void
    {
        $secretary = User::factory()->create();
        Member::factory()->count(3)->create(['status' => MemberStatus::Active->value]);

        $first = Condolence::factory()->create(['amount_per_member' => 5000, 'status' => 'open']);
        $first->generateLevies();
        $second = Condolence::factory()->create(['amount_per_member' => 3000, 'status' => 'open']);
        $second->generateLevies();

        $levy = CondolenceLevy::where('condolence_id', $first->id)->firstOrFail();
        Payment::create([
            'condolence_levy_id' => $levy->id,
            'condolence_id' => $first->id,
            'member_id' => $levy->member_id,
            'amount' => 5000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->actingAs($secretary, 'web')
            ->get(route('reports.condolences-matrix', ['status' => 'all']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($secretary, 'web')
            ->get(route('reports.condolences-matrix', ['status' => 'open']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_matrix_pdf_button_redirects_to_chunked_download(): void
    {
        $secretary = User::factory()->create();
        Member::factory()->count(3)->create(['status' => MemberStatus::Active->value]);

        $this->actingAs($secretary, 'web');

        Livewire::test(ListCondolences::class)
            ->callAction('matrixPdf', ['status' => 'all', 'members' => 'all'])
            ->assertRedirect(route('reports.condolences-matrix', ['status' => 'all']));
    }

    public function test_member_range_options_offer_all_for_single_area_pdfs(): void
    {
        $this->assertSame(['all' => 'All members (3)'], ReportController::memberRangeOptions(3, 200, ReportController::AREA_PDF_ALL_LIMIT));

        $options = ReportController::memberRangeOptions(993, 200, ReportController::AREA_PDF_ALL_LIMIT);
        $this->assertSame('All members (993)', $options['all']);
        $this->assertCount(6, $options);

        $matrixOptions = ReportController::memberRangeOptions(993, 90, ReportController::MATRIX_PDF_ALL_LIMIT);
        $this->assertSame('All members (993)', $matrixOptions['all']);
        $this->assertCount(13, $matrixOptions);
    }

    public function test_matrix_and_area_reports_support_member_chunks_and_csv(): void
    {
        $secretary = User::factory()->create();
        Member::factory()->count(3)->create(['status' => MemberStatus::Active->value]);

        $condolence = Condolence::factory()->create(['amount_per_member' => 5000, 'status' => 'open']);
        $condolence->generateLevies();

        $this->actingAs($secretary, 'web')
            ->get(route('reports.condolences-matrix', ['status' => 'all', 'members' => '0:2']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($secretary, 'web')
            ->get(route('reports.condolence', [$condolence, 'members' => '0:2']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $merged = $this->actingAs($secretary, 'web')
            ->get(route('reports.condolences-matrix', ['status' => 'all']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $merged->getContent());

        $csv = $this->actingAs($secretary, 'web')
            ->get(route('reports.condolences-matrix-csv', ['status' => 'all']))
            ->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Arrears', $csv->streamedContent());

        $areaCsv = $this->actingAs($secretary, 'web')
            ->get(route('reports.condolence-csv', $condolence))
            ->assertOk();
        $areaCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Outstanding', $areaCsv->streamedContent());
    }

    public function test_condolence_requires_a_deceased_member(): void
    {
        $this->expectException(ValidationException::class);

        Condolence::factory()->create(['deceased_member_id' => null]);
    }

    public function test_condolence_cannot_target_an_active_member(): void
    {
        $active = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $this->expectException(ValidationException::class);

        Condolence::factory()->create(['deceased_member_id' => $active->id]);
    }

    public function test_condolence_cannot_target_a_suspended_member(): void
    {
        $suspended = Member::factory()->create(['status' => MemberStatus::Suspended->value]);

        $this->expectException(ValidationException::class);

        Condolence::factory()->create(['deceased_member_id' => $suspended->id]);
    }

    public function test_condolence_can_only_be_created_once_per_deceased_member(): void
    {
        $deceased = Member::factory()->deceased()->create();

        Condolence::factory()->create(['deceased_member_id' => $deceased->id]);

        $this->expectException(ValidationException::class);

        Condolence::factory()->create(['deceased_member_id' => $deceased->id]);
    }
}
