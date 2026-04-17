<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ExpenseTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_type',
        'document_no',
        'posting_date',
        'document_date',
        'account_type',
        'category_code',
        'expense_type',
        'amount',
        'amount_lcy',
        'currency_code',
        'currency_factor',
        'vat_amount',
        'vat_bus_posting_group',
        'vendor_id',
        'customer_id',
        'employee_id',
        'item_id',
        'product_category_id',
        'purchase_order_no',
        'sales_order_no',
        'invoice_no',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimensions',
        'gl_entry_id',
        'expense_account_id',
        'status',
        'reversed_by',
        'posted_by',
        'description',
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
        'posted_by' => 'integer',
        'reversed_by' => 'integer',
    ];

    /**
     * Boot the model to handle automatic assignment of auditing fields.
     */
    protected static function booted(): void
    {
        static::creating(function (ExpenseTransaction $transaction) {
            if (Auth::check()) {
                // Automatically assign the current user to posted_by if not explicitly set
                $transaction->posted_by = $transaction->posted_by ?? Auth::id();
            }
        });
    }

    // --- Core Relationships ---

    /**
     * Link to the metadata/setup model via category_code.
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_code', 'category_code');
    }

    /**
     * The G/L account where the expense is recorded.
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    /**
     * The resulting G/L entry if posted.
     */
    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    // --- Source/Tracking Relationships ---

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

    /**
     * Link to inventory item if applicable.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Link to the general Product Category (used for sales returns or COGS grouping).
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    // --- Audit & Sub-system Relationships ---

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by');
    }

    // --- Scopes ---

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

    // --- Helper Methods ---

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
        return (float)$this->amount + (float)$this->vat_amount;
    }
}
