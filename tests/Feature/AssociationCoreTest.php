<?php

namespace Tests\Feature;

use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use App\Filament\Resources\Condolences\Pages\ListCondolences;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MemberDepositsRelationManager;
use App\Http\Controllers\ReportController;
use App\Models\Arrear;
use App\Models\ArrearPayment;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Deposit;
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

    public function test_condolence_arrear_requires_a_condolence_link(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $this->expectException(ValidationException::class);

        Arrear::create([
            'member_id' => $member->id,
            'reason' => 'condolence',
            'title' => 'Manual condolence top-up',
            'amount_expected' => 1000,
        ]);
    }

    public function test_arrear_part_payments_update_status(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $arrear = Arrear::create([
            'member_id' => $member->id,
            'reason' => 'annual_dues',
            'title' => '2026 annual dues',
            'amount_expected' => 10000,
        ]);

        $this->assertEquals(LevyStatus::Unpaid->value, $arrear->status);

        ArrearPayment::create([
            'arrear_id' => $arrear->id,
            'member_id' => $member->id,
            'amount' => 4000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reason' => 'annual_dues',
        ]);

        $arrear->refresh();
        $this->assertEquals(LevyStatus::Partial->value, $arrear->status);
        $this->assertEquals(4000, (float) $arrear->amount_paid);

        ArrearPayment::create([
            'arrear_id' => $arrear->id,
            'member_id' => $member->id,
            'amount' => 6000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reason' => 'annual_dues',
        ]);

        $arrear->refresh();
        $this->assertEquals(LevyStatus::Paid->value, $arrear->status);
    }

    public function test_member_account_totals_combine_levies_and_arrears(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

        Arrear::create([
            'member_id' => $member->id,
            'reason' => 'fine',
            'title' => 'Late-coming fine',
            'amount_expected' => 2000,
        ]);

        $totals = $member->refresh()->accountTotals();

        $this->assertEquals(7000, $totals['expected']);
        $this->assertEquals(0, $totals['paid']);
        $this->assertEquals(7000, $totals['outstanding']);
    }

    public function test_opening_arrears_count_toward_member_outstanding(): void
    {
        $member = Member::factory()->create([
            'status' => MemberStatus::Active->value,
            'opening_arrears' => 15000,
        ]);

        $totals = $member->accountTotals();

        $this->assertEquals(15000, $totals['expected']);
        $this->assertEquals(0, $totals['paid']);
        $this->assertEquals(15000, $totals['outstanding']);
    }

    public function test_new_levies_shows_only_levies_still_owing(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $deceasedOne = Member::factory()->deceased()->create();
        $paidUp = Condolence::factory()->create(['deceased_member_id' => $deceasedOne->id, 'amount_per_member' => 5000]);
        $paidUp->generateLevies();

        $deceasedTwo = Member::factory()->deceased()->create();
        $owing = Condolence::factory()->create(['deceased_member_id' => $deceasedTwo->id, 'amount_per_member' => 8000]);
        $owing->generateLevies();

        $paidLevy = CondolenceLevy::where('condolence_id', $paidUp->id)->where('member_id', $member->id)->firstOrFail();
        Payment::create([
            'condolence_levy_id' => $paidLevy->id,
            'condolence_id' => $paidUp->id,
            'member_id' => $member->id,
            'amount' => 5000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        // Paid-off levy excluded; only the ₦8,000 still owing shows.
        $this->assertEquals(8000, $member->refresh()->leviesOwing());
    }

    public function test_log_payment_action_records_condolence_payment_from_profile(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'condolence',
                'allocation' => 'specific',
                'condolence_levy_id' => $levy->id,
                'amount' => 2000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'condolence_levy_id' => $levy->id,
            'member_id' => $member->id,
            'amount' => 2000,
            'reason' => 'condolence',
        ]);
        $this->assertEquals('partial', $levy->refresh()->status);
    }

    public function test_log_payment_dialog_opens_on_member_profile(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->mountTableAction('logPayment')
            ->assertHasNoErrors();
    }

    public function test_log_payment_action_records_arrear_payment_from_profile(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $arrear = Arrear::create([
            'member_id' => $member->id,
            'reason' => 'annual_dues',
            'title' => '2026 annual dues',
            'amount_expected' => 10000,
        ]);

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'annual_dues',
                'arrear_id' => $arrear->id,
                'amount' => 10000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('arrear_payments', [
            'arrear_id' => $arrear->id,
            'member_id' => $member->id,
            'amount' => 10000,
            'reason' => 'annual_dues',
        ]);
        $this->assertEquals('paid', $arrear->refresh()->status);
    }

    public function test_log_payment_auto_spreads_deposit_oldest_first(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $older = Condolence::factory()->create([
            'deceased_member_id' => Member::factory()->deceased()->create()->id,
            'amount_per_member' => 5000,
            'date_announced' => now()->subMonths(2)->toDateString(),
        ]);
        $older->generateLevies();

        $newer = Condolence::factory()->create([
            'deceased_member_id' => Member::factory()->deceased()->create()->id,
            'amount_per_member' => 10000,
            'date_announced' => now()->toDateString(),
        ]);
        $newer->generateLevies();

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'condolence',
                'allocation' => 'auto',
                'amount' => 7000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $olderLevy = CondolenceLevy::where('condolence_id', $older->id)->where('member_id', $member->id)->firstOrFail();
        $newerLevy = CondolenceLevy::where('condolence_id', $newer->id)->where('member_id', $member->id)->firstOrFail();

        $this->assertEquals('paid', $olderLevy->refresh()->status);
        $this->assertEquals('partial', $newerLevy->refresh()->status);
        $this->assertDatabaseHas('payments', [
            'condolence_levy_id' => $olderLevy->id,
            'amount' => 5000,
        ]);
        $this->assertDatabaseHas('payments', [
            'condolence_levy_id' => $newerLevy->id,
            'amount' => 2000,
        ]);

        $splits = Payment::where('member_id', $member->id)->get();
        $this->assertCount(2, $splits);
        // One shared reference ties both split rows to the single ₦7,000 deposit.
        $this->assertEquals(1, $splits->pluck('reference')->unique()->count());
        $this->assertStringStartsWith('DEP-', (string) $splits->first()->reference);
        $this->assertStringContainsString('Split 1 of 2', (string) $splits->firstWhere('condolence_levy_id', $olderLevy->id)->notes);
        $this->assertStringContainsString('Split 2 of 2', (string) $splits->firstWhere('condolence_levy_id', $newerLevy->id)->notes);

        // And one bulk deposit row records the handover itself.
        $deposit = Deposit::where('member_id', $member->id)->firstOrFail();
        $this->assertEquals(7000, (float) $deposit->amount);
        $this->assertEquals($splits->first()->reference, $deposit->reference);
        // Previous outstanding kept as arrears, new balance after deduction.
        $this->assertEquals(15000, (float) $deposit->outstanding_before);
        $this->assertEquals(8000, (float) $deposit->outstanding_after);
    }

    public function test_log_payment_accepts_thousand_separated_amounts(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $arrear = Arrear::create([
            'member_id' => $member->id,
            'reason' => 'annual_dues',
            'title' => '2026 annual dues',
            'amount_expected' => 20000,
        ]);

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'annual_dues',
                'arrear_id' => $arrear->id,
                'amount' => '10,000',
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('arrear_payments', [
            'arrear_id' => $arrear->id,
            'amount' => 10000,
        ]);
        $this->assertDatabaseHas('deposits', [
            'member_id' => $member->id,
            'amount' => 10000,
        ]);
    }

    public function test_log_payment_auto_overpay_becomes_credit(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'condolence',
                'allocation' => 'auto',
                'amount' => 8000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();
        $this->assertEquals('paid', $levy->refresh()->status);
        $this->assertEquals(3000, (float) $member->refresh()->credit_balance);

        $deposit = Deposit::where('member_id', $member->id)->firstOrFail();
        $this->assertEquals(8000, (float) $deposit->amount);
        $this->assertEquals(3000, (float) $deposit->credit_added);
    }

    public function test_new_levy_auto_debits_member_credit(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        Member::query()->whereKey($member->id)->update(['credit_balance' => 4000]);

        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();
        $this->assertEquals('partial', $levy->refresh()->status);
        $this->assertEquals(1000, (float) $levy->amount_expected - (float) $levy->amount_paid);
        $this->assertEquals(0, (float) $member->refresh()->credit_balance);
        $this->assertDatabaseHas('payments', [
            'condolence_levy_id' => $levy->id,
            'amount' => 4000,
            'payment_method' => 'credit',
        ]);
    }

    public function test_log_payment_auto_spreads_across_levies_arrears_and_opening(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create([
            'status' => MemberStatus::Active->value,
            'opening_arrears' => 2000,
        ]);

        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

        $arrear = Arrear::create([
            'member_id' => $member->id,
            'reason' => 'annual_dues',
            'title' => '2026 annual dues',
            'amount_expected' => 3000,
        ]);

        $this->actingAs($secretary, 'web');

        // Owes 5000 levy + 3000 dues + 2000 opening = 10000; deposit 9000.
        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'condolence',
                'allocation' => 'auto',
                'amount' => 9000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();
        $this->assertEquals('paid', $levy->refresh()->status);
        $this->assertEquals('paid', $arrear->refresh()->status);
        $this->assertEquals(1000, (float) $member->refresh()->opening_arrears);

        $deposit = Deposit::where('member_id', $member->id)->firstOrFail();
        $this->assertEquals(9000, (float) $deposit->amount);
        $this->assertEquals(1000, (float) $deposit->opening_applied);
        $this->assertEquals(1000, $member->accountTotals()['outstanding']);
    }

    public function test_log_payment_overpay_on_single_item_becomes_credit(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();
        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();

        $arrear = Arrear::create([
            'member_id' => $member->id,
            'reason' => 'fine',
            'title' => 'Fine',
            'amount_expected' => 2000,
        ]);

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'condolence',
                'allocation' => 'specific',
                'condolence_levy_id' => $levy->id,
                'amount' => 6000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertEquals('paid', $levy->refresh()->status);
        $this->assertEquals(1000, (float) $member->refresh()->credit_balance);

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'reason' => 'fine',
                'arrear_id' => $arrear->id,
                'amount' => 2500,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertEquals('paid', $arrear->refresh()->status);
        // ₦1,000 credit from before + ₦500 excess now.
        $this->assertEquals(1500, (float) $member->refresh()->credit_balance);
    }

    public function test_member_list_defaults_to_active_with_status_tabs(): void
    {
        $secretary = User::factory()->create();
        $this->actingAs($secretary, 'web');

        $tabs = Livewire::test(ListMembers::class)->instance()->getTabs();

        $this->assertEquals(['active', 'suspended', 'deceased', 'all'], array_keys($tabs));
    }
}
