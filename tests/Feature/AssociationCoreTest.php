<?php

namespace Tests\Feature;

use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use App\Filament\Member\Resources\MyDepositResource;
use App\Filament\Resources\Condolences\Pages\ListCondolences;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MemberDepositsRelationManager;
use App\Http\Controllers\ReportController;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
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

    public function test_member_account_totals_combine_levies_and_opening(): void
    {
        $member = Member::factory()->create([
            'status' => MemberStatus::Active->value,
            'opening_arrears' => 2000,
        ]);
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

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

    public function test_log_payment_action_records_specific_levy_from_profile(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 10000]);
        $condolence->generateLevies();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'allocation' => 'specific',
                'condolence_levy_id' => $levy->id,
                'amount' => 10000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'condolence_levy_id' => $levy->id,
            'member_id' => $member->id,
            'amount' => 10000,
            'reason' => 'condolence',
        ]);
        $this->assertEquals('paid', $levy->refresh()->status);
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
        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 20000]);
        $condolence->generateLevies();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();

        $this->actingAs($secretary, 'web');

        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'allocation' => 'specific',
                'condolence_levy_id' => $levy->id,
                'amount' => '10,000',
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'condolence_levy_id' => $levy->id,
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

    public function test_log_payment_auto_spreads_across_levies_and_opening(): void
    {
        $secretary = User::factory()->create();
        $member = Member::factory()->create([
            'status' => MemberStatus::Active->value,
            'opening_arrears' => 2000,
        ]);

        $deceased = Member::factory()->deceased()->create();
        $condolence = Condolence::factory()->create(['deceased_member_id' => $deceased->id, 'amount_per_member' => 5000]);
        $condolence->generateLevies();

        $this->actingAs($secretary, 'web');

        // Owes 5000 levy + 2000 opening = 7000; deposit 6000.
        Livewire::test(MemberDepositsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
            ->callTableAction('logPayment', null, [
                'allocation' => 'auto',
                'amount' => 6000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $levy = CondolenceLevy::where('condolence_id', $condolence->id)->where('member_id', $member->id)->firstOrFail();
        $this->assertEquals('paid', $levy->refresh()->status);
        $this->assertEquals(1000, (float) $member->refresh()->opening_arrears);

        $deposit = Deposit::where('member_id', $member->id)->firstOrFail();
        $this->assertEquals(6000, (float) $deposit->amount);
        $this->assertEquals(1000, (float) $deposit->opening_applied);
        $this->assertEquals(1000, $member->accountTotals()['outstanding']);
    }

    public function test_log_payment_overpay_becomes_credit(): void
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
                'allocation' => 'specific',
                'condolence_levy_id' => $levy->id,
                'amount' => 6000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertEquals('paid', $levy->refresh()->status);
        $this->assertEquals(1000, (float) $member->refresh()->credit_balance);
        $this->assertEquals(1000, (float) Deposit::where('member_id', $member->id)->firstOrFail()->credit_added);
    }

    public function test_member_list_defaults_to_active_with_status_tabs(): void
    {
        $secretary = User::factory()->create();
        $this->actingAs($secretary, 'web');

        $tabs = Livewire::test(ListMembers::class)->instance()->getTabs();

        $this->assertEquals(['active', 'suspended', 'deceased', 'all'], array_keys($tabs));
    }

    public function test_levy_chain_carries_previous_outstanding_forward(): void
    {
        $member = Member::factory()->create([
            'status' => MemberStatus::Active->value,
            'opening_arrears' => 12000,
        ]);

        $older = Condolence::factory()->create([
            'deceased_member_id' => Member::factory()->deceased()->create()->id,
            'amount_per_member' => 3000,
            'date_announced' => now()->subMonths(2)->toDateString(),
        ]);
        $older->generateLevies();

        $newer = Condolence::factory()->create([
            'deceased_member_id' => Member::factory()->deceased()->create()->id,
            'amount_per_member' => 3000,
            'date_announced' => now()->toDateString(),
        ]);
        $newer->generateLevies();

        $olderLevy = CondolenceLevy::where('condolence_id', $older->id)->where('member_id', $member->id)->firstOrFail();
        $newerLevy = CondolenceLevy::where('condolence_id', $newer->id)->where('member_id', $member->id)->firstOrFail();

        Payment::create([
            'condolence_levy_id' => $olderLevy->id,
            'condolence_id' => $older->id,
            'member_id' => $member->id,
            'amount' => 3000,
            'paid_at' => now()->subMonth()->toDateString(),
            'payment_method' => 'cash',
        ]);

        // Arrears into the older condolence is the untouched opening balance.
        $this->assertEquals(12000, $olderLevy->refresh()->carriedArrears());
        $this->assertEquals(12000, $olderLevy->runningOutstanding());

        // Next arrears = previous outstanding; new outstanding adds the levy.
        $this->assertEquals(12000, $newerLevy->refresh()->carriedArrears());
        $this->assertEquals(15000, $newerLevy->runningOutstanding());
    }

    public function test_condolence_reads_payment_records_from_deposits(): void
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
                'allocation' => 'auto',
                'amount' => 7000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertHasNoErrors();

        // One bulk deposit, linked to both levies it touched.
        $deposit = Deposit::where('member_id', $member->id)->firstOrFail();
        $this->assertEquals(2, $deposit->allocations()->count());
        $this->assertEquals(
            [5000.0, 2000.0],
            $deposit->allocations()->orderBy('id')->pluck('amount')->map(fn ($v): float => (float) $v)->all()
        );

        // Each condolence reads the same figures from deposits as from splits.
        $olderLevy = CondolenceLevy::where('condolence_id', $older->id)->where('member_id', $member->id)->firstOrFail();
        $this->assertTrue($older->deposits()->where('deposits.id', $deposit->id)->exists());
        $this->assertEquals(
            (float) $older->payments()->where('member_id', $member->id)->sum('amount'),
            (float) $older->deposits()->where('deposits.member_id', $member->id)->sum('deposit_allocations.amount')
        );
        $this->assertEquals(5000, (float) $olderLevy->deposits()->sum('deposit_allocations.amount'));
    }

    public function test_admin_and_member_panels_enforce_roles(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $memberUser = User::factory()->create(['role' => User::ROLE_MEMBER, 'member_id' => $member->id]);

        $adminPanel = Filament::getPanel('admin');
        $memberPanel = Filament::getPanel('member');

        $this->assertTrue($admin->canAccessPanel($adminPanel));
        $this->assertFalse($admin->canAccessPanel($memberPanel));
        $this->assertFalse($memberUser->canAccessPanel($adminPanel));
        $this->assertTrue($memberUser->canAccessPanel($memberPanel));

        $member->update(['status' => MemberStatus::Suspended->value]);

        $this->assertFalse($memberUser->refresh()->canAccessPanel($memberPanel));
    }

    public function test_create_login_from_member_profile(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);

        $this->actingAs($secretary, 'web');

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('createLogin', [
                'name' => $member->full_name,
                'email' => 'newmember@example.com',
                'password' => 'secret123',
            ])
            ->assertHasNoErrors();

        $user = User::where('email', 'newmember@example.com')->firstOrFail();
        $this->assertEquals(User::ROLE_MEMBER, $user->role);
        $this->assertEquals($member->id, $user->member_id);
        $this->assertTrue($member->refresh()->user()->isNot(null));
    }

    public function test_member_user_sees_only_own_deposits(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $other = Member::factory()->create(['status' => MemberStatus::Active->value]);

        Deposit::create([
            'member_id' => $member->id, 'amount' => 1000, 'paid_at' => now()->toDateString(),
            'payment_method' => 'cash', 'reason' => 'condolence',
        ]);
        Deposit::create([
            'member_id' => $other->id, 'amount' => 2000, 'paid_at' => now()->toDateString(),
            'payment_method' => 'cash', 'reason' => 'condolence',
        ]);

        $memberUser = User::factory()->create(['role' => User::ROLE_MEMBER, 'member_id' => $member->id]);
        $this->actingAs($memberUser, 'web');

        $query = MyDepositResource::getEloquentQuery();

        $this->assertEquals([$member->id], $query->pluck('member_id')->unique()->all());
        $this->assertEquals(1000, (float) $query->sum('amount'));
    }

    public function test_member_bank_details_save_from_create_form(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($secretary, 'web');

        Livewire::test(CreateMember::class)
            ->fillForm([
                'title' => 'Mr',
                'first_name' => 'Banked',
                'last_name' => 'Member',
                'status' => MemberStatus::Active->value,
                'bank_name' => 'Guaranty Trust Bank',
                'account_number' => '0123456789',
                'account_name' => 'Banked Member',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('first_name', 'Banked')->firstOrFail();

        $this->assertSame('Guaranty Trust Bank', $member->bank_name);
        $this->assertSame('0123456789', $member->account_number);
        $this->assertSame('Banked Member', $member->account_name);
    }

    public function test_member_account_number_must_be_ten_digits(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($secretary, 'web');

        Livewire::test(CreateMember::class)
            ->fillForm([
                'title' => 'Mr',
                'first_name' => 'Short',
                'last_name' => 'Account',
                'status' => MemberStatus::Active->value,
                'bank_name' => 'Zenith Bank',
                'account_number' => '123456789',
            ])
            ->call('create')
            ->assertHasFormErrors(['account_number']);

        $this->assertNull(Member::where('first_name', 'Short')->first());
    }
}
