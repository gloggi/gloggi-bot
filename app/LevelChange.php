<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $user_id
 * @property string $level
 * @property boolean $jumped_away
 */
class LevelChange extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'user_id', 'level', 'jumped_away',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static function getReport() {
        return LevelChange::orderBy('created_at')->get()->reduce(function ($carry, $item) {
            $carry[$item->name] = $item->level;
            return $carry;
        }, []);
    }
}
