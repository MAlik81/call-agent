<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $attributes = [
        'value' => '', // default empty string
    ];

    /**
     * Mutator to set the value.
     * Stores plain text as-is. Arrays/objects are stored as JSON.
     */
    public function setValueAttribute($value)
{
    if (is_array($value) || is_object($value)) {
        $this->attributes['value'] = json_encode($value);
    } else {
        $this->attributes['value'] = json_encode($value); // wrap string in quotes
    }
}


    /**
     * Accessor to get the value.
     * If value is JSON, decode it. Otherwise, return plain text.
     */
    public function getValueAttribute($value)
{
    return json_decode($value, true);
}

}
