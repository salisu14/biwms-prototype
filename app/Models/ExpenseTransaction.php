<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type', 'document_no', 'posting_date', 'document_date',
        'account_type', 'category_code', 'expense_type',
        'amount', 'amount_lcy', 'currency_code', 'currency_factor',
        'vat_amount', 'vat_bus_posting_group', 'vat_prod_posting_group',
        'vendor_id', 'customer_id', 'employee_id',
        'item_id', 'category_id',
        'purchase_order_no', 'sales_order_no', 'invoice_no',
        'shortcut_dimension_1_code', 'shortcut_dimension_2_code', 'dimensions',
        'gl_entry_id', 'expense_account_id',
        'status', 'reversed_by',
        'posted_by', 'description',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'account_type' => AccountType::class,
        'amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'vat_amount' => 'decimal:4',
        'dimensions' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by');
    }

    public function scopeByType($query, AccountType $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeDirect($query)
    {
        return $query->where('expense_type', 'direct');
    }

    public function scopeIndirect($query)
    {
        return $query->where('expense_type', 'indirect');
    }

    public function scopeByPeriod($query, \DateTime $from, \DateTime $to)
    {
        return $query->whereBetween('posting_date', [$from, $to]);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function isDirect(): bool
    {
        return $this->expense_type === 'direct';
    }

    public function isIndirect(): bool
    {
        return $this->expense_type === 'indirect';
    }

    public function isCOGS(): bool
    {
        return $this->account_type === AccountType::COGS;
    }

    public function getNetAmount(): float
    {
        return $this->amount + $this->vat_amount;
    }
}
