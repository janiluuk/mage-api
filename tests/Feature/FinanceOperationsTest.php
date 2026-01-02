<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FinanceOperationsHistory;
use App\Models\UserWallet;
use App\Models\WalletType;
use App\Constant\FinanceOperationConstant;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for Finance Operations
 * 
 * These tests verify financial transaction tracking and wallet management.
 */
class FinanceOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_operation_can_be_created(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 100,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
            'wallet_id' => null,
        ]);

        $this->assertDatabaseHas('finance_operations_histories', [
            'user_id' => $user->id,
            'money' => 100,
            'type' => FinanceOperationConstant::ENROLLMENT,
        ]);
    }

    public function test_finance_operation_has_correct_type_enrollment(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 500,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        $this->assertEquals(FinanceOperationConstant::ENROLLMENT, $financeOp->type);
        $this->assertEquals(FinanceOperationConstant::EXECUTED, $financeOp->status);
    }

    public function test_finance_operation_has_correct_type_writeoff(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 50,
            'type' => FinanceOperationConstant::WRITE_OFFS,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        $this->assertEquals(FinanceOperationConstant::WRITE_OFFS, $financeOp->type);
    }

    public function test_finance_operation_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 100,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        $this->assertInstanceOf(User::class, $financeOp->user);
        $this->assertEquals($user->id, $financeOp->user->id);
    }

    public function test_multiple_finance_operations_for_user(): void
    {
        $user = User::factory()->create();

        // Create multiple operations
        FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 100,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 50,
            'type' => FinanceOperationConstant::WRITE_OFFS,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 200,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        $operations = FinanceOperationsHistory::where('user_id', $user->id)->get();
        $this->assertCount(3, $operations);
    }

    public function test_finance_operation_calculates_balance(): void
    {
        $user = User::factory()->create();

        // Enrollment operations (add credits)
        FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 500,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 300,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        // Write-off operations (deduct credits)
        FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 200,
            'type' => FinanceOperationConstant::WRITE_OFFS,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        // Calculate balance
        $enrollments = FinanceOperationsHistory::where('user_id', $user->id)
            ->where('type', FinanceOperationConstant::ENROLLMENT)
            ->sum('money');

        $writeoffs = FinanceOperationsHistory::where('user_id', $user->id)
            ->where('type', FinanceOperationConstant::WRITE_OFFS)
            ->sum('money');

        $balance = $enrollments - $writeoffs;

        $this->assertEquals(800, $enrollments);
        $this->assertEquals(200, $writeoffs);
        $this->assertEquals(600, $balance);
    }

    public function test_finance_operation_can_be_canceled(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 100,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::PENDING,
        ]);

        // Cancel the operation by updating status
        $financeOp->status = FinanceOperationConstant::CANCELED;
        $financeOp->save();

        $financeOp->refresh();
        $this->assertEquals(FinanceOperationConstant::CANCELED, $financeOp->status);
        
        // Verify the operation was persisted
        $this->assertDatabaseHas('finance_operations_histories', [
            'id' => $financeOp->id,
            'status' => FinanceOperationConstant::CANCELED,
        ]);
    }

    public function test_finance_operation_status_constants(): void
    {
        $this->assertEquals(1, FinanceOperationConstant::ENROLLMENT);
        $this->assertEquals(2, FinanceOperationConstant::WRITE_OFFS);
        $this->assertEquals(1, FinanceOperationConstant::CREATED);
        $this->assertEquals(2, FinanceOperationConstant::EXECUTED);
        $this->assertEquals(3, FinanceOperationConstant::PENDING);
        $this->assertEquals(4, FinanceOperationConstant::CANCELED);
    }

    public function test_finance_operation_getters(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 250,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        $this->assertEquals($user->id, $financeOp->getUserId());
        $this->assertEquals(250, $financeOp->getMoney());
        $this->assertEquals(FinanceOperationConstant::ENROLLMENT, $financeOp->getType());
        $this->assertEquals(FinanceOperationConstant::EXECUTED, $financeOp->getStatus());
    }

    public function test_large_finance_operations(): void
    {
        $user = User::factory()->create();

        $financeOp = FinanceOperationsHistory::create([
            'user_id' => $user->id,
            'money' => 100000,
            'type' => FinanceOperationConstant::ENROLLMENT,
            'status' => FinanceOperationConstant::EXECUTED,
        ]);

        $this->assertEquals(100000, $financeOp->money);
    }
}
