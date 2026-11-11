<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One immutable entry in the stock ledger. Mistakes are corrected with a reversing movement, never by editing.
 *
 * Only the StockService creates movements.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements cannot be modified; record a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Stock movements cannot be deleted; record a reversal instead.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'direction' => 'integer',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'balance_after' => 'decimal:3',
            'moved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The document that caused this movement (goods receipt, adjustment, invoice, requisition, ...).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reverses_movement_id');
    }

    /**
     * @return HasOne<StockMovement, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'reverses_movement_id');
    }

    public function isReversal(): bool
    {
        return $this->reverses_movement_id !== null;
    }

    /**
     * The signed quantity: positive when stock came in, negative when it went out.
     */
    public function signedQuantity(): string
    {
        return $this->direction === 1 ? (string) $this->quantity : '-'.$this->quantity;
    }

    /**
     * Value of the movement at its unit cost, rounded to money precision.
     */
    public function value(): string
    {
        return bcadd(bcmul((string) $this->quantity, (string) $this->unit_cost, 6), '0', 2);
    }

    /**
     * @param  Builder<StockMovement>  $query
     */
    public function scopeNotReversed(Builder $query): void
    {
        $query->whereNull('reverses_movement_id')->whereDoesntHave('reversal');
    }
}
