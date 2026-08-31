<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class FlexPayCheckout extends Model
{
    protected $table = 'flexpay_checkout_sessions';
    protected $guarded = [];
}
