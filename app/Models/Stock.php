<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;

class Stock extends Model
{
    use HasUlids, InteractsWithMedia;
    protected $guarded = [];

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiveOrderDetail()
    {
        return $this->belongsTo(ReceiveOrderDetail::class);
    }
}
